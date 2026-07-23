<?php
require_once __DIR__ . '/../public/includes/bootstrap.php';
$db = Database::connect();
try {
    $db->exec("ALTER TABLE rooms ADD COLUMN image_url TEXT DEFAULT NULL AFTER view_type");
    echo "Added image_url column to rooms table.\n";
} catch (Exception $e) {
    echo "Column may already exist: " . $e->getMessage() . "\n";
}
