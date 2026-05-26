<?php

declare(strict_types=1);

class Reservation
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $statement = $this->db->query(
            "SELECT r.*, g.first_name, g.last_name, g.email AS guest_email, g.phone,
                    rm.room_number, rm.room_type, u.full_name AS user_name
             FROM reservations r
             INNER JOIN guests g ON g.guest_id = r.guest_id
             INNER JOIN rooms rm ON rm.room_id = r.room_id
             LEFT JOIN users u ON u.user_id = r.user_id
             ORDER BY r.created_at DESC"
        );

        return $statement->fetchAll();
    }

    public function find(int $reservationId): ?array
    {
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
        $this->validateDates($data['check_in'], $data['check_out']);

        if (!$this->roomIsAvailable((int) $data['room_id'], $data['check_in'], $data['check_out'])) {
            throw new RuntimeException('The selected room is not available for those dates.');
        }

        $statement = $this->db->prepare(
            'INSERT INTO reservations (user_id, guest_id, room_id, check_in, check_out, adults, children, addons, total_amount, status)
             VALUES (:user_id, :guest_id, :room_id, :check_in, :check_out, :adults, :children, :addons, :total_amount, :status)'
        );

        $saved = $statement->execute([
            'user_id' => $data['user_id'] ?? null,
            'guest_id' => (int) $data['guest_id'],
            'room_id' => (int) $data['room_id'],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'adults' => (int) $data['adults'],
            'children' => (int) $data['children'],
            'addons' => trim((string) ($data['addons'] ?? '')),
            'total_amount' => (float) $data['total_amount'],
            'status' => $data['status'],
        ]);

        if ($saved) {
            $this->syncRoomStatus((int) $data['room_id'], $data['status']);
        }

        return $saved;
    }

    public function update(int $reservationId, array $data): bool
    {
        $existing = $this->find($reservationId);

        if (!$existing) {
            return false;
        }

        $this->validateDates($data['check_in'], $data['check_out']);

        if (!$this->roomIsAvailable((int) $data['room_id'], $data['check_in'], $data['check_out'], $reservationId)) {
            throw new RuntimeException('The selected room is not available for those dates.');
        }

        $statement = $this->db->prepare(
            'UPDATE reservations
             SET guest_id = :guest_id,
                 room_id = :room_id,
                 check_in = :check_in,
                 check_out = :check_out,
                 adults = :adults,
                 children = :children,
                 addons = :addons,
                 total_amount = :total_amount,
                 status = :status
             WHERE reservation_id = :reservation_id'
        );

        $saved = $statement->execute([
            'guest_id' => (int) $data['guest_id'],
            'room_id' => (int) $data['room_id'],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'adults' => (int) $data['adults'],
            'children' => (int) $data['children'],
            'addons' => trim((string) ($data['addons'] ?? '')),
            'total_amount' => (float) $data['total_amount'],
            'status' => $data['status'],
            'reservation_id' => $reservationId,
        ]);

        if ($saved) {
            if ((int) $existing['room_id'] !== (int) $data['room_id']) {
                $this->syncRoomStatus((int) $existing['room_id'], 'Available');
            }

            $this->syncRoomStatus((int) $data['room_id'], $data['status']);
        }

        return $saved;
    }

    public function delete(int $reservationId): bool
    {
        $existing = $this->find($reservationId);

        if (!$existing) {
            return false;
        }

        $statement = $this->db->prepare('DELETE FROM reservations WHERE reservation_id = :reservation_id');
        $deleted = $statement->execute(['reservation_id' => $reservationId]);

        if ($deleted) {
            $this->syncRoomStatus((int) $existing['room_id'], 'Available');
        }

        return $deleted;
    }

    public function updateStatus(int $reservationId, string $status): bool
    {
        $existing = $this->find($reservationId);

        if (!$existing) {
            return false;
        }

        $statement = $this->db->prepare('UPDATE reservations SET status = :status WHERE reservation_id = :reservation_id');
        $saved = $statement->execute([
            'status' => $status,
            'reservation_id' => $reservationId,
        ]);

        if ($saved) {
            $this->syncRoomStatus((int) $existing['room_id'], $status);
        }

        return $saved;
    }

    public function roomIsAvailable(int $roomId, string $checkIn, string $checkOut, ?int $excludeReservationId = null): bool
    {
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

    public function dashboardSummary(): array
    {
        $customersThisMonth = (int) $this->db->query(
            "SELECT COUNT(DISTINCT guest_id)
             FROM reservations
             WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')"
        )->fetchColumn();

        $pendingReservations = (int) $this->db->query(
            "SELECT COUNT(*) FROM reservations WHERE status = 'Pending'"
        )->fetchColumn();

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

    private function syncRoomStatus(int $roomId, string $reservationStatus): void
    {
        $roomStatus = match ($reservationStatus) {
            'Checked-in' => 'Occupied',
            'Cancelled', 'Checked-out' => 'Available',
            default => 'Reserved',
        };

        $statement = $this->db->prepare('UPDATE rooms SET status = :status WHERE room_id = :room_id');
        $statement->execute([
            'status' => $roomStatus,
            'room_id' => $roomId,
        ]);
    }

    private function validateDates(string $checkIn, string $checkOut): void
    {
        if ($checkIn === '' || $checkOut === '') {
            throw new RuntimeException('Check-in and check-out dates are required.');
        }

        if (strtotime($checkOut) <= strtotime($checkIn)) {
            throw new RuntimeException('Check-out date must be after the check-in date.');
        }
    }
}
