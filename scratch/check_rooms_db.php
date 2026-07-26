<?php
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();
$stmt = $db->query("SELECT room_id, room_number, room_type, price_per_night, bed_type, max_capacity, view_type FROM rooms ORDER BY room_id ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
