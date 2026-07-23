<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/includes/bootstrap.php';

try {
    $db = Database::connect();
    $tables = ['users', 'guests', 'rooms', 'reservations', 'payments', 'room_reviews'];

    $sqlDump = "-- Emperor Hotel Database Seed Data Dump\n";
    $sqlDump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "USE emperors_hotel_db;\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        $sqlDump .= "-- Table: $table\n";
        $sqlDump .= "TRUNCATE TABLE `$table`;\n";

        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            $cols = array_keys($rows[0]);
            $colNames = implode('`, `', $cols);
            
            $sqlDump .= "INSERT INTO `$table` (`$colNames`) VALUES\n";
            $valueLines = [];

            foreach ($rows as $row) {
                $vals = array_map(function($v) use ($db) {
                    if ($v === null) return 'NULL';
                    return $db->quote((string)$v);
                }, array_values($row));
                $valueLines[] = "(" . implode(', ', $vals) . ")";
            }

            $sqlDump .= implode(",\n", $valueLines) . ";\n\n";
        }
    }

    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    file_put_contents(__DIR__ . '/seed_large_dataset.sql', $sqlDump);
    echo "Exported database to database/seed_large_dataset.sql (" . strlen($sqlDump) . " bytes).\n";

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
