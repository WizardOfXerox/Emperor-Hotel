<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';
require_once __DIR__ . '/../includes/room_selection.php';
require_once __DIR__ . '/../includes/calendar_picker.php';

requireAuth('../auth/login.php');
requireRole('user', '../admin/dashboard.php');

$user = currentUser();
$userFullName = trim(($user['full_name'] ?? '') !== '' ? (string)$user['full_name'] : trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($userFullName === '') {
    $userFullName = (string)($user['username'] ?? 'Guest User');
}

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
$selectedRoomId = isset($_GET['selected_room']) ? (int) $_GET['selected_room'] : (isset($_GET['room_id']) ? (int) $_GET['room_id'] : null);
$checkIn = trim((string) ($_GET['check_in'] ?? ''));
$checkOut = trim((string) ($_GET['check_out'] ?? ''));

if ($checkIn === '') $checkIn = (new DateTimeImmutable('today'))->format('Y-m-d');
if ($checkOut === '') $checkOut = (new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');

$selectedRoomObj = $selectedRoomId ? $roomModel->find($selectedRoomId) : null;
if (!$selectedRoomObj && !empty($rooms)) {
    $selectedRoomObj = $rooms[0];
    $selectedRoomId = (int)$selectedRoomObj['room_id'];
}
$selectedRoomType = $selectedRoomObj ? $selectedRoomObj['room_type'] : null;

$inD = DateTimeImmutable::createFromFormat('!Y-m-d', $checkIn) ?: new DateTimeImmutable('today');
$outD = DateTimeImmutable::createFromFormat('!Y-m-d', $checkOut) ?: (new DateTimeImmutable('today'))->modify('+1 day');
$calcNights = max(1, (int) round(($outD->getTimestamp() - $inD->getTimestamp()) / 86400));
$calcTotal = $selectedRoomObj ? ((float)$selectedRoomObj['price_per_night'] * $calcNights) : 0.0;

$catalog = roomCatalog();

$reservations = $reservationModel->userReservations((int) $user['user_id']);
$paymentTotals = $paymentModel->totalsByReservation();
$paymentsByReservation = [];

foreach ($reservations as $reservation) {
    $paymentsByReservation[(int) $reservation['reservation_id']] = $paymentModel->forReservation((int) $reservation['reservation_id']);
}

renderSiteLayoutStart('My Dashboard', $user, '../site/', ['../assets/css/user/dashboard.css?v=20260527-layout']);
?>
<section class="card rounded-4 p-4 shadow-lg border mb-5" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h6 class="text-uppercase tracking-wider text-warning font-serif fw-bold m-0 mb-1"><i class="bi bi-bookmark-check-fill me-2"></i>Express Suite Reservation</h6>
            <h2 class="h3 font-serif fw-bold text-white mb-1">Confirm Your Reservation Details</h2>
            <p class="text-light opacity-75 text-xs m-0">Review your selected stay dates, guest information, and payment route below.</p>
        </div>
        <span class="badge rounded-pill px-3 py-2 text-xs fw-bold" style="background: rgba(212, 175, 55, 0.2); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.4);">
            <i class="bi bi-shield-lock-fill me-1"></i>Secure Booking Checkout
        </span>
    </div>

    <form method="post" action="dashboard.php" id="bookingCheckoutForm">
        <input type="hidden" name="action" value="book">
        <input type="hidden" name="room_id" value="<?= (int)($selectedRoomObj['room_id'] ?? 0) ?>">
        
        <div class="row g-4">
            <!-- Left Column: Guest Details & Payment Route -->
            <div class="col-12 col-lg-7 col-xl-7">
                <div class="p-3 rounded-4 border mb-4" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="font-serif fw-bold text-warning m-0 text-sm"><i class="bi bi-person-bounding-box me-2"></i>Guest Account Details</h5>
                        <span class="badge rounded-pill text-xs fw-semibold" style="background: rgba(212, 175, 55, 0.2); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.3);">
                            <i class="bi bi-person-check-fill me-1"></i>Verified Guest
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-xs text-light opacity-75 fw-bold mb-1" for="full_name">Full Name</label>
                        <input class="form-control form-control-sm bg-dark text-white border-secondary rounded-3 text-xs fw-bold" id="full_name" name="full_name" type="text" value="<?= e($userFullName) ?>" required>
                    </div>

                    <!-- Stay Dates Card with Centered Calendar Popup Trigger -->
                    <div class="p-3 rounded-3 border mb-3 position-relative" style="background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(212, 175, 55, 0.35) !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-xs text-uppercase tracking-wider text-warning font-serif fw-bold"><i class="bi bi-calendar-event-fill me-1"></i>Selected Stay Dates</span>
                            <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-3 py-1 font-serif fw-bold" data-bs-toggle="modal" data-bs-target="#calendarPickerModal">
                                <i class="bi bi-pencil-square me-1"></i>Change Dates
                            </button>
                        </div>
                        <div class="d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="modal" data-bs-target="#calendarPickerModal">
                            <div>
                                <span class="fs-6 font-serif fw-bold text-white me-2">
                                    <?= date('M d, Y', strtotime($checkIn)) ?> &ndash; <?= date('M d, Y', strtotime($checkOut)) ?>
                                </span>
                                <span class="badge bg-gold text-dark text-xs fw-bold px-2 py-1 rounded-pill"><?= $calcNights ?> Night<?= $calcNights > 1 ? 's' : '' ?></span>
                            </div>
                            <i class="bi bi-calendar3 text-warning fs-5"></i>
                        </div>
                        <input type="hidden" id="check_in" name="check_in" value="<?= e($checkIn) ?>">
                        <input type="hidden" id="check_out" name="check_out" value="<?= e($checkOut) ?>">
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label text-xs text-light opacity-75 fw-bold mb-1" for="phone">Contact Phone</label>
                            <input class="form-control form-control-sm bg-dark text-white border-secondary rounded-3 text-xs fw-bold" id="phone" name="phone" type="text" placeholder="+63 917 123 4567">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xs text-light opacity-75 fw-bold mb-1">Suite Capacity</label>
                            <div class="p-2 rounded-3 text-xs fw-semibold" style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                <i class="bi bi-people-fill me-1"></i>Up to 5 Guests Max
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Route -->
                <div class="p-3 rounded-4 border" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
                    <h5 class="font-serif fw-bold text-warning mb-2 text-sm"><i class="bi bi-credit-card-2-front me-2"></i>Select Payment Method</h5>
                    <label class="form-label text-xs text-light opacity-75 fw-bold mb-2">How would you like to settle your reservation?</label>
                    
                    <select class="form-select form-select-sm bg-dark text-white border-warning rounded-3 text-xs fw-bold mb-2" id="payment_method" name="payment_method">
                        <?php foreach (Payment::methods() as $method): ?>
                            <option value="<?= e($method) ?>"><?= e($method) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <small class="text-light opacity-75 text-xs d-block mb-3">
                        <i class="bi bi-info-circle text-warning me-1"></i>Cash creates a pending reservation reference to present at front desk check-in. Credit card or online methods will route to immediate simulated processing.
                    </small>

                    <button class="btn btn-warning w-100 rounded-pill py-2 font-serif fw-bold text-dark shadow" type="submit" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); border: none;">
                        <i class="bi bi-check-circle-fill me-2"></i>Confirm &amp; Submit Reservation
                    </button>
                </div>
            </div>

            <!-- Right Column: Selected Room Summary & Cost Breakdown -->
            <div class="col-12 col-lg-5 col-xl-5">
                <div class="p-3 rounded-4 border h-100 d-flex flex-column justify-content-between" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.35) !important;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary">
                            <h5 class="font-serif fw-bold text-warning m-0 text-sm"><i class="bi bi-door-open-fill me-2"></i>Selected Suite</h5>
                            
                            <!-- Dropdown Room Quick Switcher -->
                            <div class="dropdown">
                                <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-3 py-1 text-xs font-serif dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-arrow-repeat me-1"></i>Switch Room
                                </button>
                                <div class="dropdown-menu dropdown-menu-dark shadow-lg rounded-3 p-2 border" style="max-height: 300px; overflow-y: auto; background: rgba(15, 23, 42, 0.98); min-width: 240px;">
                                    <?php foreach ($rooms as $rm): ?>
                                        <a class="dropdown-item rounded-2 py-1 px-2 text-xs d-flex align-items-center justify-content-between" href="dashboard.php?selected_room=<?= (int)$rm['room_id'] ?>&check_in=<?= urlencode($checkIn) ?>&check_out=<?= urlencode($checkOut) ?>">
                                            <span>#<?= e($rm['room_number']) ?> &mdash; <?= e($rm['room_type']) ?></span>
                                            <span class="text-warning font-mono ms-2">₱<?= number_format((float)$rm['price_per_night']) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Room Card Preview -->
                        <?php if ($selectedRoomObj): 
                            $selCatalog = $catalog[$selectedRoomType] ?? null;
                            $selImg = $selCatalog['hero'] ?? '../assets/images/rooms/hero.jpg';
                        ?>
                            <div class="rounded-4 overflow-hidden mb-3 border position-relative" style="border: 1px solid rgba(212, 175, 55, 0.3) !important;">
                                <img src="<?= e($selImg) ?>" alt="<?= e($selectedRoomType) ?>" class="w-100 object-fit-cover" style="height: 160px;">
                                <div class="position-absolute top-0 start-0 p-2">
                                    <span class="badge bg-gold text-dark font-serif fw-bold px-2 py-1 text-xs">Floor <?= e($selectedRoomObj['floor']) ?></span>
                                </div>
                                <div class="position-absolute top-0 end-0 p-2">
                                    <span class="badge bg-dark bg-opacity-75 text-warning font-serif fw-bold px-2 py-1 border border-warning text-xs">Room #<?= e($selectedRoomObj['room_number']) ?></span>
                                </div>
                                <div class="p-3 bg-dark">
                                    <h6 class="font-serif fw-bold text-white mb-1"><?= e($selectedRoomType) ?></h6>
                                    <span class="text-xs text-warning fw-bold">₱<?= number_format((float)$selectedRoomObj['price_per_night']) ?> / night</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning text-xs mb-3">Please select a room suite to proceed.</div>
                        <?php endif; ?>

                        <!-- Cost Tracker -->
                        <div class="p-3 rounded-3 border mb-3" style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(212, 175, 55, 0.25) !important;">
                            <h6 class="font-serif fw-bold text-warning mb-2 text-xs text-uppercase tracking-wider"><i class="bi bi-calculator me-1"></i>Cost Breakdown</h6>
                            <div class="d-flex align-items-center justify-content-between text-xs text-light opacity-90 mb-1">
                                <span>Stay Duration:</span>
                                <span class="fw-bold text-white"><?= $calcNights ?> Night<?= $calcNights > 1 ? 's' : '' ?></span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between text-xs text-light opacity-90 mb-2">
                                <span>Rate / Night:</span>
                                <span class="fw-bold text-white">₱<?= number_format((float)($selectedRoomObj['price_per_night'] ?? 0)) ?></span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary">
                                <strong class="text-gold font-serif">Estimated Total:</strong>
                                <strong class="fs-5 text-warning font-serif">₱<?= number_format($calcTotal) ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Included Perks -->
                    <div>
                        <h6 class="font-serif fw-bold text-warning mb-2 text-xs"><i class="bi bi-gift-fill me-1"></i>Suite Inclusions</h6>
                        <ul class="list-unstyled text-xs text-light opacity-90 mb-0">
                            <li class="mb-1"><i class="bi bi-check-circle-fill me-1 text-warning"></i>Complimentary breakfast set</li>
                            <li class="mb-1"><i class="bi bi-check-circle-fill me-1 text-warning"></i>High-speed priority Wi-Fi</li>
                            <li><i class="bi bi-check-circle-fill me-1 text-warning"></i>Nespresso Machine &amp; Premium Teas</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>

<section class="card rounded-4 p-4 shadow-lg border" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h6 class="text-uppercase tracking-wider text-warning font-serif fw-bold m-0 mb-1"><i class="bi bi-clock-history me-2"></i>My Reservations</h6>
            <h2 class="h3 font-serif fw-bold text-white mb-0">Booking History</h2>
        </div>
        <span class="badge rounded-pill px-3 py-2 text-xs fw-bold" style="background: rgba(212, 175, 55, 0.2); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.4);">
            <?= count($reservations) ?> Bookings Total
        </span>
    </div>

    <div class="d-flex flex-column gap-3">
        <?php if (!$reservations): ?>
            <div class="text-center py-4 text-light opacity-75">
                <i class="bi bi-calendar-x fs-1 text-warning opacity-50 d-block mb-2"></i>
                <p class="m-0 text-sm">You have no reservations yet. Select a room suite above to book your stay!</p>
            </div>
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

                $statusBadgeStyle = match ($reservation['status']) {
                    'Confirmed' => 'background: rgba(16, 185, 129, 0.35); border: 1px solid #10B981; color: #A7F3D0;',
                    'Checked-in' => 'background: rgba(59, 130, 246, 0.35); border: 1px solid #3B82F6; color: #BFDBFE;',
                    'Checked-out' => 'background: rgba(168, 85, 247, 0.35); border: 1px solid #A855F7; color: #DDD6FE;',
                    'Cancelled' => 'background: rgba(239, 68, 68, 0.35); border: 1px solid #EF4444; color: #FCA5A5;',
                    default => 'background: rgba(245, 158, 11, 0.35); border: 1px solid #F59E0B; color: #FDE68A;',
                };
            ?>
            <article class="p-3 rounded-4 border" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3 pb-2 border-bottom border-secondary">
                    <div>
                        <strong class="font-serif text-white fs-6 me-2">Room #<?= e($reservation['room_number']) ?></strong>
                        <span class="text-xs text-warning font-serif fw-bold">&mdash; <?= e($reservation['room_type']) ?></span>
                    </div>
                    <span class="badge rounded-pill text-xs px-3 py-1 fw-bold" style="<?= $statusBadgeStyle ?>"><?= e($reservation['status']) ?></span>
                </div>

                <div class="row g-3 align-items-center">
                    <div class="col-6 col-md-3">
                        <small class="text-light opacity-75 text-xs d-block mb-1">Stay Range</small>
                        <strong class="text-white text-xs d-block"><?= date('M d, Y', strtotime($reservation['check_in'])) ?> &rarr; <?= date('M d, Y', strtotime($checkOut)) ?></strong>
                    </div>

                    <div class="col-6 col-md-3">
                        <small class="text-light opacity-75 text-xs d-block mb-1">Total Amount</small>
                        <strong class="text-warning text-sm d-block font-mono">₱<?= number_format($reservationTotal) ?></strong>
                    </div>

                    <div class="col-6 col-md-3">
                        <small class="text-light opacity-75 text-xs d-block mb-1">Payment Status</small>
                        <strong class="text-white text-xs d-block"><?= $balanceDue <= 0.01 ? 'Paid in Full' : 'Balance: ₱' . number_format($balanceDue) ?></strong>
                        <?php if ($latestPayment): ?>
                            <small class="text-light opacity-50 text-xs d-block">Ref: <?= e($latestPayment['transaction_reference']) ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-3 text-end d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
                        <?php if ($activeBalanceDue > 0.01 && in_array($reservation['status'], ['Pending', 'Confirmed'], true)): ?>
                            <a class="btn btn-xs btn-warning rounded-pill px-3 fw-bold font-serif" href="payment.php?reservation_id=<?= $reservationId ?>&payment_method=Online%20Payment">Pay Now</a>
                        <?php endif; ?>

                        <?php if (in_array($reservation['status'], ['Checked-out', 'Confirmed'], true)): ?>
                            <button class="btn btn-xs btn-outline-warning rounded-pill px-3 fw-bold font-serif" type="button" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $reservationId ?>">
                                <i class="bi bi-star-fill me-1"></i>Review Stay
                            </button>
                        <?php endif; ?>

                        <?php if (in_array($reservation['status'], ['Pending', 'Confirmed'], true)): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="reservation_id" value="<?= $reservationId ?>">
                                <button class="btn btn-xs btn-outline-danger rounded-pill px-3 fw-bold" type="submit" onclick="return confirm('Are you sure you want to cancel this reservation?')">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Review Modal for this reservation -->
                <div class="modal fade" id="reviewModal<?= $reservationId ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light border-gold-glow rounded-4 p-3 shadow-lg" style="background: rgba(15, 23, 42, 0.96) !important; border: 1px solid rgba(212, 175, 55, 0.45) !important;">
                            <div class="modal-header border-secondary">
                                <h5 class="modal-title font-serif text-warning fw-bold"><i class="bi bi-star-fill me-2"></i>Rate Your Stay (Room #<?= e($reservation['room_number']) ?>)</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="dashboard.php">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="submit_review">
                                    <input type="hidden" name="reservation_id" value="<?= $reservationId ?>">
                                    <input type="hidden" name="room_id" value="<?= (int)$reservation['room_id'] ?>">

                                    <div class="mb-3 text-center">
                                        <label class="form-label text-xs text-uppercase tracking-wider text-light opacity-75 d-block mb-2">Overall Star Rating</label>
                                        <select name="rating" class="form-select form-select-sm bg-dark text-warning border-warning fw-bold text-center fs-5 mx-auto rounded-3" style="max-width: 240px;">
                                            <option value="5">★★★★★ (5/5 Exceptional)</option>
                                            <option value="4">★★★★☆ (4/5 Very Good)</option>
                                            <option value="3">★★★☆☆ (3/5 Good)</option>
                                            <option value="2">★★☆☆☆ (2/5 Fair)</option>
                                            <option value="1">★☆☆☆☆ (1/5 Poor)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label text-xs text-uppercase tracking-wider text-light opacity-75">Your Feedback / Comments</label>
                                        <textarea name="comment" rows="3" class="form-control form-control-sm bg-dark text-light border-secondary rounded-3" placeholder="Describe your room comfort, cleanliness, and stay experience..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-sm btn-warning rounded-pill px-4 font-serif fw-bold text-dark">Submit Review</button>
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
<script>
document.addEventListener("DOMContentLoaded", () => {
    setTimeout(() => {
        const checkedInput = document.querySelector(".room-choice-input:checked");
        if (checkedInput) {
            checkedInput.dispatchEvent(new Event("change", { bubbles: true }));
            const card = checkedInput.closest("[data-room-card]");
            if (card) {
                card.scrollIntoView({ behavior: "smooth", block: "nearest" });
            }
        }
    }, 300);
});
</script>
<?php renderCalendarPickerModal($checkIn, $checkOut); ?>
<?php renderRoomAvailabilityUpdater(); ?>
<?php renderSiteLayoutEnd(); ?>
