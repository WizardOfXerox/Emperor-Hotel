<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/standalone_2d_map.php';

if (!isFeatureMapEnabled()) {
    setFlash('warning', 'The Interactive Hotel Map feature is currently disabled in system configuration.');
    redirect('home.php');
}

$db = Database::connect();
$user = currentUser();
$dashboardHref = $user ? ($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php') : '../auth/login.php';
$dashboardLabel = 'DASHBOARD';

$today = new DateTimeImmutable('today');
$checkIn = (string) ($_GET['check_in'] ?? $today->format('Y-m-d'));
$checkOut = (string) ($_GET['check_out'] ?? $today->modify('+1 day')->format('Y-m-d'));

renderHeader('Interactive 2D Hotel Map | Emperor Hotel', ['../assets/css/site/home.css?v=20260725-map'], 'home-showcase-page');
?>
<header class="home-header">
    <nav class="home-nav">
        <div class="home-nav__container">
            <a class="home-nav__logo" href="home.php" aria-label="Emperor Hotel home">
                <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
            </a>

            <div class="home-nav__links">
                <a class="home-nav__link" href="home.php">HOME</a>
                <a class="home-nav__link" href="rooms.php">ROOMS</a>
                <a class="home-nav__link" href="suites.php">SUITES</a>
                <a class="home-nav__link home-nav__link--active" href="map.php">MAP</a>
                <a class="home-nav__link" href="contact.php">CONTACT</a>
            </div>

            <div class="home-nav__auth">
                <button type="button" class="btn btn-sm btn-outline-warning theme-toggle-btn rounded-circle me-2 d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; padding: 0;" onclick="toggleEmperorTheme()" title="Switch to Light Mode" aria-label="Switch to Light Mode"><i class="bi bi-sun-fill fs-5"></i></button>
                <?php if ($user): ?>
                    <a class="home-nav__cta home-nav__cta--primary" href="<?php echo e($dashboardHref); ?>"><?php echo e($dashboardLabel); ?></a>
                    <a class="home-nav__cta home-nav__cta--secondary" href="../auth/logout.php" title="Log Out"><i class="bi bi-box-arrow-right d-sm-none"></i><span class="d-none d-sm-inline">LOG OUT</span></a>
                <?php else: ?>
                    <a class="home-nav__cta home-nav__cta--primary" href="../auth/login.php">LOG IN</a>
                    <a class="home-nav__cta home-nav__cta--secondary" href="../auth/register.php">REGISTER</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<main class="py-5">
    <div class="container-fluid px-lg-4 px-xl-5">
        <div class="text-center mb-4">
            <p class="eyebrow text-warning mb-1"><i class="bi bi-map-fill me-1"></i>2D Architectural Blueprint Map</p>
            <h1 class="font-serif fw-bold text-gold display-5">Interactive 2D Hotel Map</h1>
            <p class="text-light opacity-75 max-w-2xl mx-auto">Explore Emperor Hotel's luxury suites floor-by-floor. Hover any room on the 2D blueprint layout to inspect live pricing, capacity, and status overlays for your stay dates.</p>
        </div>

        <!-- Date Picker Bar -->
        <div class="panel-card p-4 mb-4 max-w-4xl mx-auto rounded-4 shadow-lg border border-warning" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px);">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label text-xs fw-semibold text-warning" for="siteMapCheckIn"><i class="bi bi-calendar-event me-1"></i>Check-In Date</label>
                    <input type="date" class="form-control bg-dark text-light border-secondary" id="siteMapCheckIn" name="check_in" value="<?php echo e($checkIn); ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-5">
                    <label class="form-label text-xs fw-semibold text-warning" for="siteMapCheckOut"><i class="bi bi-calendar-check me-1"></i>Check-Out Date</label>
                    <input type="date" class="form-control bg-dark text-light border-secondary" id="siteMapCheckOut" name="check_out" value="<?php echo e($checkOut); ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-warning fw-bold py-2"><i class="bi bi-search me-1"></i>Filter</button>
                </div>
            </form>
        </div>

        <!-- 2D Architectural Floor Plan Blueprint Section -->
        <div class="panel-card p-4 rounded-4 shadow-lg border border-warning" style="background: rgba(15, 23, 42, 0.92);">
            <?php renderStandalone2DMap($db, 'public', $checkIn, $checkOut); ?>
        </div>
    </div>
</main>

<?php renderSupportWidget('customer'); ?>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js?v=<?= time() ?>" defer></script>
</body>
</html>
