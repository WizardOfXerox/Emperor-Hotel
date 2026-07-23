<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';

$user = currentUser();
$catalog = roomCatalog();

$db = Database::connect();
$roomModel = new Room($db);
$reservationModel = new Reservation($db);

$rooms = $roomModel->all();
$roomStats = $roomModel->typeSummary();

$checkIn = trim((string) ($_GET['check_in'] ?? ''));
$checkOut = trim((string) ($_GET['check_out'] ?? ''));

if ($checkIn === '') $checkIn = (new DateTimeImmutable('today'))->format('Y-m-d');
if ($checkOut === '') $checkOut = (new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');

// Fetch Most Booked Room from database
$mostBookedStmt = $db->query("
    SELECT r.*, COUNT(res.reservation_id) AS booking_count,
           COALESCE(AVG(rev.rating), 5.0) AS avg_rating,
           COUNT(rev.review_id) AS review_count
    FROM rooms r
    LEFT JOIN reservations res ON r.room_id = res.room_id
    LEFT JOIN room_reviews rev ON r.room_id = rev.room_id
    GROUP BY r.room_id
    ORDER BY booking_count DESC, avg_rating DESC
    LIMIT 1
");
$mostBookedRoom = $mostBookedStmt->fetch(PDO::FETCH_ASSOC);

renderHeader('Rooms Directory | Emperor Hotel', ['../assets/css/site/home.css'], '');
?>

<nav class="home-nav" aria-label="Primary navigation">
    <div class="home-nav__container">
        <a class="home-nav__logo" href="home.php" aria-label="Emperor Hotel home">
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </a>

        <div class="home-nav__links">
            <a class="home-nav__link" href="home.php">HOME</a>
            <a class="home-nav__link home-nav__link--active" href="rooms.php">ROOMS</a>
            <a class="home-nav__link" href="suites.php">SUITES</a>
            <a class="home-nav__link" href="contact.php">CONTACT</a>
        </div>

        <div class="home-nav__auth">
            <button type="button" class="btn btn-sm btn-outline-warning theme-toggle-btn rounded-circle me-2 d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; padding: 0;" onclick="toggleEmperorTheme()" title="Switch to Light Mode" aria-label="Switch to Light Mode"><i class="bi bi-sun-fill fs-5"></i></button>
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

<div class="container-fluid px-lg-4 px-xl-5 py-4">

    <!-- Page Title Header Banner -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 p-4 rounded-4 shadow-lg border rooms-page-banner">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill px-3 py-1 font-serif fw-bold text-xs rooms-banner-badge"><i class="bi bi-door-open-fill me-1"></i>ROOM DIRECTORY &amp; SELECTION</span>
                <span class="text-warning text-xs font-serif opacity-75">EMPEROR HOTEL</span>
            </div>
            <h1 class="h3 font-serif fw-bold mb-0">Explore &amp; Pick Your Suite</h1>
            <p class="text-xs mb-0 opacity-75">Filter by stay dates, search room numbers, or toggle categories using the sidebar filter on the right.</p>
        </div>
        <div>
            <span class="badge rounded-pill px-3 py-2 text-xs font-serif fw-bold rooms-total-pill">
                Showing <?= count($rooms) ?> Suites Total
            </span>
        </div>
    </div>

    <!-- ⭐ MOST BOOKED SUITE SHOWCASE SECTION -->
    <?php if ($mostBookedRoom): 
        $mbType = $mostBookedRoom['room_type'];
        $mbInfo = $catalog[$mbType] ?? null;
        $mbHeroImg = $mbInfo['hero'] ?? '../assets/images/rooms/imperial-deluxe/hero.jpg';
        $mbDetailUrl = 'room-detail.php?id=' . (int)$mostBookedRoom['room_id'] . '&check_in=' . urlencode($checkIn) . '&check_out=' . urlencode($checkOut);
        $mbRating = number_format((float)($mostBookedRoom['avg_rating'] ?: 5.0), 1);
    ?>
        <div class="card rounded-4 overflow-hidden border shadow-xl mb-4 most-booked-showcase-card">
            <div class="row g-0 align-items-center">
                <div class="col-12 col-md-5 col-lg-4 position-relative overflow-hidden" style="min-height: 220px;">
                    <img src="<?= e($mbHeroImg) ?>" alt="Most Booked Suite" class="w-100 h-100 object-fit-cover" style="min-height: 220px;">
                    <div class="position-absolute top-0 start-0 p-3">
                        <span class="badge bg-warning text-dark font-serif fw-bold px-3 py-2 shadow rounded-pill text-xs">
                            <i class="bi bi-trophy-fill me-1 text-dark"></i>MOST BOOKED SUITE
                        </span>
                    </div>
                </div>
                <div class="col-12 col-md-7 col-lg-8 p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge font-serif fw-bold px-2 py-1 text-xs floor-badge">Floor <?= e($mostBookedRoom['floor']) ?></span>
                                <span class="badge font-serif fw-bold px-2 py-1 text-xs room-number-badge">Room #<?= e($mostBookedRoom['room_number']) ?></span>
                                <span class="badge bg-success text-white font-serif fw-bold px-2 py-1 text-xs"><i class="bi bi-fire me-1"></i><?= (int)$mostBookedRoom['booking_count'] ?> Total Bookings</span>
                            </div>
                            <div>
                                <span class="text-warning fw-bold text-xs"><i class="bi bi-star-fill me-1"></i><?= $mbRating ?> / 5.0 Rating</span>
                            </div>
                        </div>

                        <h4 class="font-serif fw-bold mb-1 most-booked-title"><?= e($mbType) ?> — Room #<?= e($mostBookedRoom['room_number']) ?></h4>
                        <p class="text-xs opacity-75 mb-3"><?= e($mbInfo['tagline'] ?? 'Our most requested guest suite featuring premier amenities and city skyline views.') ?></p>

                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                            <span class="fs-4 fw-bold text-warning font-serif">₱<?= number_format((float)$mostBookedRoom['price_per_night']) ?> <small class="fs-6 opacity-75 font-sans fw-normal">/ night</small></span>
                            <span class="badge rounded-pill text-xs px-3 py-2 room-pill-gold"><i class="bi bi-people-fill me-1"></i>Up to <?= (int)$mostBookedRoom['max_capacity'] ?> Guests</span>
                            <span class="badge rounded-pill text-xs px-3 py-2 room-pill-glass"><i class="bi bi-eye-fill me-1"></i><?= e($mostBookedRoom['view_type']) ?></span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between border-top border-secondary pt-3 mt-2">
                        <small class="text-muted text-xs"><i class="bi bi-check-circle-fill text-success me-1"></i> Top Guest Choice with <?= (int)$mostBookedRoom['review_count'] ?> Verified Reviews</small>
                        <a href="<?= e($mbDetailUrl) ?>" class="btn btn-warning rounded-pill px-4 py-2 font-serif fw-bold btn-sm shadow"><i class="bi bi-eye-fill me-1"></i>View Most Booked Suite</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Grid + Right Sidebar Layout -->
    <div class="row g-4">

        <!-- LEFT: ROOM CARDS GRID -->
        <div class="col-12 col-lg-8 col-xl-9">
            
            <!-- Dynamic Grid Layout Switcher Bar -->
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 p-3 rounded-4 border shadow-sm layout-switcher-bar gap-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-xs font-serif fw-bold text-uppercase tracking-wider opacity-75">
                        <i class="bi bi-grid-fill text-warning me-1"></i>DISPLAY LAYOUT:
                    </span>
                    <span id="activeLayoutLabel" class="badge rounded-pill text-xs fw-bold font-serif" style="background: rgba(212, 175, 55, 0.2); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.4);">
                        Auto Responsive
                    </span>
                </div>

                <!-- Layout Switcher Button Group -->
                <div class="btn-group btn-group-sm rounded-pill p-1 border shadow-sm layout-btn-group flex-wrap" role="group" aria-label="Room Grid Layout Options">
                    <button type="button" class="btn btn-sm rounded-pill layout-toggle-btn active" data-cols="auto" onclick="setRoomGridLayout('auto')" title="Auto Responsive (Based on Screen Size)">
                        <i class="bi bi-aspect-ratio me-1"></i>Auto
                    </button>
                    <button type="button" class="btn btn-sm rounded-pill layout-toggle-btn" data-cols="5" onclick="setRoomGridLayout(5)" title="5 Columns (Ultra Wide)">
                        <i class="bi bi-grid-3x3 me-1"></i>5 Cols
                    </button>
                    <button type="button" class="btn btn-sm rounded-pill layout-toggle-btn" data-cols="4" onclick="setRoomGridLayout(4)" title="4 Columns (Compact Grid)">
                        <i class="bi bi-grid-3x2-gap-fill me-1"></i>4 Cols
                    </button>
                    <button type="button" class="btn btn-sm rounded-pill layout-toggle-btn" data-cols="3" onclick="setRoomGridLayout(3)" title="3 Columns (Standard Grid)">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i>3 Cols
                    </button>
                    <button type="button" class="btn btn-sm rounded-pill layout-toggle-btn" data-cols="2" onclick="setRoomGridLayout(2)" title="2 Columns (Comfort View)">
                        <i class="bi bi-grid-fill me-1"></i>2 Cols
                    </button>
                    <button type="button" class="btn btn-sm rounded-pill layout-toggle-btn" data-cols="1" onclick="setRoomGridLayout(1)" title="1 Column (List View)">
                        <i class="bi bi-view-list me-1"></i>1 Col List
                    </button>
                </div>
            </div>

            <div class="row g-4" id="kioskRoomGrid">
                <?php foreach ($rooms as $rm): 
                    $rmType = $rm['room_type'];
                    $rmInfo = $catalog[$rmType] ?? null;
                    $heroImg = $rmInfo['hero'] ?? '../assets/images/rooms/imperial-deluxe/hero.jpg';
                    $maxCap = (int)($rm['capacity'] ?? ($rmInfo['max_capacity'] ?? 2));
                    $isAvail = $reservationModel->roomIsAvailable((int)$rm['room_id'], $checkIn, $checkOut);
                    $perks = $rmInfo['included_perks'] ?? ['Complimentary breakfast', 'Priority Wi-Fi'];
                    
                    $detailUrl = 'room-detail.php?id=' . (int)$rm['room_id'] . '&check_in=' . urlencode($checkIn) . '&check_out=' . urlencode($checkOut);
                ?>
                    <div class="col-12 col-md-6 col-xl-4 kiosk-room-item" id="room-card-<?= (int)$rm['room_id'] ?>" data-type="<?= e($rmType) ?>" data-avail="<?= $isAvail ? '1' : '0' ?>" data-room-num="<?= e($rm['room_number']) ?>">
                        <div class="card rounded-4 h-100 overflow-hidden border shadow-lg d-flex flex-column justify-content-between position-relative kiosk-room-card">
                            
                            <div>
                                <!-- Suite Photo Banner & Status Badges -->
                                <div class="position-relative overflow-hidden" style="height: 170px;">
                                    <img src="<?= e($heroImg) ?>" alt="<?= e($rmType) ?>" class="w-100 h-100 object-fit-cover">
                                    
                                    <div class="position-absolute top-0 start-0 p-2">
                                        <span class="badge font-serif fw-bold px-2 py-1 text-xs floor-badge">Floor <?= e($rm['floor']) ?></span>
                                    </div>

                                    <div class="position-absolute top-0 end-0 p-2 d-flex flex-column align-items-end gap-1">
                                        <span class="badge font-serif fw-bold px-2 py-1 text-xs room-number-badge">Room #<?= e($rm['room_number']) ?></span>
                                        <?php if ($isAvail): ?>
                                            <span class="badge status-badge-available font-serif fw-bold px-2 py-1 text-xs shadow"><i class="bi bi-check-circle-fill me-1"></i>Available</span>
                                        <?php else: ?>
                                            <span class="badge status-badge-reserved font-serif fw-bold px-2 py-1 text-xs shadow"><i class="bi bi-calendar-x-fill me-1"></i>Reserved</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Room Details & Price Header -->
                                <div class="p-3">
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                        <div>
                                            <span class="text-xs text-uppercase tracking-wider text-warning font-serif fw-bold d-block"><?= e($rmType) ?></span>
                                            <h5 class="font-serif fw-bold mb-0">Room #<?= e($rm['room_number']) ?></h5>
                                        </div>
                                        <div class="text-end">
                                            <strong class="fs-5 text-warning font-serif d-block">₱<?= number_format((float)$rm['price_per_night']) ?></strong>
                                            <small class="opacity-75 text-xs">per night</small>
                                        </div>
                                    </div>

                                    <p class="opacity-75 text-xs mb-3 line-clamp-2" style="min-height: 36px;"><?= e($rmInfo['tagline'] ?? '') ?></p>

                                    <!-- Key Specs Pills -->
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge rounded-pill text-xs fw-semibold px-2 py-1 room-pill-gold">
                                            <i class="bi bi-people-fill me-1"></i>Up to <?= $maxCap ?> Guests
                                        </span>
                                        <span class="badge rounded-pill text-xs fw-semibold px-2 py-1 room-pill-glass">
                                            <i class="bi bi-eye-fill me-1"></i><?= e($rmInfo['view_type'] ?? 'Skyline View') ?>
                                        </span>
                                    </div>

                                    <!-- Executive Perks -->
                                    <div class="border-top border-secondary pt-2">
                                        <small class="opacity-75 text-xs fw-bold d-block mb-1"><i class="bi bi-stars text-warning me-1"></i>Suite Amenities:</small>
                                        <ul class="list-unstyled text-xs opacity-80 m-0 ps-1">
                                            <?php foreach (array_slice($perks, 0, 2) as $pk): ?>
                                                <li class="mb-1"><i class="bi bi-check2 text-warning me-1"></i><?= e($pk) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Navigation Action Button -> View Room Detail -->
                            <div class="p-3 pt-0">
                                <a href="<?= e($detailUrl) ?>" class="btn btn-warning w-100 rounded-pill py-2 font-serif fw-bold text-dark shadow-sm text-xs d-flex align-items-center justify-content-center gap-2" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); border: none;">
                                    <i class="bi bi-eye-fill me-1"></i>View Room #<?= e($rm['room_number']) ?>
                                </a>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT: SIDEBAR FILTER PANEL -->
        <div class="col-12 col-lg-4 col-xl-3">
            <div class="position-sticky-desktop" style="z-index: 10;">
                <div class="card rounded-4 p-4 shadow-lg border d-flex flex-column gap-4 sidebar-filter-card">

                    <!-- 1. Search Bar -->
                    <div>
                        <label class="form-label text-xs text-uppercase tracking-wider font-serif text-warning fw-bold mb-2">
                            <i class="bi bi-search me-1"></i>Search Suite
                        </label>
                        <div class="position-relative">
                            <input type="text" id="kioskSearchInput" class="form-control form-control-sm bg-dark text-white border-secondary rounded-pill text-xs px-3 py-2 ps-4" placeholder="Search room # or perk...">
                            <i class="bi bi-search text-warning position-absolute top-50 start-0 translate-middle-y ms-2 text-xs"></i>
                        </div>
                    </div>

                    <!-- 2. Stay Date Calendar Filter -->
                    <div class="border-top border-secondary pt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label text-xs text-uppercase tracking-wider font-serif text-warning fw-bold mb-0">
                                <i class="bi bi-calendar-range-fill me-1"></i>Stay Calendar
                            </label>
                            <span id="roomsStayDurationBadge" class="badge rounded-pill text-xs fw-bold" style="background: rgba(212, 175, 55, 0.2); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.4);">
                                <?= date('M j', strtotime($checkIn)) ?> – <?= date('M j', strtotime($checkOut)) ?>
                            </span>
                        </div>

                        <form method="get" action="rooms.php" id="roomsCalendarForm" class="mb-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label text-xs text-light opacity-75 fw-bold mb-1">Check-In</label>
                                    <input type="date" name="check_in" id="roomsCheckInInput" value="<?= e($checkIn) ?>" class="form-control form-control-sm bg-dark text-white border-warning text-xs fw-bold py-1" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-xs text-light opacity-75 fw-bold mb-1">Check-Out</label>
                                    <input type="date" name="check_out" id="roomsCheckOutInput" value="<?= e($checkOut) ?>" class="form-control form-control-sm bg-dark text-white border-warning text-xs fw-bold py-1" required>
                                </div>
                            </div>
                        </form>

                        <!-- Compact 7-Column Calendar Month Grid -->
                        <div id="roomsCalendarGridContainer" class="p-2 rounded-3 border" style="background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(212, 175, 55, 0.25) !important;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <button type="button" class="btn btn-xs btn-outline-warning rounded-circle p-0" onclick="shiftRoomsCalendarMonth(-1)" style="width: 24px; height: 24px; color: #FFDF73; border-color: rgba(212, 175, 55, 0.4);"><i class="bi bi-chevron-left text-xs"></i></button>
                                <span class="font-serif fw-bold text-xs" id="roomsCalendarMonthTitle" style="color: #FFDF73;">July 2026</span>
                                <button type="button" class="btn btn-xs btn-outline-warning rounded-circle p-0" onclick="shiftRoomsCalendarMonth(1)" style="width: 24px; height: 24px; color: #FFDF73; border-color: rgba(212, 175, 55, 0.4);"><i class="bi bi-chevron-right text-xs"></i></button>
                            </div>
                            <div class="calendar-grid-header mb-1 text-center font-serif fw-bold text-uppercase" style="font-size: 10px; display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">
                                <div style="color: #FBBF24;">Su</div>
                                <div style="color: #F8FAFC;">Mo</div>
                                <div style="color: #F8FAFC;">Tu</div>
                                <div style="color: #F8FAFC;">We</div>
                                <div style="color: #F8FAFC;">Th</div>
                                <div style="color: #F8FAFC;">Fr</div>
                                <div style="color: #FBBF24;">Sa</div>
                            </div>
                            <div id="roomsCalendarDaysGrid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;"></div>
                        </div>
                    </div>

                    <!-- 3. Category & Availability Checkbox Filters -->
                    <div class="border-top border-secondary pt-3">
                        <label class="form-label text-xs text-uppercase tracking-wider font-serif text-warning fw-bold mb-3">
                            <i class="bi bi-funnel-fill me-1"></i>Suite Category Filter
                        </label>
                        <div class="d-flex flex-column gap-2" id="kioskCategoryCheckboxes">
                            <label class="form-check-label d-flex align-items-center justify-content-between p-2 rounded-3 border custom-filter-item">
                                <span class="text-xs font-serif fw-semibold">
                                    <input type="checkbox" class="form-check-input me-2 filter-checkbox" data-filter="Imperial Deluxe"> Imperial Deluxe
                                </span>
                                <span class="badge filter-price-badge font-serif fw-bold">₱8,500</span>
                            </label>

                            <label class="form-check-label d-flex align-items-center justify-content-between p-2 rounded-3 border custom-filter-item">
                                <span class="text-xs font-serif fw-semibold">
                                    <input type="checkbox" class="form-check-input me-2 filter-checkbox" data-filter="Royal Executive"> Royal Executive
                                </span>
                                <span class="badge filter-price-badge font-serif fw-bold">₱7,500</span>
                            </label>

                            <label class="form-check-label d-flex align-items-center justify-content-between p-2 rounded-3 border custom-filter-item">
                                <span class="text-xs font-serif fw-semibold">
                                    <input type="checkbox" class="form-check-input me-2 filter-checkbox" data-filter="Emperor Presidential"> Emperor Presidential
                                </span>
                                <span class="badge filter-price-badge font-serif fw-bold">₱12,500</span>
                            </label>

                            <label class="form-check-label d-flex align-items-center justify-content-between p-2 rounded-3 border custom-filter-item filter-avail-item mt-2">
                                <span class="text-xs font-serif fw-bold">
                                    <input type="checkbox" class="form-check-input me-2 filter-checkbox" data-filter="available-only"> Available Only
                                </span>
                                <i class="bi bi-check-circle-fill text-success text-xs"></i>
                            </label>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

</div>

<!-- Compact 7-Column Calendar Cell Styles -->
<style>
@media (min-width: 992px) {
    .position-sticky-desktop {
        position: sticky;
        top: 96px;
    }
}
.rooms-cal-day-btn {
    width: 26px;
    height: 26px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-weight: 700;
    font-size: 10px;
    color: #F8FAFC;
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(212, 175, 55, 0.25);
    cursor: pointer;
    transition: all 0.2s ease;
}
.rooms-cal-day-btn:hover:not(.is-disabled) {
    background: rgba(212, 175, 55, 0.3) !important;
    border-color: #D4AF37 !important;
    color: #FFDF73 !important;
    transform: scale(1.08);
}
.rooms-cal-day-btn.is-selected {
    background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%) !important;
    color: #070A10 !important;
    border: none !important;
    font-weight: 900 !important;
    box-shadow: 0 2px 8px rgba(212, 175, 55, 0.6) !important;
}
.rooms-cal-day-btn.in-range {
    background: rgba(212, 175, 55, 0.25) !important;
    border: 1px solid rgba(212, 175, 55, 0.5) !important;
    color: #FFDF73 !important;
}
.rooms-cal-day-btn.is-disabled {
    opacity: 0.3;
    cursor: not-allowed;
    background: rgba(15, 23, 42, 0.4);
    border-color: transparent;
}
.rooms-cal-day-empty {
    width: 26px;
    height: 26px;
    margin: 0 auto;
    opacity: 0;
}
.custom-filter-item {
    transition: all 0.2s ease;
}
.custom-filter-item:hover {
    background: rgba(212, 175, 55, 0.15) !important;
    border-color: rgba(212, 175, 55, 0.4) !important;
}
</style>

<!-- Interactive Sidebar Filter & Calendar JavaScript -->
<script>
let roomsCalYear = 2026;
let roomsCalMonth = 6;

document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.filter-checkbox');
    const items = document.querySelectorAll('.kiosk-room-item');
    const searchInput = document.getElementById('kioskSearchInput');
    const allCheckbox = document.querySelector('.filter-checkbox[data-filter="all"]');

    function filterGrid() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const checkedFilters = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.getAttribute('data-filter'));

        const isAvailOnlyChecked = checkedFilters.includes('available-only');
        const categoryFilters = checkedFilters.filter(f => f !== 'available-only');

        items.forEach(item => {
            const type = item.getAttribute('data-type');
            const avail = item.getAttribute('data-avail');
            const roomNum = item.getAttribute('data-room-num');
            const textContent = item.textContent.toLowerCase();

            let matchCategory = (categoryFilters.length === 0) || categoryFilters.includes(type);

            if (isAvailOnlyChecked && avail !== '1') {
                matchCategory = false;
            }

            let matchQuery = query === '' || textContent.includes(query) || roomNum.includes(query);

            if (matchCategory && matchQuery) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', filterGrid);
    });

    if (searchInput) {
        searchInput.addEventListener('input', filterGrid);
    }

    initRoomsCalendar();
});

function initRoomsCalendar() {
    const checkInInput = document.getElementById('roomsCheckInInput');
    const checkOutInput = document.getElementById('roomsCheckOutInput');
    const form = document.getElementById('roomsCalendarForm');
    if (!checkInInput || !checkOutInput) return;

    const inVal = checkInInput.value ? new Date(checkInInput.value + 'T00:00:00') : new Date();
    roomsCalYear = inVal.getFullYear();
    roomsCalMonth = inVal.getMonth();

    checkInInput.addEventListener('change', function() {
        if (form) form.submit();
    });
    checkOutInput.addEventListener('change', function() {
        if (form) form.submit();
    });

    renderRoomsCalendarGrid();
}

function renderRoomsCalendarGrid() {
    const daysGrid = document.getElementById('roomsCalendarDaysGrid');
    const titleEl = document.getElementById('roomsCalendarMonthTitle');
    const checkInInput = document.getElementById('roomsCheckInInput');
    const checkOutInput = document.getElementById('roomsCheckOutInput');

    if (!daysGrid || !titleEl || !checkInInput || !checkOutInput) return;

    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    titleEl.textContent = `${monthNames[roomsCalMonth]} ${roomsCalYear}`;

    const firstDay = new Date(roomsCalYear, roomsCalMonth, 1).getDay();
    const totalDays = new Date(roomsCalYear, roomsCalMonth + 1, 0).getDate();

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const checkInDate = checkInInput.value ? new Date(checkInInput.value + 'T00:00:00') : null;
    const checkOutDate = checkOutInput.value ? new Date(checkOutInput.value + 'T00:00:00') : null;

    let html = '';
    for (let i = 0; i < firstDay; i++) {
        html += '<div class="rooms-cal-day-empty"></div>';
    }

    for (let day = 1; day <= totalDays; day++) {
        const cellDate = new Date(roomsCalYear, roomsCalMonth, day);
        cellDate.setHours(0, 0, 0, 0);

        const yyyymmdd = `${roomsCalYear}-${String(roomsCalMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        let cellClass = 'rooms-cal-day-btn';
        const isPast = cellDate < today;

        if (isPast) {
            cellClass += ' is-disabled';
        } else if (checkInDate && checkOutDate) {
            const cellTime = cellDate.getTime();
            const inTime = checkInDate.getTime();
            const outTime = checkOutDate.getTime();

            if (cellTime === inTime || cellTime === outTime) {
                cellClass += ' is-selected';
            } else if (cellTime > inTime && cellTime < outTime) {
                cellClass += ' in-range';
            }
        }

        html += `<button type="button" class="${cellClass}" onclick="selectRoomsCalDate('${yyyymmdd}')" ${isPast ? 'disabled' : ''}>${day}</button>`;
    }

    daysGrid.innerHTML = html;
}

function selectRoomsCalDate(dateStr) {
    const checkInInput = document.getElementById('roomsCheckInInput');
    const checkOutInput = document.getElementById('roomsCheckOutInput');
    const form = document.getElementById('roomsCalendarForm');
    if (!checkInInput || !checkOutInput || !form) return;

    const parts = dateStr.split('-');
    const selected = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    const checkIn = checkInInput.value ? new Date(checkInInput.value + 'T00:00:00') : null;

    if (!checkInInput.dataset.selectingState || checkInInput.dataset.selectingState === 'end') {
        checkInInput.value = dateStr;
        checkInInput.dataset.selectingState = 'start';
        const nextDay = new Date(selected);
        nextDay.setDate(nextDay.getDate() + 1);
        checkOutInput.value = nextDay.toISOString().split('T')[0];
    } else {
        if (checkIn && selected <= checkIn) {
            checkInInput.value = dateStr;
            const nextDay = new Date(selected);
            nextDay.setDate(nextDay.getDate() + 1);
            checkOutInput.value = nextDay.toISOString().split('T')[0];
        } else {
            checkOutInput.value = dateStr;
            checkInInput.dataset.selectingState = 'end';
            renderRoomsCalendarGrid();
            form.submit();
            return;
        }
    }

    renderRoomsCalendarGrid();
}

function shiftRoomsCalendarMonth(delta) {
    roomsCalMonth += delta;
    if (roomsCalMonth > 11) {
        roomsCalMonth = 0;
        roomsCalYear++;
    } else if (roomsCalMonth < 0) {
        roomsCalMonth = 11;
        roomsCalYear--;
    }
    renderRoomsCalendarGrid();
}

function getAutoResponsiveCols() {
    const width = window.innerWidth;
    if (width >= 1600) return 5;
    if (width >= 1200) return 4;
    if (width >= 992) return 3;
    if (width >= 768) return 2;
    return 1;
}

function setRoomGridLayout(mode) {
    const items = document.querySelectorAll('.kiosk-room-item');
    const label = document.getElementById('activeLayoutLabel');
    const btns = document.querySelectorAll('.layout-toggle-btn');
    const leftCol = document.getElementById('kioskMainContainer');

    btns.forEach(b => {
        const val = b.getAttribute('data-cols');
        if (String(val) === String(mode)) {
            b.classList.add('active');
        } else {
            b.classList.remove('active');
        }
    });

    let effectiveCols = mode;
    let isAuto = (mode === 'auto');

    if (isAuto) {
        effectiveCols = getAutoResponsiveCols();
    } else {
        effectiveCols = parseInt(mode, 10);
    }

    items.forEach(item => {
        item.className = item.className.replace(/col-\S+/g, '').trim();
        item.classList.add('kiosk-room-item');

        if (effectiveCols === 5) {
            item.classList.add('col-12', 'col-sm-6', 'col-md-4', 'col-lg-3', 'col-xxl-five');
        } else if (effectiveCols === 4) {
            item.classList.add('col-12', 'col-sm-6', 'col-md-4', 'col-lg-3', 'col-xl-3');
        } else if (effectiveCols === 3) {
            item.classList.add('col-12', 'col-md-6', 'col-lg-4', 'col-xl-4');
        } else if (effectiveCols === 2) {
            item.classList.add('col-12', 'col-md-6');
        } else {
            item.classList.add('col-12');
        }
    });

    if (label) {
        if (isAuto) {
            label.textContent = `Auto (${effectiveCols} Cols for ${window.innerWidth}px)`;
        } else if (effectiveCols === 5) {
            label.textContent = '5 Columns (Ultra Wide)';
        } else if (effectiveCols === 4) {
            label.textContent = '4 Columns (Compact Grid)';
        } else if (effectiveCols === 3) {
            label.textContent = '3 Columns (Standard Grid)';
        } else if (effectiveCols === 2) {
            label.textContent = '2 Columns (Comfort View)';
        } else {
            label.textContent = '1 Column (List View)';
        }
    }

    localStorage.setItem('emperor_rooms_layout_mode', mode);
}

window.addEventListener('resize', () => {
    const savedMode = localStorage.getItem('emperor_rooms_layout_mode') || 'auto';
    if (savedMode === 'auto') {
        setRoomGridLayout('auto');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const savedMode = localStorage.getItem('emperor_rooms_layout_mode') || 'auto';
    setRoomGridLayout(savedMode === 'auto' ? 'auto' : (isNaN(savedMode) ? 'auto' : parseInt(savedMode, 10)));
});
</script>

<?php renderSupportWidget('customer'); ?>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js" defer></script>
</body>
</html>
