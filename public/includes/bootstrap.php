<?php

declare(strict_types=1);

function loadEnvironmentFile(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));

        if ($name === '') {
            continue;
        }

        if ($value !== '') {
            $firstChar = $value[0];
            $lastChar = substr($value, -1);

            if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $value = stripcslashes($value);
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

define('APP_ROOT', dirname(__DIR__, 2));
define('PUBLIC_ROOT', dirname(__DIR__));

loadEnvironmentFile(APP_ROOT . '/.env');

$timezone = getenv('APP_TIMEZONE') ?: 'Asia/Manila';
date_default_timezone_set($timezone);

require_once APP_ROOT . '/app/config/database.php';
require_once APP_ROOT . '/app/helpers/auth.php';
require_once APP_ROOT . '/app/helpers/mailer.php';

spl_autoload_register(static function (string $className): void {
    $modelPath = APP_ROOT . '/app/models/' . $className . '.php';

    if (file_exists($modelPath)) {
        require_once $modelPath;
    }
});

function isFeatureMapEnabled(): bool
{
    $val = getenv('FEATURE_MAP_ENABLED');
    if ($val === false && isset($_ENV['FEATURE_MAP_ENABLED'])) {
        $val = $_ENV['FEATURE_MAP_ENABLED'];
    }
    if ($val === false && isset($_SERVER['FEATURE_MAP_ENABLED'])) {
        $val = $_SERVER['FEATURE_MAP_ENABLED'];
    }
    if ($val === false || $val === null || $val === '') {
        return true;
    }

    $clean = strtolower(trim((string) $val));
    return !in_array($clean, ['false', '0', 'off', 'no', 'disabled'], true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_GET['check_in']) && !empty($_GET['check_out'])) {
    $_SESSION['selected_check_in'] = trim((string)$_GET['check_in']);
    $_SESSION['selected_check_out'] = trim((string)$_GET['check_out']);
}
if (!empty($_GET['selected_room'])) {
    $_SESSION['selected_room_id'] = (int)$_GET['selected_room'];
} else if (!empty($_GET['id']) && str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'room-detail.php')) {
    $_SESSION['selected_room_id'] = (int)$_GET['id'];
}
