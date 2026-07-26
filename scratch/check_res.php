<?php
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();
$stmt = $db->query("SELECT reservation_id, room_id, check_in, check_out, status FROM reservations WHERE status NOT IN ('Cancelled', 'Checked-out') AND NOT (check_out <= '2026-07-26' OR check_in >= '2026-07-27') ORDER BY room_id ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Overlapping reservations for 2026-07-26 to 2026-07-27 count: " . count($rows) . "\n";
print_r($rows);
