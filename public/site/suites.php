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

renderHeader('Suites Showcase | Emperor Hotel', ['../assets/css/site/home.css', '../assets/css/site/rooms.css'], 'home-showcase-page rooms-showcase-page');
?>

<nav class="home-nav" aria-label="Primary navigation">
    <div class="home-nav__container">
        <a class="home-nav__logo" href="home.php" aria-label="Emperor Hotel home">
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </a>

        <div class="home-nav__links">
            <a class="home-nav__link" href="home.php">HOME</a>
            <a class="home-nav__link" href="rooms.php">ROOMS</a>
            <a class="home-nav__link home-nav__link--active" href="suites.php">SUITES</a>
        </div>

        <div class="home-nav__auth">
            <?php if ($user): ?>
                <a class="home-nav__cta home-nav__cta--primary" href="<?= e($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php') ?>">DASHBOARD</a>
                <a class="home-nav__cta home-nav__cta--secondary" href="../auth/logout.php" title="Log Out"><i class="bi bi-box-arrow-right d-sm-none"></i><span class="d-none d-sm-inline">LOG OUT</span></a>
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
</main>

<?php renderSupportWidget('customer'); ?>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js" defer></script>
</body>
</html>
