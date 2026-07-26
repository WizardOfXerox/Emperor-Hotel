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
        if (isset($_SERVER['REQUEST_URI'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }
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

function splitFullName(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];

    return [
        'first_name' => $parts[0] ?? $fullName,
        'last_name' => count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Guest',
    ];
}

// ──── Issue #16 Fix: CSRF Token Protection ────

function csrfToken(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrfToken()) . '">';
}

function validateCsrf(): bool
{
    $token = $_POST['_csrf_token'] ?? '';
    return $token !== '' && hash_equals(csrfToken(), $token);
}

// ──── Issue #17 Fix: Session-Based Rate Limiting ────

function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    $rateLimits = $_SESSION['_rate_limits'] ?? [];
    $entry = $rateLimits[$key] ?? ['count' => 0, 'expires' => 0];

    if (time() > $entry['expires']) {
        return true; // Window expired, allow
    }

    return $entry['count'] < $maxAttempts;
}

function recordAttempt(string $key, int $windowSeconds = 300): void
{
    $rateLimits = $_SESSION['_rate_limits'] ?? [];
    $entry = $rateLimits[$key] ?? ['count' => 0, 'expires' => 0];

    if (time() > $entry['expires']) {
        $entry = ['count' => 1, 'expires' => time() + $windowSeconds];
    } else {
        $entry['count']++;
    }

    $rateLimits[$key] = $entry;
    $_SESSION['_rate_limits'] = $rateLimits;
}

function clearRateLimit(string $key): void
{
    unset($_SESSION['_rate_limits'][$key]);
}

// ──── Issue #19 Fix: Redirect URL Validation ────

function isInternalRedirect(string $url): bool
{
    // Only allow relative paths (starting with ../ or /) — block external URLs
    $url = trim($url);
    if ($url === '') return false;
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
        return false;
    }
    return str_starts_with($url, '../') || str_starts_with($url, '/') || str_starts_with($url, './');
}
