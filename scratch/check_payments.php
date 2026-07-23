<?php
require_once __DIR__ . '/../public/includes/bootstrap.php';
$db = Database::connect();
$rows = $db->query("SELECT payment_status, COUNT(*) as cnt, SUM(amount) as sum_amt FROM payments GROUP BY payment_status")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
