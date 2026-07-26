<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/includes/bootstrap.php';

echo "Deduplicating and cleaning contact_messages database threads...\n";

try {
    $db = Database::connect();
    $rows = $db->query('SELECT message_id, message FROM contact_messages')->fetchAll();
    $cleanedCount = 0;

    foreach ($rows as $row) {
        $msg = (string) $row['message'];

        if (str_contains($msg, '[Guest Follow-up Reply')) {
            $parts = explode('[Guest Follow-up Reply', $msg);
            $mainText = trim(array_shift($parts));

            $uniqueReplies = [];
            $seenContents = [];

            foreach ($parts as $p) {
                $subParts = explode(']:', $p, 2);
                $hdr = $subParts[0] ?? '';
                $body = cleanEmailReplyBody($subParts[1] ?? '');

                if ($body === '') {
                    continue;
                }

                $normalizedBody = strtolower((string)preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $body));
                if (!in_array($normalizedBody, $seenContents, true)) {
                    $seenContents[] = $normalizedBody;
                    $uniqueReplies[] = '[Guest Follow-up Reply' . $hdr . ']:' . "\n" . $body;
                }
            }

            $finalMessage = $mainText;
            if (!empty($uniqueReplies)) {
                $finalMessage .= "\n\n" . implode("\n\n", $uniqueReplies);
            }

            if ($finalMessage !== $msg) {
                $stmt = $db->prepare('UPDATE contact_messages SET message = :m WHERE message_id = :id');
                $stmt->execute(['m' => $finalMessage, 'id' => $row['message_id']]);
                $cleanedCount++;
            }
        }
    }

    echo "Successfully deduplicated and cleaned {$cleanedCount} message thread(s) in database!\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
