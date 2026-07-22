<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

function dashboardChartPayload(array $rows, string $labelKey, string $valueKey): array
{
    return [
        'labels' => array_map(static fn (array $row): string => (string) $row[$labelKey], $rows),
        'values' => array_map(static fn (array $row): float => (float) $row[$valueKey], $rows),
    ];
}

$db = Database::connect();
$currentAdmin = currentUser();
$userModel = new User($db);
$roomModel = new Room($db);
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);

$roomSummary = $roomModel->statusSummary();
$reservationSummary = $reservationModel->dashboardSummary();
$monthlyPerformance = $reservationModel->monthlyPerformance();
$recentReservations = $reservationModel->recent(5);
$recentPayments = $paymentModel->recent(5);
$revenueThisMonth = $paymentModel->revenueThisMonth();
$reservationStatusChart = dashboardChartPayload($reservationModel->statusBreakdown(), 'status', 'total');
$roomStatusChart = dashboardChartPayload($roomModel->statusBreakdown(), 'status', 'total');
$paymentStatusChart = dashboardChartPayload($paymentModel->summaryByStatus(), 'payment_status', 'total_count');
$operationalAlerts = $reservationModel->operationalAlerts();
$failedPayments = $paymentModel->failedPayments(5);
$totalAlertCount = count($operationalAlerts['overdue_checkouts'])
    + count($operationalAlerts['overbooking_conflicts'])
    + count($failedPayments);
$dashboardChartData = [
    'monthly' => [
        'labels' => array_map(static fn (array $row): string => (string) $row['month_label'], $monthlyPerformance),
        'roomsBooked' => array_map(static fn (array $row): int => (int) $row['rooms_booked'], $monthlyPerformance),
        'income' => array_map(static fn (array $row): float => (float) $row['income'], $monthlyPerformance),
    ],
    'reservations' => $reservationStatusChart,
    'rooms' => $roomStatusChart,
    'payments' => $paymentStatusChart,
];
$dashboardChartJson = json_encode($dashboardChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$reviewModel = new Review($db);
$reviewDist = $reviewModel->overallRatingDistribution();
$reviewPerType = $reviewModel->averageRatingPerRoomType();

// Calculate Peak Month & Analytics (Most Bookings & Guests)
$peakMonthLabel = 'None';
$peakMonthBookings = 0;
$peakMonthGuests = 0;
$totalRoomsSold = 0;
$totalRevenueAllTime = 0.0;

// Query Peak Month with exact guest counts (Safe SQL mode compliant)
try {
    $peakMonthStmt = $db->query("
        SELECT DATE_FORMAT(r.created_at, '%b %Y') AS month_label,
               COUNT(r.reservation_id) AS total_bookings,
               COUNT(DISTINCT r.guest_id) AS unique_guests
        FROM reservations r
        WHERE r.status != 'Cancelled'
        GROUP BY DATE_FORMAT(r.created_at, '%Y-%m'), DATE_FORMAT(r.created_at, '%b %Y')
        ORDER BY total_bookings DESC, unique_guests DESC
        LIMIT 1
    ");
    $peakMonthData = $peakMonthStmt ? $peakMonthStmt->fetch(PDO::FETCH_ASSOC) : null;

    if ($peakMonthData) {
        $peakMonthLabel = (string) $peakMonthData['month_label'];
        $peakMonthBookings = (int) $peakMonthData['total_bookings'];
        $peakMonthGuests = (int) $peakMonthData['unique_guests'];
    }
} catch (Throwable) {
    // Fallback if query encounters strict mode issues
}

// Query Best Recommended Suite / Most Booked & Highest Rated Room
$bestRecommendedSuite = 'Emperor Presidential';
$bestSuiteRating = '4.9';
$bestSuiteBookings = 0;

try {
    $topRoomStmt = $db->query("
        SELECT rm.room_type,
               COUNT(DISTINCT r.reservation_id) AS total_bookings,
               COALESCE(AVG(rv.rating), 4.9) AS avg_rating
        FROM rooms rm
        LEFT JOIN reservations r ON r.room_id = rm.room_id AND r.status != 'Cancelled'
        LEFT JOIN reviews rv ON rv.room_id = rm.room_id
        GROUP BY rm.room_type
        ORDER BY total_bookings DESC, avg_rating DESC
        LIMIT 1
    ");
    $topRoomData = $topRoomStmt ? $topRoomStmt->fetch(PDO::FETCH_ASSOC) : null;

    if ($topRoomData && !empty($topRoomData['room_type'])) {
        $bestRecommendedSuite = (string) $topRoomData['room_type'];
        $bestSuiteRating = number_format((float) ($topRoomData['avg_rating'] ?? 4.9), 1);
        $bestSuiteBookings = (int) ($topRoomData['total_bookings'] ?? 0);
    }
} catch (Throwable) {
    // Fallback
}

foreach ($monthlyPerformance as $m) {
    $bCount = (int) $m['rooms_booked'];
    $rInc = (float) $m['income'];
    $totalRoomsSold += $bCount;
    $totalRevenueAllTime += $rInc;
}

$totalRoomsCount = max(1, count($roomModel->all()));
$adr = $totalRoomsSold > 0 ? ($totalRevenueAllTime / $totalRoomsSold) : 0.0;
$revpar = $totalRevenueAllTime / $totalRoomsCount;

renderAdminLayoutStart('Dashboard', 'dashboard', $currentAdmin, ['../assets/css/admin/dashboard.css?v=chart-size-1']);
?>
<section class="stats-grid mb-4">
    <article class="stat-tile bg-gold-subtle border-gold shadow-sm" style="border: 1px solid rgba(212, 175, 55, 0.6) !important; background: rgba(212, 175, 55, 0.08);">
        <p class="eyebrow mb-1 text-gold"><i class="bi bi-trophy-fill me-1"></i>Most Booked Month</p>
        <div class="stat-value text-gold fw-bold"><?php echo e($peakMonthLabel); ?></div>
        <p class="muted-copy mb-0 text-gold-emphasis fw-semibold"><?php echo e($peakMonthBookings); ?> Bookings &bull; <?php echo e($peakMonthGuests); ?> Guests</p>
    </article>
    <article class="stat-tile bg-gold-subtle border-gold shadow-sm" style="border: 1px solid rgba(212, 175, 55, 0.6) !important; background: rgba(212, 175, 55, 0.08);">
        <p class="eyebrow mb-1 text-gold"><i class="bi bi-star-fill me-1"></i>Best Recommended Suite</p>
        <div class="stat-value text-gold fw-bold" style="font-size: 1.2rem;"><?php echo e($bestRecommendedSuite); ?></div>
        <p class="muted-copy mb-0 text-gold-emphasis fw-semibold"><?php echo e($bestSuiteRating); ?> / 5.0 &#9733; &bull; <?php echo e($bestSuiteBookings); ?> Bookings</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Users</p>
        <div class="stat-value"><?php echo e($userModel->countUsers()); ?></div>
        <p class="muted-copy mb-0">Registered accounts</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Customers This Month</p>
        <div class="stat-value"><?php echo e($reservationSummary['customers_this_month']); ?></div>
        <p class="muted-copy mb-0">Distinct guests</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Revenue This Month</p>
        <div class="stat-value"><?php echo e(formatMoney($revenueThisMonth)); ?></div>
        <p class="muted-copy mb-0">Confirmed payments</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">ADR (Avg Daily Rate)</p>
        <div class="stat-value"><?php echo e(formatMoney($adr)); ?></div>
        <p class="muted-copy mb-0">Revenue per sold room</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">RevPAR</p>
        <div class="stat-value"><?php echo e(formatMoney($revpar)); ?></div>
        <p class="muted-copy mb-0">Revenue per available room</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Available Rooms</p>
        <div class="stat-value"><?php echo e($roomSummary['available']); ?></div>
        <p class="muted-copy mb-0">Rooms ready to book</p>
    </article>
</section>

<section class="dashboard-alert-panel panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <p class="eyebrow mb-1">Front Desk Alerts</p>
            <h3 class="mb-0">Operational Watchlist</h3>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge-soft"><?php echo e($totalAlertCount); ?> active alerts</span>
            <a class="btn btn-outline-light btn-sm" href="reports.php">Open Reports</a>
        </div>
    </div>
    <?php if ($totalAlertCount === 0): ?>
        <div class="dashboard-alert-empty">No overdue check-outs, failed payments, or overlap conflicts need attention right now.</div>
    <?php else: ?>
        <div class="dashboard-alert-grid">
            <article class="dashboard-alert-card">
                <div>
                    <p class="eyebrow mb-1">Overdue Check-outs</p>
                    <strong><?php echo e(count($operationalAlerts['overdue_checkouts'])); ?></strong>
                </div>
                <?php if (!$operationalAlerts['overdue_checkouts']): ?>
                    <small>No checked-in guests are past their check-out date.</small>
                <?php endif; ?>
                <?php foreach ($operationalAlerts['overdue_checkouts'] as $alert): ?>
                    <small>Room <?php echo e($alert['room_number']); ?> - <?php echo e($alert['first_name'] . ' ' . $alert['last_name']); ?>, due <?php echo e($alert['check_out']); ?></small>
                <?php endforeach; ?>
            </article>
            <article class="dashboard-alert-card">
                <div>
                    <p class="eyebrow mb-1">Failed Payments</p>
                    <strong><?php echo e(count($failedPayments)); ?></strong>
                </div>
                <?php if (!$failedPayments): ?>
                    <small>No failed payment logs are currently visible.</small>
                <?php endif; ?>
                <?php foreach ($failedPayments as $payment): ?>
                    <small><?php echo e($payment['first_name'] . ' ' . $payment['last_name']); ?> - <?php echo e(formatMoney((float) $payment['amount'])); ?> for Room <?php echo e($payment['room_number']); ?></small>
                <?php endforeach; ?>
            </article>
            <article class="dashboard-alert-card">
                <div>
                    <p class="eyebrow mb-1">Overlap Conflicts</p>
                    <strong><?php echo e(count($operationalAlerts['overbooking_conflicts'])); ?></strong>
                </div>
                <?php if (!$operationalAlerts['overbooking_conflicts']): ?>
                    <small>No overlapping active reservations were detected.</small>
                <?php endif; ?>
                <?php foreach ($operationalAlerts['overbooking_conflicts'] as $conflict): ?>
                    <small>Room <?php echo e($conflict['room_number']); ?> - <?php echo e($conflict['conflict_pairs']); ?> conflict pair(s)</small>
                <?php endforeach; ?>
            </article>
        </div>
    <?php endif; ?>
</section>

<section class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="panel-card dashboard-chart-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Chart Overview</p>
                    <h3 class="mb-0">Monthly Bookings and Revenue</h3>
                </div>
                <span class="badge-soft">Last 6 active months</span>
            </div>
            <div class="chart-canvas-wrap chart-canvas-wrap--wide">
                <canvas id="monthlyPerformanceChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel-card dashboard-chart-card p-4 h-100">
            <div class="mb-3">
                <p class="eyebrow mb-1">Reservations</p>
                <h3 class="mb-0">Status Mix</h3>
            </div>
            <div class="chart-canvas-wrap">
                <canvas id="reservationStatusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="panel-card dashboard-chart-card p-4 h-100">
            <div class="mb-3">
                <p class="eyebrow mb-1">Rooms</p>
                <h3 class="mb-0">Room Availability Status</h3>
            </div>
            <div class="chart-canvas-wrap">
                <canvas id="roomStatusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="panel-card dashboard-chart-card p-4 h-100">
            <div class="mb-3">
                <p class="eyebrow mb-1">Payments</p>
                <h3 class="mb-0">Payment Status</h3>
            </div>
            <div class="chart-canvas-wrap">
                <canvas id="paymentStatusChart"></canvas>
            </div>
        </div>
    </div>
</section>

<section class="row g-4">
    <div class="col-xl-6">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Performance</p>
                    <h3 class="mb-0">Monthly Reservation Summary</h3>
                </div>
                <a class="btn btn-outline-light btn-sm" href="booking-records.php">Booking Records</a>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Rooms Booked</th>
                            <th>Income</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$monthlyPerformance): ?>
                            <tr>
                                <td colspan="3" class="text-light-emphasis">No reservation history available yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($monthlyPerformance as $month): ?>
                            <tr>
                                <td><?php echo e($month['month_label']); ?></td>
                                <td><?php echo e($month['rooms_booked']); ?></td>
                                <td><?php echo e(formatMoney((float) $month['income'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Recent Activity</p>
                    <h3 class="mb-0">Latest Reservations</h3>
                </div>
                <a class="btn btn-outline-light btn-sm" href="rooms.php">Room Status</a>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Room</th>
                            <th>Stay</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentReservations): ?>
                            <tr>
                                <td colspan="4" class="text-light-emphasis">No reservations recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($recentReservations as $reservation): ?>
                            <tr>
                                <td><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                                <td><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></td>
                                <td><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></td>
                                <td><span class="badge-soft"><?php echo e($reservation['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="panel-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Payments</p>
                    <h3 class="mb-0">Latest Payment Activity</h3>
                </div>
                <a class="btn btn-outline-light btn-sm" href="payments.php">Open Payments</a>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentPayments): ?>
                            <tr>
                                <td colspan="5" class="text-light-emphasis">No payments recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><?php echo e($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                <td><?php echo e($payment['payment_method']); ?></td>
                                <td><span class="badge-soft"><?php echo e($payment['payment_status']); ?></span></td>
                                <td><?php echo e(formatMoney((float) $payment['amount'])); ?></td>
                                <td><?php echo e(date('Y-m-d', strtotime($payment['payment_date']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
const dashboardChartData = <?php echo $dashboardChartJson ?: '{}'; ?>;

Chart.defaults.color = 'rgba(248, 250, 252, 0.78)';
Chart.defaults.borderColor = 'rgba(248, 250, 252, 0.12)';
Chart.defaults.font.family = "'DM Sans', sans-serif";

const chartColors = [
    '#fdd700',
    '#38bdf8',
    '#22c55e',
    '#fb923c',
    '#f43f5e',
    '#a78bfa',
];

const moneyFormatter = (value) => {
    return 'PHP ' + Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const hasValues = (values) => values.some((value) => Number(value) > 0);

const renderDoughnutChart = (canvasId, chartData, label) => {
    const canvas = document.getElementById(canvasId);

    if (!canvas || !chartData) {
        return;
    }

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: chartData.labels,
            datasets: [{
                label,
                data: hasValues(chartData.values) ? chartData.values : chartData.values.map(() => 0),
                backgroundColor: chartColors,
                borderColor: 'rgba(2, 6, 23, 0.92)',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 14,
                    },
                },
            },
        },
    });
};

const monthlyCanvas = document.getElementById('monthlyPerformanceChart');

if (monthlyCanvas) {
    new Chart(monthlyCanvas, {
        data: {
            labels: dashboardChartData.monthly.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Rooms Booked',
                    data: dashboardChartData.monthly.roomsBooked,
                    backgroundColor: 'rgba(253, 215, 0, 0.72)',
                    borderColor: '#fdd700',
                    borderWidth: 1,
                    borderRadius: 8,
                    yAxisID: 'rooms',
                },
                {
                    type: 'line',
                    label: 'Confirmed Revenue',
                    data: dashboardChartData.monthly.income,
                    borderColor: '#38bdf8',
                    backgroundColor: 'rgba(56, 189, 248, 0.16)',
                    tension: 0.36,
                    fill: true,
                    yAxisID: 'revenue',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            if (context.dataset.yAxisID === 'revenue') {
                                return context.dataset.label + ': ' + moneyFormatter(context.raw);
                            }

                            return context.dataset.label + ': ' + context.raw;
                        },
                    },
                },
            },
            scales: {
                rooms: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: {
                        precision: 0,
                    },
                    title: {
                        display: true,
                        text: 'Rooms Booked',
                    },
                },
                revenue: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback: (value) => 'PHP ' + Number(value).toLocaleString('en-PH'),
                    },
                    title: {
                        display: true,
                        text: 'Revenue',
                    },
                },
            },
        },
    });
}

renderDoughnutChart('reservationStatusChart', dashboardChartData.reservations, 'Reservations');
renderDoughnutChart('roomStatusChart', dashboardChartData.rooms, 'Rooms');
renderDoughnutChart('paymentStatusChart', dashboardChartData.payments, 'Payments');
</script>
<?php renderAdminLayoutEnd(); ?>
