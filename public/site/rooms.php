<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';
require_once __DIR__ . '/../includes/hotel_map.php';
require_once __DIR__ . '/../includes/calendar_picker.php';

$db = Database::connect();
$roomModel = new Room($db);
$reviewModel = new Review($db);

$user = currentUser();

$checkIn = trim((string) ($_GET['check_in'] ?? ''));
$checkOut = trim((string) ($_GET['check_out'] ?? ''));
$bedType = trim((string) ($_GET['bed_type'] ?? ''));
$minCapacity = (int) ($_GET['min_capacity'] ?? 0);

$today = new DateTimeImmutable('today');
if ($checkIn === '') $checkIn = $today->format('Y-m-d');
if ($checkOut === '') $checkOut = $today->modify('+1 day')->format('Y-m-d');

$scoredRooms = $roomModel->calculateRecommendationScores();
$catalog = roomCatalog();

renderHeader('Suites & Rooms - Emperor Hotel', ['../assets/css/site/rooms.css']);
renderSiteLayoutStart();
?>

<div class="container py-4">
    <!-- Header Title & Filter Bar -->
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="font-serif text-gold fw-bold m-0"><i class="bi bi-door-open me-2"></i>Suites & Room Inventory</h1>
            <p class="text-muted small m-0">Filter by stay dates, bed configuration, or view individual suite details.</p>
        </div>
        <button type="button" class="btn btn-gold rounded-pill px-4 font-serif fw-bold shadow" data-bs-toggle="modal" data-bs-target="#calendarPickerModal">
            <i class="bi bi-calendar-range me-2"></i>Select Stay Dates (<?= date('M d', strtotime($checkIn)) ?> - <?= date('M d', strtotime($checkOut)) ?>)
        </button>
    </div>

    <!-- Filter Form Bar -->
    <div class="card bg-dark text-light border-gold-glow rounded-4 p-3 mb-4 shadow">
        <form method="GET" action="rooms.php" class="row g-2 align-items-end">
            <input type="hidden" name="check_in" value="<?= e($checkIn) ?>">
            <input type="hidden" name="check_out" value="<?= e($checkOut) ?>">

            <div class="col-md-4 col-sm-6">
                <label class="form-label text-xs text-uppercase tracking-wider text-muted">Bed Size Preference</label>
                <select name="bed_type" class="form-select form-select-dark bg-dark text-light border-secondary">
                    <option value="">All Bed Sizes</option>
                    <option value="Queen Bed" <?= $bedType === 'Queen Bed' ? 'selected' : '' ?>>Queen Bed</option>
                    <option value="King Bed" <?= $bedType === 'King Bed' ? 'selected' : '' ?>>King Bed</option>
                    <option value="Super King Master Suite" <?= $bedType === 'Super King Master Suite' ? 'selected' : '' ?>>Super King Suite</option>
                </select>
            </div>

            <div class="col-md-4 col-sm-6">
                <label class="form-label text-xs text-uppercase tracking-wider text-muted">Minimum Guest Capacity</label>
                <select name="min_capacity" class="form-select form-select-dark bg-dark text-light border-secondary">
                    <option value="0">Any Capacity</option>
                    <option value="2" <?= $minCapacity === 2 ? 'selected' : '' ?>>2+ Guests</option>
                    <option value="4" <?= $minCapacity === 4 ? 'selected' : '' ?>>4+ Guests</option>
                    <option value="6" <?= $minCapacity === 6 ? 'selected' : '' ?>>6+ Guests</option>
                </select>
            </div>

            <div class="col-md-4 col-sm-12 d-flex gap-2">
                <button type="submit" class="btn btn-outline-warning w-100 rounded-pill fw-bold">
                    <i class="bi bi-funnel-fill me-1"></i>Filter
                </button>
                <a href="rooms.php" class="btn btn-outline-secondary rounded-pill px-3">Reset</a>
            </div>
        </form>
    </div>

    <!-- Scored Room Recommendation Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
        <?php foreach ($scoredRooms as $rm): 
            if ($bedType !== '' && strtolower($rm['bed_type'] ?? '') !== strtolower($bedType)) continue;
            if ($minCapacity > 0 && (int)($rm['max_capacity'] ?? 2) < $minCapacity) continue;

            $typeInfo = $catalog[$rm['room_type']] ?? [
                'hero' => '../assets/images/rooms/hero.jpg',
                'tagline' => 'Luxury suite',
            ];
            $isTopChoice = ((float)$rm['recommendation_score'] >= 75.0);
        ?>
            <div class="col">
                <div class="card h-100 bg-dark text-light border-gold-glow rounded-4 overflow-hidden shadow transition-all hover-lift">
                    <div class="position-relative">
                        <img src="<?= e($typeInfo['hero']) ?>" class="card-img-top w-100 object-fit-cover" style="height: 220px;" alt="<?= e($rm['room_type']) ?>">
                        
                        <span class="position-absolute top-0 start-0 m-3 badge bg-gold text-dark font-serif fw-bold px-3 py-1 rounded-pill">
                            #<?= e($rm['room_number']) ?>
                        </span>

                        <?php if ($isTopChoice): ?>
                            <span class="position-absolute top-0 end-0 m-3 badge bg-warning text-dark font-serif fw-bold px-3 py-1 rounded-pill shadow">
                                <i class="bi bi-fire me-1"></i>Popular Choice (★ <?= $rm['recommendation_score'] ?>)
                            </span>
                        <?php else: ?>
                            <span class="position-absolute top-0 end-0 m-3 badge <?= $rm['status'] === 'Available' ? 'bg-success' : 'bg-secondary' ?> px-3 py-1 rounded-pill">
                                <?= e($rm['status']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <small class="text-gold text-uppercase tracking-wider fw-bold">Floor <?= e($rm['floor']) ?></small>
                                <small class="text-warning fw-bold"><i class="bi bi-star-fill me-1"></i><?= $rm['avg_rating'] ?> (<?= $rm['review_count'] ?>)</small>
                            </div>

                            <h5 class="card-title font-serif text-gold fw-bold mb-1"><?= e($rm['room_type']) ?></h5>
                            <p class="card-text text-muted small mb-3 text-truncate"><?= e($typeInfo['tagline']) ?></p>

                            <div class="p-2 bg-black bg-opacity-40 rounded-3 border border-secondary mb-3 small">
                                <div class="text-light mb-1"><i class="bi bi-door-closed text-gold me-2"></i><?= e($rm['bed_type'] ?? 'King Bed') ?></div>
                                <div class="text-light"><i class="bi bi-people text-gold me-2"></i>Up to <?= e($rm['max_capacity'] ?? 2) ?> Guests • <?= e($rm['view_type'] ?? 'City View') ?></div>
                            </div>
                        </div>

                        <div>
                            <div class="d-flex align-items-baseline justify-content-between mb-3">
                                <span class="text-muted text-xs text-uppercase">Nightly Rate</span>
                                <span class="font-serif text-gold fs-5 fw-bold">₱<?= number_format((float)$rm['price_per_night'], 2) ?></span>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="room-detail.php?id=<?= (int)$rm['room_id'] ?>" class="btn btn-outline-gold rounded-pill btn-sm fw-bold py-2">
                                    <i class="bi bi-eye me-1"></i>Inspect Suite Details
                                </a>
                                <?php if ($rm['status'] === 'Available'): ?>
                                    <a href="../user/dashboard.php?selected_room=<?= (int)$rm['room_id'] ?>&check_in=<?= e($checkIn) ?>&check_out=<?= e($checkOut) ?>" 
                                       class="btn btn-gold rounded-pill btn-sm fw-bold py-2 shadow">
                                        <i class="bi bi-lightning-fill me-1"></i>Book Room #<?= e($roomNumber ?? $rm['room_number']) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Interactive Floor Map -->
    <?php renderHotelFloorMap($db, 'public'); ?>
</div>

<?php
renderCalendarPickerModal($checkIn, $checkOut);
renderSiteLayoutEnd();
