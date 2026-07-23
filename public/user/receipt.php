<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';

requireAuth('../auth/login.php');
requireRole('user', '../admin/dashboard.php');

$db = Database::connect();
$user = currentUser();
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);
$guestModel = new Guest($db);

$reservationId = (int) ($_GET['reservation_id'] ?? 0);
$reservation = $reservationModel->find($reservationId);

if (!$reservation || (int) $reservation['user_id'] !== (int) $user['user_id']) {
    setFlash('error', 'Reservation receipt not found for this account.');
    redirect('dashboard.php');
}

$guest = $guestModel->find((int)($reservation['guest_id'] ?? 0));
$payments = $paymentModel->forReservation($reservationId);
$latestPayment = $payments[0] ?? null;

$catalog = roomCatalog();
$roomType = $reservation['room_type'];
$typeCatalog = $catalog[$roomType] ?? null;
$heroImg = $typeCatalog['hero'] ?? '../assets/images/rooms/imperial-deluxe/hero.jpg';

$checkIn = new DateTimeImmutable($reservation['check_in']);
$checkOut = new DateTimeImmutable($reservation['check_out']);
$nights = max(1, (int) round(($checkOut->getTimestamp() - $checkIn->getTimestamp()) / 86400));
$totalAmount = (float)$reservation['total_amount'];

renderSiteLayoutStart('Reservation Receipt', $user, '../site/');
?>

<div class="container py-5" style="max-width: 850px;">
    <!-- Printable Voucher Card -->
    <div class="card rounded-4 shadow-lg border p-4 p-md-5 position-relative overflow-hidden print-area" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
        
        <!-- Header Banner -->
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between pb-4 mb-4 border-bottom border-secondary gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="fs-4 font-serif text-warning fw-bold">EMPEROR HOTEL</span>
                    <span class="badge bg-gold text-dark text-xs font-serif fw-bold px-2 py-1">OFFICIAL VOUCHER</span>
                </div>
                <small class="text-light opacity-75 text-xs">Luxury Stay Reservation &amp; Payment Summary</small>
            </div>
            <div class="text-sm-end">
                <span class="text-xs text-uppercase tracking-wider text-warning font-serif fw-bold d-block">Booking Reference</span>
                <span class="fs-5 text-white font-mono fw-bold">#REF-<?= str_pad((string)$reservationId, 5, '0', STR_PAD_LEFT) ?></span>
            </div>
        </div>

        <!-- Suite Hero & Stay Dates Row -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-md-5">
                <div class="rounded-4 overflow-hidden border position-relative shadow" style="border: 1px solid rgba(212, 175, 55, 0.35) !important; height: 180px;">
                    <img src="<?= e($heroImg) ?>" alt="<?= e($roomType) ?>" class="w-100 h-100 object-fit-cover">
                    <div class="position-absolute top-0 start-0 p-2">
                        <span class="badge bg-gold text-dark text-xs font-serif fw-bold">Room #<?= e($reservation['room_number']) ?></span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-7 d-flex flex-column justify-content-between">
                <div>
                    <span class="text-xs text-uppercase tracking-wider text-warning font-serif fw-bold d-block mb-1">Reserved Suite</span>
                    <h3 class="font-serif fw-bold text-white mb-2"><?= e($roomType) ?></h3>
                    <p class="text-light opacity-75 text-xs mb-3"><?= e($typeCatalog['tagline'] ?? 'Luxury Stay') ?></p>
                </div>

                <div class="p-3 rounded-3 border" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.25) !important;">
                    <div class="row text-center text-sm-start g-2">
                        <div class="col-6 col-sm-5">
                            <small class="text-light opacity-75 text-xs d-block">Check-In</small>
                            <strong class="text-white text-sm"><?= $checkIn->format('M d, Y') ?></strong>
                        </div>
                        <div class="col-6 col-sm-5">
                            <small class="text-light opacity-75 text-xs d-block">Check-Out</small>
                            <strong class="text-white text-sm"><?= $checkOut->format('M d, Y') ?></strong>
                        </div>
                        <div class="col-12 col-sm-2 text-sm-end mt-2 mt-sm-0">
                            <span class="badge rounded-pill text-xs fw-bold px-2 py-1" style="background: rgba(212, 175, 55, 0.25); color: #FFDF73; border: 1px solid #D4AF37;">
                                <?= $nights ?> Night<?= $nights > 1 ? 's' : '' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guest Details & Payment Breakdown -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6">
                <div class="p-3 rounded-4 border h-100" style="background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(212, 175, 55, 0.25) !important;">
                    <h6 class="font-serif fw-bold text-warning mb-3 text-xs text-uppercase tracking-wider"><i class="bi bi-person-badge-fill me-2"></i>Guest Account Information</h6>
                    
                    <div class="mb-2 text-xs">
                        <span class="text-light opacity-75 d-block">Guest Name:</span>
                        <strong class="text-white"><?= e(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? $user['full_name'])) ?></strong>
                    </div>

                    <div class="mb-2 text-xs">
                        <span class="text-light opacity-75 d-block">Email Address:</span>
                        <strong class="text-white"><?= e($guest['email'] ?? $user['email']) ?></strong>
                    </div>

                    <div class="text-xs">
                        <span class="text-light opacity-75 d-block">Contact Phone:</span>
                        <strong class="text-white"><?= e($guest['phone'] ?? 'Not specified') ?></strong>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="p-3 rounded-4 border h-100" style="background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(212, 175, 55, 0.25) !important;">
                    <h6 class="font-serif fw-bold text-warning mb-3 text-xs text-uppercase tracking-wider"><i class="bi bi-receipt-cutoff me-2"></i>Payment &amp; Billing Summary</h6>
                    
                    <div class="d-flex align-items-center justify-content-between text-xs mb-2">
                        <span class="text-light opacity-75">Payment Method:</span>
                        <strong class="text-white"><?= e($latestPayment['payment_method'] ?? 'Cash') ?></strong>
                    </div>

                    <div class="d-flex align-items-center justify-content-between text-xs mb-2">
                        <span class="text-light opacity-75">Reservation Status:</span>
                        <span class="badge bg-gold text-dark text-xs fw-bold px-2 py-1"><?= e($reservation['status']) ?></span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between text-xs pt-2 border-top border-secondary mt-2">
                        <strong style="color: #FFDF73;">Total Paid / Due:</strong>
                        <strong class="fs-5 text-warning font-serif">₱<?= number_format($totalAmount) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Front Desk & Check-In Notice -->
        <div class="p-3 rounded-3 border mb-4" style="background: rgba(212, 175, 55, 0.1); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-info-circle-fill text-warning fs-4"></i>
                <div class="text-xs text-light opacity-90">
                    <strong class="text-warning font-serif d-block mb-1">Important Check-In Instructions:</strong>
                    Please present this official booking reference (#REF-<?= str_pad((string)$reservationId, 5, '0', STR_PAD_LEFT) ?>) and a valid government ID upon arrival at the Emperor Hotel Front Desk. Standard check-in time starts at 2:00 PM.
                </div>
            </div>
        </div>

        <!-- Action Buttons (Print / Back to Dashboard) -->
        <div class="d-flex align-items-center justify-content-between no-print pt-3 border-top border-secondary">
            <a href="dashboard.php" class="btn btn-outline-warning rounded-pill px-4 font-serif fw-bold text-xs">
                <i class="bi bi-arrow-left me-2"></i>Return to Dashboard
            </a>

            <button type="button" onclick="window.print()" class="btn btn-warning rounded-pill px-4 font-serif fw-bold text-dark text-xs shadow" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); border: none;">
                <i class="bi bi-printer-fill me-2"></i>Print Receipt / Save PDF
            </button>
        </div>

    </div>
</div>

<style>
@media print {
    .no-print, nav, footer, .support-widget-container {
        display: none !important;
    }
    body {
        background: #fff !important;
        color: #000 !important;
    }
    .print-area {
        background: #fff !important;
        color: #000 !important;
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
}
</style>

<?php renderSiteLayoutEnd(); ?>
