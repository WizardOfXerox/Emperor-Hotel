<?php

declare(strict_types=1);

class Room
{
    private const ROOM_TYPES = [
        'Imperial Deluxe',
        'Royal Executive',
        'Emperor Presidential',
    ];

    private const ROOM_STATUSES = ['Available', 'Reserved', 'Occupied'];

    public function __construct(private PDO $db)
    {
    }

    public static function types(): array
    {
        return self::ROOM_TYPES;
    }

    public static function statuses(): array
    {
        return self::ROOM_STATUSES;
    }

    public function all(): array
    {
        $statement = $this->db->query('SELECT * FROM rooms ORDER BY floor ASC, room_number ASC');

        return $statement->fetchAll();
    }

    public function availableRooms(): array
    {
        $statement = $this->db->prepare("SELECT * FROM rooms WHERE status = 'Available' ORDER BY floor ASC, room_number ASC");
        $statement->execute();

        return $statement->fetchAll();
    }

    public function find(int $roomId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM rooms WHERE room_id = :room_id LIMIT 1');
        $statement->execute(['room_id' => $roomId]);
        $room = $statement->fetch();

        return $room ?: null;
    }

    public function findByNumber(string $roomNumber): ?array
    {
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

        $statement = $this->db->prepare(
            'INSERT INTO rooms (room_number, room_type, floor, capacity_adults, capacity_children, price_per_night, status, description)
             VALUES (:room_number, :room_type, :floor, :capacity_adults, :capacity_children, :price_per_night, :status, :description)'
        );

        return $statement->execute([
            'room_number' => trim($data['room_number']),
            'room_type' => $data['room_type'],
            'floor' => (int) $data['floor'],
            'capacity_adults' => (int) $data['capacity_adults'],
            'capacity_children' => (int) $data['capacity_children'],
            'price_per_night' => $pricePerNight,
            'status' => $data['status'],
            'description' => trim((string) ($data['description'] ?? '')),
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

        $statement = $this->db->prepare(
            'UPDATE rooms
             SET room_number = :room_number,
                 room_type = :room_type,
                 floor = :floor,
                 capacity_adults = :capacity_adults,
                 capacity_children = :capacity_children,
                 price_per_night = :price_per_night,
                 status = :status,
                 description = :description
             WHERE room_id = :room_id'
        );

        return $statement->execute([
            'room_number' => trim($data['room_number']),
            'room_type' => $data['room_type'],
            'floor' => (int) $data['floor'],
            'capacity_adults' => (int) $data['capacity_adults'],
            'capacity_children' => (int) $data['capacity_children'],
            'price_per_night' => $pricePerNight,
            'status' => $data['status'],
            'description' => trim((string) ($data['description'] ?? '')),
            'room_id' => $roomId,
        ]);
    }

    public function updateTypePrice(string $roomType, float $pricePerNight): int
    {
        $this->assertRoomType($roomType);

        if ($pricePerNight <= 0) {
            throw new RuntimeException('Room price must be greater than zero.');
        }

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

    public function delete(int $roomId): bool
    {
        $statement = $this->db->prepare('DELETE FROM rooms WHERE room_id = :room_id');

        return $statement->execute(['room_id' => $roomId]);
    }

    public function statusSummary(): array
    {
        $summary = [
            'available' => 0,
            'not_available' => 0,
        ];

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
                    'capacity_adults',
                    'capacity_children',
                    'price_per_night',
                    'status',
                    'description',
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
                'capacity_adults' => $this->nodeText($roomNode, 'capacity_adults') ?: 5,
                'capacity_children' => $this->nodeText($roomNode, 'capacity_children') ?: 0,
                'price_per_night' => $this->nodeText($roomNode, 'price_per_night'),
                'status' => $this->nodeText($roomNode, 'status') ?: 'Available',
                'description' => $this->nodeText($roomNode, 'description'),
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
        if (!in_array($roomType, self::ROOM_TYPES, true)) {
            throw new RuntimeException('Please choose a valid room type.');
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
        $capacityAdults = (int) ($data['capacity_adults'] ?? 0);
        $capacityChildren = (int) ($data['capacity_children'] ?? 0);

        if ($floor < 1) {
            throw new RuntimeException('Room floor must be at least 1.');
        }

        if ($capacityAdults < 1) {
            throw new RuntimeException('Room guest capacity must be at least 1.');
        }

        if ($capacityChildren < 0) {
            throw new RuntimeException('Room guest capacity cannot be negative.');
        }
    }
}
