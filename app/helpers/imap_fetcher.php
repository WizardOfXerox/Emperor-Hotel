<?php

declare(strict_types=1);

/**
 * Pure PHP SSL Socket IMAP Sync Engine.
 * 
 * Connects directly to ssl://imap.gmail.com:993 (or configured IMAP host)
 * using native PHP streams so it works 100% reliably out-of-the-box in XAMPP
 * WITHOUT requiring the php_imap extension to be enabled in php.ini!
 */

/**
 * Clean, truncate, and format raw email reply body text.
 * Strips MIME boundary markers, headers, and quoted history text.
 */
function cleanEmailReplyBody(string $rawBody): string
{
    // Decode quoted-printable if encoded
    if (str_contains($rawBody, '=E2=80') || str_contains($rawBody, 'Content-Transfer-Encoding: quoted-printable') || preg_match('/=[0-9A-F]{2}/i', $rawBody)) {
        $rawBody = quoted_printable_decode($rawBody);
    }

    $rawBody = strip_tags($rawBody);
    $lines = explode("\n", str_replace("\r\n", "\n", $rawBody));
    $cleanLines = [];

    foreach ($lines as $line) {
        // Normalize all unicode spaces (including \u{00A0} and \u{202F}) to standard space
        $trimmed = preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $line);
        $trimmed = trim((string)$trimmed);

        if ($trimmed === '') {
            continue;
        }

        // Stop parsing at quoted thread dividers (e.g. "On Sun, Jul 26... wrote:")
        if (
            preg_match('/^On\s+[A-Za-z0-9,.\s:]+(wrote|written|said)\s*:/i', $trimmed) ||
            preg_match('/^On\s+.*<.*@.*>/i', $trimmed) ||
            preg_match('/^On\s+[A-Z][a-z]{2},\s+[A-Z][a-z]{2}\s+\d+/i', $trimmed) ||
            (str_starts_with($trimmed, 'On ') && str_contains($trimmed, '<') && str_contains($trimmed, '>')) ||
            (str_starts_with($trimmed, 'On ') && str_contains(strtolower($trimmed), 'wrote')) ||
            str_starts_with($trimmed, '-----Original Message-----') ||
            str_starts_with($trimmed, '---------- Forwarded message ---------') ||
            str_starts_with($trimmed, '> THE EMPEROR HOTEL') ||
            str_starts_with($trimmed, 'THE EMPEROR HOTEL')
        ) {
            break;
        }

        // Ignore MIME boundaries (e.g. --000000000000cfc8d40657828df4)
        if (str_starts_with($trimmed, '--') && strlen($trimmed) > 10) {
            continue;
        }

        // Ignore MIME headers
        if (
            preg_match('/^Content-Type\s*:/i', $trimmed) ||
            preg_match('/^Content-Transfer-Encoding\s*:/i', $trimmed) ||
            preg_match('/^Content-Disposition\s*:/i', $trimmed) ||
            str_starts_with(strtolower($trimmed), 'charset=')
        ) {
            continue;
        }

        // Ignore quoted history lines starting with '>'
        if (str_starts_with($trimmed, '>')) {
            continue;
        }

        $cleanLines[] = $trimmed;
    }

    $cleaned = implode("\n", $cleanLines);
    $cleaned = preg_replace("/\n{3,}/", "\n\n", $cleaned);

    return trim($cleaned);
}

function fetchImapLine($socket): string
{
    stream_set_timeout($socket, 2);
    $line = fgets($socket);
    $info = stream_get_meta_data($socket);
    if ($info['timed_out'] ?? false) {
        return '';
    }
    return $line !== false ? $line : '';
}

function sendImapCommand($socket, string $tag, string $command): array
{
    fwrite($socket, "{$tag} {$command}\r\n");
    $lines = [];
    $start = time();
    while (!feof($socket) && (time() - $start) < 4) {
        $line = fetchImapLine($socket);
        if ($line === '') {
            break;
        }
        $lines[] = $line;
        if (str_starts_with($line, "{$tag} OK") || str_starts_with($line, "{$tag} NO") || str_starts_with($line, "{$tag} BAD")) {
            break;
        }
    }
    return $lines;
}

function syncGmailReplies(PDO $db): array
{
    $imapHost = getenv('IMAP_HOST') ?: ($_ENV['IMAP_HOST'] ?? ($_SERVER['IMAP_HOST'] ?? 'imap.gmail.com'));
    $imapPort = (int) (getenv('IMAP_PORT') ?: ($_ENV['IMAP_PORT'] ?? ($_SERVER['IMAP_PORT'] ?? 993)));

    $smtpUser = getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? ($_SERVER['SMTP_USER'] ?? ''));
    $smtpPass = getenv('SMTP_PASSWORD') ?: (getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASSWORD'] ?? ($_ENV['SMTP_PASS'] ?? ($_SERVER['SMTP_PASSWORD'] ?? ($_SERVER['SMTP_PASS'] ?? '')))));

    $imapUser = getenv('IMAP_USER') ?: ($_ENV['IMAP_USER'] ?? ($_SERVER['IMAP_USER'] ?? $smtpUser));
    $imapPass = getenv('IMAP_PASSWORD') ?: (getenv('IMAP_PASS') ?: ($_ENV['IMAP_PASSWORD'] ?? ($_ENV['IMAP_PASS'] ?: ($_SERVER['IMAP_PASSWORD'] ?? ($_SERVER['SMTP_PASS'] ?? $smtpPass)))));

    if (empty($imapUser) || empty($imapPass)) {
        return [
            'success' => false,
            'synced_count' => 0,
            'message' => 'Gmail credentials (IMAP_USER & IMAP_PASS or SMTP credentials) are missing in environment configuration. Please set SMTP_USER / SMTP_PASS or IMAP_USER / IMAP_PASS in .env.',
            'configured' => false,
        ];
    }

    // Try native php_imap extension first if available
    if (function_exists('imap_open')) {
        $mailboxStr = "{" . $imapHost . ":" . $imapPort . "/imap/ssl/novalidate-cert}INBOX";
        $mailbox = @imap_open($mailboxStr, $imapUser, $imapPass);

        if ($mailbox) {
            $emails = imap_search($mailbox, 'UNSEEN');
            $syncedCount = 0;

            if ($emails) {
                $contactMessageModel = new ContactMessage($db);

                foreach ($emails as $emailNumber) {
                    $header = imap_headerinfo($mailbox, $emailNumber);
                    $fromEmail = '';
                    if (isset($header->from[0])) {
                        $fromEmail = strtolower(trim($header->from[0]->mailbox . '@' . $header->from[0]->host));
                    }

                    if ($fromEmail === '') {
                        continue;
                    }

                    $body = imap_fetchbody($mailbox, $emailNumber, "1");
                    if (empty($body)) {
                        $body = imap_body($mailbox, $emailNumber);
                    }

                    $cleanReplyText = cleanEmailReplyBody((string)$body);
                    
                    if ($cleanReplyText === '') {
                        continue;
                    }

                    $stmt = $db->prepare('SELECT message_id FROM contact_messages WHERE LOWER(email) = :email ORDER BY created_at DESC LIMIT 1');
                    $stmt->execute(['email' => $fromEmail]);
                    $targetMessageId = (int) $stmt->fetchColumn();

                    if ($targetMessageId > 0) {
                        $contactMessageModel->appendGuestReply($targetMessageId, $cleanReplyText);
                        $syncedCount++;
                        imap_setflag_full($mailbox, (string)$emailNumber, "\\Seen");
                    }
                }
            }

            imap_close($mailbox);

            return [
                'success' => true,
                'synced_count' => $syncedCount,
                'message' => "Successfully synced {$syncedCount} incoming Gmail reply message(s) via IMAP extension!",
                'configured' => true,
            ];
        }
    }

    // Fallback: Pure PHP SSL Socket Connection (Zero Extensions Required!)
    $remote = "ssl://{$imapHost}:{$imapPort}";
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);

    $socket = @stream_socket_client($remote, $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        return [
            'success' => false,
            'synced_count' => 0,
            'message' => "Could not connect to Gmail IMAP server at {$remote}: {$errstr} ({$errno}). Check internet connection or firewall.",
            'configured' => true,
        ];
    }

    // Read initial greeting
    fetchImapLine($socket);

    // LOGIN
    $loginLines = sendImapCommand($socket, 'A1', 'LOGIN "' . addslashes($imapUser) . '" "' . addslashes($imapPass) . '"');
    $loginOk = false;
    foreach ($loginLines as $l) {
        if (str_starts_with($l, 'A1 OK')) {
            $loginOk = true;
            break;
        }
    }

    if (!$loginOk) {
        @fclose($socket);
        return [
            'success' => false,
            'synced_count' => 0,
            'message' => "Gmail IMAP Login failed for {$imapUser}. Please ensure you are using a 16-character Gmail App Password (not your normal account password) and that IMAP is enabled in Gmail Settings.",
            'configured' => true,
        ];
    }

    // SELECT INBOX
    sendImapCommand($socket, 'A2', 'SELECT INBOX');

    // SEARCH UNSEEN first
    $searchLines = sendImapCommand($socket, 'A3', 'SEARCH UNSEEN');
    $msgSeqNums = [];

    foreach ($searchLines as $l) {
        if (str_starts_with($l, '* SEARCH')) {
            $parts = explode(' ', trim($l));
            array_shift($parts); // remove '*'
            array_shift($parts); // remove 'SEARCH'
            foreach ($parts as $p) {
                if (ctype_digit($p)) {
                    $msgSeqNums[] = (int) $p;
                }
            }
        }
    }

    // Fallback: If no UNSEEN messages found, check ALL recent messages in case Gmail marked as SEEN
    if (empty($msgSeqNums)) {
        $searchAllLines = sendImapCommand($socket, 'A3_ALL', 'SEARCH ALL');
        $allNums = [];
        foreach ($searchAllLines as $l) {
            if (str_starts_with($l, '* SEARCH')) {
                $parts = explode(' ', trim($l));
                array_shift($parts);
                array_shift($parts);
                foreach ($parts as $p) {
                    if (ctype_digit($p)) {
                        $allNums[] = (int) $p;
                    }
                }
            }
        }
        if (!empty($allNums)) {
            // Check the last 30 messages in the INBOX
            $msgSeqNums = array_slice($allNums, -30);
        }
    }

    $syncedCount = 0;
    $contactMessageModel = new ContactMessage($db);
    $hotelEmail = strtolower(trim($imapUser));

    foreach ($msgSeqNums as $seq) {
        // FETCH HEADER & BODY
        $fetchLines = sendImapCommand($socket, "A4_{$seq}", "FETCH {$seq} (BODY[HEADER.FIELDS (FROM)] BODY[TEXT])");
        $rawHeader = '';
        $rawBody = '';
        $isBodySection = false;

        foreach ($fetchLines as $fl) {
            if (str_ireplace('from:', '', $fl) !== $fl) {
                if (preg_match('/<([^>]+)>/', $fl, $matches)) {
                    $rawHeader = strtolower(trim($matches[1]));
                } else {
                    $parts = explode(':', $fl, 2);
                    $rawHeader = strtolower(trim($parts[1] ?? ''));
                }
            }
            if (str_contains($fl, 'BODY[TEXT]')) {
                $isBodySection = true;
                continue;
            }
            if ($isBodySection && !str_starts_with($fl, "A4_{$seq}")) {
                $rawBody .= $fl;
            }
        }

        // If BODY[TEXT] was empty, retry fetching full BODY[1]
        if (trim($rawBody) === '') {
            $fetchBody1Lines = sendImapCommand($socket, "A4_B1_{$seq}", "FETCH {$seq} (BODY[1])");
            foreach ($fetchBody1Lines as $fl) {
                if (!str_starts_with($fl, "A4_B1_{$seq}") && !str_starts_with($fl, '* ')) {
                    $rawBody .= $fl;
                }
            }
        }

        if ($rawHeader !== '' && $rawHeader !== $hotelEmail) {
            $cleanText = cleanEmailReplyBody($rawBody);

            if ($cleanText !== '') {
                $stmt = $db->prepare('SELECT message_id FROM contact_messages WHERE LOWER(email) = LOWER(:email) ORDER BY created_at DESC LIMIT 1');
                $stmt->execute(['email' => $rawHeader]);
                $targetMessageId = (int) $stmt->fetchColumn();

                if ($targetMessageId > 0) {
                    $appended = $contactMessageModel->appendGuestReply($targetMessageId, $cleanText);
                    if ($appended) {
                        $syncedCount++;
                        // Mark message as SEEN
                        sendImapCommand($socket, "A5_{$seq}", "STORE {$seq} +FLAGS (\\Seen)");
                    }
                }
            }
        }
    }

    sendImapCommand($socket, 'A99', 'LOGOUT');
    @fclose($socket);

    return [
        'success' => true,
        'synced_count' => $syncedCount,
        'message' => "Successfully synced {$syncedCount} incoming Gmail reply message(s) via Pure PHP Socket Client!",
        'configured' => true,
    ];
}
