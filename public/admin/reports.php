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
    $advAnalytics = $reservationModel->advancedHospitalityAnalytics($startDate, $endDate);
} catch (Throwable $exception) {
    setFlash('error', $exception->getMessage());
    redirect('reports.php');
}

renderAdminLayoutStart('Reports', 'reports', $currentAdmin, ['../assets/css/admin/reports.css']);
?>
<section class="panel-card report-filter-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
        <div>
            <p class="eyebrow mb-1">Reports & Executive Analytics</p>
            <h3 class="mb-0">Occupancy, Revenue, and Advanced Hospitality Metrics</h3>
        </div>
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
            <button class="btn btn-warning fw-semibold" type="submit">Show Report</button>
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

<!-- ADVANCED HOSPITALITY ANALYTICS SECTION -->
<section class="stats-grid mb-4">
    <article class="stat-tile border-gold-subtle" style="border: 1px solid rgba(212, 175, 55, 0.4) !important; background: rgba(212, 175, 55, 0.05);">
        <p class="eyebrow mb-1 text-gold"><i class="bi bi-clock-history me-1"></i>ALOS (Avg Length of Stay)</p>
        <div class="stat-value text-gold"><?php echo e($advAnalytics['alos']); ?> <span class="fs-6 font-mono text-light-emphasis">nights</span></div>
        <p class="muted-copy mb-0">Average stay duration per guest</p>
    </article>

    <article class="stat-tile border-gold-subtle" style="border: 1px solid rgba(212, 175, 55, 0.4) !important; background: rgba(212, 175, 55, 0.05);">
        <p class="eyebrow mb-1 text-gold"><i class="bi bi-calendar2-range me-1"></i>Booking Lead Time</p>
        <div class="stat-value text-gold"><?php echo e($advAnalytics['lead_time_days']); ?> <span class="fs-6 font-mono text-light-emphasis">days</span></div>
        <p class="muted-copy mb-0">Avg advance reservation window</p>
    </article>

    <article class="stat-tile border-gold-subtle" style="border: 1px solid rgba(212, 175, 55, 0.4) !important; background: rgba(212, 175, 55, 0.05);">
        <p class="eyebrow mb-1 text-gold"><i class="bi bi-x-circle me-1"></i>Cancellation Loss Rate</p>
        <div class="stat-value text-gold"><?php echo e($advAnalytics['cancellation_rate']); ?>%</div>
        <p class="muted-copy mb-0"><?php echo e(formatMoney($advAnalytics['lost_revenue'])); ?> unearned revenue</p>
    </article>

    <article class="stat-tile border-gold-subtle" style="border: 1px solid rgba(212, 175, 55, 0.4) !important; background: rgba(212, 175, 55, 0.05);">
        <p class="eyebrow mb-1 text-gold"><i class="bi bi-heart-fill me-1"></i>Repeat Guest Loyalty</p>
        <div class="stat-value text-gold"><?php echo e($advAnalytics['repeat_guest_rate']); ?>%</div>
        <p class="muted-copy mb-0"><?php echo e($advAnalytics['repeat_guests_count']); ?> returning guests (2+ stays)</p>
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

<!-- Tabular Data Breakdown Section (Converted to Interactive Visual Graphs & Table Toggle) -->
<section class="row g-4">
    <!-- Card 1: Room Nights by Type -->
    <div class="col-xl-6">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Occupancy</p>
                    <h3 class="mb-0">Room Nights by Type</h3>
                </div>
                <div class="btn-group btn-group-sm rounded-pill overflow-hidden border border-secondary border-opacity-25" role="group">
                    <button type="button" class="btn btn-warning view-toggle-btn active fw-bold px-3 py-1" onclick="toggleReportView(this, 'graph')"><i class="bi bi-bar-chart-fill me-1"></i>Graph</button>
                    <button type="button" class="btn btn-outline-secondary view-toggle-btn px-3 py-1" onclick="toggleReportView(this, 'table')"><i class="bi bi-table me-1"></i>Table</button>
                </div>
            </div>
            <div class="report-graph-view" style="height: 250px; position: relative;">
                <canvas id="roomNightsChart"></canvas>
            </div>
            <div class="report-table-view table-responsive" style="display: none;">
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

    <!-- Card 2: Confirmed Revenue by Room Type -->
    <div class="col-xl-6">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Revenue</p>
                    <h3 class="mb-0">Confirmed Revenue by Room Type</h3>
                </div>
                <div class="btn-group btn-group-sm rounded-pill overflow-hidden border border-secondary border-opacity-25" role="group">
                    <button type="button" class="btn btn-warning view-toggle-btn active fw-bold px-3 py-1" onclick="toggleReportView(this, 'graph')"><i class="bi bi-pie-chart-fill me-1"></i>Graph</button>
                    <button type="button" class="btn btn-outline-secondary view-toggle-btn px-3 py-1" onclick="toggleReportView(this, 'table')"><i class="bi bi-table me-1"></i>Table</button>
                </div>
            </div>
            <div class="report-graph-view" style="height: 250px; position: relative;">
                <canvas id="revenueRoomTypeChart"></canvas>
            </div>
            <div class="report-table-view table-responsive" style="display: none;">
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

    <!-- Card 3: Revenue by Method -->
    <div class="col-xl-5">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Payment Methods</p>
                    <h3 class="mb-0">Revenue by Method</h3>
                </div>
                <div class="btn-group btn-group-sm rounded-pill overflow-hidden border border-secondary border-opacity-25" role="group">
                    <button type="button" class="btn btn-warning view-toggle-btn active fw-bold px-3 py-1" onclick="toggleReportView(this, 'graph')"><i class="bi bi-bar-chart-steps me-1"></i>Graph</button>
                    <button type="button" class="btn btn-outline-secondary view-toggle-btn px-3 py-1" onclick="toggleReportView(this, 'table')"><i class="bi bi-table me-1"></i>Table</button>
                </div>
            </div>
            <div class="report-graph-view" style="height: 250px; position: relative;">
                <canvas id="revenueMethodChart"></canvas>
            </div>
            <div class="report-table-view table-responsive" style="display: none;">
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
                                <td><?php echo e(!empty($row['payment_method']) ? $row['payment_method'] : 'E-Wallet'); ?></td>
                                <td><?php echo e($row['payment_count']); ?></td>
                                <td><?php echo e(formatMoney((float) $row['confirmed_revenue'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Card 4: Daily Booking Records -->
    <div class="col-xl-7">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Reservation Trend</p>
                    <h3 class="mb-0">Daily Booking Records</h3>
                </div>
                <div class="btn-group btn-group-sm rounded-pill overflow-hidden border border-secondary border-opacity-25" role="group">
                    <button type="button" class="btn btn-warning view-toggle-btn active fw-bold px-3 py-1" onclick="toggleReportView(this, 'graph')"><i class="bi bi-graph-up me-1"></i>Graph</button>
                    <button type="button" class="btn btn-outline-secondary view-toggle-btn px-3 py-1" onclick="toggleReportView(this, 'table')"><i class="bi bi-table me-1"></i>Table</button>
                </div>
            </div>
            <div class="report-graph-view" style="height: 250px; position: relative;">
                <canvas id="dailyBookingBarChart"></canvas>
            </div>
            <div class="report-table-view table-responsive report-trend-table" style="display: none;">
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
$trendFormattedDates = array_map(static function($dateStr) {
    $ts = strtotime((string) $dateStr);
    return $ts ? date('M j', $ts) : (string) $dateStr;
}, array_column($trendReport['rows'], 'reservation_date'));
$trendDates = json_encode($trendFormattedDates);
$trendActive = json_encode(array_column($trendReport['rows'], 'active_reservations'));
$trendCancelled = json_encode(array_column($trendReport['rows'], 'cancelled_reservations'));

$roomTypes = json_encode(array_column($occupancyReport['by_room_type'], 'room_type'));
$bookedNights = json_encode(array_column($occupancyReport['by_room_type'], 'booked_room_nights'));

$paymentMethods = json_encode(array_column($revenueReport['by_payment_method'], 'payment_method'));
$paymentRevenues = json_encode(array_column($revenueReport['by_payment_method'], 'confirmed_revenue'));

$ratingTypes = json_encode(array_keys($ratingsPerType));
$ratingScores = json_encode(array_map(fn($item) => (float)$item['avg_rating'], array_values($ratingsPerType)));

// Additional JSON arrays for lower breakdown charts
$roomNightsTypes = json_encode(array_column($occupancyReport['by_room_type'], 'room_type'));
$roomNightsBooked = json_encode(array_column($occupancyReport['by_room_type'], 'booked_room_nights'));
$roomNightsAvailable = json_encode(array_column($occupancyReport['by_room_type'], 'available_room_nights'));

$revenueTypeNames = json_encode(array_column($revenueReport['by_room_type'], 'room_type'));
$revenueTypeValues = json_encode(array_map(fn($item) => (float)$item['confirmed_revenue'], $revenueReport['by_room_type']));

$paymentMethodNames = json_encode(array_map(fn($item) => !empty($item['payment_method']) ? $item['payment_method'] : 'E-Wallet', $revenueReport['by_payment_method']));
$paymentMethodRevenues = json_encode(array_map(fn($item) => (float)$item['confirmed_revenue'], $revenueReport['by_payment_method']));
?>
<script>
function toggleReportView(btn, mode) {
    const card = btn.closest('.panel-card');
    if (!card) return;
    card.querySelectorAll('.view-toggle-btn').forEach(b => {
        b.classList.remove('active', 'btn-warning', 'fw-bold');
        b.classList.add('btn-outline-secondary');
    });
    btn.classList.add('active', 'btn-warning', 'fw-bold');
    btn.classList.remove('btn-outline-secondary');
    
    const graphView = card.querySelector('.report-graph-view');
    const tableView = card.querySelector('.report-table-view');
    if (mode === 'graph') {
        if (graphView) graphView.style.display = 'block';
        if (tableView) tableView.style.display = 'none';
    } else {
        if (graphView) graphView.style.display = 'none';
        if (tableView) tableView.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const isLightMode = document.documentElement.classList.contains('light-mode');
    Chart.defaults.color = isLightMode ? '#334155' : '#94a3b8';
    Chart.defaults.borderColor = isLightMode ? 'rgba(15, 23, 42, 0.08)' : 'rgba(248, 250, 252, 0.08)';
    Chart.defaults.font.family = "'Outfit', 'Segoe UI', system-ui, sans-serif";

    // High-Contrast Pill Badge Renderer for Chart Labels
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

    // Value Labels Plugin for Line and Bar Charts
    const emperorValuePlugin = {
        id: 'emperorValueLabels',
        afterDatasetsDraw(chart) {
            const { ctx, chartArea } = chart;
            if (!chartArea) return;
            const isLight = document.documentElement.classList.contains('light-mode');

            chart.data.datasets.forEach((dataset, datasetIndex) => {
                const meta = chart.getDatasetMeta(datasetIndex);
                if (meta.hidden) return;

                meta.data.forEach((element, index) => {
                    const value = dataset.data[index];
                    if (value === null || value === undefined || value === 0) return;

                    if (chart.config.type === 'line') {
                        const text = String(value);
                        const x = element.x;
                        const yOffset = datasetIndex === 0 ? -14 : 14;
                        const y = Math.max(chartArea.top + 12, Math.min(chartArea.bottom - 12, element.y + yOffset));
                        const bgColor = datasetIndex === 0 ? '#fdd700' : '#ef4444';
                        const textColor = datasetIndex === 0 ? '#020617' : '#ffffff';
                        drawPillBadge(ctx, text, x, y, bgColor, textColor);
                    } else if (chart.config.type === 'bar') {
                        if (chart.options.indexAxis === 'y') {
                            const text = typeof value === 'number' && value >= 1000 
                                ? '₱' + Math.round(value / 1000) + 'k' 
                                : (typeof value === 'number' && value < 6 ? value.toFixed(1) + ' ★' : String(value));
                            const x = Math.min(element.x + 24, chartArea.right - 18);
                            const y = element.y;
                            drawPillBadge(ctx, text, x, y, isLight ? '#0284c7' : '#38bdf8', '#020617');
                        } else {
                            const text = typeof value === 'number' ? value.toLocaleString() : String(value);
                            const x = element.x;
                            const y = Math.max(chartArea.top + 14, element.y - 12);
                            drawPillBadge(ctx, text, x, y, isLight ? '#b45309' : '#fdd700', '#020617');
                        }
                    }
                });
            });
        }
    };

    // Doughnut Percentage Pill Plugin
    const emperorDoughnutPlugin = {
        id: 'emperorDoughnutLabels',
        afterDatasetsDraw(chart) {
            if (chart.config.type !== 'doughnut' && chart.config.type !== 'pie') return;
            const { ctx } = chart;
            const dataset = chart.data.datasets[0];
            if (!dataset || !dataset.data) return;

            const total = dataset.data.reduce((a, b) => a + Number(b || 0), 0);
            const meta = chart.getDatasetMeta(0);
            if (meta.hidden) return;

            meta.data.forEach((element, index) => {
                const value = Number(dataset.data[index] || 0);
                if (!value || total <= 0) return;

                const percent = Math.round((value / total) * 100);
                if (percent < 4) return;

                const position = element.tooltipPosition();
                const text = percent + '%';
                drawPillBadge(ctx, text, position.x, position.y, 'rgba(2, 6, 23, 0.88)', '#ffffff');
            });
        }
    };

    // 1. Demand Trend Line Chart
    const trendCtx = document.getElementById('trendLineChart')?.getContext('2d');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            plugins: [emperorValuePlugin],
            data: {
                labels: <?= $trendDates ?>,
                datasets: [
                    {
                        label: 'Active Reservations',
                        data: <?= $trendActive ?>,
                        borderColor: isLightMode ? '#b45309' : '#fdd700',
                        backgroundColor: isLightMode ? 'rgba(217, 119, 6, 0.15)' : 'rgba(253, 215, 0, 0.15)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: isLightMode ? '#b45309' : '#fdd700',
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
                layout: { padding: { top: 22, right: 20, left: 10, bottom: 5 } },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12 } }
                },
                scales: {
                    x: {
                        grid: { color: isLightMode ? 'rgba(0, 0, 0, 0.05)' : 'rgba(248, 250, 252, 0.05)' },
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12,
                            font: { size: 11, weight: '500' }
                        }
                    },
                    y: { grid: { color: isLightMode ? 'rgba(0, 0, 0, 0.05)' : 'rgba(248, 250, 252, 0.05)' }, beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    // 2. Payment Method Doughnut Chart
    const paymentCtx = document.getElementById('paymentPieChart')?.getContext('2d');
    if (paymentCtx) {
        new Chart(paymentCtx, {
            type: 'doughnut',
            plugins: [emperorDoughnutPlugin],
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
                layout: { padding: { top: 15, bottom: 15 } },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12 } }
                },
                cutout: '60%'
            }
        });
    }

    // 3. Occupancy Bar Chart
    const occupancyCtx = document.getElementById('occupancyBarChart')?.getContext('2d');
    if (occupancyCtx) {
        new Chart(occupancyCtx, {
            type: 'bar',
            plugins: [emperorValuePlugin],
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
                layout: { padding: { top: 22, right: 15 } },
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
            plugins: [emperorValuePlugin],
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
                layout: { padding: { right: 35 } },
                plugins: { legend: { display: false } },
                scales: {
                    x: { max: 5.0, min: 0, grid: { color: 'rgba(248, 250, 252, 0.05)' } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // 5. Room Nights by Type Chart (Occupancy Breakdown)
    const roomNightsCtx = document.getElementById('roomNightsChart')?.getContext('2d');
    if (roomNightsCtx) {
        new Chart(roomNightsCtx, {
            type: 'bar',
            plugins: [emperorValuePlugin],
            data: {
                labels: <?= $roomNightsTypes ?>,
                datasets: [
                    {
                        label: 'Booked Nights',
                        data: <?= $roomNightsBooked ?>,
                        backgroundColor: 'rgba(253, 215, 0, 0.85)',
                        borderColor: '#fdd700',
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: 'Available Nights',
                        data: <?= $roomNightsAvailable ?>,
                        backgroundColor: isLightMode ? 'rgba(100, 116, 139, 0.3)' : 'rgba(248, 250, 252, 0.15)',
                        borderColor: 'rgba(248, 250, 252, 0.2)',
                        borderWidth: 1,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 22 } },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12 } }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: 'rgba(248, 250, 252, 0.05)' }, beginAtZero: true }
                }
            }
        });
    }

    // 6. Confirmed Revenue by Room Type Chart
    const revenueTypeCtx = document.getElementById('revenueRoomTypeChart')?.getContext('2d');
    if (revenueTypeCtx) {
        new Chart(revenueTypeCtx, {
            type: 'doughnut',
            plugins: [emperorDoughnutPlugin],
            data: {
                labels: <?= $revenueTypeNames ?>,
                datasets: [{
                    data: <?= $revenueTypeValues ?>,
                    backgroundColor: ['#38bdf8', '#fdd700', '#a855f7'],
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
                cutout: '60%'
            }
        });
    }

    // 7. Revenue by Payment Method Chart
    const revenueMethodCtx = document.getElementById('revenueMethodChart')?.getContext('2d');
    if (revenueMethodCtx) {
        new Chart(revenueMethodCtx, {
            type: 'bar',
            plugins: [emperorValuePlugin],
            data: {
                labels: <?= $paymentMethodNames ?>,
                datasets: [{
                    label: 'Revenue (PHP)',
                    data: <?= $paymentMethodRevenues ?>,
                    backgroundColor: ['#fdd700', '#38bdf8', '#22c55e', '#a855f7', '#f97316'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                layout: { padding: { right: 50 } },
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(248, 250, 252, 0.05)' }, beginAtZero: true },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // 8. Daily Booking Records Chart
    const dailyBookingCtx = document.getElementById('dailyBookingBarChart')?.getContext('2d');
    if (dailyBookingCtx) {
        new Chart(dailyBookingCtx, {
            type: 'bar',
            plugins: [emperorValuePlugin],
            data: {
                labels: <?= $trendDates ?>,
                datasets: [
                    {
                        label: 'Active',
                        data: <?= $trendActive ?>,
                        backgroundColor: 'rgba(253, 215, 0, 0.85)',
                        borderRadius: 4
                    },
                    {
                        label: 'Cancelled',
                        data: <?= $trendCancelled ?>,
                        backgroundColor: 'rgba(239, 68, 68, 0.85)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 22 } },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12 } }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, minRotation: 0, autoSkip: true, maxTicksLimit: 12 }
                    },
                    y: { grid: { color: 'rgba(248, 250, 252, 0.05)' }, beginAtZero: true, stacked: false }
                }
            }
        });
    }
});
</script>
<?php renderAdminLayoutEnd(); ?>
