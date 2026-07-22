<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

$db = Database::connect();
$currentAdmin = currentUser();
$reservationModel = new Reservation($db);

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$reservations = $reservationModel->searchAndFilter($search, $statusFilter);

renderAdminLayoutStart('Booking Logs', 'booking-records', $currentAdmin, ['../assets/css/admin/reservations.css?v=20260530-booking-logs']);
?>
<section class="panel-card p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <p class="eyebrow mb-1">System Archives & History</p>
            <h3 class="mb-0">Booking Logs & Audit Trail</h3>
            <p class="muted-copy mb-0">Read-only historical audit log of all guest reservations, status records, stay durations, and total pricing.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge-soft"><?php echo e(count($reservations)); ?> total log entries</span>
            <a class="btn btn-warning btn-sm fw-semibold" href="reservations.php"><i class="bi bi-calendar-check me-1"></i>Manage Reservations</a>
            <a class="btn btn-outline-warning btn-sm fw-semibold" href="create-reservation.php"><i class="bi bi-plus-circle me-1"></i>Create Reservation</a>
        </div>
    </div>

    <form method="get" class="row g-2 mb-4 align-items-center">
        <div class="col-md-6 col-lg-7">
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-warning"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control bg-dark text-light border-secondary" placeholder="Search by guest name, room #, email, or log ID..." value="<?php echo e($search); ?>">
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
            </select>
        </div>
        <div class="col-md-2 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-warning w-100 fw-semibold">Filter</button>
            <?php if ($search !== '' || ($statusFilter !== '' && $statusFilter !== 'all')): ?>
                <a href="booking-records.php" class="btn btn-outline-light" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-dark-soft align-middle mb-0 booking-records-table">
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Date Logged</th>
                    <th>Guest</th>
                    <th>Room</th>
                    <th>Stay Dates</th>
                    <th>Status</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$reservations): ?>
                    <tr>
                        <td colspan="7" class="text-light-emphasis text-center py-4">No booking records yet.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($reservations as $reservation): ?>
                    <tr>
                        <td class="fw-bold text-gold">#RES-<?php echo e((string) $reservation['reservation_id']); ?></td>
                        <td class="small text-muted"><?php echo e(date('M d, Y • h:i A', strtotime((string) $reservation['created_at']))); ?></td>
                        <td class="fw-semibold"><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                        <td><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></td>
                        <td class="small"><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></td>
                        <td><span class="badge-soft"><?php echo e($reservation['status']); ?></span></td>
                        <td class="fw-bold text-warning"><?php echo e(formatMoney((float) $reservation['total_amount'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderAdminLayoutEnd(); ?>
