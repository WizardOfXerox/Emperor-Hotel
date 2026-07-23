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
        $isSimulated = isset($_POST['is_simulated']);
        $paymentStatus = $isSimulated ? 'Pending' : (string) ($_POST['payment_status'] ?? 'Confirmed');

        $paymentModel->create([
            'reservation_id' => $reservationId,
            'amount' => (float) ($_POST['amount'] ?? 0),
            'payment_method' => (string) ($_POST['payment_method'] ?? 'Cash'),
            'payment_status' => $paymentStatus,
            'is_simulated' => $isSimulated,
        ]);

        setFlash('success', $isSimulated ? 'Simulated transaction recorded.' : 'Payment recorded. Fully paid pending reservations are confirmed automatically.');
        redirect('payments.php');
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

$payments = $paymentModel->all();
$reservations = $reservationModel->all();
$paymentTotals = $paymentModel->totalsByReservation();
$summaryRows = $paymentModel->summaryByStatus();

renderAdminLayoutStart('Payments', 'payments', $currentAdmin, ['../assets/css/admin/payments.css']);
?>
<section class="row g-4">
    <div class="col-xl-4">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Payment Entry</p>
            <h3 class="mb-2">Record Guest Payment</h3>
            <p class="muted-copy">Select a reservation to log a Cash, Credit Card, GCash, or Bank payment and update the reservation balance.</p>

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

                <div>
                    <label class="form-label" for="amount">Payment Amount (PHP)</label>
                    <input class="form-control" id="amount" name="amount" type="number" min="0.01" step="0.01" required data-payment-amount <?php echo !$reservations ? 'disabled' : ''; ?>>
                    <div class="form-text" data-payment-entry-note>Choose a reservation to fill the maximum payable amount.</div>
                </div>
                <div>
                    <label class="form-label" for="payment_method">Payment Method</label>
                    <select class="form-select" id="payment_method" name="payment_method" <?php echo !$reservations ? 'disabled' : ''; ?>>
                        <?php foreach (Payment::methods() as $method): ?>
                            <option value="<?php echo e($method); ?>" <?php echo $selectedPaymentMethod === $method ? 'selected' : ''; ?>><?php echo e($method); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="payment_status">Transaction Status</label>
                    <select class="form-select" id="payment_status" name="payment_status" <?php echo !$reservations ? 'disabled' : ''; ?>>
                        <option value="Confirmed" <?php echo $selectedPaymentStatus === 'Confirmed' ? 'selected' : ''; ?>>Confirmed (Payment Accepted)</option>
                        <option value="Pending" <?php echo $selectedPaymentStatus === 'Pending' ? 'selected' : ''; ?>>Pending (Needs Review)</option>
                        <option value="Refunded" <?php echo $selectedPaymentStatus === 'Refunded' ? 'selected' : ''; ?>>Refunded (Process Refund)</option>
                    </select>
                    <div class="form-text">Use Confirmed for accepted payments. Use Pending for admin review. Use Refunded to record a guest refund.</div>
                </div>
                <div class="panel-card p-3">
                    <p class="eyebrow mb-1">Transaction Reference</p>
                    <p class="muted-copy small mb-0">An official reference like <strong>PAY-00001-YYYYMMDDHHMMSS</strong> will be generated automatically upon saving.</p>
                </div>
                <button class="btn btn-warning fw-semibold" type="submit" <?php echo !$reservations ? 'disabled' : ''; ?>><i class="bi bi-check-circle me-1"></i>Save Payment Transaction</button>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Transactions</p>
                    <h3 class="mb-0">Transaction Report Log</h3>
                </div>
                <span class="badge-soft"><?php echo e(count($payments)); ?> transactions</span>
            </div>
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
                                <td colspan="7" class="text-light-emphasis">No transaction logs yet.</td>
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
                                        <?php if ($status === 'Pending'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="payment_id" value="<?php echo e($payment['payment_id']); ?>">
                                                <input type="hidden" name="amount" value="<?php echo e($payment['amount']); ?>">
                                                <input type="hidden" name="payment_status" value="Confirmed">
                                                <button type="submit" class="btn btn-sm btn-success text-nowrap px-2 py-1 text-xs fw-bold shadow-sm" title="Approve and confirm this payment">
                                                    <i class="bi bi-check-circle me-1"></i>Confirm
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="payment_id" value="<?php echo e($payment['payment_id']); ?>">
                                                <input type="hidden" name="amount" value="<?php echo e($payment['amount']); ?>">
                                                <input type="hidden" name="payment_status" value="Refunded">
                                                <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap px-2 py-1 text-xs fw-bold" title="Refund this transaction">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Refund
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
            const query = this.value.toLowerCase().trim();
            const options = Array.from(reservationSelect.options);
            let firstMatch = null;

            options.forEach((opt, idx) => {
                if (idx === 0) return;
                const txt = opt.textContent.toLowerCase();
                const matches = txt.includes(query);
                opt.style.display = matches ? "" : "none";
                if (matches && !firstMatch) firstMatch = opt;
            });

            if (query.length > 0 && firstMatch) {
                reservationSelect.value = firstMatch.value;
            } else if (query.length === 0) {
                options.forEach((opt) => (opt.style.display = ""));
            }

            reservationSelect.dispatchEvent(new Event("change"));
        });
    }
    const simulatedInput = form.querySelector("#is_simulated");
    const statusInput = form.querySelector("#payment_status");
    const methodInput = form.querySelector("#payment_method");
    const submitButton = form.querySelector("button[type='submit']");
    const entryNote = form.querySelector("[data-payment-entry-note]");
    const paymentEntryFields = [amountInput, methodInput, statusInput, simulatedInput, submitButton].filter(Boolean);
    const money = (amount) => `PHP ${Number(amount || 0).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
    const text = (selector, value) => {
        const element = tracker.querySelector(selector);

        if (element) {
            element.textContent = value;
        }
    };
    const setPaymentEntryState = (enabled, note) => {
        paymentEntryFields.forEach((field) => {
            field.disabled = !enabled;
        });

        if (entryNote) {
            entryNote.textContent = note;
            entryNote.classList.toggle("text-warning", !enabled);
        }
    };

    const updateTracker = () => {
        const option = reservationSelect && reservationSelect.value ? reservationSelect.selectedOptions[0] : null;

        if (!option) {
            text("[data-payment-status]", "Choose reservation");
            text("[data-payment-guest]", "Choose a reservation");
            text("[data-payment-room]", "Choose a room");
            text("[data-payment-stay]", "Choose dates");
            text("[data-payment-total]", money(0));
            text("[data-payment-confirmed]", money(0));
            text("[data-payment-pending]", money(0));
            text("[data-payment-balance]", money(0));
            text("[data-payment-active-balance]", money(0));
            if (amountInput) {
                amountInput.value = "";
                amountInput.removeAttribute("max");
                amountInput.dataset.autofilled = "true";
            }
            setPaymentEntryState(false, "Choose a reservation before recording a payment.");
            return;
        }

        const balance = Number(option.dataset.balance || 0);
        const activeBalance = Number(option.dataset.activeBalance || 0);
        const pending = Number(option.dataset.pending || 0);

        text("[data-payment-status]", option.dataset.status || "Reservation");
        text("[data-payment-guest]", option.dataset.guest || "Guest");
        text("[data-payment-room]", option.dataset.room || "Room");
        text("[data-payment-stay]", option.dataset.stay || "Stay dates");
        text("[data-payment-total]", money(option.dataset.total));
        text("[data-payment-confirmed]", money(option.dataset.confirmed));
        text("[data-payment-pending]", money(option.dataset.pending));
        text("[data-payment-balance]", money(balance));
        text("[data-payment-active-balance]", money(activeBalance));

        if (amountInput && (amountInput.dataset.autofilled !== "false")) {
            amountInput.value = activeBalance > 0 ? activeBalance.toFixed(2) : "0.00";
            amountInput.max = activeBalance.toFixed(2);
            amountInput.dataset.autofilled = "true";
        }

        if (activeBalance <= 0) {
            const note = pending > 0
                ? "No new payment can be added because pending transaction(s) already reserve the remaining balance. Review or adjust the existing transaction below."
                : "No new payment can be added because this reservation has no remaining payable balance.";
            setPaymentEntryState(false, note);
            return;
        }

        setPaymentEntryState(true, `You can record up to ${money(activeBalance)} for this reservation.`);
    };

    if (amountInput) {
        amountInput.addEventListener("input", () => {
            amountInput.dataset.autofilled = "false";
        });
    }

    if (reservationSelect) {
        reservationSelect.addEventListener("change", () => {
            if (amountInput) {
                amountInput.dataset.autofilled = "true";
            }

            updateTracker();
        });
    }

    if (simulatedInput) {
        simulatedInput.addEventListener("change", () => {
            if (simulatedInput.checked) {
                if (statusInput) {
                    statusInput.value = "Pending";
                }
            } else if (statusInput && statusInput.value === "Pending") {
                statusInput.value = "Confirmed";
            }
        });
    }

    updateTracker();
});
</script>
<?php renderAdminLayoutEnd(); ?>
