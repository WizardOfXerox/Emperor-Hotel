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
            <div class="hero-search-bar bg-dark bg-opacity-80 p-3 rounded-4 border border-gold-glow shadow-lg mt-3 text-start" style="max-width: 600px; margin: 0 auto;">
                <form action="rooms.php" method="GET" id="availabilitySearchForm" class="row g-2 align-items-center">
                    <div class="col-6 col-sm-5">
                        <label class="text-xs text-uppercase tracking-wider text-muted mb-1 d-block">Stay Dates</label>
                        <button type="button" class="btn btn-outline-warning btn-sm w-100 rounded-pill text-truncate fw-bold" data-bs-toggle="modal" data-bs-target="#calendarPickerModal">
                            <i class="bi bi-calendar-range me-1"></i>Pick Dates
                        </button>
                        <input type="hidden" name="check_in" value="<?= e($checkIn) ?>">
                        <input type="hidden" name="check_out" value="<?= e($checkOut) ?>">
                    </div>
                    <div class="col-6 col-sm-4">
                        <label class="text-xs text-uppercase tracking-wider text-muted mb-1 d-block">Bed Preference</label>
                        <select name="bed_type" class="form-select form-select-sm bg-dark text-light border-secondary rounded-pill">
                            <option value="">All Bed Types</option>
                            <option value="Queen Bed">Queen Bed</option>
                            <option value="King Bed">King Bed</option>
                            <option value="Super King Master Suite">Super King Suite</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-3 mt-2 mt-sm-0">
                        <label class="d-none d-sm-block text-xs mb-1">&nbsp;</label>
                        <button type="submit" class="btn btn-gold btn-sm w-100 rounded-pill fw-bold py-2">
                            Search
                        </button>
                    </div>
                </form>
            </div>

            <a href="#suites-rooms" class="mt-3 d-inline-block">EXPLORE SUITES</a>
        </div>
    </section>

    <?php renderRoomShowcaseSection(); ?>

    <section class="container py-5">
        <?php renderHotelFloorMap($db, 'public'); ?>
    </section>

    <?php renderCalendarPickerModal($checkIn, $checkOut); ?>
</main>
<?php renderSupportWidget('customer'); ?>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js" defer></script>
</body>
</html>
