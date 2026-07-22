<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = currentUser();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized access.']);
        exit;
    }

    $db = Database::connect();

    // Fetch reservations created in the last 48 hours or still in Pending status
    $stmt = $db->query("
        SELECT r.reservation_id, r.status, r.check_in, r.check_out, r.total_amount, r.created_at,
               g.first_name, g.last_name, g.email,
               rm.room_number, rm.room_type
        FROM reservations r
        INNER JOIN guests g ON g.guest_id = r.guest_id
        INNER JOIN rooms rm ON rm.room_id = r.room_id
        WHERE r.status = 'Pending' OR r.created_at >= NOW() - INTERVAL 48 HOUR
        ORDER BY r.created_at DESC
        LIMIT 10
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = [];
    $pendingCount = 0;

    foreach ($rows as $r) {
        if ($r['status'] === 'Pending') {
            $pendingCount++;
        }

        $createdTime = strtotime($r['created_at']);
        $diffMinutes = max(1, (int) round((time() - $createdTime) / 60));
        $timeAgo = $diffMinutes < 60 ? "{$diffMinutes}m ago" : ((int)floor($diffMinutes / 60) . "h ago");

        $notifications[] = [
            'reservation_id' => (int) $r['reservation_id'],
            'guest_name' => trim($r['first_name'] . ' ' . $r['last_name']),
            'room_type' => $r['room_type'],
            'room_number' => $r['room_number'],
            'amount' => formatMoney((float) $r['total_amount']),
            'status' => $r['status'],
            'check_in' => $r['check_in'],
            'check_out' => $r['check_out'],
            'time_ago' => $timeAgo,
            'is_new' => $diffMinutes <= 30 || $r['status'] === 'Pending',
        ];
    }

    echo json_encode([
        'ok' => true,
        'count' => count($notifications),
        'pending_count' => $pendingCount,
        'notifications' => $notifications,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
