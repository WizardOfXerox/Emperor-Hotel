<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

define('APP_ROOT', dirname(__DIR__, 2));
define('PUBLIC_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/app/config/database.php';
require_once APP_ROOT . '/app/helpers/auth.php';

spl_autoload_register(static function (string $className): void {
    $modelPath = APP_ROOT . '/app/models/' . $className . '.php';

    if (file_exists($modelPath)) {
        require_once $modelPath;
    }
});
