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
    $reservationModel = new Reservation($db);
    $paymentModel = new Payment($db);

    $operationalAlerts = $reservationModel->operationalAlerts();
    $failedPayments = $paymentModel->failedPayments(5);

    $alertCount = count($operationalAlerts['overdue_checkouts'])
        + count($operationalAlerts['overbooking_conflicts'])
        + count($failedPayments);

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

    // Add Operational Alerts to Notifications list
    foreach ($operationalAlerts['overbooking_conflicts'] as $conflict) {
        $notifications[] = [
            'reservation_id' => 'conflict_' . $conflict['room_id'],
            'guest_name' => 'Room ' . $conflict['room_number'] . ' Overbooking Conflict',
            'room_type' => $conflict['conflict_pairs'] . ' overlapping reservation pair(s)',
            'room_number' => $conflict['room_number'],
            'amount' => 'Requires Action',
            'status' => 'Conflict',
            'check_in' => 'Immediate',
            'check_out' => 'Action Needed',
            'time_ago' => 'Active Alert',
            'is_new' => true,
            'url' => '../admin/reservations.php?search=' . urlencode((string)$conflict['room_number']),
        ];
        $pendingCount++;
    }

    foreach ($operationalAlerts['overdue_checkouts'] as $overdue) {
        $notifications[] = [
            'reservation_id' => 'overdue_' . $overdue['reservation_id'],
            'guest_name' => 'Overdue: ' . trim($overdue['first_name'] . ' ' . $overdue['last_name']),
            'room_type' => $overdue['room_type'],
            'room_number' => $overdue['room_number'],
            'amount' => 'Checkout Due',
            'status' => 'Overdue',
            'check_in' => $overdue['check_in'],
            'check_out' => $overdue['check_out'],
            'time_ago' => 'Due ' . $overdue['check_out'],
            'is_new' => true,
            'url' => '../admin/reservations.php?search=' . urlencode(trim($overdue['first_name'] . ' ' . $overdue['last_name'])),
        ];
        $pendingCount++;
    }

    foreach ($failedPayments as $fp) {
        $notifications[] = [
            'reservation_id' => 'failed_pay_' . $fp['payment_id'],
            'guest_name' => 'Failed Payment: ' . trim($fp['first_name'] . ' ' . $fp['last_name']),
            'room_type' => 'Room #' . $fp['room_number'],
            'room_number' => $fp['room_number'],
            'amount' => formatMoney((float)$fp['amount']),
            'status' => 'Failed',
            'check_in' => 'Payment Issue',
            'check_out' => 'Review Log',
            'time_ago' => 'Failed Log',
            'is_new' => true,
            'url' => '../admin/payments.php',
        ];
        $pendingCount++;
    }

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
            'url' => '../admin/reservations.php?search=' . urlencode(trim($r['first_name'] . ' ' . $r['last_name'])),
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
