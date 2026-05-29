<?php

declare(strict_types=1);

class User
{
    public function __construct(private PDO $db)
    {
    }

    public function countUsers(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function all(): array
    {
        $statement = $this->db->query('SELECT user_id, full_name, email, role, created_at FROM users ORDER BY created_at DESC');

        return $statement->fetchAll();
    }

    public function find(int $userId): ?array
    {
        $statement = $this->db->prepare('SELECT user_id, full_name, email, role, created_at FROM users WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function create(array $data): bool
    {
        if (trim((string) $data['full_name']) === '' || trim((string) $data['email']) === '' || trim((string) $data['password']) === '') {
            throw new RuntimeException('Full name, email, and password are required.');
        }

        $this->validateEmail((string) $data['email']);
        $this->validatePassword((string) $data['password']);
        $this->assertEmailIsAvailable((string) $data['email']);

        $role = in_array($data['role'], ['admin', 'user'], true) ? $data['role'] : 'user';

        $statement = $this->db->prepare(
            'INSERT INTO users (full_name, email, password_hash, role) VALUES (:full_name, :email, :password_hash, :role)'
        );

        return $statement->execute([
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $role,
        ]);
    }

    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);

        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    public function update(int $userId, array $data): bool
    {
        if (trim((string) $data['full_name']) === '' || trim((string) $data['email']) === '') {
            throw new RuntimeException('Full name and email are required.');
        }

        $this->validateEmail((string) $data['email']);
        $this->assertEmailIsAvailable((string) $data['email'], $userId);

        $role = in_array($data['role'], ['admin', 'user'], true) ? $data['role'] : 'user';
        $password = trim((string) ($data['password'] ?? ''));

        if ($password !== '') {
            $this->validatePassword($password);

            $statement = $this->db->prepare(
                'UPDATE users SET full_name = :full_name, email = :email, role = :role, password_hash = :password_hash WHERE user_id = :user_id'
            );

            return $statement->execute([
                'full_name' => trim($data['full_name']),
                'email' => trim($data['email']),
                'role' => $role,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'user_id' => $userId,
            ]);
        }

        $statement = $this->db->prepare(
            'UPDATE users SET full_name = :full_name, email = :email, role = :role WHERE user_id = :user_id'
        );

        return $statement->execute([
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'role' => $role,
            'user_id' => $userId,
        ]);
    }

    public function delete(int $userId): bool
    {
        $statement = $this->db->prepare('DELETE FROM users WHERE user_id = :user_id');

        return $statement->execute(['user_id' => $userId]);
    }

    private function validateEmail(string $email): void
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        if (strlen($email) > 150) {
            throw new RuntimeException('Email address is too long.');
        }
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 6) {
            throw new RuntimeException('Password must be at least 6 characters long.');
        }
    }

    private function assertEmailIsAvailable(string $email, ?int $ignoreUserId = null): void
    {
        $existingUser = $this->findByEmail(trim($email));

        if (!$existingUser) {
            return;
        }

        if ($ignoreUserId !== null && (int) $existingUser['user_id'] === $ignoreUserId) {
            return;
        }

        throw new RuntimeException('That email address is already registered.');
    }
}
