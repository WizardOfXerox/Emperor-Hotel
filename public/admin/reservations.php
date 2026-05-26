<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

function buildReservationTotal(array $room, string $checkIn, string $checkOut, string $submittedTotal): float
{
    if (trim($submittedTotal) !== '') {
        return (float) $submittedTotal;
    }

    $seconds = strtotime($checkOut) - strtotime($checkIn);
    $nights = max(1, (int) floor($seconds / 86400));

    return $nights * (float) $room['price_per_night'];
}

$db = Database::connect();
$currentAdmin = currentUser();
$guestModel = new Guest($db);
$roomModel = new Room($db);
$reservationModel = new Reservation($db);
$editReservation = null;
$reservationStatuses = ['Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'delete') {
            $reservationModel->delete((int) ($_POST['reservation_id'] ?? 0));
            setFlash('success', 'Reservation deleted.');
            redirect('reservations.php');
        }

        $roomId = (int) ($_POST['room_id'] ?? 0);
        $room = $roomModel->find($roomId);

        if (!$room) {
            throw new RuntimeException('Please select a valid room.');
        }

        $guestId = $guestModel->upsertFromDetails([
            'guest_id' => $_POST['guest_id'] ?? null,
            'first_name' => (string) ($_POST['first_name'] ?? ''),
            'last_name' => (string) ($_POST['last_name'] ?? ''),
            'phone' => (string) ($_POST['phone'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
        ]);

        $payload = [
            'guest_id' => $guestId,
            'room_id' => $roomId,
            'check_in' => (string) ($_POST['check_in'] ?? ''),
            'check_out' => (string) ($_POST['check_out'] ?? ''),
            'adults' => (int) ($_POST['adults'] ?? 1),
            'children' => (int) ($_POST['children'] ?? 0),
            'addons' => (string) ($_POST['addons'] ?? ''),
            'total_amount' => buildReservationTotal($room, (string) ($_POST['check_in'] ?? ''), (string) ($_POST['check_out'] ?? ''), (string) ($_POST['total_amount'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'Pending'),
        ];

        if ($action === 'create') {
            $reservationModel->create($payload);
            setFlash('success', 'Reservation created.');
            redirect('reservations.php');
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

$rooms = $roomModel->all();
$reservations = $reservationModel->all();

renderAdminLayoutStart('Reservations', 'reservations', $currentAdmin);
?>
<section class="row g-4">
    <div class="col-xl-4">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1"><?php echo $editReservation ? 'Update reservation' : 'Create reservation'; ?></p>
            <h3 class="mb-3"><?php echo $editReservation ? 'Edit Reservation' : 'New Reservation'; ?></h3>
            <form method="post" class="d-grid gap-3">
                <input type="hidden" name="action" value="<?php echo $editReservation ? 'update' : 'create'; ?>">
                <input type="hidden" name="guest_id" value="<?php echo e($editReservation['guest_id'] ?? ''); ?>">
                <?php if ($editReservation): ?>
                    <input type="hidden" name="reservation_id" value="<?php echo e($editReservation['reservation_id']); ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="first_name">First Name</label>
                        <input class="form-control" id="first_name" name="first_name" type="text" value="<?php echo e($editReservation['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input class="form-control" id="last_name" name="last_name" type="text" value="<?php echo e($editReservation['last_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div>
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" type="text" value="<?php echo e($editReservation['phone'] ?? ''); ?>">
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="<?php echo e($editReservation['guest_email'] ?? ''); ?>">
                </div>
                <div>
                    <label class="form-label" for="room_id">Room</label>
                    <select class="form-select" id="room_id" name="room_id" required>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo e($room['room_id']); ?>" <?php echo ((int) ($editReservation['room_id'] ?? 0) === (int) $room['room_id']) ? 'selected' : ''; ?>>
                                <?php echo e($room['room_number'] . ' • ' . $room['room_type'] . ' • ' . formatMoney((float) $room['price_per_night'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="check_in">Check In</label>
                        <input class="form-control" id="check_in" name="check_in" type="date" value="<?php echo e($editReservation['check_in'] ?? ''); ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="check_out">Check Out</label>
                        <input class="form-control" id="check_out" name="check_out" type="date" value="<?php echo e($editReservation['check_out'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-4">
                        <label class="form-label" for="adults">Adults</label>
                        <input class="form-control" id="adults" name="adults" type="number" value="<?php echo e($editReservation['adults'] ?? 1); ?>" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="children">Children</label>
                        <input class="form-control" id="children" name="children" type="number" value="<?php echo e($editReservation['children'] ?? 0); ?>" required>
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
                    <label class="form-label" for="addons">Add-ons</label>
                    <input class="form-control" id="addons" name="addons" type="text" value="<?php echo e($editReservation['addons'] ?? ''); ?>" placeholder="Breakfast, Parking, Airport Shuttle">
                </div>
                <div>
                    <label class="form-label" for="total_amount">Total Amount <span class="text-light-emphasis small">(leave blank to auto-calculate)</span></label>
                    <input class="form-control" id="total_amount" name="total_amount" type="number" step="0.01" value="<?php echo e($editReservation['total_amount'] ?? ''); ?>">
                </div>
                <button class="btn btn-warning fw-semibold" type="submit"><?php echo $editReservation ? 'Save Reservation' : 'Create Reservation'; ?></button>
                <?php if ($editReservation): ?>
                    <a class="btn btn-outline-light" href="reservations.php">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Reservations</p>
                    <h3 class="mb-0">Booking Records</h3>
                </div>
                <span class="badge-soft"><?php echo e(count($reservations)); ?> reservations</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Room</th>
                            <th>Stay</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                                <td><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></td>
                                <td><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></td>
                                <td><span class="badge-soft"><?php echo e($reservation['status']); ?></span></td>
                                <td><?php echo e(formatMoney((float) $reservation['total_amount'])); ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-light" href="reservations.php?edit=<?php echo e($reservation['reservation_id']); ?>">Edit</a>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="reservation_id" value="<?php echo e($reservation['reservation_id']); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php renderAdminLayoutEnd(); ?>
