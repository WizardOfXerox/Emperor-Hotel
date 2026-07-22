<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

function formatReportPercent(float $value): string
{
    return number_format($value, 1) . '%';
}

$db = Database::connect();
$currentAdmin = currentUser();
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);

$today = new DateTimeImmutable('today');
$defaultStartDate = $today->modify('first day of this month')->format('Y-m-d');
$defaultEndDate = $today->format('Y-m-d');
$startDate = (string) ($_GET['start_date'] ?? $defaultStartDate);
$endDate = (string) ($_GET['end_date'] ?? $defaultEndDate);

try {
    $occupancyReport = $reservationModel->occupancyReport($startDate, $endDate);
    $revenueReport = $paymentModel->revenueReport($startDate, $endDate);
    $trendReport = $reservationModel->reservationTrendReport($startDate, $endDate);
} catch (Throwable $exception) {
    setFlash('error', $exception->getMessage());
    redirect('reports.php');
}

renderAdminLayoutStart('Reports', 'reports', $currentAdmin, ['../assets/css/admin/reports.css']);
?>
<section class="panel-card report-filter-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
        <div>
            <p class="eyebrow mb-1">Reports</p>
            <h3 class="mb-0">Occupancy, Revenue, and Reservation Trends</h3>
        </div>
        <span class="badge-soft"><?php echo e($startDate); ?> to <?php echo e($endDate); ?></span>
    </div>
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="start_date">Start Date</label>
            <input class="form-control" id="start_date" name="start_date" type="date" value="<?php echo e($startDate); ?>" required>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="end_date">End Date</label>
            <input class="form-control" id="end_date" name="end_date" type="date" value="<?php echo e($endDate); ?>" required>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-warning fw-semibold" type="submit">Run Report</button>
        </div>
    </form>
</section>

<section class="stats-grid mb-4">
    <article class="stat-tile">
        <p class="eyebrow mb-2">Period Length</p>
        <div class="stat-value"><?php echo e($occupancyReport['days']); ?></div>
        <p class="muted-copy mb-0">Report day(s)</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Occupancy</p>
        <div class="stat-value"><?php echo e(formatReportPercent((float) $occupancyReport['occupancy_rate'])); ?></div>
        <p class="muted-copy mb-0"><?php echo e($occupancyReport['booked_room_nights']); ?> of <?php echo e($occupancyReport['total_room_nights']); ?> room nights booked</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Confirmed Revenue</p>
        <div class="stat-value"><?php echo e(formatMoney((float) $revenueReport['total_revenue'])); ?></div>
        <p class="muted-copy mb-0">Confirmed payments in this date range</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Reservations Created</p>
        <div class="stat-value"><?php echo e($trendReport['total_reservations']); ?></div>
        <p class="muted-copy mb-0">Total booking records created</p>
    </article>
</section>

<section class="row g-4">
    <div class="col-xl-6">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Occupancy</p>
            <h3 class="mb-3">Room Nights by Type</h3>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Room Type</th>
                            <th>Rooms</th>
                            <th>Booked Nights</th>
                            <th>Available Nights</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($occupancyReport['by_room_type'] as $row): ?>
                            <tr>
                                <td><?php echo e($row['room_type']); ?></td>
                                <td><?php echo e($row['room_count']); ?></td>
                                <td><?php echo e($row['booked_room_nights']); ?></td>
                                <td><?php echo e($row['available_room_nights']); ?></td>
                                <td><span class="badge-soft"><?php echo e(formatReportPercent((float) $row['occupancy_rate'])); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Revenue</p>
            <h3 class="mb-3">Confirmed Revenue by Room Type</h3>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Room Type</th>
                            <th>Payments</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($revenueReport['by_room_type'] as $row): ?>
                            <tr>
                                <td><?php echo e($row['room_type']); ?></td>
                                <td><?php echo e($row['payment_count']); ?></td>
                                <td><?php echo e(formatMoney((float) $row['confirmed_revenue'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Payment Methods</p>
            <h3 class="mb-3">Revenue by Method</h3>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Payments</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$revenueReport['by_payment_method']): ?>
                            <tr>
                                <td colspan="3" class="text-light-emphasis">No confirmed payments in this date range.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($revenueReport['by_payment_method'] as $row): ?>
                            <tr>
                                <td><?php echo e($row['payment_method']); ?></td>
                                <td><?php echo e($row['payment_count']); ?></td>
                                <td><?php echo e(formatMoney((float) $row['confirmed_revenue'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1">Reservation Trend</p>
            <h3 class="mb-3">Daily Booking Records</h3>
            <div class="table-responsive report-trend-table">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Active</th>
                            <th>Cancelled</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trendReport['rows'] as $row): ?>
                            <tr>
                                <td><?php echo e($row['reservation_date']); ?></td>
                                <td><?php echo e($row['active_reservations']); ?></td>
                                <td><?php echo e($row['cancelled_reservations']); ?></td>
                                <td><?php echo e($row['total_reservations']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php
$reviewModel = new Review($db);
$ratingsPerType = $reviewModel->averageRatingPerRoomType();
$ratingDist = $reviewModel->overallRatingDistribution();
?>
<section class="panel-card p-4 mt-4 mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <p class="eyebrow mb-1">Guest Satisfaction & Ratings</p>
            <h3 class="mb-0">Guest Review Breakdown</h3>
        </div>
        <span class="badge bg-gold text-dark fw-bold px-3 py-2 rounded-pill">Recommendation Engine Data</span>
    </div>
    <div class="row g-4">
        <div class="col-md-6">
            <h5 class="font-serif text-gold mb-3">Average Rating by Suite Type</h5>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle">
                    <thead>
                        <tr>
                            <th>Room Type</th>
                            <th>Average Rating</th>
                            <th>Total Reviews</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ratingsPerType as $type => $data): ?>
                            <tr>
                                <td><strong class="text-gold"><?= e($type) ?></strong></td>
                                <td><span class="text-warning fw-bold">★ <?= number_format((float)$data['avg_rating'], 1) ?></span> / 5.0</td>
                                <td><?= (int)$data['review_count'] ?> review(s)</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <h5 class="font-serif text-gold mb-3">Overall Star Rating Distribution</h5>
            <?php foreach ($ratingDist as $stars => $count): ?>
                <div class="d-flex align-items-center mb-2">
                    <span class="text-warning small text-nowrap me-2" style="width: 70px;"><?= $stars ?> Stars</span>
                    <div class="progress flex-grow-1 bg-dark border border-secondary" style="height: 12px;">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= array_sum($ratingDist) > 0 ? ($count / array_sum($ratingDist) * 100) : 0 ?>%"></div>
                    </div>
                    <span class="text-muted small ms-2" style="width: 40px; text-align: right;"><?= $count ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php renderAdminLayoutEnd(); ?>
