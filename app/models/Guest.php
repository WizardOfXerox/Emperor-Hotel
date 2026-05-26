<?php

declare(strict_types=1);

class Guest
{
    public function __construct(private PDO $db)
    {
    }

    public function find(int $guestId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM guests WHERE guest_id = :guest_id LIMIT 1');
        $statement->execute(['guest_id' => $guestId]);
        $guest = $statement->fetch();

        return $guest ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM guests WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $guest = $statement->fetch();

        return $guest ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM guests WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $guest = $statement->fetch();

        return $guest ?: null;
    }

    public function create(array $data): int
    {
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
}
