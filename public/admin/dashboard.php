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

$advAnalytics = $reservationModel->advancedHospitalityAnalytics();

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
        <p class="eyebrow mb-2">ALOS (Avg Stay)</p>
        <div class="stat-value text-gold"><?php echo e($advAnalytics['alos']); ?> <span class="fs-6 font-mono text-light-emphasis">nights</span></div>
        <p class="muted-copy mb-0">Average stay duration</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Booking Lead Time</p>
        <div class="stat-value text-gold"><?php echo e($advAnalytics['lead_time_days']); ?> <span class="fs-6 font-mono text-light-emphasis">days</span></div>
        <p class="muted-copy mb-0">Advance booking window</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Repeat Guest Loyalty</p>
        <div class="stat-value text-gold"><?php echo e($advAnalytics['repeat_guest_rate']); ?>%</div>
        <p class="muted-copy mb-0">Returning guests (2+ stays)</p>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Available Rooms</p>
        <div class="stat-value"><?php echo e($roomSummary['available']); ?></div>
        <p class="muted-copy mb-0">Rooms ready to book</p>
    </article>
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
                <a class="btn btn-outline-warning btn-sm" href="reservations.php"><i class="bi bi-calendar-check me-1"></i>Manage Reservations</a>
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
const dashboardChartData = <?php echo json_encode($dashboardChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const chartColors = ['#fdd700', '#38bdf8', '#22c55e', '#f97316', '#ef4444', '#a855f7'];

const moneyFormatter = (val) => 'PHP ' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const hasValues = (values) => values.some((value) => Number(value) > 0);
const isLightMode = document.documentElement.classList.contains('light-mode');

Chart.defaults.color = isLightMode ? '#334155' : '#94a3b8';
Chart.defaults.borderColor = isLightMode ? 'rgba(15, 23, 42, 0.08)' : 'rgba(248, 250, 252, 0.08)';
Chart.defaults.font.family = "'Outfit', 'Segoe UI', system-ui, sans-serif";

const drawPillBadge = (ctx, text, x, y, bgColor, textColor) => {
    ctx.save();
    ctx.font = '700 10px "Outfit", "Segoe UI", system-ui, sans-serif';
    const metrics = ctx.measureText(text);
    const textWidth = metrics.width;
    const textHeight = 11;
    const px = 5;
    const py = 2;
    const rectX = x - textWidth / 2 - px;
    const rectY = y - textHeight / 2 - py;
    const rectW = textWidth + px * 2;
    const rectH = textHeight + py * 2;
    const radius = 4;

    ctx.fillStyle = bgColor;
    ctx.beginPath();
    if (ctx.roundRect) {
        ctx.roundRect(rectX, rectY, rectW, rectH, radius);
    } else {
        ctx.rect(rectX, rectY, rectW, rectH);
    }
    ctx.fill();

    ctx.fillStyle = textColor;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, x, y);
    ctx.restore();
};

const comboChartValuePlugin = {
    id: 'comboChartValueLabels',
    afterDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (meta.hidden) return;

            meta.data.forEach((element, index) => {
                const value = dataset.data[index];
                if (value === null || value === undefined || value === 0) return;

                if (dataset.type === 'line' || chart.config.type === 'line') {
                    const text = typeof value === 'number' && value >= 1000 
                        ? '₱' + Math.round(value / 1000) + 'k' 
                        : String(value);
                    const x = element.x;
                    const y = Math.max(chartArea.top + 12, element.y - 15);
                    drawPillBadge(ctx, text, x, y, isLightMode ? '#0284c7' : '#38bdf8', '#020617');
                } else if (dataset.type === 'bar' || chart.config.type === 'bar') {
                    const text = String(value);
                    const x = element.x;
                    const y = Math.min(element.y + 14, chartArea.bottom - 12);
                    drawPillBadge(ctx, text, x, y, isLightMode ? '#b45309' : '#fdd700', '#020617');
                }
            });
        });
    }
};

const renderStatusChart = (canvasId, chartData, label) => {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const total = chartData.values.reduce((a, b) => a + Number(b || 0), 0);

    new Chart(canvas, {
        type: 'bar',
        plugins: [{
            id: 'statusValueLabels',
            afterDatasetsDraw(chart) {
                const { ctx, chartArea } = chart;
                if (!chartArea) return;
                const meta = chart.getDatasetMeta(0);
                if (meta.hidden) return;

                meta.data.forEach((element, index) => {
                    const val = Number(chartData.values[index] || 0);
                    const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                    const text = val + ' (' + pct + '%)';
                    
                    const x = Math.min(element.x + 28, chartArea.right - 22);
                    const y = element.y;
                    
                    const barColor = chartColors[index % chartColors.length];
                    const textColor = barColor === '#fdd700' || barColor === '#38bdf8' || barColor === '#22c55e' ? '#020617' : '#ffffff';
                    drawPillBadge(ctx, text, x, y, barColor, textColor);
                });
            }
        }],
        data: {
            labels: chartData.labels,
            datasets: [{
                label,
                data: chartData.values,
                backgroundColor: chartColors.slice(0, chartData.labels.length),
                borderRadius: 6,
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            layout: { padding: { right: 65 } },
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { grid: { color: isLightMode ? 'rgba(0, 0, 0, 0.05)' : 'rgba(248, 250, 252, 0.05)' }, beginAtZero: true },
                y: { grid: { display: false } }
            }
        }
    });
};

const monthlyCanvas = document.getElementById('monthlyPerformanceChart');

if (monthlyCanvas) {
    new Chart(monthlyCanvas, {
        plugins: [comboChartValuePlugin],
        data: {
            labels: dashboardChartData.monthly.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Rooms Booked',
                    data: dashboardChartData.monthly.roomsBooked,
                    backgroundColor: isLightMode ? 'rgba(217, 119, 6, 0.85)' : 'rgba(253, 215, 0, 0.72)',
                    borderColor: isLightMode ? '#b45309' : '#fdd700',
                    borderWidth: 1,
                    borderRadius: 8,
                    yAxisID: 'rooms',
                },
                {
                    type: 'line',
                    label: 'Confirmed Revenue',
                    data: dashboardChartData.monthly.income,
                    borderColor: isLightMode ? '#0284c7' : '#38bdf8',
                    backgroundColor: isLightMode ? 'rgba(2, 132, 199, 0.15)' : 'rgba(56, 189, 248, 0.16)',
                    tension: 0.36,
                    fill: true,
                    yAxisID: 'revenue',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 32, right: 15 } },
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

renderStatusChart('reservationStatusChart', dashboardChartData.reservations, 'Reservations');
renderStatusChart('roomStatusChart', dashboardChartData.rooms, 'Rooms');
renderStatusChart('paymentStatusChart', dashboardChartData.payments, 'Payments');
</script>
<?php renderAdminLayoutEnd(); ?>
