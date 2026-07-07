<?php

declare(strict_types=1);

class Guest
{
    public function __construct(private PDO $db)
    {
    }

    public function find(int $guestId): ?array
    {
        // SQL: Finds one guest record by its primary key for editing and reservation links.
        $statement = $this->db->prepare('SELECT * FROM guests WHERE guest_id = :guest_id LIMIT 1');
        $statement->execute(['guest_id' => $guestId]);
        $guest = $statement->fetch();

        return $guest ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        // SQL: Finds a guest by email so reservation forms can reuse an existing guest record.
        $statement = $this->db->prepare('SELECT * FROM guests WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $guest = $statement->fetch();

        return $guest ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        // SQL: Finds the guest profile linked to a registered user account.
        $statement = $this->db->prepare('SELECT * FROM guests WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $guest = $statement->fetch();

        return $guest ?: null;
    }

    public function search(string $term = ''): array
    {
        $term = trim($term);
        $params = [];
        $where = '';

        if ($term !== '') {
            $where = "WHERE CONCAT(g.first_name, ' ', g.last_name) LIKE :term
                      OR g.email LIKE :term
                      OR g.phone LIKE :term";
            $params['term'] = '%' . $term . '%';
        }

        // SQL: Lists guests with reservation count, last stay, and total spending.
        // The LEFT JOIN keeps guests visible even when they have no reservations yet.
        $statement = $this->db->prepare(
            "SELECT g.*,
                    COALESCE(stats.reservation_count, 0) AS reservation_count,
                    stats.last_stay,
                    COALESCE(stats.total_spent, 0) AS total_spent
             FROM guests g
             LEFT JOIN (
                SELECT guest_id,
                       COUNT(*) AS reservation_count,
                       MAX(check_out) AS last_stay,
                       SUM(total_amount) AS total_spent
                FROM reservations
                GROUP BY guest_id
             ) stats ON stats.guest_id = g.guest_id
             {$where}
             ORDER BY g.created_at DESC"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function reservationHistory(int $guestId): array
    {
        // SQL: Shows a guest's booking history with room details and payment totals.
        // The payment subquery separates confirmed money from pending payment logs.
        $statement = $this->db->prepare(
            "SELECT r.*,
                    rm.room_number,
                    rm.room_type,
                    COALESCE(payments.confirmed_paid, 0) AS confirmed_paid,
                    COALESCE(payments.pending_paid, 0) AS pending_paid
             FROM reservations r
             INNER JOIN rooms rm ON rm.room_id = r.room_id
             LEFT JOIN (
                SELECT reservation_id,
                       SUM(CASE WHEN payment_status = 'Confirmed' THEN amount ELSE 0 END) AS confirmed_paid,
                       SUM(CASE WHEN payment_status = 'Pending' THEN amount ELSE 0 END) AS pending_paid
                FROM payments
                GROUP BY reservation_id
             ) payments ON payments.reservation_id = r.reservation_id
             WHERE r.guest_id = :guest_id
             ORDER BY r.created_at DESC"
        );
        $statement->execute(['guest_id' => $guestId]);

        return $statement->fetchAll();
    }

    public function create(array $data): int
    {
        $this->validateGuestData($data);

        // SQL: Creates a guest profile that can be used by reservations and walk-in bookings.
        $statement = $this->db->prepare(
            'INSERT INTO guests (user_id, first_name, last_name, phone, email) VALUES (:user_id, :first_name, :last_name, :phone, :email)'
        );
        $statement->execute([
            'user_id' => $data['user_id'] ?? null,
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $guestId, array $data): bool
    {
        $this->validateGuestData($data);

        // SQL: Updates contact/profile details for one guest record.
        $statement = $this->db->prepare(
            'UPDATE guests SET user_id = :user_id, first_name = :first_name, last_name = :last_name, phone = :phone, email = :email WHERE guest_id = :guest_id'
        );

        return $statement->execute([
            'user_id' => $data['user_id'] ?? null,
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'guest_id' => $guestId,
        ]);
    }

    public function delete(int $guestId): bool
    {
        // SQL: Deletes one guest record by primary key.
        $statement = $this->db->prepare('DELETE FROM guests WHERE guest_id = :guest_id');

        return $statement->execute(['guest_id' => $guestId]);
    }

    public function upsertFromDetails(array $data): int
    {
        $guestId = isset($data['guest_id']) ? (int) $data['guest_id'] : 0;

        if ($guestId > 0 && $this->find($guestId)) {
            $this->update($guestId, $data);

            return $guestId;
        }

        $email = trim((string) ($data['email'] ?? ''));

        if ($email !== '') {
            $existingGuest = $this->findByEmail($email);

            if ($existingGuest) {
                $this->update((int) $existingGuest['guest_id'], $data);

                return (int) $existingGuest['guest_id'];
            }
        }

        return $this->create($data);
    }

    public function ensureForUser(array $user): int
    {
        $existingGuest = $this->findByUserId((int) $user['user_id']);

        $nameParts = preg_split('/\s+/', trim($user['full_name'])) ?: [];
        $firstName = $nameParts[0] ?? $user['full_name'];
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'Guest';

        $data = [
            'user_id' => (int) $user['user_id'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => '',
            'email' => $user['email'],
        ];

        if ($existingGuest) {
            $this->update((int) $existingGuest['guest_id'], $data);

            return (int) $existingGuest['guest_id'];
        }

        return $this->create($data);
    }

    private function validateGuestData(array $data): void
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('Guest first name and last name are required.');
        }

        $this->validateNamePart($firstName, 'Guest first name');
        $this->validateNamePart($lastName, 'Guest last name');

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid guest email address.');
        }

        if (strlen($email) > 150) {
            throw new RuntimeException('Guest email is too long.');
        }

        if (strlen($phone) > 30) {
            throw new RuntimeException('Guest phone number is too long.');
        }
    }

    private function validateNamePart(string $name, string $label): void
    {
        if (!preg_match("/^[\\p{L}](?:[\\p{L} .'-]*[\\p{L}.])?$/u", $name)) {
            throw new RuntimeException($label . ' can only include letters, spaces, periods, apostrophes, and hyphens.');
        }
    }
}
