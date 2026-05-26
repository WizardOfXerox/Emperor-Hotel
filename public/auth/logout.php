<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

logoutCurrentUser();
session_start();
setFlash('success', 'You have been logged out.');
redirect('login.php');
