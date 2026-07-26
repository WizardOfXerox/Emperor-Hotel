<?php
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();

$db->exec("UPDATE rooms SET bed_type = '1 King Bed', max_capacity = 2, view_type = 'City Skyline View' WHERE room_type = 'Imperial Deluxe'");
$db->exec("UPDATE rooms SET bed_type = '2 Queen Beds', max_capacity = 4, view_type = 'Garden Terrace View' WHERE room_type = 'Royal Executive'");
$db->exec("UPDATE rooms SET bed_type = '2 Emperor King Beds', max_capacity = 6, view_type = 'Panoramic Ocean View' WHERE room_type = 'Emperor Presidential'");

echo "Room specifications updated successfully in MySQL database!\n";
$stmt = $db->query("SELECT room_id, room_number, room_type, price_per_night, bed_type, max_capacity, view_type FROM rooms WHERE room_number IN ('101', '201', '301')");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
