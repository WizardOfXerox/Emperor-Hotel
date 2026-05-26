<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

function e(string|null|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlashMessages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return $messages;
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['auth_user'] = [
        'user_id' => (int) $user['user_id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function logoutCurrentUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function currentUser(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function requireAuth(string $loginPath): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to continue.');
        redirect($loginPath);
    }
}

function requireRole(string $role, string $fallbackPath): void
{
    $user = currentUser();

    if (!$user || $user['role'] !== $role) {
        setFlash('warning', 'You do not have access to that page.');
        redirect($fallbackPath);
    }
}

function formatMoney(float $amount): string
{
    return 'PHP ' . number_format($amount, 2);
}
