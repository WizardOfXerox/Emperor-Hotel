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

renderHeader('Home | Emperor Hotel', ['../assets/css/site/home.css', '../assets/css/site/rooms.css'], 'home-showcase-page');
?>
<nav class="home-nav" aria-label="Primary navigation">
    <div class="home-nav__container">
        <a class="home-nav__logo" href="home.php" aria-label="Emperor Hotel home">
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </a>

        <div class="home-nav__links">
            <a class="home-nav__link home-nav__link--active" href="home.php">HOME</a>
            <a class="home-nav__link" href="#suites-rooms">SUITES &amp; ROOM</a>
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

    <section class="home-hero" aria-label="Emperor Hotel homepage">
        <img src="../assets/images/home/hero.jpg" alt="Exterior view of Emperor Hotel at sunset">
        <div class="home-hero__content">
            <h1>EMPEROR'S HOTEL</h1>
            <p>HOME SECTION</p>
            <a href="#suites-rooms">SUITES &amp; ROOM</a>
        </div>
    </section>

    <?php renderRoomShowcaseSection(); ?>
</main>
<?php renderSupportWidget('customer'); ?>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js" defer></script>
</body>
</html>
