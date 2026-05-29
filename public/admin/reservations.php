<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';
require_once __DIR__ . '/../includes/room_selection.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

function buildReservationTotal(array $room, string $checkIn, string $checkOut): float
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
$guestModel = new Guest($db);
$roomModel = new Room($db);
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);
$editReservation = null;
$reservationStatuses = Reservation::statuses();

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

        $checkIn = (string) ($_POST['check_in'] ?? '');
        $checkOut = (string) ($_POST['check_out'] ?? '');
        $adults = (int) ($_POST['adults'] ?? 1);
        $children = (int) ($_POST['children'] ?? 0);
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $room = null;

        if ($roomId > 0) {
            $room = $roomModel->find($roomId);
        }

        if (!$room) {
            throw new RuntimeException('Please select a room card before saving the reservation.');
        }

        $fullName = trim((string) ($_POST['full_name'] ?? ''));

        if ($fullName === '') {
            throw new RuntimeException('Full name is required.');
        }

        $name = splitFullName($fullName);
        $guestId = $guestModel->upsertFromDetails([
            'guest_id' => $_POST['guest_id'] ?? null,
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'phone' => (string) ($_POST['phone'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
        ]);

        $payload = [
            'guest_id' => $guestId,
            'room_id' => $roomId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => $adults,
            'children' => $children,
            'total_amount' => buildReservationTotal($room, $checkIn, $checkOut),
            'status' => (string) ($_POST['status'] ?? 'Pending'),
        ];

        if ($action === 'create') {
            if (in_array($room['status'], ['Cleaning', 'Maintenance'], true)) {
                throw new RuntimeException('Please choose a room that is not under cleaning or maintenance.');
            }

            $paymentMethod = (string) ($_POST['payment_method'] ?? 'Cash');

            if (!in_array($paymentMethod, Payment::methods(), true)) {
                throw new RuntimeException('Please choose a valid payment method.');
            }

            $reservationId = $reservationModel->createAndGetId($payload);

            if ($paymentMethod === 'Cash') {
                $paymentId = $paymentModel->createAndGetId([
                    'reservation_id' => $reservationId,
                    'amount' => (float) $payload['total_amount'],
                    'payment_method' => 'Cash',
                    'currency' => 'PHP',
                    'payment_status' => 'Pending',
                    'is_simulated' => false,
                    'notes' => 'Automatic pending cash payment reference generated during reservation creation.',
                ]);
                $payment = $paymentModel->find($paymentId);
                $reference = (string) ($payment['transaction_reference'] ?? ('Reservation #' . $reservationId));

                setFlash('success', 'Reservation created. Payment reference: ' . $reference . '.');
                redirect('reservations.php');
            }

            setFlash('success', 'Reservation created. Continue payment processing for ' . $paymentMethod . '.');
            redirect('payments.php?' . http_build_query([
                'reservation_id' => $reservationId,
                'payment_method' => $paymentMethod,
            ]));
        }

        if ($action === 'update') {
            $reservationModel->update((int) ($_POST['reservation_id'] ?? 0), array_merge($payload, [
                'guest_id' => $guestId,
            ]));
            setFlash('success', 'Reservation updated.');
            redirect('reservations.php');
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('reservations.php');
    }
}

if (isset($_GET['edit'])) {
    $editReservation = $reservationModel->find((int) $_GET['edit']);
}

$prefillGuest = null;

if (!$editReservation && isset($_GET['guest_id'])) {
    $prefillGuest = $guestModel->find((int) $_GET['guest_id']);
}

$availabilityCheckIn = (string) ($_GET['check_in'] ?? ($editReservation['check_in'] ?? ''));
$availabilityCheckOut = (string) ($_GET['check_out'] ?? ($editReservation['check_out'] ?? ''));
$availabilityDatesValid = $reservationModel->dateRangeIsValid($availabilityCheckIn, $availabilityCheckOut);
$rooms = $availabilityDatesValid
    ? $reservationModel->roomsWithDateAvailability(
        $availabilityCheckIn,
        $availabilityCheckOut,
        isset($editReservation['reservation_id']) ? (int) $editReservation['reservation_id'] : null
    )
    : $roomModel->all();
$reservations = $reservationModel->all();

renderAdminLayoutStart('Reservations', 'reservations', $currentAdmin, ['../assets/css/admin/reservations.css?v=20260530-actions']);
?>
<section class="row g-4">
    <div class="col-xl-5">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1"><?php echo $editReservation ? 'Update reservation' : 'Create reservation'; ?></p>
            <h3 class="mb-3"><?php echo $editReservation ? 'Edit Reservation' : 'New Reservation'; ?></h3>
            <form method="get" class="availability-filter-card mb-4">
                <?php if ($editReservation): ?>
                    <input type="hidden" name="edit" value="<?php echo e($editReservation['reservation_id']); ?>">
                <?php endif; ?>
                <?php if ($prefillGuest): ?>
                    <input type="hidden" name="guest_id" value="<?php echo e($prefillGuest['guest_id']); ?>">
                <?php endif; ?>
                <div>
                    <p class="eyebrow mb-1">Date-aware Availability</p>
                    <p class="muted-copy small mb-0">Choose stay dates first so the room cards show rooms available for that exact date range.</p>
                </div>
                <div class="row g-2">
                    <div class="col-md-5">
                        <input class="form-control" name="check_in" type="date" value="<?php echo e($availabilityCheckIn); ?>" aria-label="Availability check-in">
                    </div>
                    <div class="col-md-5">
                        <input class="form-control" name="check_out" type="date" value="<?php echo e($availabilityCheckOut); ?>" aria-label="Availability check-out">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-outline-light" type="submit">Check</button>
                    </div>
                </div>
                <?php if ($availabilityCheckIn !== '' || $availabilityCheckOut !== ''): ?>
                    <div class="form-text">
                        <?php echo $availabilityDatesValid ? 'Room availability is filtered by the selected dates.' : 'Enter a valid check-in and check-out date to filter room availability.'; ?>
                    </div>
                <?php endif; ?>
            </form>
            <form method="post" class="d-grid gap-3" data-dynamic-room-availability data-availability-url="room-availability.php">
                <input type="hidden" name="action" value="<?php echo $editReservation ? 'update' : 'create'; ?>">
                <input type="hidden" name="guest_id" value="<?php echo e($editReservation['guest_id'] ?? ($prefillGuest['guest_id'] ?? '')); ?>">
                <?php if ($editReservation): ?>
                    <input type="hidden" name="reservation_id" value="<?php echo e($editReservation['reservation_id']); ?>">
                <?php endif; ?>
                <div>
                    <label class="form-label" for="full_name">Full Name</label>
                    <input class="form-control" id="full_name" name="full_name" type="text" value="<?php echo e(trim((string) (($editReservation['first_name'] ?? ($prefillGuest['first_name'] ?? '')) . ' ' . ($editReservation['last_name'] ?? ($prefillGuest['last_name'] ?? ''))))); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" type="text" value="<?php echo e($editReservation['phone'] ?? ($prefillGuest['phone'] ?? '')); ?>">
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="<?php echo e($editReservation['guest_email'] ?? ($prefillGuest['email'] ?? '')); ?>">
                </div>
                <div>
                    <label class="form-label">Room</label>
                    <?php renderRoomChoiceCards($rooms, isset($editReservation['room_id']) ? (int) $editReservation['room_id'] : null, true, $db); ?>
                    <div class="form-text" data-room-availability-note>Use the filters for all, available, or unavailable rooms. Room cards update automatically when check-in and check-out dates change.</div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="check_in">Check In</label>
                        <input class="form-control" id="check_in" name="check_in" type="date" value="<?php echo e($editReservation['check_in'] ?? $availabilityCheckIn); ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="check_out">Check Out</label>
                        <input class="form-control" id="check_out" name="check_out" type="date" value="<?php echo e($editReservation['check_out'] ?? $availabilityCheckOut); ?>" required>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-4">
                        <label class="form-label" for="adults">Adults</label>
                        <input class="form-control" id="adults" name="adults" type="number" min="1" value="<?php echo e($editReservation['adults'] ?? 1); ?>" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="children">Children</label>
                        <input class="form-control" id="children" name="children" type="number" min="0" value="<?php echo e($editReservation['children'] ?? 0); ?>" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach ($reservationStatuses as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php echo (($editReservation['status'] ?? 'Pending') === $status) ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label">Room Inclusions</label>
                    <?php renderRoomInclusionPreview($editReservation['room_type'] ?? null); ?>
                </div>
                <?php renderReservationCostTracker(); ?>
                <?php if (!$editReservation): ?>
                    <div class="panel-card p-3">
                        <p class="eyebrow mb-1">Payment Route</p>
                        <h4 class="h6 mb-3">Customer Payment Mode</h4>
                        <div>
                            <label class="form-label" for="payment_method">Payment Mode</label>
                            <select class="form-select" id="payment_method" name="payment_method" data-reservation-payment-method>
                                <?php foreach (Payment::methods() as $method): ?>
                                    <option value="<?php echo e($method); ?>"><?php echo e($method); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" data-payment-route-message>Cash creates an automatic pending payment reference for the full reservation total. Card or online methods continue to the Payments page.</div>
                        </div>
                    </div>
                <?php endif; ?>
                <button class="btn btn-warning fw-semibold" type="submit"><?php echo $editReservation ? 'Save Reservation' : 'Create Reservation'; ?></button>
                <?php if ($editReservation): ?>
                    <a class="btn btn-outline-light" href="reservations.php">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Reservations</p>
                    <h3 class="mb-0">Booking Records</h3>
                </div>
                <span class="badge-soft"><?php echo e(count($reservations)); ?> reservations</span>
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
                        <?php foreach ($reservations as $reservation): ?>
                            <?php
                                $canExtendStay = in_array($reservation['status'], ['Pending', 'Confirmed', 'Checked-in'], true);
                                $minimumExtensionDate = minimumExtensionCheckOut((string) $reservation['check_out']);
                            ?>
                            <tr>
                                <td><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                                <td>
                                    <div><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></div>
                                </td>
                                <td><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></td>
                                <td><span class="badge-soft"><?php echo e($reservation['status']); ?></span></td>
                                <td><?php echo e(formatMoney((float) $reservation['total_amount'])); ?></td>
                                <td class="reservation-actions-cell">
                                    <div class="front-desk-actions" aria-label="Reservation actions">
                                        <?php $frontDeskActions = $reservationModel->availableFrontDeskActions($reservation); ?>
                                        <?php if ($frontDeskActions): ?>
                                            <div class="reservation-action-row">
                                                <?php foreach ($frontDeskActions as $actionKey => $actionLabel): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="<?php echo e($actionKey); ?>">
                                                        <input type="hidden" name="reservation_id" value="<?php echo e($reservation['reservation_id']); ?>">
                                                        <button class="btn btn-sm <?php echo $actionKey === 'cancel' ? 'btn-outline-danger' : 'btn-outline-warning'; ?>" type="submit"><?php echo e($actionLabel); ?></button>
                                                    </form>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="reservation-action-row">
                                            <a class="btn btn-sm btn-outline-light" href="receipt.php?reservation_id=<?php echo e($reservation['reservation_id']); ?>">Receipt</a>
                                            <a class="btn btn-sm btn-outline-light" href="reservations.php?edit=<?php echo e($reservation['reservation_id']); ?>&check_in=<?php echo e($reservation['check_in']); ?>&check_out=<?php echo e($reservation['check_out']); ?>">Edit</a>
                                            <a class="btn btn-sm btn-warning" href="payments.php?reservation_id=<?php echo e($reservation['reservation_id']); ?>">Payment</a>
                                        </div>
                                        <?php if ($canExtendStay): ?>
                                            <form method="post" class="extend-stay-form" title="Extend stay in the same room">
                                                <input type="hidden" name="action" value="extend_stay">
                                                <input type="hidden" name="reservation_id" value="<?php echo e($reservation['reservation_id']); ?>">
                                                <label class="visually-hidden" for="new_check_out_<?php echo e($reservation['reservation_id']); ?>">New check-out date</label>
                                                <input
                                                    class="form-control form-control-sm"
                                                    id="new_check_out_<?php echo e($reservation['reservation_id']); ?>"
                                                    name="new_check_out"
                                                    type="date"
                                                    min="<?php echo e($minimumExtensionDate); ?>"
                                                    value="<?php echo e($minimumExtensionDate); ?>"
                                                    required
                                                >
                                                <button class="btn btn-sm btn-outline-warning" type="submit">Extend</button>
                                            </form>
                                        <?php endif; ?>
                                        <div class="reservation-action-row reservation-action-row--danger">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="reservation_id" value="<?php echo e($reservation['reservation_id']); ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                            </form>
                                        </div>
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
document.querySelectorAll("[data-reservation-payment-method]").forEach((methodSelect) => {
    const form = methodSelect.closest("form");
    const message = form ? form.querySelector("[data-payment-route-message]") : null;

    const syncPaymentRoute = () => {
        const isCash = methodSelect.value === "Cash";

        if (message) {
            message.textContent = isCash
                ? "Cash creates an automatic pending payment reference for the full reservation total."
                : "This method will open the Payments page after the reservation is created.";
        }
    };

    methodSelect.addEventListener("change", syncPaymentRoute);
    syncPaymentRoute();
});
</script>
<?php renderRoomAvailabilityUpdater(); ?>
<?php renderAdminLayoutEnd(); ?>
