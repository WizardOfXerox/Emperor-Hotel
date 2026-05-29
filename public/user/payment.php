<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('user', '../admin/dashboard.php');

$db = Database::connect();
$user = currentUser();
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);
$reservationId = (int) ($_GET['reservation_id'] ?? ($_POST['reservation_id'] ?? 0));
$nonCashMethods = array_values(array_filter(
    Payment::methods(),
    static fn (string $method): bool => $method !== 'Cash'
));
$selectedPaymentMethod = (string) ($_GET['payment_method'] ?? ($_POST['payment_method'] ?? 'Online Payment'));

if (!in_array($selectedPaymentMethod, $nonCashMethods, true)) {
    $selectedPaymentMethod = 'Online Payment';
}

$reservation = $reservationModel->find($reservationId);

if (!$reservation || (int) $reservation['user_id'] !== (int) $user['user_id']) {
    setFlash('error', 'Reservation not found for this account.');
    redirect('dashboard.php');
}

if ($reservation['status'] === 'Cancelled') {
    setFlash('error', 'Cancelled reservations cannot accept payments.');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $paymentMethod = (string) ($_POST['payment_method'] ?? $selectedPaymentMethod);

        if (!in_array($paymentMethod, $nonCashMethods, true)) {
            throw new RuntimeException('Please choose a valid non-cash payment method.');
        }

        $customerReference = trim((string) ($_POST['customer_reference'] ?? ''));
        $notes = 'Customer simulated ' . $paymentMethod . ' payment submitted from the customer payment page.';

        if ($customerReference !== '') {
            $notes .= ' Customer note/reference: ' . $customerReference;
        }

        $paymentId = $paymentModel->createAndGetId([
            'reservation_id' => $reservationId,
            'amount' => (float) ($_POST['amount'] ?? 0),
            'payment_method' => $paymentMethod,
            'currency' => 'PHP',
            'payment_status' => 'Pending',
            'is_simulated' => true,
            'notes' => $notes,
        ]);
        $payment = $paymentModel->find($paymentId);
        $reference = (string) ($payment['transaction_reference'] ?? ('Reservation #' . $reservationId));

        setFlash('success', 'Payment submitted for admin review. Transaction reference: ' . $reference . '.');
        redirect('dashboard.php');
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('payment.php?' . http_build_query([
            'reservation_id' => $reservationId,
            'payment_method' => $selectedPaymentMethod,
        ]));
    }
}

$totals = $paymentModel->totalsForReservation($reservationId);
$payments = $paymentModel->forReservation($reservationId);
$activeBalanceDue = (float) $totals['active_balance_due'];

renderSiteLayoutStart('Payment', $user, '../site/', ['../assets/css/user/payment.css']);
?>
<section class="payment-page row g-4 justify-content-center">
    <div class="col-xl-7">
        <div class="panel-card p-4">
            <p class="eyebrow mb-1">Customer Payment</p>
            <h1 class="h3 mb-3">Continue Payment</h1>
            <p class="muted-copy">This page records a simulated non-cash payment for admin review. No real money is processed by this project yet.</p>

            <div class="cost-tracker mb-4">
                <div class="cost-tracker__head">
                    <div>
                        <p class="eyebrow mb-1">Reservation Summary</p>
                        <h4 class="mb-0">Room <?php echo e($reservation['room_number']); ?> - <?php echo e($reservation['room_type']); ?></h4>
                    </div>
                    <span class="badge-soft"><?php echo e($reservation['status']); ?></span>
                </div>
                <div class="cost-tracker__grid">
                    <div><span>Stay</span><strong><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></strong></div>
                    <div><span>Total</span><strong><?php echo e(formatMoney((float) $totals['reservation_total'])); ?></strong></div>
                    <div><span>Confirmed paid</span><strong><?php echo e(formatMoney((float) $totals['confirmed_amount'])); ?></strong></div>
                    <div><span>Pending review</span><strong><?php echo e(formatMoney((float) $totals['pending_amount'])); ?></strong></div>
                    <div class="cost-tracker__total"><span>Remaining payable</span><strong><?php echo e(formatMoney($activeBalanceDue)); ?></strong></div>
                </div>
            </div>

            <?php if ($activeBalanceDue <= 0.01): ?>
                <div class="alert alert-warning mb-4">This reservation already has no remaining payable balance because confirmed and pending payments cover the total.</div>
                <a class="btn btn-outline-light" href="dashboard.php">Back to Dashboard</a>
            <?php else: ?>
                <form method="post" class="d-grid gap-3">
                    <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">
                    <div>
                        <label class="form-label" for="payment_method">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <?php foreach ($nonCashMethods as $method): ?>
                                <option value="<?php echo e($method); ?>" <?php echo $selectedPaymentMethod === $method ? 'selected' : ''; ?>><?php echo e($method); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="amount">Amount</label>
                        <input class="form-control" id="amount" name="amount" type="number" min="0.01" max="<?php echo e(number_format($activeBalanceDue, 2, '.', '')); ?>" step="0.01" value="<?php echo e(number_format($activeBalanceDue, 2, '.', '')); ?>" required>
                        <div class="form-text">Partial payments are allowed, but pending plus confirmed payments cannot exceed the reservation total.</div>
                    </div>
                    <div>
                        <label class="form-label" for="customer_reference">Optional Note / Reference</label>
                        <textarea class="form-control" id="customer_reference" name="customer_reference" rows="3" placeholder="Example: card last 4 digits, bank reference, or online wallet note"></textarea>
                    </div>
                    <div class="payment-page__simulation-note panel-card p-3">
                        <p class="eyebrow mb-1">Simulated Transaction</p>
                        <p class="muted-copy small mb-0">The system will generate a transaction reference automatically and save this payment as Pending for admin review.</p>
                    </div>
                    <button class="btn btn-warning fw-semibold" type="submit">Submit Payment for Review</button>
                    <a class="btn btn-outline-light" href="dashboard.php">Back to Dashboard</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="payment-page__history panel-card p-4 h-100">
            <p class="eyebrow mb-1">Transaction History</p>
            <h2 class="h4 mb-3">This Reservation</h2>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$payments): ?>
                            <tr>
                                <td colspan="4" class="text-light-emphasis">No transactions yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo e($payment['payment_method']); ?></td>
                                <td><span class="badge-soft"><?php echo e($payment['payment_status']); ?></span></td>
                                <td><?php echo e(formatMoney((float) $payment['amount'])); ?></td>
                                <td><small class="text-light-emphasis"><?php echo e($payment['transaction_reference']); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php renderSiteLayoutEnd(); ?>
