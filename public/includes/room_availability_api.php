<?php

declare(strict_types=1);

function roomAvailabilityPayload(Reservation $reservationModel, string $checkIn, string $checkOut, ?int $excludeReservationId = null): array
{
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    if (trim($checkIn) === '') {
        $checkIn = $today;
    }
    if (trim($checkOut) === '') {
        $checkOut = $tomorrow;
    }

    if (!$reservationModel->dateRangeIsValid($checkIn, $checkOut)) {
        $checkIn = $today;
        $checkOut = $tomorrow;
    }

    $rooms = $reservationModel->roomsWithDateAvailability($checkIn, $checkOut, $excludeReservationId);

    return [
        'ok' => true,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'rooms' => array_map(
            static fn (array $room): array => [
                'room_id' => (int) $room['room_id'],
                'available' => (bool) ($room['is_available_for_dates'] ?? false),
                'label' => (string) ($room['availability_label'] ?? $room['status']),
            ],
            $rooms
        ),
    ];
}

function sendRoomAvailabilityJson(PDO $db): void
{
    $reservationModel = new Reservation($db);
    $excludeReservationId = isset($_GET['exclude_reservation_id']) && (int) $_GET['exclude_reservation_id'] > 0
        ? (int) $_GET['exclude_reservation_id']
        : null;

    header('Content-Type: application/json');
    echo json_encode(
        roomAvailabilityPayload(
            $reservationModel,
            (string) ($_GET['check_in'] ?? ''),
            (string) ($_GET['check_out'] ?? ''),
            $excludeReservationId
        ),
        JSON_THROW_ON_ERROR
    );
}
