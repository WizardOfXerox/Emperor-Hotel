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
$selectedReservationId = (int) ($_GET['reservation_id'] ?? ($_POST['reservation_id'] ?? 0));
$selectedPaymentMethod = (string) ($_GET['payment_method'] ?? ($_POST['payment_method'] ?? ''));
$selectedPaymentStatus = (string) ($_GET['payment_status'] ?? ($_POST['payment_status'] ?? 'Confirmed'));

if (!in_array($selectedPaymentMethod, Payment::methods(), true)) {
    $selectedPaymentMethod = '';
}
if (!in_array($selectedPaymentStatus, Payment::statuses(), true)) {
    $selectedPaymentStatus = 'Confirmed';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create_payment');

    try {
        if ($action === 'update_status') {
            $paymentModel->updateReview(
                (int) ($_POST['payment_id'] ?? 0),
                (float) ($_POST['amount'] ?? 0),
                (string) ($_POST['payment_status'] ?? 'Pending')
            );
            setFlash('success', 'Transaction review updated. Fully paid pending reservations are confirmed automatically.');
            redirect('payments.php');
        }

        $reservationId = (int) ($_POST['reservation_id'] ?? 0);
        $paymentStatus = (string) ($_POST['payment_status'] ?? 'Confirmed');

        $pendingStmt = $db->prepare("SELECT payment_id, amount FROM payments WHERE reservation_id = :res_id AND payment_status = 'Pending' ORDER BY payment_id DESC LIMIT 1");
        $pendingStmt->execute(['res_id' => $reservationId]);
        $pendingPayment = $pendingStmt->fetch(PDO::FETCH_ASSOC);

        if ($pendingPayment) {
            $paymentModel->updateReview(
                (int) $pendingPayment['payment_id'],
                (float) $pendingPayment['amount'],
                $paymentStatus
            );
            setFlash('success', 'Transaction updated to ' . $paymentStatus . '. Reservation status synchronized automatically.');
            redirect('payments.php?reservation_id=' . $reservationId);
        }

        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount <= 0 && $reservationId > 0) {
            $resObj = $reservationModel->find($reservationId);
            $totals = $paymentModel->totalsForReservation($reservationId);
            $amount = max(0.01, (float) ($resObj['total_amount'] ?? 0) - (float) ($totals['confirmed_amount'] ?? 0));
        }

        $paymentModel->create([
            'reservation_id' => $reservationId,
            'amount' => $amount,
            'payment_method' => 'Cash',
            'payment_status' => $paymentStatus,
            'is_simulated' => false,
        ]);

        setFlash('success', 'Payment action processed successfully. Reservation status updated automatically.');
        redirect('payments.php?reservation_id=' . $reservationId);
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        $query = [];

        if ($selectedReservationId > 0) {
            $query['reservation_id'] = $selectedReservationId;
        }

        if ($selectedPaymentMethod !== '') {
            $query['payment_method'] = $selectedPaymentMethod;
        }

        redirect('payments.php' . ($query ? '?' . http_build_query($query) : ''));
    }
}

$allPayments = $paymentModel->all();
$logSearch = trim((string) ($_GET['log_search'] ?? ''));
$logStatus = trim((string) ($_GET['log_status'] ?? ''));
$logMethod = trim((string) ($_GET['log_method'] ?? ''));
$logPage = (int) ($_GET['page'] ?? 1);
$logPerPage = (int) ($_GET['per_page'] ?? 10);

$paginatedPaymentData = $paymentModel->paginated([
    'search' => $logSearch,
    'status' => $logStatus,
    'payment_method' => $logMethod,
], $logPage, $logPerPage);

$payments = $paginatedPaymentData['rows'];
$reservations = $reservationModel->all();
$paymentTotals = $paymentModel->totalsByReservation();
$summaryRows = $paymentModel->summaryByStatus();

$latestReferences = [];
foreach ($allPayments as $p) {
    if (!isset($latestReferences[(int) $p['reservation_id']]) && !empty($p['transaction_reference'])) {
        $latestReferences[(int) $p['reservation_id']] = $p['transaction_reference'];
    }
}

renderAdminLayoutStart('Payments', 'payments', $currentAdmin, ['../assets/css/admin/payments.css']);
?>
<section class="row g-4">
    <div class="col-xl-4">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Payment Entry</p>
            <h3 class="mb-2">Record Guest Payment</h3>
            <p class="muted-copy">Select a reservation to log a Cash, Credit Card, E-Wallet, or Bank payment and update the reservation balance.</p>

            <?php if (!$reservations): ?>
                <div class="alert alert-warning">Create a reservation before recording a payment.</div>
            <?php endif; ?>

            <form method="post" class="d-grid gap-3">
                <input type="hidden" name="action" value="create_payment">
                <div>
                    <label class="form-label" for="reservation_id">Reservation</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text"><i class="bi bi-search text-warning"></i></span>
                        <input type="text" class="form-control" id="reservation_search_input" placeholder="🔍 Search guest name, room #, or ID..." autocomplete="off" <?php echo !$reservations ? 'disabled' : ''; ?>>
                    </div>
                    <select class="form-select" id="reservation_id" name="reservation_id" required data-payment-reservation <?php echo !$reservations ? 'disabled' : ''; ?>>
                        <option value="">Choose a reservation</option>
                        <?php foreach ($reservations as $reservation): ?>
                            <?php
                                $reservationId = (int) $reservation['reservation_id'];
                                $totals = $paymentTotals[$reservationId] ?? [
                                    'logged_amount' => 0.0,
                                    'confirmed_amount' => 0.0,
                                    'pending_amount' => 0.0,
                                ];
                                $reservationTotal = (float) $reservation['total_amount'];
                                $balanceDue = max(0, $reservationTotal - (float) $totals['confirmed_amount']);
                                $activeBalanceDue = max(0, $reservationTotal - (float) $totals['confirmed_amount'] - (float) $totals['pending_amount']);
                                $referenceId = $latestReferences[$reservationId] ?? ('PAY-' . str_pad((string) $reservationId, 5, '0', STR_PAD_LEFT) . '-' . date('YmdHis'));
                            ?>
                            <option
                                value="<?php echo e($reservationId); ?>"
                                data-total="<?php echo e(number_format($reservationTotal, 2, '.', '')); ?>"
                                data-confirmed="<?php echo e(number_format((float) $totals['confirmed_amount'], 2, '.', '')); ?>"
                                data-pending="<?php echo e(number_format((float) $totals['pending_amount'], 2, '.', '')); ?>"
                                data-logged="<?php echo e(number_format((float) $totals['logged_amount'], 2, '.', '')); ?>"
                                data-balance="<?php echo e(number_format($balanceDue, 2, '.', '')); ?>"
                                data-active-balance="<?php echo e(number_format($activeBalanceDue, 2, '.', '')); ?>"
                                data-guest="<?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?>"
                                data-room="<?php echo e('Room ' . $reservation['room_number'] . ' - ' . $reservation['room_type']); ?>"
                                data-stay="<?php echo e($reservation['check_in'] . ' to ' . $reservation['check_out']); ?>"
                                data-status="<?php echo e($reservation['status']); ?>"
                                data-reference="<?php echo e($referenceId); ?>"
                                <?php echo $selectedReservationId === $reservationId ? 'selected' : ''; ?>
                            >
                                <?php echo e('#' . $reservationId . ' - ' . $reservation['first_name'] . ' ' . $reservation['last_name'] . ' - Room ' . $reservation['room_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cost-tracker" data-payment-cost-tracker>
                    <div class="cost-tracker__head">
                        <div>
                            <p class="eyebrow mb-1">Cost Tracker</p>
                            <h4 class="mb-0">Reservation Balance</h4>
                        </div>
                        <span class="badge-soft" data-payment-status>Choose reservation</span>
                    </div>
                    <div class="cost-tracker__grid">
                        <div><span>Guest</span><strong data-payment-guest>Choose a reservation</strong></div>
                        <div><span>Room</span><strong data-payment-room>Choose a room</strong></div>
                        <div><span>Stay</span><strong data-payment-stay>Choose dates</strong></div>
                        <div><span>Reservation total</span><strong data-payment-total>PHP 0.00</strong></div>
                        <div><span>Confirmed paid</span><strong data-payment-confirmed>PHP 0.00</strong></div>
                        <div><span>Pending logs</span><strong data-payment-pending>PHP 0.00</strong></div>
                        <div class="cost-tracker__total"><span>Balance due</span><strong data-payment-balance>PHP 0.00</strong></div>
                        <div><span>Remaining payable</span><strong data-payment-active-balance>PHP 0.00</strong></div>
                    </div>
                    <p class="muted-copy small mb-0">Confirmed payments reduce the balance. Pending payments reserve part of the balance until reviewed.</p>
                </div>

                <div class="panel-card p-3">
                    <p class="eyebrow mb-1">Transaction Reference ID</p>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-hash text-warning fs-5"></i>
                        <code class="fs-6 fw-bold text-warning text-break" data-payment-reference>Choose a reservation</code>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center mt-1">
                    <button class="btn btn-success fw-bold flex-grow-1 py-2" type="submit" name="payment_status" value="Confirmed" data-payment-btn-confirm <?php echo !$reservations ? 'disabled' : ''; ?>>
                        <i class="bi bi-check-circle-fill me-1"></i>Confirm Payment
                    </button>
                    <button class="btn btn-outline-danger fw-bold flex-grow-1 py-2" type="submit" name="payment_status" value="Refunded" data-payment-btn-refund <?php echo !$reservations ? 'disabled' : ''; ?>>
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Process Refund
                    </button>
                </div>
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
                        <?php if (!$summaryRows): ?>
                            <tr>
                                <td colspan="3" class="text-light-emphasis">No payment summaries yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($summaryRows as $summary): ?>
                            <tr>
                                <td><span class="badge-soft"><?php echo e($summary['payment_status'] ?: 'Confirmed'); ?></span></td>
                                <td><?php echo e($summary['total_count']); ?></td>
                                <td><?php echo e(formatMoney((float) $summary['total_amount'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-card p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                <div>
                    <p class="eyebrow mb-1">Transactions</p>
                    <h3 class="mb-0">Transaction Report Log</h3>
                </div>
                <span class="badge-soft"><?php echo e($paginatedPaymentData['total']); ?> transaction(s)</span>
            </div>

            <form method="get" class="row g-2 mb-4 align-items-center">
                <?php if ($selectedReservationId > 0): ?>
                    <input type="hidden" name="reservation_id" value="<?php echo e($selectedReservationId); ?>">
                <?php endif; ?>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-warning"><i class="bi bi-search"></i></span>
                        <input type="text" name="log_search" class="form-control bg-dark text-light border-secondary" placeholder="Search reference, guest, room #..." value="<?php echo e($logSearch); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="log_status" class="form-select bg-dark text-light border-secondary" onchange="this.form.submit()">
                        <option value="all" <?php echo $logStatus === 'all' || $logStatus === '' ? 'selected' : ''; ?>>All Statuses</option>
                        <?php foreach (Payment::statuses() as $st): ?>
                            <option value="<?php echo e($st); ?>" <?php echo $logStatus === $st ? 'selected' : ''; ?>><?php echo e($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="log_method" class="form-select bg-dark text-light border-secondary" onchange="this.form.submit()">
                        <option value="all" <?php echo $logMethod === 'all' || $logMethod === '' ? 'selected' : ''; ?>>All Methods</option>
                        <?php foreach (Payment::methods() as $m): ?>
                            <option value="<?php echo e($m); ?>" <?php echo $logMethod === $m ? 'selected' : ''; ?>><?php echo e($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <select name="per_page" class="form-select bg-dark text-light border-secondary" onchange="this.form.submit()">
                        <?php foreach ([10, 25, 50, 100] as $limit): ?>
                            <option value="<?php echo $limit; ?>" <?php echo $logPerPage === $limit ? 'selected' : ''; ?>><?php echo $limit; ?> / pg</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($logSearch !== '' || ($logStatus !== '' && $logStatus !== 'all') || ($logMethod !== '' && $logMethod !== 'all')): ?>
                        <a href="payments.php<?php echo $selectedReservationId > 0 ? '?reservation_id=' . $selectedReservationId : ''; ?>" class="btn btn-outline-light" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reservation</th>
                            <th>Guest</th>
                            <th>Method / Reference</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$payments): ?>
                            <tr>
                                <td colspan="7" class="text-light-emphasis text-center py-4">No transaction logs match the selected filters.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($payments as $payment): ?>
                            <?php 
                                $status = $payment['payment_status'];
                                $statusBadgeClass = match($status) {
                                    'Confirmed', 'Paid' => 'bg-success text-white',
                                    'Pending' => 'bg-warning text-dark',
                                    'Failed', 'Refunded' => 'bg-danger text-white',
                                    default => 'bg-secondary text-white'
                                };
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold">#<?php echo e($payment['reservation_id']); ?> - Room <?php echo e($payment['room_number']); ?></div>
                                    <small class="text-light-emphasis">Reservation total: <?php echo e(formatMoney((float) $payment['total_amount'])); ?></small>
                                </td>
                                <td><?php echo e($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo e($payment['payment_method']); ?></div>
                                    <small class="text-light-emphasis"><?php echo e($payment['transaction_reference'] ?: 'No reference'); ?></small>
                                </td>
                                <td><span class="badge <?php echo $statusBadgeClass; ?> px-2 py-1 text-xs fw-bold"><?php echo e($status); ?></span></td>
                                <td class="fw-bold text-warning"><?php echo e(formatMoney((float) $payment['amount'])); ?></td>
                                <td><?php echo e(date('Y-m-d', strtotime($payment['payment_date']))); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <a class="btn btn-sm btn-warning text-nowrap px-2 py-1 text-xs fw-semibold" href="payments.php?reservation_id=<?php echo e($payment['reservation_id']); ?>" title="Populate this reservation in Payment Entry form">
                                            <i class="bi bi-wallet2 me-1"></i>Manage Entry
                                        </a>
                                        <a class="btn btn-sm btn-outline-warning text-nowrap px-2 py-1 text-xs" href="receipt.php?reservation_id=<?php echo e($payment['reservation_id']); ?>" title="View Printable Receipt">
                                            <i class="bi bi-receipt me-1"></i>Receipt
                                        </a>
                                        <a class="btn btn-sm btn-outline-light text-nowrap px-2 py-1 text-xs" href="reservations.php?search=<?php echo e($payment['reservation_id']); ?>" title="View Full Reservation Record">
                                            <i class="bi bi-journal-text me-1"></i>Reservation
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php renderPaginationControl($paginatedPaymentData['total'], $paginatedPaymentData['page'], $paginatedPaymentData['per_page']); ?>
        </div>
    </div>
</section>
<script>
document.querySelectorAll("[data-payment-cost-tracker]").forEach((tracker) => {
    const form = tracker.closest("form");

    if (!form) {
        return;
    }

    const reservationSelect = form.querySelector("[data-payment-reservation]");
    const searchInput = form.querySelector("#reservation_search_input");
    const amountInput = form.querySelector("[data-payment-amount]");

    if (searchInput && reservationSelect) {
        searchInput.addEventListener("input", function () {
            const query = this.value.toLowerCase().replace(/[^a-z0-9]/g, '');
            const options = Array.from(reservationSelect.options);
            let firstMatch = null;

            options.forEach((opt, idx) => {
                if (idx === 0) return;
                const txt = opt.textContent.toLowerCase().replace(/[^a-z0-9]/g, '');
                const matches = query === '' || txt.includes(query);
                opt.style.display = matches ? "" : "none";
                if (matches && !firstMatch) firstMatch = opt;
            });

            if (query.length > 0) {
                if (firstMatch) {
                    reservationSelect.value = firstMatch.value;
                }
            } else {
                options.forEach((opt) => (opt.style.display = ""));
            }

            updateTracker();
        });

        // Sync search input placeholder/value if preselected
        if (reservationSelect.value && reservationSelect.selectedOptions[0]) {
            searchInput.value = reservationSelect.selectedOptions[0].textContent.trim();
        }
    }

    const confirmBtn = form.querySelector("[data-payment-btn-confirm]");
    const refundBtn = form.querySelector("[data-payment-btn-refund]");
    const referenceTag = form.querySelector("[data-payment-reference]");

    const money = (amount) => `PHP ${Number(amount || 0).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
    const text = (selector, value) => {
        const element = form.querySelector(selector) || tracker.querySelector(selector);

        if (element) {
            element.textContent = value;
        }
    };

    const updateTracker = () => {
        const option = reservationSelect && reservationSelect.value ? reservationSelect.selectedOptions[0] : null;

        if (!option || !option.value) {
            text("[data-payment-status]", "Choose reservation");
            text("[data-payment-guest]", "Choose a reservation");
            text("[data-payment-room]", "Choose a room");
            text("[data-payment-stay]", "Choose dates");
            text("[data-payment-total]", money(0));
            text("[data-payment-confirmed]", money(0));
            text("[data-payment-pending]", money(0));
            text("[data-payment-balance]", money(0));
            text("[data-payment-active-balance]", money(0));
            text("[data-payment-reference]", "Choose a reservation");

            if (confirmBtn) confirmBtn.disabled = true;
            if (refundBtn) refundBtn.disabled = true;
            return;
        }

        const balance = Number(option.dataset.balance || 0);
        const activeBalance = Number(option.dataset.activeBalance || 0);
        const pending = Number(option.dataset.pending || 0);
        const status = option.dataset.status || "";

        text("[data-payment-status]", status || "Reservation");
        text("[data-payment-guest]", option.dataset.guest || "Guest");
        text("[data-payment-room]", option.dataset.room || "Room");
        text("[data-payment-stay]", option.dataset.stay || "Stay dates");
        text("[data-payment-total]", money(option.dataset.total));
        text("[data-payment-confirmed]", money(option.dataset.confirmed));
        text("[data-payment-pending]", money(option.dataset.pending));
        text("[data-payment-balance]", money(balance));
        text("[data-payment-active-balance]", money(activeBalance));
        text("[data-payment-reference]", option.dataset.reference || "PAY-00000-YYYYMMDDHHMMSS");

        const isConfirmedOrPaid = (status === "Confirmed" || status === "Checked-in" || status === "Checked-out");
        const isCancelled = (status === "Cancelled");

        if (confirmBtn) {
            confirmBtn.disabled = isConfirmedOrPaid || isCancelled || (balance <= 0 && pending <= 0);
        }
        if (refundBtn) {
            refundBtn.disabled = isCancelled;
        }
    };

    if (reservationSelect) {
        reservationSelect.addEventListener("change", () => {
            if (searchInput && reservationSelect.selectedOptions[0] && reservationSelect.value) {
                searchInput.value = reservationSelect.selectedOptions[0].textContent.trim();
            }

            updateTracker();
        });
    }

    updateTracker();
});
</script>
<?php renderAdminLayoutEnd(); ?>
