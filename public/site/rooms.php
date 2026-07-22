<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';
require_once __DIR__ . '/../includes/room_showcase.php';

$user = currentUser();
$catalog = roomCatalog();
$rooms = [];
$roomStats = [];
$roomDataUnavailable = false;

try {
    $db = Database::connect();
    $roomModel = new Room($db);
    $rooms = $roomModel->all();
    $roomStats = $roomModel->typeSummary();
} catch (Throwable) {
    $roomDataUnavailable = true;
}

renderHeader('Suites & Rooms | Emperor Hotel', ['../assets/css/site/home.css', '../assets/css/site/rooms.css'], 'home-showcase-page rooms-showcase-page');
?>

<nav class="home-nav" aria-label="Primary navigation">
    <div class="home-nav__container">
        <a class="home-nav__logo" href="home.php" aria-label="Emperor Hotel home">
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </a>

        <div class="home-nav__links">
            <a class="home-nav__link" href="home.php">HOME</a>
            <a class="home-nav__link home-nav__link--active" href="rooms.php">SUITES</a>
            <a class="home-nav__link" href="suites.php"><i class="bi bi-grid-3x3-gap-fill me-1 text-warning"></i>KIOSK</a>
        </div>

        <div class="home-nav__auth">
            <?php if ($user): ?>
                <a class="home-nav__cta home-nav__cta--primary" href="<?= e($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php') ?>">DASHBOARD</a>
                <a class="home-nav__cta home-nav__cta--secondary" href="../auth/logout.php">LOG OUT</a>
            <?php else: ?>
                <a class="home-nav__cta home-nav__cta--primary" href="../auth/login.php">LOG IN</a>
                <a class="home-nav__cta home-nav__cta--secondary" href="../auth/register.php">REGISTER</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main>
    <div class="rooms-flash">
        <?php renderFlashBlock(); ?>
    </div>

    <section class="rooms-hero" aria-label="Emperor Hotel suites page">
        <img src="../assets/images/rooms/hero.jpg" alt="Luxury Suite at Emperor Hotel">
        <div class="rooms-hero__content">
            <h1>SUITES &amp; ROOMS</h1>
            <p>DISCOVER EXTRAORDINARY ACCOMMODATIONS</p>
            <a href="#suite-catalog">EXPLORE SUITES</a>
        </div>
    </section>

    <div id="suite-catalog">
        <?php renderRoomShowcaseSection(); ?>
    </div>

    <section class="container py-5 text-center">
        <h3 class="font-serif text-gold mb-3">Looking for specific room specifications or guest reviews?</h3>
        <p class="text-muted mb-4">Click any suite below to view detailed high-res photos, bed dimensions, and verified guest ratings.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="room-detail.php?id=1" class="btn btn-gold rounded-pill px-4 py-2 fw-bold">Inspect Imperial Deluxe (#101)</a>
            <a href="room-detail.php?id=13" class="btn btn-outline-warning rounded-pill px-4 py-2 fw-bold">Inspect Royal Executive (#201)</a>
            <a href="room-detail.php?id=25" class="btn btn-outline-warning rounded-pill px-4 py-2 fw-bold">Inspect Emperor Presidential (#301)</a>
        </div>
    </section>
</main>

<?php renderSupportWidget('customer'); ?>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js" defer></script>
</body>
</html>
