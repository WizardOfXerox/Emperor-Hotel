<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';

$user = currentUser();
$catalog = roomCatalog();
$roomStats = [];
$roomDataUnavailable = false;

try {
    $roomModel = new Room(Database::connect());
    $roomStats = $roomModel->typeSummary();
} catch (Throwable) {
    $roomDataUnavailable = true;
}

renderSiteLayoutStart('Emperor Hotel | Home', $user);
?>
<section class="site-hero" style="background-image: linear-gradient(180deg, rgba(2, 6, 23, 0.2), rgba(2, 6, 23, 0.78)), url('../assets/images/home/hero.jpg');">
    <div class="site-hero__content">
        <p class="eyebrow">Welcome to Emperor Hotel</p>
        <h1 class="site-hero__title">A refined stay shaped by comfort, quiet luxury, and memorable suites.</h1>
        <p class="site-hero__text">
            Step into polished spaces, warm service, and suite experiences designed for business stays, celebrations, and quiet escapes.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-warning btn-lg fw-semibold" href="rooms.php">Suites & Rooms</a>
            <?php if ($user): ?>
                <a class="btn btn-outline-light btn-lg" href="<?php echo e($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php'); ?>">Open Dashboard</a>
            <?php else: ?>
                <a class="btn btn-outline-light btn-lg" href="../auth/login.php">Log In</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="site-section">
    <div class="section-head">
        <div>
            <p class="eyebrow mb-2">Signature Stays</p>
            <h2 class="section-title mb-2">Three room types, each with a different stay experience.</h2>
            <p class="muted-copy mb-0">Choose the suite style that matches your trip, your comfort level, and your occasion.</p>
        </div>
        <a class="btn btn-outline-light" href="rooms.php">View Full Room Details</a>
    </div>

    <div class="room-preview-grid">
        <?php foreach ($catalog as $roomType => $roomInfo): ?>
            <?php $stats = $roomStats[$roomType] ?? null; ?>
            <article class="preview-room-card">
                <img src="<?php echo e($roomInfo['hero']); ?>" alt="<?php echo e($roomType); ?>">
                <div class="preview-room-card__body">
                    <p class="eyebrow mb-2"><?php echo e($roomType); ?></p>
                    <h3><?php echo e($roomInfo['tagline']); ?></h3>
                    <p class="muted-copy"><?php echo e($roomInfo['details']); ?></p>
                    <div class="preview-room-card__meta">
                        <?php if ($stats && $stats['total'] > 0): ?>
                            <span><?php echo e($stats['total']); ?> rooms</span>
                            <span>Starts at <?php echo e(formatMoney((float) $stats['lowest_price'])); ?></span>
                        <?php elseif ($roomDataUnavailable): ?>
                            <span>Live pricing unavailable</span>
                        <?php else: ?>
                            <span>No room records yet</span>
                        <?php endif; ?>
                        <span>Ideal for: <?php echo e($roomInfo['ideal_for']); ?></span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="site-section site-section--soft">
    <div class="feature-grid">
        <article class="feature-tile">
            <p class="eyebrow">Arrival</p>
            <h3>Calm check-in experience</h3>
            <p class="muted-copy mb-0">A front desk flow built around clear reservations, guest details, and room readiness.</p>
        </article>
        <article class="feature-tile">
            <p class="eyebrow">Comfort</p>
            <h3>Rooms with purpose</h3>
            <p class="muted-copy mb-0">Each room type has a distinct balance of space, privacy, work comfort, and luxury finish.</p>
        </article>
        <article class="feature-tile">
            <p class="eyebrow">Service</p>
            <h3>Details handled neatly</h3>
            <p class="muted-copy mb-0">Reservations, payments, and guest stays are organized so every visit feels intentional.</p>
        </article>
    </div>
</section>
<?php renderSiteLayoutEnd(); ?>
