<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/includes/bootstrap.php';

echo "Deleting test accounts from active database...\n";

try {
    $db = Database::connect();
    $targetEmails = ['wizardofxerox@gmail.com', 'vincent@gmail.com', 'lore@gmail.com', 'jane.smith@example.com'];

    foreach ($targetEmails as $email) {
        $stmt = $db->prepare('SELECT user_id, full_name FROM users WHERE LOWER(email) = LOWER(:email)');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $id = (int) $user['user_id'];
            $name = (string) $user['full_name'];
            echo "Deleting {$name} ({$email}, ID {$id})...\n";

            @$db->exec("DELETE FROM reservations WHERE user_id = {$id}");
            @$db->exec("DELETE FROM contact_messages WHERE user_id = {$id} OR LOWER(email) = " . $db->quote(strtolower($email)));
            @$db->exec("DELETE FROM users WHERE user_id = {$id}");
        }
    }

    echo "✅ Successfully cleaned test accounts from active MySQL database!\n";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
