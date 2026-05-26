<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';

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

renderSiteLayoutStart('Emperor Hotel | Suites and Rooms', $user);
?>
<section class="rooms-page-hero" style="background-image: linear-gradient(180deg, rgba(2, 6, 23, 0.24), rgba(2, 6, 23, 0.85)), url('../assets/images/rooms/hero.jpg');">
    <div class="rooms-page-hero__content">
        <p class="eyebrow">Suites & Rooms</p>
        <h1 class="site-hero__title">Explore the three signature room types of Emperor Hotel.</h1>
        <p class="site-hero__text">
            Browse each suite through its own image carousel, highlights, and stay details before choosing your room.
        </p>
    </div>
</section>

<?php if ($roomDataUnavailable): ?>
    <section class="site-section site-section--notice">
        <p class="mb-0">Live room availability is temporarily unavailable. The suite gallery and room details are still available below.</p>
    </section>
<?php endif; ?>

<section class="site-section">
    <?php $index = 0; ?>
    <?php foreach ($catalog as $roomType => $roomInfo): ?>
        <?php
            $carouselId = 'roomCarousel' . $index;
            $stats = $roomStats[$roomType] ?? [
                'available' => 0,
                'total' => 0,
                'lowest_price' => 0.0,
            ];
        ?>
        <article class="room-detail-block <?php echo $index % 2 === 1 ? 'room-detail-block--reverse' : ''; ?>">
            <div class="room-carousel-shell">
                <div id="<?php echo e($carouselId); ?>" class="carousel slide carousel-fade room-carousel" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <?php foreach ($roomInfo['carousel'] as $slideIndex => $_): ?>
                            <button
                                type="button"
                                data-bs-target="#<?php echo e($carouselId); ?>"
                                data-bs-slide-to="<?php echo e($slideIndex); ?>"
                                class="<?php echo $slideIndex === 0 ? 'active' : ''; ?>"
                                aria-current="<?php echo $slideIndex === 0 ? 'true' : 'false'; ?>"
                                aria-label="Slide <?php echo e($slideIndex + 1); ?>"
                            ></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="carousel-inner">
                        <?php foreach ($roomInfo['carousel'] as $slideIndex => $imagePath): ?>
                            <div class="carousel-item <?php echo $slideIndex === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo e($imagePath); ?>" class="d-block w-100" alt="<?php echo e($roomType . ' image ' . ($slideIndex + 1)); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo e($carouselId); ?>" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#<?php echo e($carouselId); ?>" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>

            <div class="room-detail-copy">
                <p class="eyebrow mb-2"><?php echo e($roomType); ?></p>
                <h2 class="section-title mb-3"><?php echo e($roomInfo['tagline']); ?></h2>
                <p class="muted-copy mb-3"><?php echo e($roomInfo['details']); ?></p>

                <div class="room-detail-meta">
                    <?php if ($roomDataUnavailable): ?>
                        <span class="badge-soft">Live pricing unavailable</span>
                    <?php elseif ($stats['total'] > 0): ?>
                        <span class="badge-soft"><?php echo e($stats['available']); ?> available</span>
                        <span class="badge-soft"><?php echo e($stats['total']); ?> total rooms</span>
                        <span class="badge-soft">Starts at <?php echo e(formatMoney((float) $stats['lowest_price'])); ?></span>
                    <?php else: ?>
                        <span class="badge-soft">No room records yet</span>
                    <?php endif; ?>
                </div>

                <div class="room-detail-info-grid">
                    <div class="detail-info-card">
                        <h3>Ideal For</h3>
                        <p class="muted-copy mb-0"><?php echo e($roomInfo['ideal_for']); ?></p>
                    </div>
                    <div class="detail-info-card">
                        <h3>Room Highlights</h3>
                        <ul class="detail-list">
                            <?php foreach ($roomInfo['features'] as $feature): ?>
                                <li><?php echo e($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <?php if ($user): ?>
                        <a class="btn btn-warning fw-semibold" href="<?php echo e($user['role'] === 'admin' ? '../admin/reservations.php' : '../user/dashboard.php'); ?>">Book This Room</a>
                    <?php else: ?>
                        <a class="btn btn-warning fw-semibold" href="../auth/login.php">Log In to Book</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-light" href="home.php">Back to Home</a>
                </div>
            </div>
        </article>
        <?php $index++; ?>
    <?php endforeach; ?>
</section>

<?php if ($rooms): ?>
    <section class="site-section site-section--soft">
        <div class="section-head">
            <div>
                <p class="eyebrow mb-2">Live Inventory</p>
                <h2 class="section-title mb-2">Current room records in the system</h2>
                <p class="muted-copy mb-0">This section reflects the actual room entries stored in the database.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-dark-soft align-middle mb-0">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Type</th>
                        <th>Floor</th>
                        <th>Capacity</th>
                        <th>Rate</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td><?php echo e($room['room_number']); ?></td>
                            <td><?php echo e($room['room_type']); ?></td>
                            <td><?php echo e($room['floor']); ?></td>
                            <td><?php echo e($room['capacity_adults']); ?> adults / <?php echo e($room['capacity_children']); ?> children</td>
                            <td><?php echo e(formatMoney((float) $room['price_per_night'])); ?></td>
                            <td><span class="badge-soft"><?php echo e($room['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php renderSiteLayoutEnd(); ?>
