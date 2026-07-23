<?php
require_once __DIR__ . '/../public/includes/bootstrap.php';
$db = Database::connect();
$db->exec("ALTER TABLE reservations MODIFY COLUMN status ENUM('Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled', 'Conflict') NOT NULL DEFAULT 'Pending'");
echo "ENUM updated successfully. 'Conflict' status is now available.\n";
