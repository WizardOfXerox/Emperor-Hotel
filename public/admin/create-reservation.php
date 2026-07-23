<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';
require_once __DIR__ . '/../includes/room_selection.php';
require_once __DIR__ . '/../includes/calendar_picker.php';

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
            throw new RuntimeException('This page only creates new reservations. Use Reservation Records to manage existing reservations.');
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
            redirect('reservations.php');
        }

        setFlash('success', 'Reservation created. Continue payment processing for ' . $paymentMethod . '.');
        redirect('payments.php?' . http_build_query([
            'reservation_id' => $reservationId,
            'payment_method' => $paymentMethod,
        ]));
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('create-reservation.php');
    }
}

$prefillGuest = isset($_GET['guest_id']) ? $guestModel->find((int) $_GET['guest_id']) : null;
$availabilityCheckIn = (string) ($_GET['check_in'] ?? '');
$availabilityCheckOut = (string) ($_GET['check_out'] ?? '');
$availabilityDatesValid = $reservationModel->dateRangeIsValid($availabilityCheckIn, $availabilityCheckOut);
$rooms = $availabilityDatesValid
    ? $reservationModel->roomsWithDateAvailability($availabilityCheckIn, $availabilityCheckOut)
    : $roomModel->all();

renderAdminLayoutStart('Create Reservation', 'create-reservation', $currentAdmin, ['../assets/css/admin/reservations.css?v=20260530-create-only']);
?>
<section class="row g-4 justify-content-center">
    <div class="col-xxl-10 col-xl-11">
        <div class="d-flex justify-content-end mb-3">
            <a class="btn btn-outline-warning btn-sm fw-semibold" href="reservations.php"><i class="bi bi-calendar-check me-1"></i>Manage Reservations</a>
        </div>

        <form method="post" class="d-grid gap-3" data-dynamic-room-availability data-availability-url="room-availability.php">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="guest_id" value="<?php echo e($prefillGuest['guest_id'] ?? ''); ?>">

            <!-- Section 1: Guest Details -->
            <div class="panel-card p-4">
                <h4 class="h6 mb-3 text-warning"><i class="bi bi-person-circle me-2"></i>Guest Details</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input class="form-control" id="full_name" name="full_name" type="text" value="<?php echo e(trim((string) (($prefillGuest['first_name'] ?? '') . ' ' . ($prefillGuest['last_name'] ?? '')))); ?>" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input class="form-control" id="phone" name="phone" type="tel" value="<?php echo e($prefillGuest['phone'] ?? ''); ?>" placeholder="+63 912 345 6789">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="email">Email Address</label>
                        <input class="form-control" id="email" name="email" type="email" value="<?php echo e($prefillGuest['email'] ?? ''); ?>" placeholder="guest@example.com">
                    </div>
                </div>
            </div>

            <!-- Section 2: Stay Schedule with 7-Column Interactive Calendar -->
            <div class="panel-card p-4 border-0 p-0 shadow-none bg-transparent">
                <?php renderInlineCalendarWidget($availabilityCheckIn, $availabilityCheckOut); ?>
            </div>

            <!-- Section 3: Room Selection -->
            <div class="panel-card p-4">
                <h4 class="h6 mb-3 text-warning"><i class="bi bi-door-open me-2"></i>Select Room</h4>
                <?php renderRoomChoiceCards($rooms, null, false, $db); ?>
            </div>

            <!-- Section 4: Initial Status & Inclusions -->
            <div class="panel-card p-4">
                <div class="row g-3 align-items-center mb-3">
                    <div class="col-md-6">
                        <h4 class="h6 mb-0 text-warning"><i class="bi bi-sliders me-2"></i>Initial Reservation Status</h4>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="status" name="status">
                            <?php foreach ($reservationStatuses as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php echo $status === 'Pending' ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label text-muted small">Room Inclusions Preview</label>
                    <?php renderRoomInclusionPreview(); ?>
                </div>
            </div>

            <!-- Section 5: Cost & Payment Route -->
            <div class="panel-card p-4">
                <h4 class="h6 mb-3 text-warning"><i class="bi bi-credit-card me-2"></i>Payment & Settlement</h4>
                
                <?php renderReservationCostTracker(); ?>

                <div class="row g-3 mt-3">
                    <div class="col-md-12">
                        <label class="form-label" for="payment_method">Payment Mode</label>
                        <select class="form-select" id="payment_method" name="payment_method" data-reservation-payment-method>
                            <?php foreach (Payment::methods() as $method): ?>
                                <option value="<?php echo e($method); ?>"><?php echo e($method); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <button class="btn btn-warning btn-lg fw-bold w-100 py-3 shadow" type="submit">
                <i class="bi bi-check-circle me-2"></i>Create Reservation
            </button>
        </form>
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
