<?php
require_once __DIR__ . '/../public/includes/bootstrap.php';
$db = Database::connect();
$stmt = $db->query("
    SELECT r1.reservation_id AS id1, r1.guest_id AS g1, r1.check_in AS in1, r1.check_out AS out1, r1.status AS status1,
           r2.reservation_id AS id2, r2.guest_id AS g2, r2.check_in AS in2, r2.check_out AS out2, r2.status AS status2,
           rm.room_number
    FROM reservations r1
    INNER JOIN reservations r2 ON r2.room_id = r1.room_id AND r2.reservation_id > r1.reservation_id
    INNER JOIN rooms rm ON rm.room_id = r1.room_id
    WHERE r1.status NOT IN ('Cancelled', 'Checked-out')
      AND r2.status NOT IN ('Cancelled', 'Checked-out')
      AND r1.check_in < r2.check_out
      AND r1.check_out > r2.check_in
");
$conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($conflicts);
