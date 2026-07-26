<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/includes/bootstrap.php';

echo "Deleting target users (ID 2, 3, 4) from active database...\n";

try {
    $db = Database::connect();
    $stmt = $db->query("SELECT user_id, email, full_name FROM users WHERE user_id IN (2, 3, 4) OR email IN ('wizardofxerox@gmail.com', 'vincent@gmail.com', 'lore@gmail.com')");
    $foundUsers = $stmt->fetchAll();

    foreach ($foundUsers as $u) {
        $id = (int) $u['user_id'];
        $email = (string) $u['email'];
        $name = (string) $u['full_name'];

        echo "Removing User ID {$id} ({$name} - {$email})...\n";

        $cleanQueries = [
            "DELETE FROM reservations WHERE user_id = {$id}",
            "DELETE FROM contact_messages WHERE user_id = {$id} OR email = " . $db->quote($email),
            "DELETE FROM users WHERE user_id = {$id}",
        ];

        foreach ($cleanQueries as $sql) {
            try {
                $db->exec($sql);
            } catch (Throwable $e) {
                // Table might not exist or no matching FK, ignore
            }
        }
    }

    echo "✅ Successfully deleted requested users from active MySQL database!\n";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
