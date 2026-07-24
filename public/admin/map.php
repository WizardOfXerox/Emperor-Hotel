<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/standalone_2d_map.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

if (!isFeatureMapEnabled()) {
    setFlash('warning', 'The Interactive Hotel Map feature is currently disabled in system configuration.');
    redirect('dashboard.php');
}

$db = Database::connect();
$currentAdmin = currentUser();
$roomModel = new Room($db);

$today = new DateTimeImmutable('today');
$checkIn = (string) ($_GET['check_in'] ?? $today->format('Y-m-d'));
$checkOut = (string) ($_GET['check_out'] ?? $today->modify('+1 day')->format('Y-m-d'));

$allRooms = $roomModel->all();

renderAdminLayoutStart('Interactive Hotel Map', 'map', $currentAdmin, ['../assets/css/admin/rooms.css?v=20260725-map']);
?>
<section class="panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <p class="eyebrow mb-1"><i class="bi bi-map-fill me-1 text-warning"></i>2D Architectural Blueprint</p>
            <h3 class="mb-0">Emperor Hotel 2D Map</h3>
            <p class="muted-copy mb-0">Interactive 2D floorplan blueprint vector layout with live room status overlays, stay date filtering, and guest inspection details.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge-soft"><?php echo count($allRooms); ?> Total Rooms</span>
            <button class="btn btn-warning btn-sm fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#createRoomModal">
                <i class="bi bi-plus-circle me-1"></i>New Room
            </button>
        </div>
    </div>

    <!-- Stay Dates Search Filter -->
    <form method="get" class="row g-3 align-items-end mb-2">
        <div class="col-md-4 col-lg-3">
            <label class="form-label text-xs fw-semibold text-warning" for="mapCheckIn"><i class="bi bi-calendar-event me-1"></i>Check-In Date</label>
            <input type="date" class="form-control bg-dark text-light border-secondary" id="mapCheckIn" name="check_in" value="<?php echo e($checkIn); ?>" onchange="this.form.submit()">
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="form-label text-xs fw-semibold text-warning" for="mapCheckOut"><i class="bi bi-calendar-check me-1"></i>Check-Out Date</label>
            <input type="date" class="form-control bg-dark text-light border-secondary" id="mapCheckOut" name="check_out" value="<?php echo e($checkOut); ?>" onchange="this.form.submit()">
        </div>
        <div class="col-md-4 col-lg-6 d-flex gap-2 justify-content-md-end">
            <button type="submit" class="btn btn-warning fw-semibold px-4"><i class="bi bi-search me-1"></i>Filter Availability</button>
            <a href="map.php" class="btn btn-outline-light"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset Dates</a>
        </div>
    </form>
</section>

<!-- Interactive 2D Blueprint Vector Map Component -->
<section class="mb-4">
    <?php renderStandalone2DMap($db, 'admin', $checkIn, $checkOut); ?>
</section>

<?php
renderAdminLayoutEnd();
