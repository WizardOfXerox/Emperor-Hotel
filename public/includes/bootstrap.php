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
