<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

$db = Database::connect();
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);
$reservationId = (int) ($_GET['reservation_id'] ?? 0);
$reservation = $reservationId > 0 ? $reservationModel->find($reservationId) : null;

if (!$reservation) {
    setFlash('error', 'Reservation receipt not found.');
    redirect('reservations.php');
}

$payments = $paymentModel->forReservation($reservationId);
$totals = $paymentModel->totalsForReservation($reservationId);
$confirmedPaid = (float) $totals['confirmed_amount'];
$pendingPaid = (float) $totals['pending_amount'];
$balanceDue = (float) $totals['balance_due'];
$nights = max(1, (int) floor((strtotime((string) $reservation['check_out']) - strtotime((string) $reservation['check_in'])) / 86400));

renderHeader('Receipt #' . $reservationId, ['../assets/css/admin/receipt.css'], 'receipt-page');
?>
<main class="receipt-shell">
    <div class="receipt-actions no-print">
        <a class="btn btn-outline-light" href="reservations.php">Back to Reservations</a>
        <a class="btn btn-warning" href="payments.php?reservation_id=<?php echo e($reservationId); ?>">Add Payment</a>
        <button class="btn btn-light" type="button" onclick="window.print()">Print Receipt</button>
    </div>

    <section class="receipt-paper">
        <header class="receipt-header">
            <div>
                <p class="eyebrow mb-1">Emperor Hotel</p>
                <h1>Official Reservation Receipt</h1>
                <p class="muted-copy mb-0">Receipt generated on <?php echo e(date('Y-m-d H:i')); ?></p>
            </div>
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </header>

        <div class="receipt-summary">
            <div>
                <span>Receipt No.</span>
                <strong>RES-<?php echo e(str_pad((string) $reservationId, 5, '0', STR_PAD_LEFT)); ?></strong>
            </div>
            <div>
                <span>Status</span>
                <strong><?php echo e($reservation['status']); ?></strong>
            </div>
            <div>
                <span>Total</span>
                <strong><?php echo e(formatMoney((float) $reservation['total_amount'])); ?></strong>
            </div>
            <div>
                <span>Balance Due</span>
                <strong><?php echo e(formatMoney($balanceDue)); ?></strong>
            </div>
        </div>

        <div class="receipt-grid">
            <section>
                <h2>Guest</h2>
                <p class="mb-1"><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></p>
                <p class="mb-1"><?php echo e($reservation['guest_email'] ?: 'No email provided'); ?></p>
                <p class="mb-0"><?php echo e($reservation['phone'] ?: 'No phone provided'); ?></p>
            </section>
            <section>
                <h2>Stay Details</h2>
                <p class="mb-1">Room <?php echo e($reservation['room_number']); ?> • <?php echo e($reservation['room_type']); ?></p>
                <p class="mb-1"><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></p>
                <p class="mb-0"><?php echo e($nights); ?> <?php echo $nights === 1 ? 'night' : 'nights'; ?> • <?php echo e((int) $reservation['adults']); ?> adults • <?php echo e((int) $reservation['children']); ?> children</p>
            </section>
        </div>

        <section class="receipt-section">
            <h2>Room Inclusions</h2>
            <p class="mb-0"><?php echo e(roomIncludedPerksText((string) $reservation['room_type'])); ?></p>
        </section>

        <section class="receipt-section">
            <h2>Payment Summary</h2>
            <div class="receipt-summary">
                <div>
                    <span>Confirmed Paid</span>
                    <strong><?php echo e(formatMoney($confirmedPaid)); ?></strong>
                </div>
                <div>
                    <span>Pending Review</span>
                    <strong><?php echo e(formatMoney($pendingPaid)); ?></strong>
                </div>
                <div>
                    <span>Reservation Total</span>
                    <strong><?php echo e(formatMoney((float) $reservation['total_amount'])); ?></strong>
                </div>
                <div>
                    <span>Balance Due</span>
                    <strong><?php echo e(formatMoney($balanceDue)); ?></strong>
                </div>
            </div>
        </section>

        <section class="receipt-section">
            <h2>Transaction Log</h2>
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$payments): ?>
                        <tr>
                            <td colspan="5">No payments recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo e(date('Y-m-d', strtotime((string) $payment['payment_date']))); ?></td>
                            <td><?php echo e($payment['transaction_reference']); ?></td>
                            <td><?php echo e($payment['payment_method']); ?></td>
                            <td><?php echo e($payment['payment_status']); ?></td>
                            <td class="text-end"><?php echo e(formatMoney((float) $payment['amount'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <footer class="receipt-footer">
            <p class="mb-0">Thank you for choosing Emperor Hotel.</p>
            <p class="mb-0">Pending and simulated transactions are not counted as settled until confirmed by an admin.</p>
        </footer>
    </section>
</main>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
