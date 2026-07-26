<?php

declare(strict_types=1);

/**
 * Sync incoming Gmail/IMAP replies into the contact_messages table.
 *
 * Supports environment variables:
 * IMAP_HOST (default: imap.gmail.com)
 * IMAP_PORT (default: 993)
 * IMAP_USER (default: SMTP_USER or hotel gmail)
 * IMAP_PASS (default: SMTP_PASSWORD or Gmail App Password)
 */
function syncGmailReplies(PDO $db): array
{
    $imapHost = getenv('IMAP_HOST') ?: ($_ENV['IMAP_HOST'] ?? ($_SERVER['IMAP_HOST'] ?? 'imap.gmail.com'));
    $imapPort = (int) (getenv('IMAP_PORT') ?: ($_ENV['IMAP_PORT'] ?? ($_SERVER['IMAP_PORT'] ?? 993)));
    
    // Fall back to SMTP_USER / SMTP_PASSWORD if IMAP credentials are not explicitly specified
    $smtpUser = getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? ($_SERVER['SMTP_USER'] ?? ''));
    $smtpPass = getenv('SMTP_PASSWORD') ?: (getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASSWORD'] ?? ($_ENV['SMTP_PASS'] ?? ($_SERVER['SMTP_PASSWORD'] ?? ($_SERVER['SMTP_PASS'] ?? '')))));

    $imapUser = getenv('IMAP_USER') ?: ($_ENV['IMAP_USER'] ?? ($_SERVER['IMAP_USER'] ?? $smtpUser));
    $imapPass = getenv('IMAP_PASSWORD') ?: (getenv('IMAP_PASS') ?: ($_ENV['IMAP_PASSWORD'] ?? ($_ENV['IMAP_PASS'] ?? ($_SERVER['IMAP_PASSWORD'] ?? ($_SERVER['IMAP_PASS'] ?? $smtpPass)))));

    if (empty($imapUser) || empty($imapPass)) {
        return [
            'success' => false,
            'synced_count' => 0,
            'message' => 'IMAP credentials (IMAP_USER & IMAP_PASS or SMTP credentials) are missing in environment configuration.',
            'configured' => false,
        ];
    }

    if (!function_exists('imap_open')) {
        return [
            'success' => false,
            'synced_count' => 0,
            'message' => 'PHP IMAP extension (extension=imap) is not enabled in php.ini. Please enable extension=imap in XAMPP php.ini.',
            'configured' => false,
        ];
    }

    $mailboxStr = "{" . $imapHost . ":" . $imapPort . "/imap/ssl/novalidate-cert}INBOX";
    
    // Suppress warning alerts on connection attempt
    $mailbox = @imap_open($mailboxStr, $imapUser, $imapPass);

    if (!$mailbox) {
        $lastError = imap_lasterror() ?: 'Failed to connect to IMAP server.';
        return [
            'success' => false,
            'synced_count' => 0,
            'message' => 'Gmail IMAP connection failed: ' . $lastError,
            'configured' => true,
        ];
    }

    // Search for unread/unseen email messages
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

            // Fetch plain text email body
            $body = imap_fetchbody($mailbox, $emailNumber, "1");
            if (empty($body)) {
                $body = imap_body($mailbox, $emailNumber);
            }

            // Clean up quoted text in email replies
            $cleanReplyText = trim(strip_tags((string)$body));
            
            // Look up existing guest message matching this email address
            $stmt = $db->prepare('SELECT message_id FROM contact_messages WHERE LOWER(email) = :email ORDER BY created_at DESC LIMIT 1');
            $stmt->execute(['email' => $fromEmail]);
            $targetMessageId = (int) $stmt->fetchColumn();

            if ($targetMessageId > 0) {
                $contactMessageModel->appendGuestReply($targetMessageId, $cleanReplyText);
                $syncedCount++;

                // Mark email as read in Gmail Inbox
                imap_setflag_full($mailbox, (string)$emailNumber, "\\Seen");
            }
        }
    }

    imap_close($mailbox);

    return [
        'success' => true,
        'synced_count' => $syncedCount,
        'message' => "Successfully synced {$syncedCount} incoming Gmail reply message(s)!",
        'configured' => true,
    ];
}
