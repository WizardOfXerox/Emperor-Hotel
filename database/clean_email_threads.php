<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/includes/bootstrap.php';

echo "Cleaning contact_messages database threads...\n";

try {
    $db = Database::connect();
    $rows = $db->query('SELECT message_id, message FROM contact_messages')->fetchAll();
    $cleanedCount = 0;

    foreach ($rows as $row) {
        $msg = (string) $row['message'];

        if (str_contains($msg, '[Guest Follow-up Reply')) {
            $parts = explode('[Guest Follow-up Reply', $msg);
            $cleanMsg = array_shift($parts);

            foreach ($parts as $p) {
                $lines = explode(']:', $p, 2);
                $hdr = $lines[0];
                $body = cleanEmailReplyBody($lines[1] ?? '');
                if ($body !== '') {
                    $cleanMsg .= '[Guest Follow-up Reply' . $hdr . ']:' . "\n" . $body . "\n\n";
                }
            }

            $cleanMsg = trim((string)$cleanMsg);

            $stmt = $db->prepare('UPDATE contact_messages SET message = :m WHERE message_id = :id');
            $stmt->execute(['m' => $cleanMsg, 'id' => $row['message_id']]);
            $cleanedCount++;
        }
    }

    echo "Successfully cleaned {$cleanedCount} message thread(s) in database!\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
