<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('user', '../admin/dashboard.php');

function splitFullName(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];

    return [
        'first_name' => $parts[0] ?? $fullName,
        'last_name' => count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Guest',
    ];
}

function bookingTotal(array $room, string $checkIn, string $checkOut): float
{
    $seconds = strtotime($checkOut) - strtotime($checkIn);
    $nights = max(1, (int) floor($seconds / 86400));

    return $nights * (float) $room['price_per_night'];
}

$db = Database::connect();
$user = currentUser();
$guestModel = new Guest($db);
$roomModel = new Room($db);
$reservationModel = new Reservation($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'cancel') {
            $reservation = $reservationModel->find((int) ($_POST['reservation_id'] ?? 0));

            if (!$reservation || (int) $reservation['user_id'] !== (int) $user['user_id']) {
                throw new RuntimeException('Reservation not found for this account.');
            }

            $reservationModel->updateStatus((int) $reservation['reservation_id'], 'Cancelled');
            setFlash('success', 'Reservation cancelled.');
            redirect('dashboard.php');
        }

        if ($action === 'book') {
            $room = $roomModel->find((int) ($_POST['room_id'] ?? 0));

            if (!$room) {
                throw new RuntimeException('Please choose a valid room.');
            }

            $name = splitFullName($user['full_name']);
            $existingGuest = $guestModel->findByUserId((int) $user['user_id']);
            $guestPayload = [
                'user_id' => (int) $user['user_id'],
                'first_name' => $name['first_name'],
                'last_name' => $name['last_name'],
                'phone' => (string) ($_POST['phone'] ?? ''),
                'email' => $user['email'],
            ];

            if ($existingGuest) {
                $guestModel->update((int) $existingGuest['guest_id'], $guestPayload);
                $guestId = (int) $existingGuest['guest_id'];
            } else {
                $guestId = $guestModel->create($guestPayload);
            }

            $reservationModel->create([
                'user_id' => (int) $user['user_id'],
                'guest_id' => $guestId,
                'room_id' => (int) $_POST['room_id'],
                'check_in' => (string) ($_POST['check_in'] ?? ''),
                'check_out' => (string) ($_POST['check_out'] ?? ''),
                'adults' => (int) ($_POST['adults'] ?? 1),
                'children' => (int) ($_POST['children'] ?? 0),
                'addons' => (string) ($_POST['addons'] ?? ''),
                'total_amount' => bookingTotal($room, (string) ($_POST['check_in'] ?? ''), (string) ($_POST['check_out'] ?? '')),
                'status' => 'Pending',
            ]);

            setFlash('success', 'Reservation request submitted.');
            redirect('dashboard.php');
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('dashboard.php');
    }
}

$rooms = $roomModel->availableRooms();
$reservations = $reservationModel->userReservations((int) $user['user_id']);

renderSiteLayoutStart('My Dashboard', $user, '../site/');
?>
<section class="row g-4">
    <div class="col-xl-5">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Book a Stay</p>
            <h1 class="h3 mb-3">Create a Reservation</h1>
            <p class="muted-copy">Your account details are reused automatically. Just choose your room and dates.</p>
            <form method="post" class="d-grid gap-3">
                <input type="hidden" name="action" value="book">
                <div>
                    <label class="form-label" for="room_id">Room</label>
                    <select class="form-select" id="room_id" name="room_id" required>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo e($room['room_id']); ?>">
                                <?php echo e($room['room_number'] . ' • ' . $room['room_type'] . ' • ' . formatMoney((float) $room['price_per_night'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="check_in">Check In</label>
                        <input class="form-control" id="check_in" name="check_in" type="date" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="check_out">Check Out</label>
                        <input class="form-control" id="check_out" name="check_out" type="date" required>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-4">
                        <label class="form-label" for="adults">Adults</label>
                        <input class="form-control" id="adults" name="adults" type="number" min="1" value="1" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="children">Children</label>
                        <input class="form-control" id="children" name="children" type="number" min="0" value="0" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" type="text">
                    </div>
                </div>
                <div>
                    <label class="form-label" for="addons">Add-ons</label>
                    <input class="form-control" id="addons" name="addons" type="text" placeholder="Breakfast, Parking, Airport Shuttle">
                </div>
                <button class="btn btn-warning fw-semibold" type="submit">Submit Reservation</button>
            </form>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">My Reservations</p>
                    <h2 class="h3 mb-0">Booking History</h2>
                </div>
                <span class="badge-soft"><?php echo e(count($reservations)); ?> bookings</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Stay</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$reservations): ?>
                            <tr>
                                <td colspan="5" class="text-light-emphasis">You have no reservations yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></td>
                                <td><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></td>
                                <td><span class="badge-soft"><?php echo e($reservation['status']); ?></span></td>
                                <td><?php echo e(formatMoney((float) $reservation['total_amount'])); ?></td>
                                <td class="text-end">
                                    <?php if (in_array($reservation['status'], ['Pending', 'Confirmed'], true)): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="reservation_id" value="<?php echo e($reservation['reservation_id']); ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-light-emphasis small">No action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php renderSiteLayoutEnd(); ?>
