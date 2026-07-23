<?php

declare(strict_types=1);

class Room
{
    private const ROOM_TYPES = [
        'Imperial Deluxe',
        'Royal Executive',
        'Emperor Presidential',
    ];

    private const ROOM_STATUSES = ['Available', 'Reserved', 'Occupied', 'Cleaning', 'Maintenance'];

    public function __construct(private PDO $db)
    {
    }

    public static function types(?PDO $db = null): array
    {
        $defaultTypes = self::ROOM_TYPES;

        if ($db instanceof PDO) {
            try {
                $stmt = $db->query("SELECT DISTINCT room_type FROM rooms WHERE room_type IS NOT NULL AND TRIM(room_type) != '' ORDER BY room_type ASC");
                $dbTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($dbTypes)) {
                    return array_values(array_unique(array_merge($defaultTypes, $dbTypes)));
                }
            } catch (Throwable $e) {
                // Fallback to default list if query fails
            }
        }

        return $defaultTypes;
    }

    public function getTypes(): array
    {
        return self::types($this->db);
    }

    public static function statuses(): array
    {
        return self::ROOM_STATUSES;
    }

    public function all(): array
    {
        // SQL: Reads the complete room inventory in floor/room-number order for room lists and XML export.
        $statement = $this->db->query('SELECT * FROM rooms ORDER BY floor ASC, room_number ASC');

        return $statement->fetchAll();
    }

    public function paginated(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        [$whereSql, $params] = $this->buildRoomFilterSql($filters);
        $sortKey = (string) ($filters['sort'] ?? 'floor');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $sortableColumns = [
            'floor' => 'floor',
            'room_number' => 'room_number',
            'room_type' => 'room_type',
            'price_per_night' => 'price_per_night',
            'status' => 'status',
            'created_at' => 'created_at',
        ];
        $orderBy = $sortableColumns[$sortKey] ?? 'floor';

        $countStatement = $this->db->prepare("SELECT COUNT(*) FROM rooms {$whereSql}");
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $statement = $this->db->prepare(
            "SELECT *
             FROM rooms
             {$whereSql}
             ORDER BY {$orderBy} {$direction}, room_number ASC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'rows' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function availableRooms(): array
    {
        // SQL: Reads only rooms whose current operational status is Available.
        $statement = $this->db->prepare("SELECT * FROM rooms WHERE status = 'Available' ORDER BY floor ASC, room_number ASC");
        $statement->execute();

        return $statement->fetchAll();
    }

    public function find(int $roomId): ?array
    {
        // SQL: Finds one room by primary key for editing, reservations, and validation.
        $statement = $this->db->prepare('SELECT * FROM rooms WHERE room_id = :room_id LIMIT 1');
        $statement->execute(['room_id' => $roomId]);
        $room = $statement->fetch();

        return $room ?: null;
    }

    public function findByNumber(string $roomNumber): ?array
    {
        // SQL: Finds one room by unique room number, mainly for XML import update-or-create logic.
        $statement = $this->db->prepare('SELECT * FROM rooms WHERE room_number = :room_number LIMIT 1');
        $statement->execute(['room_number' => $roomNumber]);
        $room = $statement->fetch();

        return $room ?: null;
    }

    public function create(array $data): bool
    {
        if (trim((string) $data['room_number']) === '') {
            throw new RuntimeException('Room number is required.');
        }

        $this->assertRoomType((string) ($data['room_type'] ?? ''));
        $this->assertRoomStatus((string) ($data['status'] ?? ''));
        $this->validateRoomNumbers($data);
        $pricePerNight = (float) ($data['price_per_night'] ?? -1);

        if ($pricePerNight <= 0) {
            throw new RuntimeException('Room price must be greater than zero.');
        }

        $bedType = trim((string) ($data['bed_type'] ?? 'King Bed'));
        $maxCapacity = max(1, (int) ($data['max_capacity'] ?? 2));
        $viewType = trim((string) ($data['view_type'] ?? 'City View'));

        $imageUrl = isset($data['image_url']) ? trim((string) $data['image_url']) : null;

        // SQL: Inserts a new room record after validating type, status, floor, and price.
        $statement = $this->db->prepare(
            'INSERT INTO rooms (room_number, room_type, floor, price_per_night, base_price_per_night, status, bed_type, max_capacity, view_type, image_url)
             VALUES (:room_number, :room_type, :floor, :price_per_night, :base_price_per_night, :status, :bed_type, :max_capacity, :view_type, :image_url)'
        );

        return $statement->execute([
            'room_number' => trim($data['room_number']),
            'room_type' => $data['room_type'],
            'floor' => (int) $data['floor'],
            'price_per_night' => $pricePerNight,
            'base_price_per_night' => $pricePerNight,
            'status' => $data['status'],
            'bed_type' => $bedType,
            'max_capacity' => $maxCapacity,
            'view_type' => $viewType,
            'image_url' => $imageUrl !== '' ? $imageUrl : null,
        ]);
    }

    public function update(int $roomId, array $data): bool
    {
        if (trim((string) $data['room_number']) === '') {
            throw new RuntimeException('Room number is required.');
        }

        $this->assertRoomType((string) ($data['room_type'] ?? ''));
        $this->assertRoomStatus((string) ($data['status'] ?? ''));
        $this->validateRoomNumbers($data);
        $pricePerNight = (float) ($data['price_per_night'] ?? -1);

        if ($pricePerNight <= 0) {
            throw new RuntimeException('Room price must be greater than zero.');
        }

        $bedType = trim((string) ($data['bed_type'] ?? 'King Bed'));
        $maxCapacity = max(1, (int) ($data['max_capacity'] ?? 2));
        $viewType = trim((string) ($data['view_type'] ?? 'City View'));
        $imageUrl = isset($data['image_url']) ? trim((string) $data['image_url']) : null;

        // SQL: Updates all editable room fields for one room record.
        $statement = $this->db->prepare(
            'UPDATE rooms
             SET room_number = :room_number,
                 room_type = :room_type,
                 floor = :floor,
                 price_per_night = :price_per_night,
                 status = :status,
                 bed_type = :bed_type,
                 max_capacity = :max_capacity,
                 view_type = :view_type,
                 image_url = :image_url
             WHERE room_id = :room_id'
        );

        return $statement->execute([
            'room_number' => trim($data['room_number']),
            'room_type' => $data['room_type'],
            'floor' => (int) $data['floor'],
            'price_per_night' => $pricePerNight,
            'status' => $data['status'],
            'bed_type' => $bedType,
            'max_capacity' => $maxCapacity,
            'view_type' => $viewType,
            'image_url' => $imageUrl !== '' ? $imageUrl : null,
            'room_id' => $roomId,
        ]);
    }

    public function updateTypePrice(string $roomType, float $pricePerNight): int
    {
        $this->assertRoomType($roomType);

        if ($pricePerNight <= 0) {
            throw new RuntimeException('Room price must be greater than zero.');
        }

        // SQL: Bulk-updates the price for every room under the selected room type.
        $statement = $this->db->prepare(
            'UPDATE rooms
             SET price_per_night = :price_per_night
             WHERE room_type = :room_type'
        );
        $statement->execute([
            'price_per_night' => $pricePerNight,
            'room_type' => $roomType,
        ]);

        return $statement->rowCount();
    }

    public function smartBulkUpdatePrice(array $options): int
    {
        $targetType = trim((string) ($options['target_type'] ?? 'all'));
        $targetValue = trim((string) ($options['target_value'] ?? ''));
        $adjustmentMode = trim((string) ($options['adjustment_mode'] ?? 'fixed'));
        $adjustmentValue = (float) ($options['adjustment_value'] ?? 0);

        if ($adjustmentValue == 0.0 && $adjustmentMode !== 'fixed') {
            throw new RuntimeException('Please enter a valid adjustment value or percentage.');
        }

        $whereClause = 'WHERE 1=1';
        $params = [];

        if ($targetType === 'suite' && $targetValue !== '' && $targetValue !== 'all') {
            $whereClause .= ' AND room_type = :target_value';
            $params['target_value'] = $targetValue;
        } elseif ($targetType === 'floor' && (int)$targetValue > 0) {
            $whereClause .= ' AND floor = :target_value';
            $params['target_value'] = (int)$targetValue;
        }

        $saveAsBase = !empty($options['save_as_base_price']);

        if ($adjustmentMode === 'fixed') {
            if ($adjustmentValue <= 0) {
                throw new RuntimeException('Price per night must be greater than zero.');
            }
            if ($saveAsBase) {
                $sql = "UPDATE rooms SET price_per_night = :new_price, base_price_per_night = :new_price {$whereClause}";
            } else {
                $sql = "UPDATE rooms SET price_per_night = :new_price {$whereClause}";
            }
            $params['new_price'] = $adjustmentValue;
        } elseif ($adjustmentMode === 'percentage') {
            $multiplier = 1 + ($adjustmentValue / 100);
            if ($multiplier <= 0) {
                throw new RuntimeException('Percentage reduction would result in negative or zero price.');
            }
            if ($saveAsBase) {
                $sql = "UPDATE rooms SET price_per_night = GREATEST(1.00, ROUND(price_per_night * :multiplier, 2)), base_price_per_night = GREATEST(1.00, ROUND(base_price_per_night * :multiplier, 2)) {$whereClause}";
            } else {
                $sql = "UPDATE rooms SET price_per_night = GREATEST(1.00, ROUND(price_per_night * :multiplier, 2)) {$whereClause}";
            }
            $params['multiplier'] = $multiplier;
        } elseif ($adjustmentMode === 'offset') {
            if ($saveAsBase) {
                $sql = "UPDATE rooms SET price_per_night = GREATEST(1.00, ROUND(price_per_night + :offset, 2)), base_price_per_night = GREATEST(1.00, ROUND(base_price_per_night + :offset, 2)) {$whereClause}";
            } else {
                $sql = "UPDATE rooms SET price_per_night = GREATEST(1.00, ROUND(price_per_night + :offset, 2)) {$whereClause}";
            }
            $params['offset'] = $adjustmentValue;
        } else {
            throw new RuntimeException('Invalid price adjustment mode.');
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function getSuiteBaseRates(): array
    {
        $stmt = $this->db->query("
            SELECT room_type, 
                   MIN(COALESCE(base_price_per_night, price_per_night)) as base_price,
                   MIN(price_per_night) as current_min_price,
                   MAX(price_per_night) as current_max_price,
                   COUNT(*) as total_rooms
            FROM rooms
            WHERE room_type IS NOT NULL AND TRIM(room_type) != ''
            GROUP BY room_type
            ORDER BY room_type ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resetToStandardPrices(?string $roomType = null): int
    {
        $sql = "UPDATE rooms SET price_per_night = COALESCE(base_price_per_night, price_per_night)";
        $params = [];
        if ($roomType !== null && $roomType !== 'all' && $roomType !== '') {
            $sql .= " WHERE room_type = :room_type";
            $params['room_type'] = $roomType;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function delete(int $roomId): bool
    {
        // SQL: Deletes one room by primary key. The database blocks deletion if restricted reservations still use it.
        $statement = $this->db->prepare('DELETE FROM rooms WHERE room_id = :room_id');

        return $statement->execute(['room_id' => $roomId]);
    }

    public function statusSummary(): array
    {
        $summary = [
            'available' => 0,
            'not_available' => 0,
        ];

        // SQL: Groups rooms by status so the dashboard can show available vs not available counts.
        $statement = $this->db->query(
            "SELECT status, COUNT(*) AS total
             FROM rooms
             GROUP BY status"
        );

        foreach ($statement->fetchAll() as $row) {
            if ($row['status'] === 'Available') {
                $summary['available'] += (int) $row['total'];
            } else {
                $summary['not_available'] += (int) $row['total'];
            }
        }

        return $summary;
    }

    public function statusBreakdown(): array
    {
        $statuses = self::ROOM_STATUSES;
        $counts = array_fill_keys($statuses, 0);

        // SQL: Counts rooms per status for the room availability chart.
        $statement = $this->db->query(
            "SELECT status, COUNT(*) AS total
             FROM rooms
             GROUP BY status"
        );

        foreach ($statement->fetchAll() as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['total'];
            }
        }

        return array_map(
            static fn (string $status): array => [
                'status' => $status,
                'total' => $counts[$status],
            ],
            $statuses
        );
    }

    public function roomsByStatus(string $status, int $limit = 5): array
    {
        $this->assertRoomStatus($status);

        // SQL: Reads a limited list of rooms matching one status for dashboard/status previews.
        $statement = $this->db->prepare(
            'SELECT *
             FROM rooms
             WHERE status = :status
             ORDER BY floor ASC, room_number ASC
             LIMIT :limit'
        );
        $statement->bindValue(':status', $status);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function typeSummary(): array
    {
        // SQL: Groups rooms by type to show total rooms, available rooms, and min/max rates per room type.
        $statement = $this->db->query(
            "SELECT
                room_type,
                COUNT(*) AS total_rooms,
                SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available_rooms,
                MIN(price_per_night) AS lowest_price,
                MAX(price_per_night) AS highest_price
             FROM rooms
             GROUP BY room_type"
        );

        $summary = [];

        foreach ($statement->fetchAll() as $row) {
            $summary[$row['room_type']] = [
                'available' => (int) $row['available_rooms'],
                'total' => (int) $row['total_rooms'],
                'lowest_price' => (float) $row['lowest_price'],
                'highest_price' => (float) $row['highest_price'],
            ];
        }

        return $summary;
    }

    public function exportToXml(): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $roomsElement = $dom->createElement('rooms');
        $dom->appendChild($roomsElement);

        foreach ($this->all() as $room) {
            $roomElement = $dom->createElement('room');
            $roomsElement->appendChild($roomElement);

            foreach (
                [
                    'room_number',
                    'room_type',
                    'floor',
                    'price_per_night',
                    'status',
                ] as $field
            ) {
                $roomElement->appendChild($dom->createElement($field, (string) $room[$field]));
            }
        }

        return $dom->saveXML() ?: '';
    }

    public function importFromXml(string $filePath): array
    {
        $dom = new DOMDocument();
        $dom->load($filePath);

        $created = 0;
        $updated = 0;

        foreach ($dom->getElementsByTagName('room') as $roomNode) {
            $roomData = [
                'room_number' => $this->nodeText($roomNode, 'room_number'),
                'room_type' => $this->nodeText($roomNode, 'room_type'),
                'floor' => $this->nodeText($roomNode, 'floor'),
                'price_per_night' => $this->nodeText($roomNode, 'price_per_night'),
                'status' => $this->nodeText($roomNode, 'status') ?: 'Available',
            ];

            if ($roomData['room_number'] === '') {
                continue;
            }

            $existingRoom = $this->findByNumber($roomData['room_number']);

            if ($existingRoom) {
                $this->update((int) $existingRoom['room_id'], $roomData);
                $updated++;
                continue;
            }

            $this->create($roomData);
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function nodeText(DOMElement $roomNode, string $tagName): string
    {
        $nodes = $roomNode->getElementsByTagName($tagName);

        if ($nodes->length === 0) {
            return '';
        }

        return trim($nodes->item(0)?->textContent ?? '');
    }

    private function assertRoomType(string $roomType): void
    {
        if (trim($roomType) === '') {
            throw new RuntimeException('Please enter or choose a room type.');
        }
    }

    private function assertRoomStatus(string $status): void
    {
        if (!in_array($status, self::ROOM_STATUSES, true)) {
            throw new RuntimeException('Please choose a valid room status.');
        }
    }

    private function validateRoomNumbers(array $data): void
    {
        $floor = (int) ($data['floor'] ?? 0);

        if ($floor < 1) {
            throw new RuntimeException('Room floor must be at least 1.');
        }
    }

    private function buildRoomFilterSql(array $filters): array
    {
        $clauses = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $clauses[] = '(room_number LIKE :search OR room_type LIKE :search OR status LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $roomType = trim((string) ($filters['room_type'] ?? ''));
        if ($roomType !== '' && $roomType !== 'all') {
            $clauses[] = 'room_type = :room_type';
            $params['room_type'] = $roomType;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, self::ROOM_STATUSES, true)) {
            $clauses[] = 'status = :status';
            $params['status'] = $status;
        }

        $floor = trim((string) ($filters['floor'] ?? ''));
        if ($floor !== '' && ctype_digit($floor)) {
            $clauses[] = 'floor = :floor';
            $params['floor'] = (int) $floor;
        }

        $bedType = trim((string) ($filters['bed_type'] ?? ''));
        if ($bedType !== '') {
            $clauses[] = 'bed_type = :bed_type';
            $params['bed_type'] = $bedType;
        }

        $minCapacity = (int) ($filters['min_capacity'] ?? 0);
        if ($minCapacity > 0) {
            $clauses[] = 'max_capacity >= :min_capacity';
            $params['min_capacity'] = $minCapacity;
        }

        $whereSql = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$whereSql, $params];
    }

    public function calculateRecommendationScores(): array
    {
        $sql = "
            SELECT 
                r.room_id,
                r.room_number,
                r.room_type,
                r.floor,
                r.price_per_night,
                r.status,
                r.bed_type,
                r.max_capacity,
                r.view_type,
                COUNT(DISTINCT res.reservation_id) AS booking_count,
                COALESCE(AVG(rev.rating), 5.0) AS avg_rating,
                COUNT(DISTINCT rev.review_id) AS review_count
            FROM rooms r
            LEFT JOIN reservations res ON r.room_id = res.room_id AND res.status IN ('Confirmed', 'Checked-in', 'Checked-out')
            LEFT JOIN room_reviews rev ON r.room_id = rev.room_id
            GROUP BY r.room_id
        ";

        $rows = $this->db->query($sql)->fetchAll();
        $maxBookings = 1;
        foreach ($rows as $row) {
            if ((int) $row['booking_count'] > $maxBookings) {
                $maxBookings = (int) $row['booking_count'];
            }
        }

        $scoredRooms = [];
        foreach ($rows as $row) {
            $bookingCount = (int) $row['booking_count'];
            $avgRating = (float) $row['avg_rating'];
            
            // Formula: 40% popularity + 60% rating
            $popularityScore = ($bookingCount / $maxBookings) * 40;
            $ratingScore = ($avgRating / 5.0) * 60;
            $totalScore = round($popularityScore + $ratingScore, 1);

            $row['recommendation_score'] = $totalScore;
            $row['booking_count'] = $bookingCount;
            $row['avg_rating'] = round($avgRating, 1);
            $row['review_count'] = (int) $row['review_count'];
            $scoredRooms[$row['room_id']] = $row;
        }

        uasort($scoredRooms, fn($a, $b) => $b['recommendation_score'] <=> $a['recommendation_score']);

        return $scoredRooms;
    }
}
