<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_showcase.php';

$user = currentUser();
$dashboardHref = '../auth/login.php';
$dashboardLabel = 'LOG IN';

if ($user) {
    $dashboardHref = $user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php';
    $dashboardLabel = 'DASHBOARD';
}

require_once __DIR__ . '/../includes/hotel_map.php';
require_once __DIR__ . '/../includes/calendar_picker.php';

$db = Database::connect();
$today = new DateTimeImmutable('today');
$checkIn = (string) ($_GET['check_in'] ?? $today->format('Y-m-d'));
$checkOut = (string) ($_GET['check_out'] ?? $today->modify('+1 day')->format('Y-m-d'));

renderHeader('Home | Emperor Hotel', ['../assets/css/site/home.css', '../assets/css/site/rooms.css'], 'home-showcase-page');
?>
<nav class="home-nav" aria-label="Primary navigation">
    <div class="home-nav__container">
        <a class="home-nav__logo" href="home.php" aria-label="Emperor Hotel home">
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </a>

        <div class="home-nav__links">
            <a class="home-nav__link home-nav__link--active" href="home.php">HOME</a>
            <a class="home-nav__link text-gold" href="rooms.php">SUITES</a>
        </div>

        <div class="home-nav__auth">
            <?php if ($user): ?>
                <a class="home-nav__cta home-nav__cta--primary" href="<?php echo e($dashboardHref); ?>"><?php echo e($dashboardLabel); ?></a>
                <a class="home-nav__cta home-nav__cta--secondary" href="../auth/logout.php">LOG OUT</a>
            <?php else: ?>
                <a class="home-nav__cta home-nav__cta--primary" href="../auth/login.php">LOG IN</a>
                <a class="home-nav__cta home-nav__cta--secondary" href="../auth/register.php">REGISTER</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main>
    <div class="home-flash">
        <?php renderFlashBlock(); ?>
    </div>

    <section class="home-hero position-relative" aria-label="Emperor Hotel homepage">
        <img src="../assets/images/home/hero.jpg" alt="Exterior view of Emperor Hotel at sunset">
        <div class="home-hero__content">
            <h1>EMPEROR'S HOTEL</h1>
            <p>SMART LUXURY & UNMATCHED ELEGANCE</p>
            
            <!-- Quick Date Search Bar -->
            <div class="hero-search-bar shadow-lg mt-4 text-start" style="max-width: 840px; width: 100%; margin: 0 auto; background: rgba(7, 10, 16, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(212, 175, 55, 0.4); border-radius: 50px; padding: 16px 28px;">
                <form action="rooms.php" method="GET" id="availabilitySearchForm" class="row g-3 align-items-center m-0">
                    <div class="col-12 col-md-5 p-0 pe-md-2">
                        <label class="text-xs text-uppercase tracking-wider text-muted mb-1 d-block fw-bold"><i class="bi bi-calendar-event text-gold me-1"></i>Stay Dates</label>
                        <button type="button" class="btn btn-outline-warning btn-sm w-100 rounded-pill py-2 px-3 text-truncate fw-bold text-start" data-bs-toggle="modal" data-bs-target="#calendarPickerModal">
                            <i class="bi bi-calendar-range me-1"></i><?= date('M d', strtotime($checkIn)) ?> – <?= date('M d', strtotime($checkOut)) ?> (1 Night)
                        </button>
                        <input type="hidden" name="check_in" value="<?= e($checkIn) ?>">
                        <input type="hidden" name="check_out" value="<?= e($checkOut) ?>">
                    </div>
                    <div class="col-12 col-md-4 p-0 px-md-2 mt-2 mt-md-0">
                        <label class="text-xs text-uppercase tracking-wider text-muted mb-1 d-block fw-bold"><i class="bi bi-door-open text-gold me-1"></i>Bed Preference</label>
                        <select name="bed_type" class="form-select form-select-sm bg-dark text-light border-secondary rounded-pill py-2 px-3">
                            <option value="">All Bed Sizes</option>
                            <option value="Queen Bed">Queen Bed</option>
                            <option value="King Bed">King Bed</option>
                            <option value="Super King Master Suite">Super King Suite</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 p-0 ps-md-2 mt-3 mt-md-0">
                        <label class="d-none d-md-block text-xs mb-1">&nbsp;</label>
                        <button type="submit" class="btn btn-gold btn-sm w-100 rounded-pill fw-bold py-2 shadow">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                    </div>
                </form>
            </div>

            <a href="#calendar-search" class="btn btn-gold rounded-pill px-4 py-2 fw-bold font-serif shadow mt-3"><i class="bi bi-calendar-range me-2"></i>SELECT STAY DATES</a>
        </div>
    </section>

    <!-- Featured Interactive Big Calendar Section -->
    <section class="container py-5" id="calendar-search">
        <?php renderInlineCalendarWidget($checkIn, $checkOut); ?>
    </section>

    <section class="container py-4 mb-5">
        <?php renderHotelFloorMap($db, 'public'); ?>
    </section>

    <?php renderCalendarPickerModal($checkIn, $checkOut); ?>
</main>
<?php renderSupportWidget('customer'); ?>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js" defer></script>
</body>
</html>
