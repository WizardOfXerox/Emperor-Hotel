<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

$db = Database::connect();
$currentAdmin = currentUser();
$paymentModel = new Payment($db);
$reservationModel = new Reservation($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $paymentModel->create([
            'reservation_id' => (int) ($_POST['reservation_id'] ?? 0),
            'amount' => (float) ($_POST['amount'] ?? 0),
            'payment_method' => (string) ($_POST['payment_method'] ?? 'Cash'),
            'currency' => (string) ($_POST['currency'] ?? 'PHP'),
            'payment_status' => (string) ($_POST['payment_status'] ?? 'Pending'),
            'transaction_reference' => (string) ($_POST['transaction_reference'] ?? ''),
            'notes' => (string) ($_POST['notes'] ?? ''),
        ]);
        setFlash('success', 'Payment recorded.');
        redirect('payments.php');
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('payments.php');
    }
}

$payments = $paymentModel->all();
$reservations = $reservationModel->all();
$summaryRows = $paymentModel->summaryByStatus();

renderAdminLayoutStart('Payments', 'payments', $currentAdmin);
?>
<section class="row g-4">
    <div class="col-xl-4">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Payment Entry</p>
            <h3 class="mb-3">Record Payment</h3>
            <form method="post" class="d-grid gap-3">
                <div>
                    <label class="form-label" for="reservation_id">Reservation</label>
                    <select class="form-select" id="reservation_id" name="reservation_id" required>
                        <?php foreach ($reservations as $reservation): ?>
                            <option value="<?php echo e($reservation['reservation_id']); ?>">
                                <?php echo e('#' . $reservation['reservation_id'] . ' • ' . $reservation['first_name'] . ' ' . $reservation['last_name'] . ' • Room ' . $reservation['room_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="amount">Amount</label>
                    <input class="form-control" id="amount" name="amount" type="number" step="0.01" required>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="payment_method">Method</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <?php foreach (['Cash', 'Credit Card', 'Debit Card', 'Bank Transfer', 'Other'] as $method): ?>
                                <option value="<?php echo e($method); ?>"><?php echo e($method); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="currency">Currency</label>
                        <select class="form-select" id="currency" name="currency">
                            <?php foreach (['PHP', 'USD', 'EUR'] as $currency): ?>
                                <option value="<?php echo e($currency); ?>"><?php echo e($currency); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label" for="payment_status">Status</label>
                    <select class="form-select" id="payment_status" name="payment_status">
                        <?php foreach (['Pending', 'Confirmed', 'Failed', 'Refunded'] as $status): ?>
                            <option value="<?php echo e($status); ?>"><?php echo e($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="transaction_reference">Reference</label>
                    <input class="form-control" id="transaction_reference" name="transaction_reference" type="text">
                </div>
                <div>
                    <label class="form-label" for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>
                <button class="btn btn-warning fw-semibold" type="submit">Save Payment</button>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="panel-card p-4 mb-4">
            <p class="eyebrow mb-1">Status Summary</p>
            <h3 class="mb-3">Payment Totals</h3>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summaryRows as $summary): ?>
                            <tr>
                                <td><span class="badge-soft"><?php echo e($summary['payment_status']); ?></span></td>
                                <td><?php echo e($summary['total_count']); ?></td>
                                <td><?php echo e(formatMoney((float) $summary['total_amount'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Transactions</p>
                    <h3 class="mb-0">Payment History</h3>
                </div>
                <span class="badge-soft"><?php echo e(count($payments)); ?> payments</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reservation</th>
                            <th>Guest</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>#<?php echo e($payment['reservation_id']); ?> • Room <?php echo e($payment['room_number']); ?></td>
                                <td><?php echo e($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                <td><?php echo e($payment['payment_method']); ?></td>
                                <td><span class="badge-soft"><?php echo e($payment['payment_status']); ?></span></td>
                                <td><?php echo e(formatMoney((float) $payment['amount'])); ?></td>
                                <td><?php echo e(date('Y-m-d', strtotime($payment['payment_date']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php renderAdminLayoutEnd(); ?>
