<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

$db = Database::connect();
$currentAdmin = currentUser();
$guestModel = new Guest($db);
$searchTerm = trim((string) ($_GET['search'] ?? ''));
$selectedGuestId = (int) ($_GET['guest_id'] ?? 0);
$guests = $guestModel->search($searchTerm);
$selectedGuest = $selectedGuestId > 0 ? $guestModel->find($selectedGuestId) : null;
$guestHistory = $selectedGuest ? $guestModel->reservationHistory($selectedGuestId) : [];

renderAdminLayoutStart('Guests', 'guests', $currentAdmin, ['../assets/css/admin/guests.css']);
?>
<section class="row g-4">
    <div class="col-xl-5">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Guest Search</p>
            <h3 class="mb-3">Find Walk-in Guests</h3>
            <form method="get" class="d-flex gap-2 mb-4">
                <input class="form-control" name="search" type="search" value="<?php echo e($searchTerm); ?>" placeholder="Search name, email, or phone">
                <button class="btn btn-warning fw-semibold" type="submit">Search</button>
            </form>

            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Stays</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$guests): ?>
                            <tr>
                                <td colspan="3" class="text-light-emphasis">No guests found.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($guests as $guest): ?>
                            <tr>
                                <td>
                                    <div><?php echo e($guest['first_name'] . ' ' . $guest['last_name']); ?></div>
                                    <small class="text-light-emphasis"><?php echo e($guest['email'] ?: 'No email'); ?> • <?php echo e($guest['phone'] ?: 'No phone'); ?></small>
                                </td>
                                <td>
                                    <div><?php echo e((int) $guest['reservation_count']); ?> stays</div>
                                    <small class="text-light-emphasis">Last: <?php echo e($guest['last_stay'] ?: 'No stay yet'); ?></small>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-light" href="guests.php?guest_id=<?php echo e($guest['guest_id']); ?>&search=<?php echo e(urlencode($searchTerm)); ?>">History</a>
                                    <a class="btn btn-sm btn-warning" href="reservations.php?guest_id=<?php echo e($guest['guest_id']); ?>">Book Again</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Guest History</p>
            <?php if (!$selectedGuest): ?>
                <h3 class="mb-3">Select a guest</h3>
                <p class="muted-copy mb-0">Choose History from the guest list to see past reservations, payment progress, and receipt links.</p>
            <?php else: ?>
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="mb-1"><?php echo e($selectedGuest['first_name'] . ' ' . $selectedGuest['last_name']); ?></h3>
                        <p class="muted-copy mb-0"><?php echo e($selectedGuest['email'] ?: 'No email'); ?> • <?php echo e($selectedGuest['phone'] ?: 'No phone'); ?></p>
                    </div>
                    <a class="btn btn-warning align-self-start" href="reservations.php?guest_id=<?php echo e($selectedGuest['guest_id']); ?>">Create Reservation</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark-soft align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Reservation</th>
                                <th>Stay</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th class="text-end">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$guestHistory): ?>
                                <tr>
                                    <td colspan="5" class="text-light-emphasis">No reservation history yet.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($guestHistory as $reservation): ?>
                                <?php
                                    $confirmedPaid = (float) $reservation['confirmed_paid'];
                                    $pendingPaid = (float) $reservation['pending_paid'];
                                    $totalAmount = (float) $reservation['total_amount'];
                                    $balanceDue = max(0, $totalAmount - $confirmedPaid);
                                ?>
                                <tr>
                                    <td>
                                        <div>#<?php echo e($reservation['reservation_id']); ?> • Room <?php echo e($reservation['room_number']); ?></div>
                                        <small class="text-light-emphasis"><?php echo e($reservation['room_type']); ?></small>
                                    </td>
                                    <td><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></td>
                                    <td><span class="badge-soft"><?php echo e($reservation['status']); ?></span></td>
                                    <td>
                                        <div><?php echo e(formatMoney($confirmedPaid)); ?> confirmed</div>
                                        <small class="text-light-emphasis"><?php echo e(formatMoney($pendingPaid)); ?> pending • <?php echo e(formatMoney($balanceDue)); ?> balance</small>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-light" href="receipt.php?reservation_id=<?php echo e($reservation['reservation_id']); ?>">Receipt</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php renderAdminLayoutEnd(); ?>
