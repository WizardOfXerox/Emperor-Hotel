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
            <a class="home-nav__link" href="rooms.php">ROOMS</a>
            <a class="home-nav__link" href="suites.php">SUITES</a>
        </div>

        <div class="home-nav__auth">
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

<main>
    <div class="home-flash">
        <?php renderFlashBlock(); ?>
    </div>

    <section class="home-hero position-relative" aria-label="Emperor Hotel homepage">
        <img src="../assets/images/home/hero.jpg" alt="Exterior view of Emperor Hotel at sunset">
        <div class="home-hero__content">
            <h1>EMPEROR'S HOTEL</h1>
            <p>SMART LUXURY & UNMATCHED ELEGANCE</p>
            
            <a href="#calendar-search" class="btn rounded-pill px-4 py-2 fw-semibold font-serif shadow text-sm text-uppercase tracking-wider mt-3" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; border: none; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);"><i class="bi bi-calendar-range me-2"></i>Select Stay Dates</a>
        </div>
    </section>

    <!-- Side-by-Side Interactive Calendar & Live Floor Map Section -->
    <section class="container-fluid px-lg-4 px-xl-5 py-5" id="calendar-search">
        <div class="row g-4 align-items-stretch">
            <div class="col-12 col-xl-5">
                <?php renderInlineCalendarWidget($checkIn, $checkOut); ?>
            </div>
            <div class="col-12 col-xl-7">
                <?php renderHotelFloorMap($db, 'public', null, $checkIn, $checkOut); ?>
            </div>
        </div>
    </section>

    <?php renderCalendarPickerModal($checkIn, $checkOut); ?>
</main>
<?php renderSupportWidget('customer'); ?>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js" defer></script>
</body>
</html>
