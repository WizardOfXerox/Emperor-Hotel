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

        // Issue #13 Fix: Skip OTP email for admin-created reservations.
        // Admin reservations don't require guest OTP verification.
        // The guest will receive their booking confirmation through normal channels.

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
        // Issue #14 Fix: Preserve form data in session so admin doesn't re-type everything
        $_SESSION['create_res_form'] = [
            'full_name' => $_POST['full_name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'check_in' => $_POST['check_in'] ?? '',
            'check_out' => $_POST['check_out'] ?? '',
            'room_id' => $_POST['room_id'] ?? '',
            'status' => $_POST['status'] ?? 'Pending',
            'payment_method' => $_POST['payment_method'] ?? 'Cash',
        ];
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

$selectedRoomId = isset($_GET['selected_room']) ? (int) $_GET['selected_room'] : (isset($_GET['room_id']) ? (int) $_GET['room_id'] : null);

// Issue #14 Fix: Retrieve preserved form data after validation error redirect
$savedForm = $_SESSION['create_res_form'] ?? [];
unset($_SESSION['create_res_form']);

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

            <!-- Combined Row: Guest Details (Left) + Stay Schedule Calendar (Right) -->
            <div class="row g-4 align-items-stretch mb-2">
                <!-- Left Column: Guest Details -->
                <div class="col-lg-5">
                    <div class="panel-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <h4 class="h6 mb-3 text-warning font-serif fw-bold"><i class="bi bi-person-circle me-2"></i>Guest Details</h4>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted" for="full_name">Full Name</label>
                                    <input class="form-control" id="full_name" name="full_name" type="text" value="<?php echo e($savedForm['full_name'] ?? trim((string) (($prefillGuest['first_name'] ?? '') . ' ' . ($prefillGuest['last_name'] ?? '')))); ?>" placeholder="e.g. John Doe" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted" for="phone">Phone Number</label>
                                    <input class="form-control" id="phone" name="phone" type="tel" value="<?php echo e($savedForm['phone'] ?? $prefillGuest['phone'] ?? ''); ?>" placeholder="+63 912 345 6789">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted" for="email">Email Address</label>
                                    <input class="form-control" id="email" name="email" type="email" value="<?php echo e($savedForm['email'] ?? $prefillGuest['email'] ?? ''); ?>" placeholder="guest@example.com">
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded-3 mt-4" style="background: rgba(212, 175, 55, 0.08); border: 1px solid rgba(212, 175, 55, 0.2);">
                            <small class="text-muted d-block"><i class="bi bi-info-circle text-warning me-1"></i> <strong>Admin Quick Tip:</strong> Guest details are automatically linked or registered to guest records upon submission.</small>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Stay Schedule Calendar -->
                <div class="col-lg-7">
                    <div class="panel-card p-0 border-0 shadow-none bg-transparent h-100">
                        <?php renderInlineCalendarWidget($availabilityCheckIn, $availabilityCheckOut); ?>
                    </div>
                </div>
            </div>

            <!-- Section 3: Room Selection -->
            <div class="panel-card p-4" id="roomSelectionSection">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                    <h4 class="h6 mb-0 text-warning font-serif fw-bold"><i class="bi bi-door-open me-2"></i>Select Room</h4>
                    <div class="d-flex align-items-center gap-2" style="max-width: 480px; width: 100%;">
                        <label for="quickRoomSelect" class="small text-nowrap fw-bold text-gold me-1"><i class="bi bi-lightning-charge-fill me-1"></i>Quick Select Room:</label>
                        <select class="form-select border-warning border-opacity-50 font-sans fw-semibold shadow-sm" id="quickRoomSelect">
                            <option value="">-- Instant Choose Room # --</option>
                            <?php foreach ($rooms as $r): ?>
                                <?php
                                    $rId = (int) $r['room_id'];
                                    $rStatus = $r['status'] ?? 'Available';
                                    $rSelected = $selectedRoomId === $rId ? 'selected' : '';
                                ?>
                                <option value="<?php echo e($rId); ?>" <?php echo $rSelected; ?>>
                                    Room <?php echo e($r['room_number']); ?> &bull; <?php echo e($r['room_type']); ?> (<?php echo formatMoney((float)$r['price_per_night']); ?>/night &bull; <?php echo e($rStatus); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php renderRoomChoiceCards($rooms, $selectedRoomId, true, $db); ?>
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
document.addEventListener("DOMContentLoaded", () => {
    const quickRoomSelect = document.getElementById("quickRoomSelect");
    const roomInputs = document.querySelectorAll('input[name="room_id"]');

    // 1. Synchronize Quick Select Dropdown -> Room Radio Input & Card
    if (quickRoomSelect) {
        quickRoomSelect.addEventListener("change", (e) => {
            const selectedVal = e.target.value;
            if (!selectedVal) return;

            const targetRadio = document.querySelector(`input[name="room_id"][value="${selectedVal}"]`);
            if (targetRadio && !targetRadio.disabled) {
                targetRadio.checked = true;
                targetRadio.dispatchEvent(new Event("change", { bubbles: true }));
                
                const card = targetRadio.closest("[data-room-card]");
                if (card) {
                    card.click();
                    card.scrollIntoView({ behavior: "smooth", block: "nearest" });
                }
            }
        });
    }

    // 2. Synchronize Room Radio Input -> Quick Select Dropdown
    roomInputs.forEach(input => {
        input.addEventListener("change", () => {
            if (input.checked && quickRoomSelect) {
                quickRoomSelect.value = input.value;
            }
        });
    });

    // 3. Auto-Select & Auto-Scroll on initial page load if room_id is present
    const checkedRadio = document.querySelector('input[name="room_id"]:checked');
    if (checkedRadio) {
        checkedRadio.dispatchEvent(new Event("change", { bubbles: true }));
        if (quickRoomSelect) {
            quickRoomSelect.value = checkedRadio.value;
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has("room_id")) {
            const card = checkedRadio.closest("[data-room-card]");
            if (card) {
                setTimeout(() => {
                    card.scrollIntoView({ behavior: "smooth", block: "center" });
                }, 200);
            }
        }
    }

    // 4. Payment Mode Sync Message
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
});
</script>
<?php renderRoomAvailabilityUpdater(); ?>
<?php renderAdminLayoutEnd(); ?>
