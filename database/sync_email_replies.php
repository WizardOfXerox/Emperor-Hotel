<?php

declare(strict_types=1);

/**
 * Automated CLI Cron Script for Syncing Incoming Gmail Replies into Emperor Hotel Database.
 * 
 * Usage via Command Line or Cron / Windows Task Scheduler:
 * php database/sync_email_replies.php
 */

require_once __DIR__ . '/../public/includes/bootstrap.php';

echo "========================================================\n";
echo "🔄 EMPEROR HOTEL - INCOMING GMAIL REPLY SYNCHRONIZER\n";
echo "========================================================\n";

try {
    $db = Database::connect();
    $result = syncGmailReplies($db);

    echo "Status: " . ($result['success'] ? "SUCCESS" : "NOTICE / WARNING") . "\n";
    echo "Message: " . $result['message'] . "\n";
    echo "Synced Count: " . $result['synced_count'] . "\n";
    echo "========================================================\n";
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
