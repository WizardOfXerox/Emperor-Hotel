<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';
require_once __DIR__ . '/../includes/room_selection.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

function buildReservationTotal(array $room, string $checkIn, string $checkOut): float
{
    $checkInTimestamp = strtotime($checkIn);
    $checkOutTimestamp = strtotime($checkOut);

    if ($checkInTimestamp === false || $checkOutTimestamp === false || $checkOutTimestamp <= $checkInTimestamp) {
        throw new RuntimeException('Select valid check-in and check-out dates before calculating the reservation total.');
    }

    $seconds = $checkOutTimestamp - $checkInTimestamp;
    $nights = max(1, (int) floor($seconds / 86400));

    return $nights * (float) $room['price_per_night'];
}

$db = Database::connect();
$currentAdmin = currentUser();
$guestModel = new Guest($db);
$roomModel = new Room($db);
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);
$reservationStatuses = Reservation::statuses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ((string) ($_POST['action'] ?? '') !== 'create') {
            throw new RuntimeException('This page only creates new reservations. Use Booking Records to manage existing reservations.');
        }

        $checkIn = (string) ($_POST['check_in'] ?? '');
        $checkOut = (string) ($_POST['check_out'] ?? '');
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $room = $roomId > 0 ? $roomModel->find($roomId) : null;

        if (!$room) {
            throw new RuntimeException('Please select a room card before saving the reservation.');
        }

        $fullName = trim((string) ($_POST['full_name'] ?? ''));

        if ($fullName === '') {
            throw new RuntimeException('Full name is required.');
        }

        $name = splitFullName($fullName);
        $guestId = $guestModel->upsertFromDetails([
            'guest_id' => $_POST['guest_id'] ?? null,
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'phone' => (string) ($_POST['phone'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
        ]);

        $payload = [
            'guest_id' => $guestId,
            'room_id' => $roomId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_amount' => buildReservationTotal($room, $checkIn, $checkOut),
            'status' => (string) ($_POST['status'] ?? 'Pending'),
        ];

        $paymentMethod = (string) ($_POST['payment_method'] ?? 'Cash');

        if (!in_array($paymentMethod, Payment::methods(), true)) {
            throw new RuntimeException('Please choose a valid payment method.');
        }

        $reservationId = $reservationModel->createAndGetId($payload);

        if ($paymentMethod === 'Cash') {
            $paymentId = $paymentModel->createAndGetId([
                'reservation_id' => $reservationId,
                'amount' => (float) $payload['total_amount'],
                'payment_method' => 'Cash',
                'payment_status' => 'Pending',
                'is_simulated' => false,
            ]);
            $payment = $paymentModel->find($paymentId);
            $reference = (string) ($payment['transaction_reference'] ?? ('Reservation #' . $reservationId));

            setFlash('success', 'Reservation created. Payment reference: ' . $reference . '.');
            redirect('booking-records.php');
        }

        setFlash('success', 'Reservation created. Continue payment processing for ' . $paymentMethod . '.');
        redirect('payments.php?' . http_build_query([
            'reservation_id' => $reservationId,
            'payment_method' => $paymentMethod,
        ]));
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('reservations.php');
    }
}

$prefillGuest = isset($_GET['guest_id']) ? $guestModel->find((int) $_GET['guest_id']) : null;
$availabilityCheckIn = (string) ($_GET['check_in'] ?? '');
$availabilityCheckOut = (string) ($_GET['check_out'] ?? '');
$availabilityDatesValid = $reservationModel->dateRangeIsValid($availabilityCheckIn, $availabilityCheckOut);
$rooms = $availabilityDatesValid
    ? $reservationModel->roomsWithDateAvailability($availabilityCheckIn, $availabilityCheckOut)
    : $roomModel->all();

renderAdminLayoutStart('Reservations', 'reservations', $currentAdmin, ['../assets/css/admin/reservations.css?v=20260530-create-only']);
?>
<section class="row g-4 justify-content-center">
    <div class="col-xxl-9 col-xl-10">
        <div class="panel-card p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                <div>
                    <p class="eyebrow mb-1">Create Reservation</p>
                    <h3 class="mb-2">New Reservation</h3>
                    <p class="muted-copy mb-0">Use this page only for creating a booking. Existing bookings are managed in the Booking Records tab.</p>
                </div>
                <a class="btn btn-outline-light btn-sm" href="booking-records.php">Open Booking Records</a>
            </div>

            <form method="get" class="availability-filter-card mb-4">
                <?php if ($prefillGuest): ?>
                    <input type="hidden" name="guest_id" value="<?php echo e($prefillGuest['guest_id']); ?>">
                <?php endif; ?>
                <div>
                    <p class="eyebrow mb-1">Date-aware Availability</p>
                    <p class="muted-copy small mb-0">Choose stay dates first so the room cards show rooms available for that exact date range.</p>
                </div>
                <div class="row g-2">
                    <div class="col-md-5">
                        <input class="form-control" name="check_in" type="date" value="<?php echo e($availabilityCheckIn); ?>" aria-label="Availability check-in">
                    </div>
                    <div class="col-md-5">
                        <input class="form-control" name="check_out" type="date" value="<?php echo e($availabilityCheckOut); ?>" aria-label="Availability check-out">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-outline-light" type="submit">Check</button>
                    </div>
                </div>
                <?php if ($availabilityCheckIn !== '' || $availabilityCheckOut !== ''): ?>
                    <div class="form-text">
                        <?php echo $availabilityDatesValid ? 'Room availability is filtered by the selected dates.' : 'Enter a valid check-in and check-out date to filter room availability.'; ?>
                    </div>
                <?php endif; ?>
            </form>

            <form method="post" class="d-grid gap-3" data-dynamic-room-availability data-availability-url="room-availability.php">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="guest_id" value="<?php echo e($prefillGuest['guest_id'] ?? ''); ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input class="form-control" id="full_name" name="full_name" type="text" value="<?php echo e(trim((string) (($prefillGuest['first_name'] ?? '') . ' ' . ($prefillGuest['last_name'] ?? '')))); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" type="text" value="<?php echo e($prefillGuest['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="<?php echo e($prefillGuest['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="check_in">Check In</label>
                        <input class="form-control" id="check_in" name="check_in" type="date" value="<?php echo e($availabilityCheckIn); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="check_out">Check Out</label>
                        <input class="form-control" id="check_out" name="check_out" type="date" value="<?php echo e($availabilityCheckOut); ?>" required>
                    </div>
                </div>

                <div>
                    <label class="form-label">Room</label>
                    <?php renderRoomChoiceCards($rooms, null, false, $db); ?>
                    <div class="form-text" data-room-availability-note>Use the filters for all, available, or unavailable rooms. Room cards update automatically when check-in and check-out dates change.</div>
                </div>

                <div class="row g-3">
                    <div class="col-md-7">
                        <div class="guest-capacity-note">
                            <span>Guest Capacity</span>
                            <strong>Every room can hold up to 5 people</strong>
                            <small>No adult or child split is required for the reservation form.</small>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="status">Initial Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach ($reservationStatuses as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php echo $status === 'Pending' ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">Room Inclusions</label>
                    <?php renderRoomInclusionPreview(); ?>
                </div>

                <?php renderReservationCostTracker(); ?>

                <div class="panel-card p-3">
                    <p class="eyebrow mb-1">Payment Route</p>
                    <h4 class="h6 mb-3">Customer Payment Mode</h4>
                    <label class="form-label" for="payment_method">Payment Mode</label>
                    <select class="form-select" id="payment_method" name="payment_method" data-reservation-payment-method>
                        <?php foreach (Payment::methods() as $method): ?>
                            <option value="<?php echo e($method); ?>"><?php echo e($method); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" data-payment-route-message>Cash creates an automatic pending payment reference for the full reservation total. Card or online methods continue to the Payments page.</div>
                </div>

                <button class="btn btn-warning fw-semibold" type="submit">Create Reservation</button>
            </form>
        </div>
    </div>
</section>
<script>
document.querySelectorAll("[data-reservation-payment-method]").forEach((methodSelect) => {
    const form = methodSelect.closest("form");
    const message = form ? form.querySelector("[data-payment-route-message]") : null;

    const syncPaymentRoute = () => {
        const isCash = methodSelect.value === "Cash";

        if (message) {
            message.textContent = isCash
                ? "Cash creates an automatic pending payment reference for the full reservation total."
                : "This method will open the Payments page after the reservation is created.";
        }
    };

    methodSelect.addEventListener("change", syncPaymentRoute);
    syncPaymentRoute();
});
</script>
<?php renderRoomAvailabilityUpdater(); ?>
<?php renderAdminLayoutEnd(); ?>
