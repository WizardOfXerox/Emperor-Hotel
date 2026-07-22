<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';
require_once __DIR__ . '/../includes/calendar_picker.php';

$user = currentUser();
$db = Database::connect();
$roomModel = new Room($db);
$reviewModel = new Review($db);

$roomId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$roomNumber = isset($_GET['room_number']) ? trim((string) $_GET['room_number']) : '';

$room = null;
if ($roomId > 0) {
    $room = $roomModel->find($roomId);
} elseif ($roomNumber !== '') {
    $room = $roomModel->findByNumber($roomNumber);
}

if (!$room) {
    // Default to first room if not specified
    $all = $roomModel->all();
    $room = $all[0] ?? null;
}

if (!$room) {
    setFlash('danger', 'Room not found.');
    redirect('rooms.php');
}

$catalog = roomCatalog();
$roomType = $room['room_type'];
$typeCatalog = $catalog[$roomType] ?? [
    'hero' => '../assets/images/rooms/hero.jpg',
    'carousel' => [],
    'tagline' => 'Luxury accommodation at Emperor Hotel',
    'details' => 'Experience refined luxury and comfort.',
    'included_perks' => ['Complimentary amenities'],
    'features' => ['King bed', 'Wi-Fi', 'Smart TV'],
];

$reviews = $reviewModel->reviewsForRoom((int) $room['room_id'], 10);
$ratingData = $reviewModel->averageRatingForRoom((int) $room['room_id']);

$checkIn = trim((string) ($_GET['check_in'] ?? ''));
$checkOut = trim((string) ($_GET['check_out'] ?? ''));
if ($checkIn === '') $checkIn = (new DateTimeImmutable('today'))->format('Y-m-d');
if ($checkOut === '') $checkOut = (new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');

$isAvailable = $roomModel->find((int)$room['room_id'])['status'] === 'Available';

renderSiteLayoutStart('Room #' . e($room['room_number']) . ' - ' . e($roomType), $user, '', ['../assets/css/site/rooms.css']);
?>

<div class="container py-4">
    <!-- Breadcrumb & Back Link -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a href="rooms.php#suite-catalog" class="btn btn-sm btn-outline-gold rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i>Back to All Suites
        </a>
        <div class="text-muted small">
            <span>Suites</span> / <span class="text-gold"><?= e($roomType) ?></span> / <span>Room #<?= e($room['room_number']) ?></span>
        </div>
    </div>

    <!-- Main Room Detail Row -->
    <div class="row g-4 mb-5">
        <!-- Room Hero & Gallery -->
        <div class="col-lg-7">
            <div class="card bg-dark text-light border-gold-glow rounded-4 overflow-hidden shadow-lg">
                <div class="position-relative">
                    <img src="<?= e($typeCatalog['hero']) ?>" class="card-img-top w-100 object-fit-cover" style="height: 420px;" alt="<?= e($roomType) ?>">
                    <span class="position-absolute top-0 start-0 m-3 badge bg-gold text-dark font-serif fs-6 px-3 py-2 fw-bold rounded-pill">
                        Room #<?= e($room['room_number']) ?>
                    </span>
                    <span class="position-absolute top-0 end-0 m-3 badge <?= $isAvailable ? 'bg-success' : 'bg-warning text-dark' ?> fs-6 px-3 py-2 rounded-pill">
                        <?= e($room['status']) ?>
                    </span>
                </div>
                
                <!-- Room Image Carousel Thumbnails -->
                <?php if (!empty($typeCatalog['carousel'])): ?>
                    <div class="card-body p-3 bg-black bg-opacity-40">
                        <div class="row g-2">
                            <?php foreach ($typeCatalog['carousel'] as $img): ?>
                                <div class="col-4">
                                    <img src="<?= e($img) ?>" class="img-fluid rounded-3 border border-secondary object-fit-cover" style="height: 90px; width: 100%;" alt="Room preview">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Room Specs & Fast Booking Box -->
        <div class="col-lg-5">
            <div class="card bg-dark text-light border-gold-glow rounded-4 p-4 shadow-lg h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <small class="text-gold text-uppercase tracking-wider fw-bold">Floor <?= e($room['floor']) ?></small>
                        <div class="text-warning">
                            <i class="bi bi-star-fill me-1"></i>
                            <span class="fw-bold"><?= number_format($ratingData['avg_rating'], 1) ?></span>
                            <span class="text-muted text-xs">(<?= $ratingData['review_count'] ?> reviews)</span>
                        </div>
                    </div>

                    <h2 class="font-serif text-gold fw-bold mb-2"><?= e($roomType) ?></h2>
                    <p class="text-muted small mb-4"><?= e($typeCatalog['tagline']) ?></p>

                    <!-- Key Spec Grid -->
                    <div class="row row-cols-2 g-3 p-3 bg-black bg-opacity-40 rounded-3 mb-4 border border-secondary">
                        <div class="col">
                            <small class="text-muted text-xs text-uppercase d-block">Bed Type</small>
                            <span class="fw-bold text-light"><i class="bi bi-door-closed text-gold me-1"></i><?= e($room['bed_type'] ?? 'King Bed') ?></span>
                        </div>
                        <div class="col">
                            <small class="text-muted text-xs text-uppercase d-block">Capacity</small>
                            <span class="fw-bold text-light"><i class="bi bi-people text-gold me-1"></i>Up to <?= e($room['max_capacity'] ?? 2) ?> Guests</span>
                        </div>
                        <div class="col">
                            <small class="text-muted text-xs text-uppercase d-block">View Type</small>
                            <span class="fw-bold text-light"><i class="bi bi-eye text-gold me-1"></i><?= e($room['view_type'] ?? 'City View') ?></span>
                        </div>
                        <div class="col">
                            <small class="text-muted text-xs text-uppercase d-block">Nightly Rate</small>
                            <span class="fw-bold text-gold fs-5">₱<?= number_format((float)$room['price_per_night'], 2) ?></span>
                        </div>
                    </div>

                    <!-- Included Perks -->
                    <div class="mb-4">
                        <h6 class="text-gold font-serif mb-2"><i class="bi bi-gift-fill me-2"></i>Included Perks</h6>
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach ($typeCatalog['included_perks'] as $perk): ?>
                                <li class="text-muted mb-1"><i class="bi bi-check-circle-fill text-gold me-2"></i><?= e($perk) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Express Booking CTA -->
                <div class="border-top border-secondary pt-3 mt-3">
                    <?php if ($isAvailable): ?>
                        <a href="../user/dashboard.php?selected_room=<?= (int)$room['room_id'] ?>&check_in=<?= e($checkIn) ?>&check_out=<?= e($checkOut) ?>" 
                           class="btn btn-gold w-100 rounded-pill py-3 font-serif fw-bold fs-6 shadow">
                            <i class="bi bi-lightning-charge-fill me-2"></i>Reserve Room #<?= e($room['room_number']) ?> Now
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 rounded-pill py-3 fw-bold disabled" disabled>
                            Currently <?= e($room['status']) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Suite Architectural Details & Features -->
    <div class="row g-4 mb-5">
        <div class="col-md-8">
            <div class="card bg-dark text-light border-gold-glow rounded-4 p-4 shadow">
                <h4 class="font-serif text-gold fw-bold mb-3"><i class="bi bi-file-text me-2"></i>Suite Overview</h4>
                <p class="text-muted leading-relaxed mb-4"><?= e($typeCatalog['details']) ?></p>

                <h5 class="font-serif text-gold fw-bold mb-3"><i class="bi bi-sliders me-2"></i>Room Amenities & Features</h5>
                <div class="row row-cols-1 row-cols-sm-2 g-3">
                    <?php foreach ($typeCatalog['features'] as $feature): ?>
                        <div class="col">
                            <div class="p-3 bg-black bg-opacity-40 rounded-3 border border-secondary d-flex align-items-center">
                                <i class="bi bi-check2-square text-gold fs-5 me-3"></i>
                                <span class="small font-semibold text-light"><?= e($feature) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-dark text-light border-gold-glow rounded-4 p-4 shadow">
                <h5 class="font-serif text-gold fw-bold mb-3"><i class="bi bi-shield-check me-2"></i>Hotel Guarantees</h5>
                <ul class="list-unstyled text-muted small mb-0">
                    <li class="mb-3 d-flex"><i class="bi bi-clock-history text-gold fs-5 me-2"></i><span><strong>24/7 Front Desk:</strong> Concierge support always available.</span></li>
                    <li class="mb-3 d-flex"><i class="bi bi-wifi text-gold fs-5 me-2"></i><span><strong>Free High-Speed Wi-Fi:</strong> Unlimited priority fiber connection.</span></li>
                    <li class="mb-3 d-flex"><i class="bi bi-arrow-repeat text-gold fs-5 me-2"></i><span><strong>Easy Rescheduling:</strong> Flexible date updates up to 24 hours prior.</span></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Verified Guest Reviews Section -->
    <div class="card bg-dark text-light border-gold-glow rounded-4 p-4 shadow">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="font-serif text-gold fw-bold m-0"><i class="bi bi-chat-square-heart me-2"></i>Verified Guest Reviews</h4>
            <div class="badge bg-gold text-dark fs-6 px-3 py-2 fw-bold rounded-pill">
                ★ <?= number_format($ratingData['avg_rating'], 1) ?> / 5.0 (<?= $ratingData['review_count'] ?> Reviews)
            </div>
        </div>

        <?php if (empty($reviews)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-chat-quote fs-1 text-gold opacity-50 d-block mb-2"></i>
                <p class="m-0">No reviews yet for Room #<?= e($room['room_number']) ?>. Be the first guest to stay and leave a review!</p>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php foreach ($reviews as $rev): ?>
                    <div class="col">
                        <div class="p-3 bg-black bg-opacity-40 rounded-3 border border-secondary h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold text-gold"><?= e($rev['full_name']) ?></span>
                                <div class="text-warning small">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="bi bi-star-<?= $s <= (int)$rev['rating'] ? 'fill' : 'blank' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p class="text-muted small mb-2">"<?= e($rev['comment'] ?: 'Great stay!') ?>"</p>
                            <small class="text-muted text-xs d-block text-end"><?= date('M d, Y', strtotime($rev['created_at'])) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
renderSiteLayoutEnd();
