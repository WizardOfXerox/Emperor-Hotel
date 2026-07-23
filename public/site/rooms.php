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
        </div>

        <div class="home-nav__auth">
            <button type="button" class="btn btn-sm btn-outline-warning theme-toggle-btn rounded-pill px-3 py-1 me-2 fw-semibold d-inline-flex align-items-center shadow-sm" onclick="toggleEmperorTheme()"><i class="bi bi-sun-fill me-1"></i> Light Mode</button>
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
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 p-4 rounded-4 text-white shadow-lg border" style="background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.4) !important;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill px-3 py-1 font-serif fw-bold text-xs" style="background: rgba(212, 175, 55, 0.2); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.4);"><i class="bi bi-door-open-fill me-1"></i>ROOM DIRECTORY &amp; SELECTION</span>
                <span class="text-warning text-xs font-serif opacity-75">EMPEROR HOTEL</span>
            </div>
            <h1 class="h3 font-serif fw-bold text-white mb-0">Explore &amp; Pick Your Suite</h1>
            <p class="text-light opacity-75 text-xs mb-0">Filter by stay dates, search room numbers, or toggle categories using the sidebar filter on the right.</p>
        </div>
        <div>
            <span class="badge rounded-pill px-3 py-2 text-xs font-serif fw-bold" style="background: rgba(212, 175, 55, 0.15); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.3);">
                Showing <?= count($rooms) ?> Suites Total
            </span>
        </div>
    </div>

    <!-- Main Grid + Right Sidebar Layout -->
    <div class="row g-4">

        <!-- LEFT: ROOM CARDS GRID -->
        <div class="col-12 col-lg-8 col-xl-9">
            <div class="row g-4" id="kioskRoomGrid">
                <?php foreach ($rooms as $rm): 
                    $rmType = $rm['room_type'];
                    $rmInfo = $catalog[$rmType] ?? null;
                    $heroImg = $rmInfo['hero'] ?? '../assets/images/rooms/hero.jpg';
                    $maxCap = (int)($rm['capacity'] ?? ($rmInfo['max_capacity'] ?? 2));
                    $isAvail = $reservationModel->roomIsAvailable((int)$rm['room_id'], $checkIn, $checkOut);
                    $perks = $rmInfo['included_perks'] ?? ['Complimentary breakfast', 'Priority Wi-Fi'];
                    
                    $detailUrl = 'room-detail.php?id=' . (int)$rm['room_id'] . '&check_in=' . urlencode($checkIn) . '&check_out=' . urlencode($checkOut);
                ?>
                    <div class="col-12 col-md-6 col-xl-4 kiosk-room-item" data-type="<?= e($rmType) ?>" data-avail="<?= $isAvail ? '1' : '0' ?>" data-room-num="<?= e($rm['room_number']) ?>">
                        <div class="card rounded-4 h-100 overflow-hidden border shadow-lg d-flex flex-column justify-content-between position-relative" style="background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(15px); border: 1px solid rgba(212, 175, 55, 0.3) !important; transition: transform 0.25s ease, box-shadow 0.25s ease;">
                            
                            <div>
                                <!-- Suite Photo Banner & Status Badges -->
                                <div class="position-relative overflow-hidden" style="height: 170px;">
                                    <img src="<?= e($heroImg) ?>" alt="<?= e($rmType) ?>" class="w-100 h-100 object-fit-cover">
                                    
                                    <div class="position-absolute top-0 start-0 p-2">
                                        <span class="badge bg-gold text-dark font-serif fw-bold px-2 py-1 text-xs">Floor <?= e($rm['floor']) ?></span>
                                    </div>

                                    <div class="position-absolute top-0 end-0 p-2 d-flex flex-column align-items-end gap-1">
                                        <span class="badge bg-dark bg-opacity-75 text-warning font-serif fw-bold px-2 py-1 border border-warning text-xs">Room #<?= e($rm['room_number']) ?></span>
                                        <?php if ($isAvail): ?>
                                            <span class="badge bg-success text-white font-serif fw-bold px-2 py-1 text-xs shadow"><i class="bi bi-check-circle-fill me-1"></i>Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger text-white font-serif fw-bold px-2 py-1 text-xs shadow"><i class="bi bi-calendar-x-fill me-1"></i>Reserved</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Room Details & Price Header -->
                                <div class="p-3">
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                        <div>
                                            <span class="text-xs text-uppercase tracking-wider text-warning font-serif fw-bold d-block"><?= e($rmType) ?></span>
                                            <h5 class="font-serif fw-bold text-white mb-0">Room #<?= e($rm['room_number']) ?></h5>
                                        </div>
                                        <div class="text-end">
                                            <strong class="fs-5 text-warning font-serif d-block">₱<?= number_format((float)$rm['price_per_night']) ?></strong>
                                            <small class="text-light opacity-75 text-xs">per night</small>
                                        </div>
                                    </div>

                                    <p class="text-light opacity-75 text-xs mb-3 line-clamp-2" style="min-height: 36px;"><?= e($rmInfo['tagline'] ?? '') ?></p>

                                    <!-- Key Specs Pills -->
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge rounded-pill text-xs fw-semibold px-2 py-1" style="background: rgba(212, 175, 55, 0.15); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.3);">
                                            <i class="bi bi-people-fill me-1"></i>Up to <?= $maxCap ?> Guests
                                        </span>
                                        <span class="badge rounded-pill text-xs fw-semibold px-2 py-1" style="background: rgba(255, 255, 255, 0.1); color: #F8FAFC;">
                                            <i class="bi bi-eye-fill me-1"></i><?= e($rmInfo['view_type'] ?? 'Skyline View') ?>
                                        </span>
                                    </div>

                                    <!-- Executive Perks -->
                                    <div class="border-top border-secondary pt-2">
                                        <small class="text-light opacity-75 text-xs fw-bold d-block mb-1"><i class="bi bi-stars text-warning me-1"></i>Suite Amenities:</small>
                                        <ul class="list-unstyled text-xs text-light opacity-80 m-0 ps-1">
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
                <div class="card rounded-4 p-4 shadow-lg text-light border d-flex flex-column gap-4" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(20px); border: 1px solid rgba(212, 175, 55, 0.35) !important;">

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
                            <label class="form-check-label d-flex align-items-center justify-content-between p-2 rounded-3 border custom-filter-item" style="background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(212, 175, 55, 0.2) !important; cursor: pointer;">
                                <span class="text-xs font-serif fw-semibold text-light">
                                    <input type="checkbox" class="form-check-input me-2 filter-checkbox" data-filter="all" checked> All Suites
                                </span>
                                <span class="badge bg-gold text-dark text-xs font-serif fw-bold"><?= count($rooms) ?></span>
                            </label>

                            <label class="form-check-label d-flex align-items-center justify-content-between p-2 rounded-3 border custom-filter-item" style="background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(212, 175, 55, 0.2) !important; cursor: pointer;">
                                <span class="text-xs font-serif fw-semibold text-light">
                                    <input type="checkbox" class="form-check-input me-2 filter-checkbox" data-filter="Imperial Deluxe"> Imperial Deluxe
                                </span>
                                <span class="badge bg-dark text-warning border border-warning text-xs font-serif fw-bold">₱8,500</span>
                            </label>

                            <label class="form-check-label d-flex align-items-center justify-content-between p-2 rounded-3 border custom-filter-item" style="background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(212, 175, 55, 0.2) !important; cursor: pointer;">
                                <span class="text-xs font-serif fw-semibold text-light">
                                    <input type="checkbox" class="form-check-input me-2 filter-checkbox" data-filter="Royal Executive"> Royal Executive
                                </span>
                                <span class="badge bg-dark text-warning border border-warning text-xs font-serif fw-bold">₱7,500</span>
                            </label>

                            <label class="form-check-label d-flex align-items-center justify-content-between p-2 rounded-3 border custom-filter-item" style="background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(212, 175, 55, 0.2) !important; cursor: pointer;">
                                <span class="text-xs font-serif fw-semibold text-light">
                                    <input type="checkbox" class="form-check-input me-2 filter-checkbox" data-filter="Emperor Presidential"> Emperor Presidential
                                </span>
                                <span class="badge bg-dark text-warning border border-warning text-xs font-serif fw-bold">₱12,500</span>
                            </label>

                            <label class="form-check-label d-flex align-items-center justify-content-between p-2 rounded-3 border custom-filter-item mt-2" style="background: rgba(22, 101, 52, 0.3); border: 1px solid rgba(34, 197, 94, 0.4) !important; cursor: pointer;">
                                <span class="text-xs font-serif fw-semibold text-success">
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

        const isAllChecked = allCheckbox && allCheckbox.checked;
        const isAvailOnlyChecked = checkedFilters.includes('available-only');

        items.forEach(item => {
            const type = item.getAttribute('data-type');
            const avail = item.getAttribute('data-avail');
            const roomNum = item.getAttribute('data-room-num');
            const textContent = item.textContent.toLowerCase();

            let matchCategory = false;

            if (isAllChecked) {
                matchCategory = true;
            } else {
                const categoryFilters = checkedFilters.filter(f => f !== 'all' && f !== 'available-only');
                if (categoryFilters.length === 0) {
                    matchCategory = true;
                } else {
                    matchCategory = categoryFilters.includes(type);
                }
            }

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
        cb.addEventListener('change', function() {
            if (this.getAttribute('data-filter') === 'all' && this.checked) {
                checkboxes.forEach(other => {
                    if (other.getAttribute('data-filter') !== 'all' && other.getAttribute('data-filter') !== 'available-only') {
                        other.checked = false;
                    }
                });
            } else if (this.getAttribute('data-filter') !== 'all' && this.getAttribute('data-filter') !== 'available-only' && this.checked) {
                if (allCheckbox) allCheckbox.checked = false;
            }
            filterGrid();
        });
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
</script>

<?php renderSupportWidget('customer'); ?>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js" defer></script>
</body>
</html>
