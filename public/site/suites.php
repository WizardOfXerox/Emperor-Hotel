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

renderSiteLayoutStart('Suites Kiosk Menu | Emperor Hotel', $user, '');
?>

<div class="container py-4">

    <!-- Kiosk Banner & Stay Date Selection Header -->
    <div class="card rounded-4 p-4 p-md-5 mb-4 shadow-lg border text-white position-relative overflow-hidden" style="background: rgba(15, 23, 42, 0.94); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important;">
        <div class="row align-items-center g-4">
            <div class="col-12 col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-gold text-dark font-serif fw-bold px-3 py-1 text-xs"><i class="bi bi-door-open-fill me-1"></i>ROOM DIRECTORY &amp; SELECTION</span>
                    <span class="text-warning text-xs font-serif opacity-75">EMPEROR HOTEL</span>
                </div>
                <h1 class="display-6 font-serif fw-bold text-white mb-2">Explore &amp; Pick Your Suite</h1>
                <p class="text-light opacity-75 text-xs m-0">Browse our complete room catalog below. Filter by category, inspect room specifications, and view room details to book.</p>
            </div>

            <div class="col-12 col-lg-5">
                <form method="get" action="suites.php" class="p-3 rounded-4 border" style="background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-xs font-serif text-warning fw-bold text-uppercase"><i class="bi bi-calendar-range-fill me-1"></i>Filter Availability Dates</span>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label text-xs text-light opacity-75 fw-bold mb-1">Check-In</label>
                            <input type="date" name="check_in" value="<?= e($checkIn) ?>" class="form-control form-control-sm bg-dark text-white border-secondary text-xs fw-bold" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xs text-light opacity-75 fw-bold mb-1">Check-Out</label>
                            <input type="date" name="check_out" value="<?= e($checkOut) ?>" class="form-control form-control-sm bg-dark text-white border-secondary text-xs fw-bold" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100 rounded-pill font-serif fw-bold text-dark shadow" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); border: none;">
                        <i class="bi bi-search me-1"></i>Update Stay Dates
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Kiosk Filter Menu Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 p-3 rounded-4 border" style="background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(212, 175, 55, 0.3) !important;">
        
        <!-- Category Filter Tabs -->
        <div class="d-flex flex-wrap align-items-center gap-2" id="kioskCategoryTabs">
            <button type="button" class="btn btn-xs rounded-pill px-3 py-2 font-serif fw-bold text-xs kiosk-tab active" data-filter="all" style="background: #D4AF37; color: #0F172A; border: 1px solid #D4AF37;">
                All Suites (<?= count($rooms) ?>)
            </button>
            <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-3 py-2 font-serif fw-bold text-xs kiosk-tab" data-filter="Imperial Deluxe">
                Imperial Deluxe
            </button>
            <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-3 py-2 font-serif fw-bold text-xs kiosk-tab" data-filter="Royal Executive">
                Royal Executive
            </button>
            <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-3 py-2 font-serif fw-bold text-xs kiosk-tab" data-filter="Emperor Presidential">
                Emperor Presidential
            </button>
            <button type="button" class="btn btn-xs btn-outline-success rounded-pill px-3 py-2 font-serif fw-bold text-xs kiosk-tab" data-filter="available-only">
                <i class="bi bi-check-circle-fill me-1"></i>Available Only
            </button>
        </div>

        <!-- Quick Search Filter Input -->
        <div class="position-relative" style="min-width: 220px;">
            <input type="text" id="kioskSearchInput" class="form-control form-control-sm bg-dark text-white border-secondary rounded-pill text-xs px-3 py-2 ps-4" placeholder="Search room # or perk...">
            <i class="bi bi-search text-warning position-absolute top-50 start-0 translate-middle-y ms-2 text-xs"></i>
        </div>
    </div>

    <!-- Kiosk Room Shopping Cards Grid -->
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
            <div class="col-12 col-md-6 col-lg-4 kiosk-room-item" data-type="<?= e($rmType) ?>" data-avail="<?= $isAvail ? '1' : '0' ?>" data-room-num="<?= e($rm['room_number']) ?>">
                <div class="card rounded-4 h-100 overflow-hidden border shadow-lg d-flex flex-column justify-content-between position-relative" style="background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(15px); border: 1px solid rgba(212, 175, 55, 0.3) !important; transition: transform 0.25s ease, box-shadow 0.25s ease;">
                    
                    <div>
                        <!-- Suite Photo Banner & Status Badges -->
                        <div class="position-relative overflow-hidden" style="height: 180px;">
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

<!-- Interactive Kiosk Filter JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.kiosk-tab');
    const items = document.querySelectorAll('.kiosk-room-item');
    const searchInput = document.getElementById('kioskSearchInput');

    let activeFilter = 'all';

    function filterGrid() {
        const query = searchInput.value.toLowerCase().trim();

        items.forEach(item => {
            const type = item.getAttribute('data-type');
            const avail = item.getAttribute('data-avail');
            const roomNum = item.getAttribute('data-room-num');
            const textContent = item.textContent.toLowerCase();

            let matchCategory = false;
            if (activeFilter === 'all') {
                matchCategory = true;
            } else if (activeFilter === 'available-only') {
                matchCategory = (avail === '1');
            } else {
                matchCategory = (type === activeFilter);
            }

            let matchQuery = query === '' || textContent.includes(query) || roomNum.includes(query);

            if (matchCategory && matchQuery) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => {
                t.classList.remove('active');
                t.style.background = 'transparent';
                t.style.color = '#FFDF73';
                t.style.borderColor = '#D4AF37';
            });

            this.classList.add('active');
            this.style.background = '#D4AF37';
            this.style.color = '#0F172A';

            activeFilter = this.getAttribute('data-filter');
            filterGrid();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', filterGrid);
    }
});
</script>

<?php renderSiteLayoutEnd(); ?>
