<?php

declare(strict_types=1);

class Reservation
{
    private const STATUSES = ['Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled'];

    private const FRONT_DESK_ACTIONS = [
        'confirm' => 'Confirmed',
        'check_in' => 'Checked-in',
        'check_out' => 'Checked-out',
        'cancel' => 'Cancelled',
    ];

    public function __construct(private PDO $db)
    {
    }

    public static function statuses(): array
    {
        return self::STATUSES;
    }

    public function all(): array
    {
        return $this->searchAndFilter();
    }

    public function searchAndFilter(?string $search = null, ?string $status = null): array
    {
        $sql = "SELECT r.*, g.first_name, g.last_name, g.email AS guest_email, g.phone,
                       rm.room_number, rm.room_type, u.full_name AS user_name
                FROM reservations r
                INNER JOIN guests g ON g.guest_id = r.guest_id
                INNER JOIN rooms rm ON rm.room_id = r.room_id
                LEFT JOIN users u ON u.user_id = r.user_id
                WHERE 1=1";
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $searchTerm = '%' . trim($search) . '%';
            $sql .= " AND (g.first_name LIKE :search 
                        OR g.last_name LIKE :search 
                        OR CONCAT(g.first_name, ' ', g.last_name) LIKE :search
                        OR g.email LIKE :search
                        OR rm.room_number LIKE :search
                        OR rm.room_type LIKE :search
                        OR CAST(r.reservation_id AS CHAR) LIKE :search)";
            $params['search'] = $searchTerm;
        }

        if ($status !== null && trim($status) !== '' && trim($status) !== 'all') {
            $sql .= " AND r.status = :status";
            $params['status'] = trim($status);
        }

        $sql .= " ORDER BY r.created_at DESC";

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function find(int $reservationId): ?array
    {
        // SQL: Finds one reservation with guest and room details for editing, receipts, and modal views.
        $statement = $this->db->prepare(
            "SELECT r.*, g.first_name, g.last_name, g.email AS guest_email, g.phone,
                    rm.room_number, rm.room_type
             FROM reservations r
             INNER JOIN guests g ON g.guest_id = r.guest_id
             INNER JOIN rooms rm ON rm.room_id = r.room_id
             WHERE r.reservation_id = :reservation_id
             LIMIT 1"
        );
        $statement->execute(['reservation_id' => $reservationId]);
        $reservation = $statement->fetch();

        return $reservation ?: null;
    }

    public function userReservations(int $userId): array
    {
        // SQL: Lists reservations owned by one logged-in user for the customer booking history.
        $statement = $this->db->prepare(
            "SELECT r.*, rm.room_number, rm.room_type, g.first_name, g.last_name
             FROM reservations r
             INNER JOIN rooms rm ON rm.room_id = r.room_id
             INNER JOIN guests g ON g.guest_id = r.guest_id
             WHERE r.user_id = :user_id
             ORDER BY r.created_at DESC"
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function create(array $data): bool
    {
        return $this->createAndGetId($data) > 0;
    }

    public function createAndGetId(array $data): int
    {
        $this->validateDates($data['check_in'], $data['check_out']);
        $this->assertStatus((string) ($data['status'] ?? ''));
        $this->assertRoomExists((int) $data['room_id']);
        $this->validateGuestId((int) ($data['guest_id'] ?? 0));
        $this->validateTotalAmount((float) ($data['total_amount'] ?? 0));

        if (!$this->roomIsAvailable((int) $data['room_id'], $data['check_in'], $data['check_out'])) {
            throw new RuntimeException('The selected room is not available for those dates.');
        }

        // SQL: Creates a reservation after validating dates, room availability, guest, status, and total amount.
        $statement = $this->db->prepare(
            'INSERT INTO reservations (user_id, guest_id, room_id, check_in, check_out, total_amount, status)
             VALUES (:user_id, :guest_id, :room_id, :check_in, :check_out, :total_amount, :status)'
        );

        $saved = $statement->execute([
            'user_id' => $data['user_id'] ?? null,
            'guest_id' => (int) $data['guest_id'],
            'room_id' => (int) $data['room_id'],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'total_amount' => (float) $data['total_amount'],
            'status' => $data['status'],
        ]);

        $reservationId = $saved ? (int) $this->db->lastInsertId() : 0;

        if ($saved) {
            $this->syncRoomStatus((int) $data['room_id']);
        }

        return $reservationId;
    }

    public function update(int $reservationId, array $data): bool
    {
        $existing = $this->find($reservationId);

        if (!$existing) {
            return false;
        }

        $this->validateDates($data['check_in'], $data['check_out']);
        $this->assertStatus((string) ($data['status'] ?? ''));
        $this->assertRoomExists((int) $data['room_id']);
        $this->validateGuestId((int) ($data['guest_id'] ?? 0));
        $this->validateTotalAmount((float) ($data['total_amount'] ?? 0));

        if (!$this->roomIsAvailable((int) $data['room_id'], $data['check_in'], $data['check_out'], $reservationId)) {
            throw new RuntimeException('The selected room is not available for those dates.');
        }

        // SQL: Updates the reservation details and rechecks availability while excluding this same reservation.
        $statement = $this->db->prepare(
            'UPDATE reservations
             SET guest_id = :guest_id,
                 room_id = :room_id,
                 check_in = :check_in,
                 check_out = :check_out,
                 total_amount = :total_amount,
                 status = :status
             WHERE reservation_id = :reservation_id'
        );

        $saved = $statement->execute([
            'guest_id' => (int) $data['guest_id'],
            'room_id' => (int) $data['room_id'],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'total_amount' => (float) $data['total_amount'],
            'status' => $data['status'],
            'reservation_id' => $reservationId,
        ]);

        if ($saved) {
            if ((int) $existing['room_id'] !== (int) $data['room_id']) {
                $this->syncRoomStatus((int) $existing['room_id']);
            }

            $this->syncRoomStatus((int) $data['room_id']);
        }

        return $saved;
    }

    public function delete(int $reservationId): bool
    {
        $existing = $this->find($reservationId);

        if (!$existing) {
            return false;
        }

        // SQL: Deletes one reservation by primary key. Payment logs cascade through the database relationship.
        $statement = $this->db->prepare('DELETE FROM reservations WHERE reservation_id = :reservation_id');
        $deleted = $statement->execute(['reservation_id' => $reservationId]);

        if ($deleted) {
            $this->syncRoomStatus((int) $existing['room_id']);
        }

        return $deleted;
    }

    public function updateStatus(int $reservationId, string $status): bool
    {
        $existing = $this->find($reservationId);

        if (!$existing) {
            return false;
        }

        $this->assertStatus($status);

        // SQL: Changes only the reservation status for front desk actions such as Confirm, Check In, and Check Out.
        $statement = $this->db->prepare('UPDATE reservations SET status = :status WHERE reservation_id = :reservation_id');
        $saved = $statement->execute([
            'status' => $status,
            'reservation_id' => $reservationId,
        ]);

        if ($saved) {
            $this->syncRoomStatus((int) $existing['room_id']);
        }

        return $saved;
    }

    public function extendStay(int $reservationId, string $newCheckOut): array
    {
        $reservation = $this->find($reservationId);

        if (!$reservation) {
            throw new RuntimeException('Reservation not found.');
        }

        if (in_array($reservation['status'], ['Cancelled', 'Checked-out'], true)) {
            throw new RuntimeException('Cancelled or checked-out reservations cannot be extended.');
        }

        $currentCheckOut = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $reservation['check_out']);
        $newCheckOutDate = DateTimeImmutable::createFromFormat('!Y-m-d', $newCheckOut);

        if (
            !$currentCheckOut
            || !$newCheckOutDate
            || $currentCheckOut->format('Y-m-d') !== (string) $reservation['check_out']
            || $newCheckOutDate->format('Y-m-d') !== $newCheckOut
        ) {
            throw new RuntimeException('Please enter a valid new check-out date.');
        }

        if ($newCheckOutDate <= $currentCheckOut) {
            throw new RuntimeException('The new check-out date must be after the current check-out date.');
        }

        if ($newCheckOutDate < new DateTimeImmutable('today')) {
            throw new RuntimeException('The new check-out date cannot be in the past.');
        }

        $room = $this->assertRoomExists((int) $reservation['room_id']);

        $extensionStart = $currentCheckOut->format('Y-m-d');

        if ($this->roomHasActiveOverlap((int) $reservation['room_id'], $extensionStart, $newCheckOut, $reservationId)) {
            throw new RuntimeException('This stay cannot be extended because the room is already reserved during the requested extension dates.');
        }

        $extraNights = (int) $currentCheckOut->diff($newCheckOutDate)->days;
        $additionalAmount = $extraNights * (float) $room['price_per_night'];
        $newTotal = (float) $reservation['total_amount'] + $additionalAmount;

        // SQL: Extends the stay by changing check-out date and increasing the reservation total.
        $statement = $this->db->prepare(
            'UPDATE reservations
             SET check_out = :check_out,
                 total_amount = :total_amount
             WHERE reservation_id = :reservation_id'
        );
        $statement->execute([
            'check_out' => $newCheckOut,
            'total_amount' => $newTotal,
            'reservation_id' => $reservationId,
        ]);

        $this->syncRoomStatus((int) $reservation['room_id']);

        return [
            'old_check_out' => (string) $reservation['check_out'],
            'new_check_out' => $newCheckOut,
            'extra_nights' => $extraNights,
            'additional_amount' => $additionalAmount,
            'new_total' => $newTotal,
        ];
    }

    public function availableFrontDeskActions(array $reservation): array
    {
        $status = (string) ($reservation['status'] ?? '');

        return match ($status) {
            'Pending' => [
                'confirm' => 'Confirm',
                'cancel' => 'Cancel',
            ],
            'Confirmed' => [
                'check_in' => 'Check In',
                'cancel' => 'Cancel',
            ],
            'Checked-in' => [
                'check_out' => 'Check Out',
            ],
            default => [],
        };
    }

    public function applyFrontDeskAction(int $reservationId, string $action): string
    {
        $reservation = $this->find($reservationId);

        if (!$reservation) {
            throw new RuntimeException('Reservation not found.');
        }

        $allowedActions = $this->availableFrontDeskActions($reservation);

        if (!isset($allowedActions[$action], self::FRONT_DESK_ACTIONS[$action])) {
            throw new RuntimeException('That front desk action is not available for this reservation status.');
        }

        if ($action === 'check_in' && $reservation['status'] !== 'Confirmed') {
            throw new RuntimeException('Reservation must be Confirmed with payment settlement before checking in.');
        }

        $newStatus = self::FRONT_DESK_ACTIONS[$action];
        $this->updateStatus($reservationId, $newStatus);

        return $newStatus;
    }

    public function roomsWithDateAvailability(string $checkIn, string $checkOut, ?int $excludeReservationId = null): array
    {
        // SQL: Reads all rooms first, then each room is checked against the selected date range.
        $rooms = $this->db->query('SELECT * FROM rooms ORDER BY floor ASC, room_number ASC')->fetchAll();

        if (!$this->dateRangeIsValid($checkIn, $checkOut)) {
            return $rooms;
        }

        foreach ($rooms as &$room) {
            $dateAvailable = $this->roomIsAvailable((int) $room['room_id'], $checkIn, $checkOut, $excludeReservationId);
            $room['is_available_for_dates'] = $dateAvailable;
            $room['availability_label'] = $this->availabilityLabel($dateAvailable);
        }

        unset($room);

        return $rooms;
    }

    public function dateRangeIsValid(string $checkIn, string $checkOut): bool
    {
        try {
            $this->validateDates($checkIn, $checkOut);
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    public function roomIsAvailable(int $roomId, string $checkIn, string $checkOut, ?int $excludeReservationId = null): bool
    {
        if ($roomId <= 0 || !$this->dateRangeIsValid($checkIn, $checkOut)) {
            return false;
        }

        // Exclude rooms currently in Maintenance
        $roomStmt = $this->db->prepare("SELECT status FROM rooms WHERE room_id = :room_id LIMIT 1");
        $roomStmt->execute(['room_id' => $roomId]);
        if ($roomStmt->fetchColumn() === 'Maintenance') {
            return false;
        }

        // SQL: Counts active overlapping reservations. Zero means the room is free for the requested dates.
        $sql = "SELECT COUNT(*)
                FROM reservations
                WHERE room_id = :room_id
                  AND status NOT IN ('Cancelled', 'Checked-out')
                  AND NOT (check_out <= :check_in OR check_in >= :check_out)";

        $params = [
            'room_id' => $roomId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ];

        if ($excludeReservationId !== null) {
            $sql .= ' AND reservation_id != :reservation_id';
            $params['reservation_id'] = $excludeReservationId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() === 0;
    }

    public function operationalAlerts(): array
    {
        // SQL: Finds checked-in reservations whose check-out date has already passed.
        $overdueStatement = $this->db->query(
            "SELECT r.reservation_id, r.check_out, g.first_name, g.last_name, rm.room_number, rm.room_type
             FROM reservations r
             INNER JOIN guests g ON g.guest_id = r.guest_id
             INNER JOIN rooms rm ON rm.room_id = r.room_id
             WHERE r.status = 'Checked-in'
               AND r.check_out < CURRENT_DATE()
             ORDER BY r.check_out ASC
             LIMIT 5"
        );

        // SQL: Self-joins reservations to detect active date overlaps for the same room.
        $conflictStatement = $this->db->query(
            "SELECT rm.room_number, rm.room_type, COUNT(*) AS conflict_pairs
             FROM reservations r1
             INNER JOIN reservations r2
                ON r2.room_id = r1.room_id
               AND r2.reservation_id > r1.reservation_id
             INNER JOIN rooms rm ON rm.room_id = r1.room_id
             WHERE r1.status NOT IN ('Cancelled', 'Checked-out')
               AND r2.status NOT IN ('Cancelled', 'Checked-out')
               AND r1.check_in < r2.check_out
               AND r1.check_out > r2.check_in
             GROUP BY rm.room_id, rm.room_number, rm.room_type
             ORDER BY conflict_pairs DESC, rm.room_number ASC
             LIMIT 5"
        );

        return [
            'overdue_checkouts' => $overdueStatement->fetchAll(),
            'overbooking_conflicts' => $conflictStatement->fetchAll(),
        ];
    }

    public function occupancyReport(string $startDate, string $endDate): array
    {
        [$start, $end] = $this->validateReportDateRange($startDate, $endDate);
        $days = ((int) $start->diff($end)->days) + 1;

        // SQL: Calculates booked room-nights per room type within the selected date range.
        // LEAST/GREATEST count only the part of each reservation that overlaps the report range.
        $statement = $this->db->prepare(
            "SELECT
                rm.room_type,
                COUNT(DISTINCT rm.room_id) AS room_count,
                COALESCE(SUM(
                    CASE
                        WHEN r.reservation_id IS NULL THEN 0
                        ELSE DATEDIFF(
                            LEAST(r.check_out, DATE_ADD(:end_date_calc, INTERVAL 1 DAY)),
                            GREATEST(r.check_in, :start_date_calc)
                        )
                    END
                ), 0) AS booked_room_nights
             FROM rooms rm
             LEFT JOIN reservations r
                ON r.room_id = rm.room_id
               AND r.status != 'Cancelled'
               AND r.check_in < DATE_ADD(:end_date_join, INTERVAL 1 DAY)
               AND r.check_out > :start_date_join
             GROUP BY rm.room_type
             ORDER BY MIN(rm.floor), rm.room_type"
        );
        $statement->execute([
            'start_date_calc' => $startDate,
            'end_date_calc' => $endDate,
            'start_date_join' => $startDate,
            'end_date_join' => $endDate,
        ]);

        $rows = [];
        $totalRooms = 0;
        $totalBookedNights = 0;

        foreach ($statement->fetchAll() as $row) {
            $roomCount = (int) $row['room_count'];
            $bookedNights = (int) $row['booked_room_nights'];
            $availableNights = max(0, ($roomCount * $days) - $bookedNights);
            $capacityNights = $roomCount * $days;

            $rows[] = [
                'room_type' => $row['room_type'],
                'room_count' => $roomCount,
                'booked_room_nights' => $bookedNights,
                'available_room_nights' => $availableNights,
                'occupancy_rate' => $capacityNights > 0 ? ($bookedNights / $capacityNights) * 100 : 0,
            ];

            $totalRooms += $roomCount;
            $totalBookedNights += $bookedNights;
        }

        $totalRoomNights = $totalRooms * $days;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days' => $days,
            'total_rooms' => $totalRooms,
            'total_room_nights' => $totalRoomNights,
            'booked_room_nights' => $totalBookedNights,
            'available_room_nights' => max(0, $totalRoomNights - $totalBookedNights),
            'occupancy_rate' => $totalRoomNights > 0 ? ($totalBookedNights / $totalRoomNights) * 100 : 0,
            'by_room_type' => $rows,
        ];
    }

    public function reservationTrendReport(string $startDate, string $endDate): array
    {
        [$start, $end] = $this->validateReportDateRange($startDate, $endDate);
        // SQL: Groups reservation creation counts by date for the trend report.
        $statement = $this->db->prepare(
            "SELECT
                DATE(created_at) AS reservation_date,
                COUNT(*) AS total_reservations,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_reservations,
                SUM(CASE WHEN status != 'Cancelled' THEN 1 ELSE 0 END) AS active_reservations
             FROM reservations
             WHERE DATE(created_at) BETWEEN :start_date AND :end_date
             GROUP BY DATE(created_at)
             ORDER BY reservation_date ASC"
        );
        $statement->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $indexedRows = [];

        foreach ($statement->fetchAll() as $row) {
            $indexedRows[(string) $row['reservation_date']] = [
                'reservation_date' => (string) $row['reservation_date'],
                'total_reservations' => (int) $row['total_reservations'],
                'cancelled_reservations' => (int) $row['cancelled_reservations'],
                'active_reservations' => (int) $row['active_reservations'],
            ];
        }

        $rows = [];
        $totalReservations = 0;
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $row = $indexedRows[$key] ?? [
                'reservation_date' => $key,
                'total_reservations' => 0,
                'cancelled_reservations' => 0,
                'active_reservations' => 0,
            ];

            $totalReservations += (int) $row['total_reservations'];
            $rows[] = $row;
        }

        return [
            'total_reservations' => $totalReservations,
            'rows' => $rows,
        ];
    }

    public function dashboardSummary(): array
    {
        // SQL: Counts unique guests with reservations created this month.
        $customersThisMonth = (int) $this->db->query(
            "SELECT COUNT(DISTINCT guest_id)
             FROM reservations
             WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')"
        )->fetchColumn();

        // SQL: Counts reservations still waiting for confirmation/payment review.
        $pendingReservations = (int) $this->db->query(
            "SELECT COUNT(*) FROM reservations WHERE status = 'Pending'"
        )->fetchColumn();

        // SQL: Counts confirmed or checked-in reservations checking out within the next 3 days.
        $upcomingCheckouts = (int) $this->db->query(
            "SELECT COUNT(*)
             FROM reservations
             WHERE status IN ('Confirmed', 'Checked-in')
               AND check_out BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY)"
        )->fetchColumn();

        return [
            'customers_this_month' => $customersThisMonth,
            'pending_reservations' => $pendingReservations,
            'upcoming_checkouts' => $upcomingCheckouts,
        ];
    }

    public function monthlyPerformance(): array
    {
        // SQL: Groups reservations by month and sums confirmed payment income for dashboard charts.
        $statement = $this->db->query(
            "SELECT DATE_FORMAT(r.created_at, '%b %Y') AS month_label,
                    COUNT(*) AS rooms_booked,
                    COALESCE(SUM(p.amount), 0) AS income
             FROM reservations r
             LEFT JOIN payments p
                ON p.reservation_id = r.reservation_id
               AND p.payment_status = 'Confirmed'
             GROUP BY YEAR(r.created_at), MONTH(r.created_at)
             ORDER BY YEAR(r.created_at) DESC, MONTH(r.created_at) DESC
             LIMIT 6"
        );

        return array_reverse($statement->fetchAll());
    }

    public function statusBreakdown(): array
    {
        $statuses = ['Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled'];
        $counts = array_fill_keys($statuses, 0);

        // SQL: Counts reservations by status for the reservation status chart.
        $statement = $this->db->query(
            "SELECT status, COUNT(*) AS total
             FROM reservations
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

    public function recent(int $limit = 5): array
    {
        // SQL: Reads the newest reservations with guest and room details for dashboard activity.
        $statement = $this->db->prepare(
            "SELECT r.reservation_id, r.status, r.check_in, r.check_out, r.total_amount,
                    g.first_name, g.last_name, rm.room_number, rm.room_type
             FROM reservations r
             INNER JOIN guests g ON g.guest_id = r.guest_id
             INNER JOIN rooms rm ON rm.room_id = r.room_id
             ORDER BY r.created_at DESC
             LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function syncRoomStatus(int $roomId): void
    {
        // SQL: Confirms the room exists before recalculating its status.
        $roomStatement = $this->db->prepare('SELECT status FROM rooms WHERE room_id = :room_id LIMIT 1');
        $roomStatement->execute(['room_id' => $roomId]);
        $currentStatus = $roomStatement->fetchColumn();

        // Preserve Maintenance status unless explicitly cleared by admin
        if ($currentStatus === 'Maintenance') {
            return;
        }

        // SQL: Finds the highest-priority active reservation for this room.
        // Checked-in wins over reserved/pending statuses because it means the room is physically occupied.
        $activeStatement = $this->db->prepare(
            "SELECT status
             FROM reservations
             WHERE room_id = :room_id
               AND status NOT IN ('Cancelled', 'Checked-out')
             ORDER BY
                CASE WHEN status = 'Checked-in' THEN 0 ELSE 1 END,
                check_in ASC,
                reservation_id ASC
             LIMIT 1"
        );
        $activeStatement->execute(['room_id' => $roomId]);
        $activeStatus = $activeStatement->fetchColumn();

        // Check latest reservation status to trigger Cleaning upon checkout
        $latestStmt = $this->db->prepare(
            "SELECT status FROM reservations WHERE room_id = :room_id ORDER BY check_out DESC, reservation_id DESC LIMIT 1"
        );
        $latestStmt->execute(['room_id' => $roomId]);
        $latestStatus = $latestStmt->fetchColumn();

        if ($activeStatus === 'Checked-in') {
            $roomStatus = 'Occupied';
        } elseif ($activeStatus !== false) {
            $roomStatus = 'Reserved';
        } elseif ($latestStatus === 'Checked-out' && ($currentStatus === 'Occupied' || $currentStatus === 'Cleaning')) {
            $roomStatus = 'Cleaning';
        } else {
            $roomStatus = 'Available';
        }

        // SQL: Writes the calculated room status back to the rooms table.
        $statement = $this->db->prepare('UPDATE rooms SET status = :status WHERE room_id = :room_id');
        $statement->execute([
            'status' => $roomStatus,
            'room_id' => $roomId,
        ]);
    }

    public function roomHasActiveOverlap(int $roomId, string $checkIn, string $checkOut, ?int $excludeReservationId = null): bool
    {
        // SQL: Counts active overlapping reservations for extension validation.
        // Active reservations (Confirmed, Checked-in) block dates permanently.
        // Unpaid Pending reservations only hold dates for a 24-hour grace window from creation time.
        $sql = "SELECT COUNT(*)
                FROM reservations
                WHERE room_id = :room_id
                  AND status NOT IN ('Cancelled', 'Checked-out')
                  AND (status IN ('Confirmed', 'Checked-in') OR (status = 'Pending' AND created_at >= NOW() - INTERVAL 24 HOUR))
                  AND NOT (check_out <= :check_in OR check_in >= :check_out)";
        $params = [
            'room_id' => $roomId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ];

        if ($excludeReservationId !== null) {
            $sql .= ' AND reservation_id != :reservation_id';
            $params['reservation_id'] = $excludeReservationId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private function availabilityLabel(bool $dateAvailable): string
    {
        if ($dateAvailable) {
            return 'Available for dates';
        }

        return 'Booked for dates';
    }

    private function validateDates(string $checkIn, string $checkOut): void
    {
        if ($checkIn === '' || $checkOut === '') {
            throw new RuntimeException('Check-in and check-out dates are required.');
        }

        $checkInDate = DateTimeImmutable::createFromFormat('!Y-m-d', $checkIn);
        $checkOutDate = DateTimeImmutable::createFromFormat('!Y-m-d', $checkOut);

        if (!$checkInDate || !$checkOutDate || $checkInDate->format('Y-m-d') !== $checkIn || $checkOutDate->format('Y-m-d') !== $checkOut) {
            throw new RuntimeException('Please enter valid check-in and check-out dates.');
        }

        if ($checkOutDate <= $checkInDate) {
            throw new RuntimeException('Check-out date must be after the check-in date.');
        }

        $today = new DateTimeImmutable('today');

        if ($checkInDate < $today) {
            throw new RuntimeException('Check-in date cannot be in the past.');
        }

        $nights = (int) $checkInDate->diff($checkOutDate)->days;
        if ($nights > 30) {
            throw new RuntimeException('Maximum stay duration for online reservations is 30 consecutive nights. For long-term corporate stays, please contact front desk.');
        }

        $maxAdvanceDate = $today->modify('+180 days');
        if ($checkInDate > $maxAdvanceDate) {
            throw new RuntimeException('Reservations can only be booked up to 180 days (6 months) in advance.');
        }
    }

    private function validateReportDateRange(string $startDate, string $endDate): array
    {
        if ($startDate === '' || $endDate === '') {
            throw new RuntimeException('Report start and end dates are required.');
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);

        if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
            throw new RuntimeException('Please enter valid report dates.');
        }

        if ($end < $start) {
            throw new RuntimeException('Report end date must be the same as or after the start date.');
        }

        return [$start, $end];
    }

    private function assertStatus(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Please choose a valid reservation status.');
        }
    }

    private function assertRoomExists(int $roomId): array
    {
        if ($roomId <= 0) {
            throw new RuntimeException('Please choose a valid room.');
        }

        // SQL: Verifies that the selected room exists before creating or updating a reservation.
        $statement = $this->db->prepare('SELECT * FROM rooms WHERE room_id = :room_id LIMIT 1');
        $statement->execute(['room_id' => $roomId]);
        $room = $statement->fetch();

        if (!$room) {
            throw new RuntimeException('The selected room does not exist.');
        }

        return $room;
    }

    private function validateGuestId(int $guestId): void
    {
        if ($guestId <= 0) {
            throw new RuntimeException('Please choose or create a valid guest record.');
        }

        // SQL: Verifies that the selected guest exists before saving a reservation.
        $statement = $this->db->prepare('SELECT COUNT(*) FROM guests WHERE guest_id = :guest_id');
        $statement->execute(['guest_id' => $guestId]);

        if ((int) $statement->fetchColumn() === 0) {
            throw new RuntimeException('The selected guest does not exist.');
        }
    }

    private function validateTotalAmount(float $totalAmount): void
    {
        if ($totalAmount <= 0) {
            throw new RuntimeException('Reservation total must be greater than zero.');
        }
    }
}
