<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

function minimumExtensionCheckOut(string $currentCheckOut): string
{
    $currentCheckOutDate = DateTimeImmutable::createFromFormat('!Y-m-d', $currentCheckOut);
    $minimumDate = $currentCheckOutDate ? $currentCheckOutDate->modify('+1 day') : new DateTimeImmutable('tomorrow');
    $today = new DateTimeImmutable('today');

    if ($minimumDate < $today) {
        return $today->format('Y-m-d');
    }

    return $minimumDate->format('Y-m-d');
}

$db = Database::connect();
$currentAdmin = currentUser();
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'delete') {
            $reservationModel->delete((int) ($_POST['reservation_id'] ?? 0));
            setFlash('success', 'Reservation deleted.');
            redirect('reservations.php');
        }

        if (in_array($action, ['confirm', 'check_in', 'check_out', 'cancel', 'resolve_conflict'], true)) {
            $newStatus = $reservationModel->applyFrontDeskAction((int) ($_POST['reservation_id'] ?? 0), $action);
            setFlash('success', 'Reservation status changed to ' . $newStatus . '.');
            redirect('reservations.php');
        }

        if ($action === 'flag_conflicts') {
            $flagged = $reservationModel->flagOverlappingConflicts();
            if ($flagged > 0) {
                setFlash('success', $flagged . ' overlapping reservation(s) flagged as Conflict.');
            } else {
                setFlash('success', 'No new overlapping conflicts detected.');
            }
            redirect('reservations.php');
        }

        if ($action === 'extend_stay') {
            $extension = $reservationModel->extendStay(
                (int) ($_POST['reservation_id'] ?? 0),
                (string) ($_POST['new_check_out'] ?? '')
            );
            setFlash(
                'success',
                'Stay extended to ' . $extension['new_check_out']
                . '. Added ' . $extension['extra_nights'] . ' night(s), additional balance '
                . formatMoney((float) $extension['additional_amount'])
                . '. New total is ' . formatMoney((float) $extension['new_total'])
                . '. Use the Payment button to collect the added balance.'
            );
            redirect('reservations.php');
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('reservations.php');
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$reservations = $reservationModel->searchAndFilter($search, $statusFilter);
$paymentTotals = $paymentModel->totalsByReservation();

// Detect active overlap conflict IDs for visual highlighting (even before flagging)
$conflictingIds = $reservationModel->getConflictingReservationIds();
$conflictIdSet = array_flip($conflictingIds);

renderAdminLayoutStart('Manage Reservations', 'reservations', $currentAdmin, ['../assets/css/admin/reservations.css?v=20260530-manage-reservations']);
?>
<section class="panel-card p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <p class="eyebrow mb-1">Reservations</p>
            <h3 class="mb-0">Reservation Records</h3>
            <p class="muted-copy mb-0">Review reservation details, update front desk status, extend stays, collect payments, and print receipts.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge-soft"><?php echo e(count($reservations)); ?> reservations</span>
            <a class="btn btn-warning btn-sm fw-semibold" href="create-reservation.php"><i class="bi bi-plus-circle me-1"></i>Create Reservation</a>
        </div>
    </div>

    <form method="get" class="row g-2 mb-4 align-items-center">
        <div class="col-md-6 col-lg-7">
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-warning"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control bg-dark text-light border-secondary" placeholder="Search by guest name, room #, email, or reservation ID..." value="<?php echo e($search); ?>">
            </div>
        </div>
        <div class="col-md-4 col-lg-3">
            <select name="status" class="form-select bg-dark text-light border-secondary" onchange="this.form.submit()">
                <option value="all" <?php echo $statusFilter === 'all' || $statusFilter === '' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Confirmed" <?php echo $statusFilter === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="Checked-in" <?php echo $statusFilter === 'Checked-in' ? 'selected' : ''; ?>>Checked-in</option>
                <option value="Checked-out" <?php echo $statusFilter === 'Checked-out' ? 'selected' : ''; ?>>Checked-out</option>
                <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                <option value="Conflict" <?php echo $statusFilter === 'Conflict' ? 'selected' : ''; ?>>⚠ Conflict</option>
            </select>
        </div>
        <div class="col-md-2 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-warning w-100 fw-semibold">Filter</button>
            <?php if ($search !== '' || ($statusFilter !== '' && $statusFilter !== 'all')): ?>
                <a href="reservations.php" class="btn btn-outline-light" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!empty($conflictingIds)): ?>
        <div class="conflict-alert-banner mb-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                <strong class="text-danger"><?php echo e(count($conflictingIds)); ?> reservation(s) have overlapping dates</strong>
                <span class="text-light-emphasis small">— Reservations for the same room with clashing check-in/check-out dates.</span>
                <form method="post" class="ms-auto">
                    <input type="hidden" name="action" value="flag_conflicts">
                    <button class="btn btn-sm btn-outline-danger fw-semibold" type="submit">
                        <i class="bi bi-flag-fill me-1"></i>Flag All as Conflict
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-dark-soft align-middle mb-0 booking-records-table">
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>Room</th>
                    <th>Stay</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th class="reservation-actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$reservations): ?>
                    <tr>
                        <td colspan="6" class="text-light-emphasis">No booking records yet.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($reservations as $reservation): ?>
                    <?php
                        $resId = (int) $reservation['reservation_id'];
                        $isConflicting = isset($conflictIdSet[$resId]);
                        $isConflictStatus = $reservation['status'] === 'Conflict';
                        $rowClass = ($isConflicting || $isConflictStatus) ? 'conflict-row' : '';
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                        <td><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></td>
                        <td>
                            <?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?>
                            <?php if ($isConflicting && !$isConflictStatus): ?>
                                <i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Date overlap detected"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isConflictStatus): ?>
                                <span class="badge-conflict"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo e($reservation['status']); ?></span>
                            <?php else: ?>
                                <span class="badge-soft"><?php echo e($reservation['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e(formatMoney((float) $reservation['total_amount'])); ?></td>
                        <td class="reservation-actions-cell">
                            <button
                                class="btn btn-sm <?php echo $isConflictStatus ? 'btn-danger' : 'btn-warning'; ?> reservation-manage-button"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#reservationActionsModal<?php echo e($reservation['reservation_id']); ?>"
                            >
                                <?php echo $isConflictStatus ? 'Resolve' : 'Manage'; ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php foreach ($reservations as $reservation): ?>
        <?php
            $reservationId = (int) $reservation['reservation_id'];
            $frontDeskActions = $reservationModel->availableFrontDeskActions($reservation);
            $canExtendStay = in_array($reservation['status'], ['Pending', 'Confirmed', 'Checked-in'], true);
            $minimumExtensionDate = minimumExtensionCheckOut((string) $reservation['check_out']);
            $reservationTotal = (float) $reservation['total_amount'];
            $totals = $paymentTotals[$reservationId] ?? [
                'confirmed_amount' => 0.0,
                'pending_amount' => 0.0,
                'logged_amount' => 0.0,
            ];
            $confirmedAmount = (float) $totals['confirmed_amount'];
            $pendingAmount = (float) $totals['pending_amount'];
            $balanceDue = max(0.0, $reservationTotal - $confirmedAmount);
        ?>
        <div
            class="modal fade reservation-action-modal"
            id="reservationActionsModal<?php echo e($reservationId); ?>"
            tabindex="-1"
            aria-labelledby="reservationActionsModalLabel<?php echo e($reservationId); ?>"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow mb-1">Reservation #<?php echo e($reservationId); ?></p>
                            <h5 class="modal-title" id="reservationActionsModalLabel<?php echo e($reservationId); ?>">
                                <?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?>
                            </h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($reservation['status'] === 'Conflict'): ?>
                            <div class="conflict-modal-banner">
                                <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                                <div>
                                    <strong class="text-danger d-block mb-1">Overlap Conflict Detected</strong>
                                    <small class="text-light-emphasis">This reservation overlaps with another active booking for the same room. Resolve by cancelling one reservation, changing the room assignment, or adjusting dates.</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="reservation-modal-summary" aria-label="Reservation details">
                            <div>
                                <span>Guest</span>
                                <strong><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></strong>
                            </div>
                            <div>
                                <span>Room</span>
                                <strong><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></strong>
                            </div>
                            <div>
                                <span>Stay</span>
                                <strong><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></strong>
                            </div>
                            <div>
                                <span>Status</span>
                                <strong><?php echo e($reservation['status']); ?></strong>
                            </div>
                            <div>
                                <span>Total</span>
                                <strong><?php echo e(formatMoney($reservationTotal)); ?></strong>
                            </div>
                            <div>
                                <span>Confirmed Paid</span>
                                <strong><?php echo e(formatMoney($confirmedAmount)); ?></strong>
                            </div>
                            <div>
                                <span>Pending Payment Logs</span>
                                <strong><?php echo e(formatMoney($pendingAmount)); ?></strong>
                            </div>
                            <div>
                                <span>Balance Due</span>
                                <strong><?php echo e(formatMoney($balanceDue)); ?></strong>
                            </div>
                        </div>

                        <div class="reservation-modal-section">
                            <h6>Front Desk Actions</h6>
                            <div class="reservation-modal-actions">
                                <?php if ($frontDeskActions): ?>
                                    <?php foreach ($frontDeskActions as $actionKey => $actionLabel): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="<?php echo e($actionKey); ?>">
                                            <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">
                                            <button class="btn btn-sm <?php echo $actionKey === 'cancel' ? 'btn-outline-danger' : 'btn-outline-warning'; ?>" type="submit"><?php echo e($actionLabel); ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-light-emphasis small">No front desk status action is available for this reservation.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="reservation-modal-section">
                            <h6>Records and Payments</h6>
                            <div class="reservation-modal-actions">
                                <a class="btn btn-sm btn-outline-warning fw-semibold" href="receipt.php?reservation_id=<?php echo e($reservationId); ?>"><i class="bi bi-receipt me-1"></i>Receipt</a>
                                <a class="btn btn-sm btn-warning fw-semibold" href="payments.php?reservation_id=<?php echo e($reservationId); ?>"><i class="bi bi-credit-card me-1"></i>Payments &amp; Refunds</a>
                            </div>
                        </div>

                        <?php if ($canExtendStay): ?>
                            <div class="reservation-modal-section">
                                <h6>Extend Stay</h6>
                                <form method="post" class="extend-stay-form reservation-modal-extend" title="Extend stay in the same room">
                                    <input type="hidden" name="action" value="extend_stay">
                                    <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">
                                    <label class="visually-hidden" for="modal_new_check_out_<?php echo e($reservationId); ?>">New check-out date</label>
                                    <input
                                        class="form-control form-control-sm"
                                        id="modal_new_check_out_<?php echo e($reservationId); ?>"
                                        name="new_check_out"
                                        type="date"
                                        min="<?php echo e($minimumExtensionDate); ?>"
                                        value="<?php echo e($minimumExtensionDate); ?>"
                                        required
                                    >
                                    <button class="btn btn-sm btn-outline-warning" type="submit">Extend</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <div class="reservation-modal-section reservation-modal-section--danger">
                            <h6>Danger Zone</h6>
                            <div class="reservation-modal-actions">
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete Reservation</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</section>
<script>
document.querySelectorAll(".reservation-action-modal").forEach((modal) => {
    document.body.appendChild(modal);
});
</script>
<?php renderAdminLayoutEnd(); ?>
