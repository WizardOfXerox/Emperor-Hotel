<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';
require_once __DIR__ . '/../includes/room_selection.php';

requireAuth('../auth/login.php');
requireRole('user', '../admin/dashboard.php');

function bookingTotal(array $room, string $checkIn, string $checkOut): float
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
$user = currentUser();
$guestModel = new Guest($db);
$roomModel = new Room($db);
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'submit_review') {
            $reviewModel = new Review($db);
            $reviewModel->create([
                'reservation_id' => (int) ($_POST['reservation_id'] ?? 0),
                'user_id' => (int) $user['user_id'],
                'room_id' => (int) ($_POST['room_id'] ?? 0),
                'rating' => (int) ($_POST['rating'] ?? 5),
                'comment' => trim((string) ($_POST['comment'] ?? '')),
            ]);
            setFlash('success', 'Thank you for your rating and review!');
            redirect('dashboard.php');
        }

        if ($action === 'cancel') {
            $reservation = $reservationModel->find((int) ($_POST['reservation_id'] ?? 0));

            if (!$reservation || (int) $reservation['user_id'] !== (int) $user['user_id']) {
                throw new RuntimeException('Reservation not found for this account.');
            }

            $reservationModel->updateStatus((int) $reservation['reservation_id'], 'Cancelled');
            setFlash('success', 'Reservation cancelled.');
            redirect('dashboard.php');
        }

        if ($action === 'book') {
            $checkIn = (string) ($_POST['check_in'] ?? '');
            $checkOut = (string) ($_POST['check_out'] ?? '');
            $roomId = (int) ($_POST['room_id'] ?? 0);
            $room = null;

            if ($roomId > 0) {
                $room = $roomModel->find($roomId);
            }

            if (!$room) {
                throw new RuntimeException('Please choose a room card before submitting your reservation.');
            }

            $fullName = trim((string) ($_POST['full_name'] ?? ''));

            if ($fullName === '') {
                throw new RuntimeException('Full name is required.');
            }

            $name = splitFullName($fullName);

            $existingGuest = $guestModel->findByUserId((int) $user['user_id']);
            $guestPayload = [
                'user_id' => (int) $user['user_id'],
                'first_name' => $name['first_name'],
                'last_name' => $name['last_name'],
                'phone' => (string) ($_POST['phone'] ?? ''),
                'email' => $user['email'],
            ];

            if ($existingGuest) {
                $guestModel->update((int) $existingGuest['guest_id'], $guestPayload);
                $guestId = (int) $existingGuest['guest_id'];
            } else {
                $guestId = $guestModel->create($guestPayload);
            }

            $totalAmount = bookingTotal($room, $checkIn, $checkOut);
            $paymentMethod = (string) ($_POST['payment_method'] ?? 'Cash');

            if (!in_array($paymentMethod, Payment::methods(), true)) {
                throw new RuntimeException('Please choose a valid payment method.');
            }

            $reservationId = $reservationModel->createAndGetId([
                'user_id' => (int) $user['user_id'],
                'guest_id' => $guestId,
                'room_id' => $roomId,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_amount' => $totalAmount,
                'status' => 'Pending',
            ]);

            if ($paymentMethod === 'Cash') {
                $paymentId = $paymentModel->createAndGetId([
                    'reservation_id' => $reservationId,
                    'amount' => $totalAmount,
                    'payment_method' => 'Cash',
                    'payment_status' => 'Pending',
                    'is_simulated' => false,
                ]);
                $payment = $paymentModel->find($paymentId);
                $reference = (string) ($payment['transaction_reference'] ?? ('Reservation #' . $reservationId));

                setFlash('success', 'Reservation submitted. Payment reference: ' . $reference . '. Please pay this at the cashier.');
                redirect('dashboard.php');
            }

            setFlash('success', 'Reservation submitted. Continue your simulated ' . $paymentMethod . ' payment.');
            redirect('payment.php?' . http_build_query([
                'reservation_id' => $reservationId,
                'payment_method' => $paymentMethod,
            ]));
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('dashboard.php');
    }
}

$rooms = $roomModel->all();
$reservations = $reservationModel->userReservations((int) $user['user_id']);
$paymentTotals = $paymentModel->totalsByReservation();
$paymentsByReservation = [];

foreach ($reservations as $reservation) {
    $paymentsByReservation[(int) $reservation['reservation_id']] = $paymentModel->forReservation((int) $reservation['reservation_id']);
}

renderSiteLayoutStart('My Dashboard', $user, '../site/', ['../assets/css/user/dashboard.css?v=20260527-layout']);
?>
<section class="panel-card user-booking-panel p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
        <div>
            <p class="eyebrow mb-1">Book a Stay</p>
            <h1 class="h3 mb-2">Create a Reservation</h1>
            <p class="muted-copy mb-0">Your account details are reused automatically. Choose your stay details on the left, then pick the room card on the right.</p>
        </div>
        <span class="badge-soft"><?php echo e(count($rooms)); ?> rooms</span>
    </div>

    <form method="post" class="user-booking-form" data-dynamic-room-availability data-availability-url="room-availability.php">
        <input type="hidden" name="action" value="book">

        <div class="user-booking-details">
            <div>
                <label class="form-label" for="full_name">Full Name</label>
                <input class="form-control" id="full_name" name="full_name" type="text" value="<?php echo e($user['full_name']); ?>" required>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="check_in">Check In</label>
                    <input class="form-control" id="check_in" name="check_in" type="date" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="check_out">Check Out</label>
                    <input class="form-control" id="check_out" name="check_out" type="date" required>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" type="text">
                </div>
                <div class="col-md-5">
                    <div class="guest-capacity-note">
                        <span>Guest Capacity</span>
                        <strong>Up to 5 people</strong>
                        <small>No adult or child split is required.</small>
                    </div>
                </div>
            </div>
            <div>
                <label class="form-label">Room Inclusions</label>
                <?php renderRoomInclusionPreview(); ?>
            </div>
            <?php renderReservationCostTracker(); ?>
            <div class="panel-card p-3">
                <p class="eyebrow mb-1">Payment Route</p>
                <h4 class="h6 mb-3">How would you like to pay?</h4>
                <label class="form-label" for="payment_method">Payment Mode</label>
                <select class="form-select" id="payment_method" name="payment_method" data-customer-payment-method>
                    <?php foreach (Payment::methods() as $method): ?>
                        <option value="<?php echo e($method); ?>"><?php echo e($method); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text" data-customer-payment-route-message>Cash creates an automatic pending payment reference to show at the cashier. Card or online methods continue to the payment page.</div>
            </div>
            <button class="btn btn-warning fw-semibold" type="submit">Submit Reservation</button>
        </div>

        <aside class="user-booking-room-panel">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2 mb-3">
                <div>
                    <p class="eyebrow mb-1">Room Selection</p>
                    <h2 class="h4 mb-0">Choose a Room</h2>
                </div>
                <span class="badge-soft">Date-aware cards</span>
            </div>
            <?php renderRoomChoiceCards($rooms, null, false, $db); ?>
            <div class="form-text" data-room-availability-note>Use the filters for all, available, or unavailable rooms. Room cards update automatically when check-in and check-out dates change.</div>
        </aside>
    </form>
</section>

<section class="panel-card booking-history-panel p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="eyebrow mb-1">My Reservations</p>
            <h2 class="h3 mb-0">Booking History</h2>
        </div>
        <span class="badge-soft"><?php echo e(count($reservations)); ?> bookings</span>
    </div>
    <div class="booking-history-list">
        <?php if (!$reservations): ?>
            <div class="booking-history-empty">You have no reservations yet.</div>
        <?php endif; ?>
        <?php foreach ($reservations as $reservation): ?>
            <?php
                $reservationId = (int) $reservation['reservation_id'];
                $reservationTotal = (float) $reservation['total_amount'];
                $totals = $paymentTotals[$reservationId] ?? [
                    'confirmed_amount' => 0.0,
                    'pending_amount' => 0.0,
                ];
                $balanceDue = max(0, $reservationTotal - (float) $totals['confirmed_amount']);
                $activeBalanceDue = max(0, $reservationTotal - (float) $totals['confirmed_amount'] - (float) $totals['pending_amount']);
                $latestPayment = $paymentsByReservation[$reservationId][0] ?? null;
            ?>
            <article class="booking-history-card">
                <div class="booking-history-card__top">
                    <div class="booking-history-card__room">
                        <strong>Room <?php echo e($reservation['room_number']); ?></strong>
                        <span><?php echo e($reservation['room_type']); ?></span>
                    </div>
                    <span class="badge-soft"><?php echo e($reservation['status']); ?></span>
                </div>
                <div class="booking-history-card__meta">
                    <div>
                        <span>Stay</span>
                        <strong><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></strong>
                    </div>
                    <div>
                        <span>Total</span>
                        <strong><?php echo e(formatMoney($reservationTotal)); ?></strong>
                    </div>
                    <div>
                        <span>Payment</span>
                        <strong><?php echo e($balanceDue <= 0.01 ? 'Paid' : 'Balance: ' . formatMoney($balanceDue)); ?></strong>
                        <?php if ((float) $totals['pending_amount'] > 0): ?>
                            <small>Pending: <?php echo e(formatMoney((float) $totals['pending_amount'])); ?></small>
                        <?php endif; ?>
                        <?php if ($latestPayment): ?>
                            <small class="booking-history-card__reference"><?php echo e($latestPayment['transaction_reference']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="booking-history-card__actions">
                    <?php if ($activeBalanceDue > 0.01 && in_array($reservation['status'], ['Pending', 'Confirmed'], true)): ?>
                        <a class="btn btn-sm btn-warning" href="payment.php?reservation_id=<?php echo e($reservationId); ?>&payment_method=Online%20Payment">Pay</a>
                    <?php endif; ?>
                    <?php if (in_array($reservation['status'], ['Checked-out', 'Confirmed'], true)): ?>
                        <button class="btn btn-sm btn-outline-warning rounded-pill px-3 me-1" type="button" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $reservationId ?>">
                            <i class="bi bi-star-fill me-1"></i>Review Stay
                        </button>
                    <?php endif; ?>
                    <?php if (in_array($reservation['status'], ['Pending', 'Confirmed'], true)): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Review Modal for this reservation -->
                <div class="modal fade" id="reviewModal<?= $reservationId ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light border-gold-glow rounded-4 p-3 shadow-lg">
                            <div class="modal-header border-secondary">
                                <h5 class="modal-title font-serif text-gold fw-bold"><i class="bi bi-star-fill me-2"></i>Rate Your Stay (Room #<?= e($reservation['room_number']) ?>)</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="dashboard.php">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="submit_review">
                                    <input type="hidden" name="reservation_id" value="<?= $reservationId ?>">
                                    <input type="hidden" name="room_id" value="<?= (int)$reservation['room_id'] ?>">

                                    <div class="mb-3 text-center">
                                        <label class="form-label text-xs text-uppercase tracking-wider text-muted d-block mb-2">Overall Star Rating</label>
                                        <select name="rating" class="form-select form-select-dark bg-dark text-warning border-gold fw-bold text-center fs-5 mx-auto" style="max-width: 200px;">
                                            <option value="5">★★★★★ (5/5 Exceptional)</option>
                                            <option value="4">★★★★☆ (4/5 Very Good)</option>
                                            <option value="3">★★★☆☆ (3/5 Good)</option>
                                            <option value="2">★★☆☆☆ (2/5 Fair)</option>
                                            <option value="1">★☆☆☆☆ (1/5 Poor)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label text-xs text-uppercase tracking-wider text-muted">Your Feedback / Comments</label>
                                        <textarea name="comment" rows="3" class="form-control form-control-dark border-secondary bg-dark text-light" placeholder="Describe your room comfort, cleanliness, and stay experience..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-outline-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-gold rounded-pill px-4 fw-bold">Submit Review</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<script>
document.querySelectorAll("[data-customer-payment-method]").forEach((methodSelect) => {
    const form = methodSelect.closest("form");
    const message = form ? form.querySelector("[data-customer-payment-route-message]") : null;

    const syncPaymentRoute = () => {
        if (!message) {
            return;
        }

        message.textContent = methodSelect.value === "Cash"
            ? "Cash creates an automatic pending payment reference to show at the cashier."
            : "This method opens the customer payment page after your reservation is created.";
    };

    methodSelect.addEventListener("change", syncPaymentRoute);
    syncPaymentRoute();
});
</script>
<?php renderRoomAvailabilityUpdater(); ?>
<?php renderSiteLayoutEnd(); ?>
