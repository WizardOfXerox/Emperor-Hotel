<?php
require_once __DIR__ . '/../public/includes/bootstrap.php';
$db = Database::connect();
$cols = $db->query("DESCRIBE rooms")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
