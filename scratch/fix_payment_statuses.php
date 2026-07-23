<?php
require_once __DIR__ . '/../public/includes/bootstrap.php';
$db = Database::connect();
$updated = $db->exec("UPDATE payments SET payment_status = 'Confirmed' WHERE payment_status = '' OR payment_status IS NULL");
echo "Updated $updated payment records with empty status to 'Confirmed'.\n";

$rows = $db->query("SELECT payment_status, COUNT(*) as cnt, SUM(amount) as sum_amt FROM payments GROUP BY payment_status")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
