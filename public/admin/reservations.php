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

        if (in_array($action, ['confirm', 'check_in', 'check_out', 'cancel'], true)) {
            $newStatus = $reservationModel->applyFrontDeskAction((int) ($_POST['reservation_id'] ?? 0), $action);
            setFlash('success', 'Reservation status changed to ' . $newStatus . '.');
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

$reservations = $reservationModel->all();
$paymentTotals = $paymentModel->totalsByReservation();

renderAdminLayoutStart('Manage Reservations', 'reservations', $currentAdmin, ['../assets/css/admin/reservations.css?v=20260530-manage-reservations']);
?>
<section class="panel-card p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <p class="eyebrow mb-1">Reservations</p>
            <h3 class="mb-0">Booking Records</h3>
            <p class="muted-copy mb-0">Review reservation details, update front desk status, extend stays, collect payments, and print receipts.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge-soft"><?php echo e(count($reservations)); ?> reservations</span>
            <a class="btn btn-warning btn-sm fw-semibold" href="reservations.php">Create Reservation</a>
        </div>
    </div>

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
                    <tr>
                        <td><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                        <td><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></td>
                        <td><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></td>
                        <td><span class="badge-soft"><?php echo e($reservation['status']); ?></span></td>
                        <td><?php echo e(formatMoney((float) $reservation['total_amount'])); ?></td>
                        <td class="reservation-actions-cell">
                            <button
                                class="btn btn-sm btn-warning reservation-manage-button"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#reservationActionsModal<?php echo e($reservation['reservation_id']); ?>"
                            >
                                Manage
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
                                <a class="btn btn-sm btn-outline-light" href="receipt.php?reservation_id=<?php echo e($reservationId); ?>">Receipt</a>
                                <a class="btn btn-sm btn-warning" href="payments.php?reservation_id=<?php echo e($reservationId); ?>">Payment</a>
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
