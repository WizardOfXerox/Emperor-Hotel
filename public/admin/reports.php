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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Visual Analytics Graphs Section -->
<section class="row g-4 mb-4">
    <!-- Chart 1: Reservation & Booking Trend -->
    <div class="col-xl-8">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Visual Analytics</p>
                    <h3 class="mb-0">Booking Demand & Reservation Trend</h3>
                </div>
                <span class="badge bg-gold text-dark fw-bold px-3 py-1 rounded-pill"><i class="bi bi-graph-up-arrow me-1"></i>Demand Curve</span>
            </div>
            <div style="height: 280px; position: relative;">
                <canvas id="trendLineChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Payment Method Revenue Share -->
    <div class="col-xl-4">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Financial Distribution</p>
                    <h3 class="mb-0">Revenue Share by Payment</h3>
                </div>
                <span class="badge bg-gold text-dark fw-bold px-3 py-1 rounded-pill"><i class="bi bi-pie-chart me-1"></i>Payment Mix</span>
            </div>
            <div style="height: 280px; position: relative;" class="d-flex align-items-center justify-content-center">
                <canvas id="paymentPieChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 3: Suite Occupancy & Booked Nights -->
    <div class="col-xl-6">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Inventory Performance</p>
                    <h3 class="mb-0">Booked Room Nights by Suite Type</h3>
                </div>
                <span class="badge bg-gold text-dark fw-bold px-3 py-1 rounded-pill"><i class="bi bi-bar-chart-line me-1"></i>Suite Volume</span>
            </div>
            <div style="height: 250px; position: relative;">
                <canvas id="occupancyBarChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 4: Suite Ratings & Guest Satisfaction -->
    <div class="col-xl-6">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Recommendation Engine</p>
                    <h3 class="mb-0">Guest Rating Score by Suite</h3>
                </div>
                <span class="badge bg-gold text-dark fw-bold px-3 py-1 rounded-pill"><i class="bi bi-star-fill me-1"></i>5-Star Analytics</span>
            </div>
            <div style="height: 250px; position: relative;">
                <canvas id="ratingsBarChart"></canvas>
            </div>
        </div>
    </div>
</section>

<!-- Tabular Data Breakdown Section -->
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

// Prepare JSON arrays for Chart.js
$trendDates = json_encode(array_column($trendReport['rows'], 'reservation_date'));
$trendActive = json_encode(array_column($trendReport['rows'], 'active_reservations'));
$trendCancelled = json_encode(array_column($trendReport['rows'], 'cancelled_reservations'));

$roomTypes = json_encode(array_column($occupancyReport['by_room_type'], 'room_type'));
$bookedNights = json_encode(array_column($occupancyReport['by_room_type'], 'booked_room_nights'));

$paymentMethods = json_encode(array_column($revenueReport['by_payment_method'], 'payment_method'));
$paymentRevenues = json_encode(array_column($revenueReport['by_payment_method'], 'confirmed_revenue'));

$ratingTypes = json_encode(array_keys($ratingsPerType));
$ratingScores = json_encode(array_map(fn($item) => (float)$item['avg_rating'], array_values($ratingsPerType)));
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Outfit', 'Segoe UI', system-ui, sans-serif";

    // 1. Trend Line Chart
    const trendCtx = document.getElementById('trendLineChart')?.getContext('2d');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= $trendDates ?>,
                datasets: [
                    {
                        label: 'Active Reservations',
                        data: <?= $trendActive ?>,
                        borderColor: '#fdd700',
                        backgroundColor: 'rgba(253, 215, 0, 0.15)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#fdd700',
                    },
                    {
                        label: 'Cancelled',
                        data: <?= $trendCancelled ?>,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.08)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#ef4444',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12 } }
                },
                scales: {
                    x: { grid: { color: 'rgba(248, 250, 252, 0.05)' } },
                    y: { grid: { color: 'rgba(248, 250, 252, 0.05)' }, beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    // 2. Payment Method Doughnut Chart
    const paymentCtx = document.getElementById('paymentPieChart')?.getContext('2d');
    if (paymentCtx) {
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: <?= $paymentMethods ?>,
                datasets: [{
                    data: <?= $paymentRevenues ?>,
                    backgroundColor: ['#fdd700', '#38bdf8', '#22c55e', '#a855f7', '#f97316'],
                    borderWidth: 2,
                    borderColor: '#0f172a'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12 } }
                },
                cutout: '65%'
            }
        });
    }

    // 3. Occupancy Bar Chart
    const occupancyCtx = document.getElementById('occupancyBarChart')?.getContext('2d');
    if (occupancyCtx) {
        new Chart(occupancyCtx, {
            type: 'bar',
            data: {
                labels: <?= $roomTypes ?>,
                datasets: [{
                    label: 'Booked Room Nights',
                    data: <?= $bookedNights ?>,
                    backgroundColor: 'rgba(253, 215, 0, 0.75)',
                    borderColor: '#fdd700',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: 'rgba(248, 250, 252, 0.05)' }, beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    // 4. Ratings Score Bar Chart
    const ratingsCtx = document.getElementById('ratingsBarChart')?.getContext('2d');
    if (ratingsCtx) {
        new Chart(ratingsCtx, {
            type: 'bar',
            data: {
                labels: <?= $ratingTypes ?>,
                datasets: [{
                    label: 'Average Score (out of 5.0)',
                    data: <?= $ratingScores ?>,
                    backgroundColor: 'rgba(56, 189, 248, 0.75)',
                    borderColor: '#38bdf8',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { max: 5.0, min: 0, grid: { color: 'rgba(248, 250, 252, 0.05)' } },
                    y: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
<?php renderAdminLayoutEnd(); ?>
