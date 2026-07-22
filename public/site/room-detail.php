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

$allRooms = $roomModel->all();
if (!$room) {
    $room = $allRooms[0] ?? null;
}

if (!$room) {
    setFlash('danger', 'Room not found.');
    redirect('rooms.php');
}

$currentIdx = 0;
foreach ($allRooms as $idx => $r) {
    if ((int)$r['room_id'] === (int)$room['room_id']) {
        $currentIdx = $idx;
        break;
    }
}

$totalRoomCount = count($allRooms);
$prevIdx = ($currentIdx - 1 + $totalRoomCount) % max(1, $totalRoomCount);
$nextIdx = ($currentIdx + 1) % max(1, $totalRoomCount);

$prevRoom = $allRooms[$prevIdx] ?? null;
$nextRoom = $allRooms[$nextIdx] ?? null;

$floorsGrouped = [];
foreach ($allRooms as $r) {
    $fl = (int) ($r['floor'] ?? (substr((string)$r['room_number'], 0, 1) ?: 1));
    $floorsGrouped[$fl][] = $r;
}
ksort($floorsGrouped);

$catalog = roomCatalog();
$roomType = $room['room_type'];
$typeCatalog = $catalog[$roomType] ?? [
    'hero' => '../assets/images/rooms/hero.jpg',
    'carousel' => [],
    'tagline' => 'Where smart comfort meets timeless elegance.',
    'details' => 'Experience refined luxury and unmatched comfort crafted for guests who appreciate warm luxury styling and efficient space.',
    'included_perks' => ['Complimentary breakfast set', 'High-speed priority Wi-Fi'],
    'features' => ['Plush luxury linen', 'Ergonomic work desk', 'Modern rainfall shower', 'Smart TV & climate control'],
];

$reviews = $reviewModel->reviewsForRoom((int) $room['room_id'], 10);
$ratingData = $reviewModel->averageRatingForRoom((int) $room['room_id']);

$checkIn = trim((string) ($_GET['check_in'] ?? ''));
$checkOut = trim((string) ($_GET['check_out'] ?? ''));
if ($checkIn === '') $checkIn = (new DateTimeImmutable('today'))->format('Y-m-d');
if ($checkOut === '') $checkOut = (new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');

$isAvailable = $room['status'] === 'Available';

$inD = DateTimeImmutable::createFromFormat('!Y-m-d', $checkIn) ?: new DateTimeImmutable('today');
$outD = DateTimeImmutable::createFromFormat('!Y-m-d', $checkOut) ?: (new DateTimeImmutable('today'))->modify('+1 day');
$nights = max(1, (int) round(($outD->getTimestamp() - $inD->getTimestamp()) / 86400));
$totalStayPrice = (float)$room['price_per_night'] * $nights;

$dateParams = '';
if (!empty($checkIn) && !empty($checkOut)) {
    $dateParams = '&check_in=' . urlencode($checkIn) . '&check_out=' . urlencode($checkOut);
}

renderHeader('Room #' . e($room['room_number']) . ' - ' . e($roomType), ['../assets/css/site/home.css', '../assets/css/site/rooms.css'], 'home-showcase-page rooms-showcase-page');
?>

<nav class="home-nav" aria-label="Primary navigation">
    <div class="home-nav__container">
        <a class="home-nav__logo" href="home.php" aria-label="Emperor Hotel home">
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </a>

        <div class="home-nav__links">
            <a class="home-nav__link" href="home.php">HOME</a>
            <a class="home-nav__link home-nav__link--active" href="rooms.php">SUITES</a>
        </div>

        <div class="home-nav__auth">
            <?php if ($user): ?>
                <a class="home-nav__cta home-nav__cta--primary" href="<?= e($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php') ?>">MY DASHBOARD</a>
                <a class="home-nav__cta home-nav__cta--secondary" href="../auth/logout.php">LOG OUT</a>
            <?php else: ?>
                <a class="home-nav__cta home-nav__cta--primary" href="../auth/login.php">LOG IN</a>
                <a class="home-nav__cta home-nav__cta--secondary" href="../auth/register.php">REGISTER</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="py-5" style="background: #070A10; min-height: 100vh;">
    <div class="container py-3">
        <!-- Navigation & Room Switcher Controls -->
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3 p-3 rounded-4" style="background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(212, 175, 55, 0.35);">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="rooms.php#suite-catalog" class="btn btn-sm rounded-pill px-3 py-2 font-serif fw-bold shadow text-uppercase tracking-wider" style="background: rgba(30, 41, 59, 0.9); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.45);">
                    <i class="bi bi-arrow-left me-1"></i>Back to Catalog
                </a>

                <!-- Floors > Rooms Dropdown Menu -->
                <div class="dropdown">
                    <button class="btn btn-sm rounded-pill px-3 py-2 font-serif fw-bold dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(30, 41, 59, 0.9); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.45);">
                        <i class="bi bi-layers-fill me-1"></i>Floors &gt; Room #<?= e($room['room_number']) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark shadow-lg rounded-4 p-2" style="background: rgba(15, 23, 42, 0.98); border: 1px solid rgba(212, 175, 55, 0.4); max-height: 380px; overflow-y: auto; min-width: 260px;">
                        <?php foreach ($floorsGrouped as $flNum => $fRooms): ?>
                            <li><h6 class="dropdown-header text-uppercase tracking-wider font-serif fw-bold" style="color: #FBBF24;"><i class="bi bi-building me-1"></i>Floor <?= $flNum ?> (<?= count($fRooms) ?> Rooms)</h6></li>
                            <?php foreach ($fRooms as $fRoom): 
                                $isCurrent = (int)$fRoom['room_id'] === (int)$room['room_id'];
                                $fBadgeStyle = match ($fRoom['status']) {
                                    'Available' => 'background: rgba(16, 185, 129, 0.35); color: #A7F3D0;',
                                    'Reserved' => 'background: rgba(59, 130, 246, 0.35); color: #BFDBFE;',
                                    'Occupied' => 'background: rgba(245, 158, 11, 0.35); color: #FDE68A;',
                                    'Cleaning' => 'background: rgba(168, 85, 247, 0.35); color: #DDD6FE;',
                                    default => 'background: rgba(148, 163, 184, 0.3); color: #F1F5F9;',
                                };
                            ?>
                                <li>
                                    <a class="dropdown-item rounded-3 py-2 px-3 d-flex align-items-center justify-content-between text-xs mb-1 <?= $isCurrent ? 'active fw-bold' : '' ?>" 
                                       href="room-detail.php?id=<?= (int)$fRoom['room_id'] ?><?= $dateParams ?>"
                                       style="<?= $isCurrent ? 'background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10;' : 'color: #F8FAFC;' ?>">
                                        <span><i class="bi bi-door-closed me-2"></i>Room #<?= e($fRoom['room_number']) ?> &mdash; <?= e($fRoom['room_type']) ?></span>
                                        <span class="badge text-xs px-2 py-1 ms-2 rounded-pill fw-bold" style="<?= $fBadgeStyle ?>"><?= $fRoom['status'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($flNum < max(array_keys($floorsGrouped))): ?>
                                <li><hr class="dropdown-divider border-secondary opacity-50 my-1"></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if ($prevRoom): ?>
                    <a href="room-detail.php?id=<?= (int)$prevRoom['room_id'] ?><?= $dateParams ?>" class="btn btn-sm rounded-pill px-3 py-2 font-serif fw-semibold text-light shadow-sm" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.3);" title="Go to Room #<?= e($prevRoom['room_number']) ?>">
                        <i class="bi bi-chevron-left me-1" style="color: #FFDF73;"></i>Prev: Room #<?= e($prevRoom['room_number']) ?>
                    </a>
                <?php endif; ?>
                <?php if ($nextRoom): ?>
                    <a href="room-detail.php?id=<?= (int)$nextRoom['room_id'] ?><?= $dateParams ?>" class="btn btn-sm rounded-pill px-3 py-2 font-serif fw-semibold text-light shadow-sm" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.3);" title="Go to Room #<?= e($nextRoom['room_number']) ?>">
                        Next: Room #<?= e($nextRoom['room_number']) ?><i class="bi bi-chevron-right ms-1" style="color: #FFDF73;"></i>
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="text-light opacity-90 small fw-semibold font-serif">
                <a href="home.php" class="text-decoration-none text-light opacity-75">Home</a> / 
                <a href="rooms.php" class="text-decoration-none text-light opacity-75">Suites</a> / 
                <span style="color: #FFDF73;"><?= e($roomType) ?></span> / 
                <span class="text-white fw-bold">Room #<?= e($room['room_number']) ?></span>
            </div>
        </div>

        <!-- Main Room Showcase Row -->
        <div class="row g-4 mb-5">
            <!-- Room Hero & Gallery -->
            <div class="col-12 col-lg-7">
                <div class="card rounded-4 overflow-hidden shadow-lg border" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
                    <div class="position-relative">
                        <img src="<?= e($typeCatalog['hero']) ?>" id="mainRoomHeroImage" class="card-img-top w-100 object-fit-cover transition-all" style="min-height: 260px; height: 45vh; max-height: 440px;" alt="<?= e($roomType) ?>">
                        
                        <span class="position-absolute top-0 start-0 m-3 badge font-serif fs-6 px-3 py-2 fw-bold rounded-pill shadow" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.5);">
                            <i class="bi bi-door-open me-1"></i>Room #<?= e($room['room_number']) ?>
                        </span>
                        
                        <?php
                        $badgeBg = match ($room['status']) {
                            'Available' => 'background: rgba(16, 185, 129, 0.9); border: 1.5px solid #10B981; color: #FFFFFF;',
                            'Reserved' => 'background: rgba(59, 130, 246, 0.9); border: 1.5px solid #3B82F6; color: #FFFFFF;',
                            'Occupied' => 'background: rgba(245, 158, 11, 0.9); border: 1.5px solid #F59E0B; color: #FFFFFF;',
                            'Cleaning' => 'background: rgba(168, 85, 247, 0.9); border: 1.5px solid #A855F7; color: #FFFFFF;',
                            default => 'background: rgba(244, 63, 94, 0.9); border: 1.5px solid #F43F5E; color: #FFFFFF;',
                        };
                        ?>
                        <span class="position-absolute top-0 end-0 m-3 badge fs-6 px-4 py-2 rounded-pill shadow-lg fw-bold" style="<?= $badgeBg ?>">
                            <i class="bi bi-circle-fill me-1 fs-6"></i><?= e($room['status']) ?>
                        </span>
                    </div>
                    
                    <!-- Room Image Gallery Thumbnails -->
                    <?php if (!empty($typeCatalog['carousel'])): ?>
                        <div class="card-body p-3" style="background: rgba(7, 10, 16, 0.6); border-top: 1px solid rgba(212, 175, 55, 0.3);">
                            <div class="row g-2">
                                <div class="col-3">
                                    <img src="<?= e($typeCatalog['hero']) ?>" class="img-fluid rounded-3 border object-fit-cover gallery-thumb active" style="height: 85px; width: 100%; cursor: pointer; border-color: #D4AF37 !important;" onclick="switchHeroImage(this.src)" alt="Main view">
                                </div>
                                <?php foreach ($typeCatalog['carousel'] as $img): ?>
                                    <div class="col-3">
                                        <img src="<?= e($img) ?>" class="img-fluid rounded-3 border object-fit-cover gallery-thumb" style="height: 85px; width: 100%; cursor: pointer; border-color: rgba(212, 175, 55, 0.3) !important;" onclick="switchHeroImage(this.src)" alt="Suite photo">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Room Specs & Fast Booking Box -->
            <div class="col-12 col-lg-5">
                <div class="card rounded-4 p-4 shadow-lg h-100 d-flex flex-column justify-content-between border" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom border-secondary">
                            <span class="badge px-3 py-1 rounded-pill font-serif fw-bold" style="background: rgba(212, 175, 55, 0.2); border: 1px solid rgba(212, 175, 55, 0.5); color: #FFDF73;">
                                <i class="bi bi-layers me-1"></i>Floor <?= e($room['floor']) ?>
                            </span>
                            <div style="color: #FBBF24;" class="fw-bold fs-6">
                                <i class="bi bi-star-fill me-1"></i><?= number_format($ratingData['avg_rating'], 1) ?> <span class="text-light opacity-75 text-xs font-normal">(<?= $ratingData['review_count'] ?> Guest Reviews)</span>
                            </div>
                        </div>

                        <h2 class="font-serif fw-bold mb-1" style="color: #FFDF73; text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);"><?= e($roomType) ?></h2>
                        <p class="text-light opacity-90 small mb-4 fw-semibold"><?= e($typeCatalog['tagline']) ?></p>

                        <!-- Key Spec Grid -->
                        <div class="row row-cols-2 g-3 p-3 rounded-4 mb-4 border" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
                            <div class="col">
                                <small class="text-light opacity-75 text-xs text-uppercase d-block fw-bold tracking-wider mb-1">Bed Configuration</small>
                                <span class="fw-bold text-white"><i class="bi bi-door-closed me-1" style="color: #FFDF73;"></i><?= e($room['bed_type'] ?? 'King Bed') ?></span>
                            </div>
                            <div class="col">
                                <small class="text-light opacity-75 text-xs text-uppercase d-block fw-bold tracking-wider mb-1">Capacity</small>
                                <span class="fw-bold text-white"><i class="bi bi-people me-1" style="color: #FFDF73;"></i>Up to <?= e($room['max_capacity'] ?? 2) ?> Guests</span>
                            </div>
                            <div class="col">
                                <small class="text-light opacity-75 text-xs text-uppercase d-block fw-bold tracking-wider mb-1">View Aspect</small>
                                <span class="fw-bold text-white"><i class="bi bi-eye me-1" style="color: #FFDF73;"></i><?= e($room['view_type'] ?? 'City View') ?></span>
                            </div>
                            <div class="col">
                                <small class="text-light opacity-75 text-xs text-uppercase d-block fw-bold tracking-wider mb-1">Nightly Rate</small>
                                <span class="fw-bold fs-5" style="color: #FBBF24;">₱<?= number_format((float)$room['price_per_night']) ?><span class="fs-6 text-light opacity-75 font-normal">/night</span></span>
                            </div>
                        </div>

                        <!-- Selected Stay Dates Preview -->
                        <div class="p-3 rounded-3 mb-4 border" style="background: rgba(7, 10, 16, 0.6); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <small class="text-light opacity-75 text-xs text-uppercase d-block fw-bold tracking-wider mb-1"><i class="bi bi-calendar-range text-warning me-1"></i>Selected Stay Dates</small>
                                    <div class="fw-bold text-white font-serif"><?= date('M d, Y', strtotime($checkIn)) ?> – <?= date('M d, Y', strtotime($checkOut)) ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge rounded-pill px-3 py-2 fw-bold" style="background: rgba(212, 175, 55, 0.25); color: #FFDF73; border: 1px solid #D4AF37;"><?= $nights ?> Night<?= $nights > 1 ? 's' : '' ?></span>
                                    <div class="text-xs fw-bold mt-1" style="color: #FBBF24;">Total: ₱<?= number_format($totalStayPrice) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Included Perks -->
                        <div class="mb-4">
                            <h6 class="font-serif mb-2 fw-bold" style="color: #FFDF73;"><i class="bi bi-gift-fill me-2"></i>Included Executive Perks</h6>
                            <ul class="list-unstyled mb-0 small">
                                <?php foreach ($typeCatalog['included_perks'] as $perk): ?>
                                    <li class="text-light opacity-90 mb-2 d-flex align-items-center fw-semibold">
                                        <i class="bi bi-check-circle-fill me-2 fs-6" style="color: #FFDF73;"></i><?= e($perk) ?>
                                    </li>
                                <?php endforeach; ?>
                                <li class="text-light opacity-90 mb-1 d-flex align-items-center fw-semibold">
                                    <i class="bi bi-check-circle-fill me-2 fs-6" style="color: #FFDF73;"></i>Nespresso Espresso Machine & Premium Teas
                                </li>
                                <li class="text-light opacity-90 mb-1 d-flex align-items-center fw-semibold">
                                    <i class="bi bi-check-circle-fill me-2 fs-6" style="color: #FFDF73;"></i>Complimentary High-Speed Priority Fiber Wi-Fi
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Express Booking CTA -->
                    <div class="pt-3 border-top border-secondary">
                        <?php if ($isAvailable): ?>
                            <a href="../user/dashboard.php?selected_room=<?= (int)$room['room_id'] ?>&check_in=<?= e($checkIn) ?>&check_out=<?= e($checkOut) ?>" 
                               class="btn w-100 rounded-pill py-3 font-serif fw-bold fs-6 shadow text-uppercase tracking-wider" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; border: none; box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);">
                                <i class="bi bi-lightning-charge-fill me-2"></i>Reserve Room #<?= e($room['room_number']) ?> Now
                            </a>
                        <?php else: ?>
                            <div class="p-3 rounded-pill text-center fw-bold" style="background: rgba(30, 41, 59, 0.85); color: #94A3B8; border: 1px solid rgba(255, 255, 255, 0.15);">
                                <i class="bi bi-info-circle me-2"></i>Room #<?= e($room['room_number']) ?> is Currently <?= e($room['status']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suite Overview & Features -->
        <div class="row g-4 mb-5">
            <div class="col-12 col-md-8">
                <div class="card rounded-4 p-4 shadow-lg border h-100" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
                    <h4 class="font-serif fw-bold mb-3" style="color: #FFDF73; text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);"><i class="bi bi-file-text-fill me-2"></i>Suite Architectural Overview</h4>
                    <p class="text-light opacity-90 leading-relaxed mb-4 fs-6 fw-normal"><?= e($typeCatalog['details']) ?></p>

                    <h5 class="font-serif fw-bold mb-3" style="color: #FFDF73;"><i class="bi bi-sliders me-2"></i>Room Amenities & Features</h5>
                    <div class="row row-cols-1 row-cols-sm-2 g-3">
                        <?php foreach ($typeCatalog['features'] as $feature): ?>
                            <div class="col">
                                <div class="p-3 rounded-3 border d-flex align-items-center h-100" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
                                    <i class="bi bi-check2-square fs-5 me-3" style="color: #FFDF73;"></i>
                                    <span class="small fw-semibold text-white"><?= e($feature) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card rounded-4 p-4 shadow-lg border h-100" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
                    <h5 class="font-serif fw-bold mb-4" style="color: #FFDF73;"><i class="bi bi-shield-check me-2"></i>5-Star Guarantees</h5>
                    <ul class="list-unstyled text-light opacity-90 small mb-0">
                        <li class="mb-3 d-flex align-items-start">
                            <i class="bi bi-award-fill fs-5 me-3 mt-1" style="color: #FFDF73;"></i>
                            <div>
                                <strong class="d-block text-white">Best Rate Guarantee</strong>
                                Direct reservation assurance with zero booking surcharges.
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <i class="bi bi-clock-history fs-5 me-3 mt-1" style="color: #FFDF73;"></i>
                            <div>
                                <strong class="d-block text-white">24/7 Butler & Concierge</strong>
                                Dedicated front desk support & luggage assistance.
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <i class="bi bi-wifi fs-5 me-3 mt-1" style="color: #FFDF73;"></i>
                            <div>
                                <strong class="d-block text-white">Ultra Fiber Wi-Fi</strong>
                                High-speed unmetered connection for all Devices.
                            </div>
                        </li>
                        <li class="d-flex align-items-start">
                            <i class="bi bi-arrow-repeat fs-5 me-3 mt-1" style="color: #FFDF73;"></i>
                            <div>
                                <strong class="d-block text-white">Flexible Date Rescheduling</strong>
                                Easy stay date adjustment prior to check-in.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Verified Guest Reviews Section -->
        <div class="card rounded-4 p-4 shadow-lg border" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2 pb-3 border-bottom border-secondary">
                <h4 class="font-serif fw-bold m-0" style="color: #FFDF73; text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);"><i class="bi bi-chat-square-heart me-2"></i>Verified Guest Reviews</h4>
                <div class="badge fs-6 px-4 py-2 fw-bold rounded-pill shadow" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10;">
                    ★ <?= number_format($ratingData['avg_rating'], 1) ?> / 5.0 (<?= $ratingData['review_count'] ?> Reviews)
                </div>
            </div>

            <?php if (empty($reviews)): ?>
                <div class="text-center py-5 text-light opacity-75">
                    <i class="bi bi-chat-quote fs-1 text-warning opacity-50 d-block mb-3"></i>
                    <p class="m-0 fs-6 fw-semibold">No reviews yet for Room #<?= e($room['room_number']) ?>. Be the first guest to experience this suite!</p>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    <?php foreach ($reviews as $rev): ?>
                        <div class="col">
                            <div class="p-3 rounded-4 border h-100" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-bold font-serif" style="color: #FFDF73;"><?= e($rev['full_name']) ?></span>
                                    <div class="text-warning small">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="bi bi-star-<?= $s <= (int)$rev['rating'] ? 'fill' : 'blank' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="text-light opacity-90 small mb-2">"<?= e($rev['comment'] ?: 'Great stay!') ?>"</p>
                                <small class="text-light opacity-50 text-xs d-block text-end"><?= date('M d, Y', strtotime($rev['created_at'])) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Room Switcher Carousel/Grid -->
        <div class="card rounded-4 p-4 shadow-lg border mt-4" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary">
                <h5 class="font-serif fw-bold m-0" style="color: #FFDF73;"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Explore Other Suites & Rooms</h5>
                <a href="rooms.php#suite-catalog" class="btn btn-outline-warning btn-sm rounded-pill font-serif fw-bold">View Catalog Grid</a>
            </div>
            
            <div class="d-flex overflow-x-auto gap-3 pb-2 custom-scrollbar" style="scroll-behavior: smooth;">
                <?php foreach ($allRooms as $otherRoom): 
                    $isSelf = (int)$otherRoom['room_id'] === (int)$room['room_id'];
                    $otherBadgeStyle = match ($otherRoom['status']) {
                        'Available' => 'background: rgba(16, 185, 129, 0.35); border: 1px solid #10B981; color: #A7F3D0;',
                        'Reserved' => 'background: rgba(59, 130, 246, 0.35); border: 1px solid #3B82F6; color: #BFDBFE;',
                        'Occupied' => 'background: rgba(245, 158, 11, 0.35); border: 1px solid #F59E0B; color: #FDE68A;',
                        'Cleaning' => 'background: rgba(168, 85, 247, 0.35); border: 1px solid #A855F7; color: #DDD6FE;',
                        default => 'background: rgba(148, 163, 184, 0.3); color: #F1F5F9;',
                    };
                ?>
                    <a href="room-detail.php?id=<?= (int)$otherRoom['room_id'] ?><?= $dateParams ?>" 
                       class="card text-decoration-none transition-all flex-shrink-0 rounded-4 p-3 <?= $isSelf ? 'border-warning shadow-lg' : '' ?>" 
                       style="width: 220px; background: <?= $isSelf ? 'rgba(212, 175, 55, 0.15)' : 'rgba(30, 41, 59, 0.7)' ?>; border: 1px solid <?= $isSelf ? '#D4AF37' : 'rgba(212, 175, 55, 0.3)' ?>;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge text-xs px-2 py-1 rounded-pill fw-bold" style="<?= $otherBadgeStyle ?>"><?= $otherRoom['status'] ?></span>
                            <small class="fw-bold font-serif" style="color: #FFDF73;">#<?= e($otherRoom['room_number']) ?></small>
                        </div>
                        <h6 class="text-white font-serif fw-bold text-truncate mb-1" style="font-size: 0.9rem;"><?= e($otherRoom['room_type']) ?></h6>
                        <div class="text-xs fw-bold" style="color: #FBBF24;">₱<?= number_format((float)$otherRoom['price_per_night']) ?><span class="text-light opacity-75 font-sans fw-normal">/night</span></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<script>
function switchHeroImage(src) {
    const heroImg = document.getElementById('mainRoomHeroImage');
    if (heroImg) {
        heroImg.src = src;
    }
    document.querySelectorAll('.gallery-thumb').forEach(el => {
        el.style.borderColor = 'rgba(212, 175, 55, 0.3)';
    });
    if (event && event.target) {
        event.target.style.borderColor = '#D4AF37';
    }
}
</script>

<?php renderSupportWidget('customer'); ?>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js" defer></script>
</body>
</html>
