<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    requireAuth('../auth/login.php');
    requireRole('admin', '../user/dashboard.php');

    $db = Database::connect();
    $roomId = (int) ($_GET['room_id'] ?? 0);

    if ($roomId <= 0) {
        throw new RuntimeException('Invalid room ID requested.');
    }

    $roomModel = new Room($db);
    $room = $roomModel->find($roomId);

    if (!$room) {
        throw new RuntimeException('Room not found.');
    }

    // Fetch active/upcoming or current checked-in reservation for this room
    $stmt = $db->prepare("
        SELECT res.*, 
               g.first_name, g.last_name, g.phone, g.email,
               p.payment_method, p.payment_status, p.amount AS payment_amount, p.transaction_reference
        FROM reservations res
        JOIN guests g ON res.guest_id = g.guest_id
        LEFT JOIN payments p ON res.reservation_id = p.reservation_id
        WHERE res.room_id = :room_id
          AND res.status NOT IN ('Cancelled', 'Checked-out')
        ORDER BY CASE res.status 
            WHEN 'Checked-in' THEN 1 
            WHEN 'Confirmed' THEN 2 
            WHEN 'Pending' THEN 3 
            ELSE 4 END, res.check_in ASC
        LIMIT 1
    ");
    $stmt->execute(['room_id' => $roomId]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'room' => [
            'room_id' => (int) $room['room_id'],
            'room_number' => (string) $room['room_number'],
            'room_type' => (string) $room['room_type'],
            'floor' => (int) $room['floor'],
            'price_per_night' => (float) $room['price_per_night'],
            'formatted_price' => formatMoney((float) $room['price_per_night']),
            'status' => (string) $room['status'],
        ],
        'has_reservation' => !empty($reservation),
        'reservation' => $reservation ? [
            'reservation_id' => (int) $reservation['reservation_id'],
            'guest_name' => trim($reservation['first_name'] . ' ' . $reservation['last_name']),
            'guest_phone' => (string) ($reservation['phone'] ?? 'N/A'),
            'guest_email' => (string) ($reservation['email'] ?? 'N/A'),
            'check_in' => (string) $reservation['check_in'],
            'check_out' => (string) $reservation['check_out'],
            'status' => (string) $reservation['status'],
            'total_amount' => (float) $reservation['total_amount'],
            'formatted_total' => formatMoney((float) $reservation['total_amount']),
            'payment_method' => (string) ($reservation['payment_method'] ?? 'N/A'),
            'payment_status' => (string) ($reservation['payment_status'] ?? 'Pending'),
            'transaction_reference' => (string) ($reservation['transaction_reference'] ?? 'N/A'),
        ] : null,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
