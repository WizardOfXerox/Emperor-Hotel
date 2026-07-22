<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

try {
    $db = Database::connect();
    $checkIn = trim((string) ($_GET['check_in'] ?? ''));
    $checkOut = trim((string) ($_GET['check_out'] ?? ''));

    $roomModel = new Room($db);
    $rooms = $roomModel->all();

    $bookedRoomStatuses = [];
    if ($checkIn !== '' && $checkOut !== '') {
        $stmt = $db->prepare("
            SELECT room_id, status
            FROM reservations
            WHERE status NOT IN ('Cancelled', 'Checked-out')
              AND NOT (check_out <= :check_in OR check_in >= :check_out)
        ");
        $stmt->execute(['check_in' => $checkIn, 'check_out' => $checkOut]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bookedRoomStatuses[(int)$row['room_id']] = $row['status'] === 'Checked-in' ? 'Occupied' : 'Reserved';
        }
    }

    $response = [];
    foreach ($rooms as $room) {
        $rId = (int) $room['room_id'];
        $status = $room['status'];

        if ($status !== 'Maintenance') {
            if (isset($bookedRoomStatuses[$rId])) {
                $status = $bookedRoomStatuses[$rId];
            } else if ($checkIn !== '' && $checkOut !== '') {
                $status = 'Available';
            }
        }

        $response[] = [
            'room_id' => $rId,
            'room_number' => $room['room_number'],
            'status' => $status,
        ];
    }

    echo json_encode([
        'success' => true,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'rooms' => $response
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
