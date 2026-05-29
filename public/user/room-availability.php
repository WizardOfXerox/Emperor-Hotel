<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/room_availability_api.php';

requireAuth('../auth/login.php');
requireRole('user', '../admin/dashboard.php');

sendRoomAvailabilityJson(Database::connect());
