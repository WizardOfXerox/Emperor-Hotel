<?php

declare(strict_types=1);

/**
 * Renders the Interactive 2D Hotel Floor Map Component
 * 
 * @param PDO $db
 * @param string $mode 'public' (room picker) or 'admin' (status manager)
 * @param array $selectedRoomId
 */
function renderHotelFloorMap(PDO $db, string $mode = 'public', ?int $selectedRoomId = null, string $checkIn = '', string $checkOut = ''): void
{
    $roomModel = new Room($db);
    $rooms = $roomModel->all();

    // Check reservations for active date range if provided
    $bookedRoomStatuses = [];
    if (!empty($checkIn) && !empty($checkOut)) {
        $stmt = $db->prepare("
            SELECT room_id, status
            FROM reservations
            WHERE status NOT IN ('Cancelled', 'Checked-out')
              AND NOT (check_out <= :check_in OR check_in >= :check_out)
        ");
        $stmt->execute(['check_in' => $checkIn, 'check_out' => $checkOut]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bookedRoomStatuses[(int)$row['room_id']] = $row['status'] === 'Checked-in' ? 'Occupied' : 'Reserved';
        }
    }

    // Apply calculated status per room
    foreach ($rooms as &$r) {
        $rId = (int)$r['room_id'];
        if ($r['status'] !== 'Maintenance') {
            if (isset($bookedRoomStatuses[$rId])) {
                $r['status'] = $bookedRoomStatuses[$rId];
            } else if (!empty($checkIn) && !empty($checkOut)) {
                $r['status'] = 'Available';
            }
        }
    }
    unset($r);

    // Group rooms by floor
    $floors = [];
    foreach ($rooms as $room) {
        $floors[$room['floor']][] = $room;
    }
    ksort($floors);
?>
<div class="hotel-map-container h-100 my-0 p-4 rounded-4 shadow-lg border" id="hotelMapContainer">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2 border-bottom pb-3">
        <div>
            <h4 class="m-0 font-serif fw-bold map-title"><i class="bi bi-diagram-3-fill me-2 text-warning"></i>Rooms Availability</h4>
            <small class="text-muted opacity-90 fw-semibold" id="mapActiveRangeSubtitle">
                <?php if (!empty($checkIn) && !empty($checkOut)): ?>
                    <span class="badge bg-gold text-dark px-2 py-1 me-1" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10;"><i class="bi bi-calendar-check me-1"></i><?= e($checkIn) ?> to <?= e($checkOut) ?></span> Live availability for stay dates.
                <?php else: ?>
                    Click any room block to inspect details or select for booking
                <?php endif; ?>
            </small>
        </div>
        <!-- Status Legend -->
        <div class="d-flex flex-wrap gap-2 text-xs small">
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold map-legend-available"><i class="bi bi-circle-fill me-1"></i>Available</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold map-legend-reserved"><i class="bi bi-circle-fill me-1"></i>Reserved</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold map-legend-occupied"><i class="bi bi-circle-fill me-1"></i>Occupied</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold map-legend-cleaning"><i class="bi bi-circle-fill me-1"></i>Cleaning</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold map-legend-maintenance"><i class="bi bi-circle-fill me-1"></i>Maintenance</span>
        </div>
    </div>

    <!-- Floor Tabs -->
    <ul class="nav nav-pills mb-4 border-bottom pb-3 gap-2 align-items-center" id="hotelMapFloorTabs" role="tablist">
        <?php foreach ($floors as $floorNum => $floorRooms): 
            $isActive = ($floorNum === 1);
        ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm <?= $isActive ? 'active' : '' ?> rounded-pill px-4 py-2 fw-bold font-serif floor-tab-btn" 
                        id="map-tab-floor-<?= $floorNum ?>" 
                        data-bs-toggle="tab" 
                        data-bs-target="#map-pane-floor-<?= $floorNum ?>" 
                        type="button" 
                        role="tab">
                    Floor <?= $floorNum ?> (<?= count($floorRooms) ?> Rooms)
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Floor Tab Panes -->
    <div class="tab-content" id="hotelMapFloorContent">
        <?php foreach ($floors as $floorNum => $floorRooms): ?>
            <div class="tab-pane fade <?= $floorNum === 1 ? 'show active' : '' ?>" id="map-pane-floor-<?= $floorNum ?>" role="tabpanel">
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-3 row-cols-lg-4 row-cols-xl-4 row-cols-xxl-4 g-2 g-sm-3">
                    <?php foreach ($floorRooms as $room): 
                        $statusClass = 'room-map-card--' . strtolower($room['status']);
                        $isSelected = ($selectedRoomId !== null && (int)$room['room_id'] === $selectedRoomId);
                    ?>
                        <div class="col">
                            <div class="card h-100 room-map-card <?= $statusClass ?> rounded-4 p-2 transition-all shadow <?= $isSelected ? 'border-warning shadow-lg' : '' ?>" 
                                 style="cursor: pointer;"
                                 data-room-id="<?= $room['room_id'] ?>"
                                 data-room-number="<?= $room['room_number'] ?>"
                                 data-room-type="<?= e($room['room_type']) ?>"
                                 data-room-price="<?= number_format((float)$room['price_per_night'], 2) ?>"
                                 data-room-status="<?= $room['status'] ?>"
                                 onclick="onHotelMapRoomClick(<?= (int)$room['room_id'] ?>, '<?= e($room['room_number']) ?>', '<?= e($room['room_type']) ?>', '<?= number_format((float)$room['price_per_night'], 2) ?>', '<?= $room['status'] ?>', '<?= e($mode) ?>')">
                                <div class="card-body p-2 text-center">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="badge text-xs px-2 py-1 rounded-pill fw-bold room-status-badge"><?= $room['status'] ?></span>
                                        <small class="fw-bold font-serif room-number-tag">#<?= e($room['room_number']) ?></small>
                                    </div>
                                    <h6 class="card-title font-serif fw-bold mb-1 text-wrap room-type-title" style="font-size: 0.85rem; line-height: 1.25;"><?= e($room['room_type']) ?></h6>
                                    <div class="text-xs fw-bold room-price-tag">₱<?= number_format((float)$room['price_per_night']) ?><span class="opacity-75 font-sans fw-normal">/night</span></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Room Inspector & Booking Modal -->
<div class="modal fade" id="roomInspectorModal" tabindex="-1" aria-labelledby="roomInspectorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
    <div class="modal-content rounded-4 shadow-lg border" id="roomInspectorModalContent">
      <div class="modal-header border-bottom px-4 py-3">
        <div>
            <h4 class="font-serif fw-bold m-0" id="inspectorRoomTitle" style="color: #b45309;"><i class="bi bi-door-open me-2"></i>Room Inspection</h4>
            <p class="text-muted text-xs m-0 fw-semibold" id="inspectorRoomSub">Loading room details...</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="roomInspectorModalBody">
        <div class="text-center py-4">
            <div class="spinner-border text-warning" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted small mt-2">Fetching guest & reservation data...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.hotel-map-container {
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(25px);
    border: 1px solid rgba(212, 175, 55, 0.45) !important;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5) !important;
}

body.light-mode .hotel-map-container {
    background: #ffffff !important;
    border: 1px solid rgba(180, 140, 60, 0.2) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05) !important;
    color: #0f172a !important;
}

.map-title {
    color: #FFDF73 !important;
}

body.light-mode .map-title {
    color: #b45309 !important;
}

.map-legend-available { background: rgba(16, 185, 129, 0.28); border: 1.5px solid #10B981; color: #6EE7B7; }
.map-legend-reserved  { background: rgba(59, 130, 246, 0.28); border: 1.5px solid #3B82F6; color: #93C5FD; }
.map-legend-occupied  { background: rgba(245, 158, 11, 0.28); border: 1.5px solid #F59E0B; color: #FDE68A; }
.map-legend-cleaning  { background: rgba(168, 85, 247, 0.28); border: 1.5px solid #A855F7; color: #E9D5FF; }
.map-legend-maintenance { background: rgba(244, 63, 94, 0.28); border: 1.5px solid #F43F5E; color: #FECDD3; }

body.light-mode .map-legend-available { background: #d1fae5; border-color: #10b981; color: #065f46; }
body.light-mode .map-legend-reserved  { background: #dbeafe; border-color: #3b82f6; color: #1e40af; }
body.light-mode .map-legend-occupied  { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
body.light-mode .map-legend-cleaning  { background: #f3e8ff; border-color: #a855f7; color: #6b21a8; }
body.light-mode .map-legend-maintenance { background: #ffe4e6; border-color: #f43f5e; color: #9f1239; }

.floor-tab-btn {
    background: rgba(30, 41, 59, 0.8);
    color: #F1F5F9;
    border: 1px solid rgba(212, 175, 55, 0.4);
}

body.light-mode .floor-tab-btn:not(.active) {
    background: #f1f5f9 !important;
    color: #334155 !important;
    border: 1px solid #cbd5e1 !important;
}

.floor-tab-btn.active {
    background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%) !important;
    color: #070A10 !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4) !important;
}

body.light-mode .floor-tab-btn.active {
    background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 15px rgba(217, 119, 6, 0.3) !important;
}

/* Room Map Cards */
.room-map-card {
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    background: rgba(30, 41, 59, 0.85);
    border: 1px solid rgba(212, 175, 55, 0.3);
    color: #ffffff;
}

.room-type-title { color: #ffffff !important; }
.room-number-tag { color: #FFDF73 !important; }
.room-price-tag { color: #FBBF24 !important; }

body.light-mode .room-map-card {
    background: #f8f6f0 !important;
    border: 1px solid rgba(180, 140, 60, 0.22) !important;
}

body.light-mode .room-type-title { color: #0f172a !important; }
body.light-mode .room-number-tag { color: #b45309 !important; }
body.light-mode .room-price-tag { color: #d97706 !important; }

.room-map-card:hover {
    transform: translateY(-4px);
    border-color: #D4AF37 !important;
    box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3) !important;
}

body.light-mode .room-map-card:hover {
    border-color: #d97706 !important;
    box-shadow: 0 10px 25px rgba(217, 119, 6, 0.2) !important;
    background: #fffbeb !important;
}

.room-map-card--available   .room-status-badge { background: rgba(16, 185, 129, 0.35); border: 1px solid #10B981; color: #A7F3D0; }
.room-map-card--reserved    .room-status-badge { background: rgba(59, 130, 246, 0.35); border: 1px solid #3B82F6; color: #BFDBFE; }
.room-map-card--occupied    .room-status-badge { background: rgba(245, 158, 11, 0.35); border: 1px solid #F59E0B; color: #FDE68A; }
.room-map-card--cleaning    .room-status-badge { background: rgba(168, 85, 247, 0.35); border: 1px solid #A855F7; color: #DDD6FE; }
.room-map-card--maintenance .room-status-badge { background: rgba(244, 63, 94, 0.35); border: 1px solid #F43F5E; color: #FECDD3; }

body.light-mode .room-map-card--available   .room-status-badge { background: #d1fae5; border-color: #10b981; color: #065f46; }
body.light-mode .room-map-card--reserved    .room-status-badge { background: #dbeafe; border-color: #3b82f6; color: #1e40af; }
body.light-mode .room-map-card--occupied    .room-status-badge { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
body.light-mode .room-map-card--cleaning    .room-status-badge { background: #f3e8ff; border-color: #a855f7; color: #6b21a8; }
body.light-mode .room-map-card--maintenance .room-status-badge { background: #ffe4e6; border-color: #f43f5e; color: #9f1239; }
</style>

<script>
async function onHotelMapRoomClick(roomId, roomNumber, roomType, price, status, mode = 'public') {
    if (mode !== 'admin') {
        if (typeof selectRoomFromCard === 'function') {
            selectRoomFromCard(roomId, roomType, price);
            const roomCard = document.querySelector(`.room-card[data-room-id="${roomId}"]`);
            if (roomCard) {
                roomCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                roomCard.classList.add('highlight-pulse');
                setTimeout(() => roomCard.classList.remove('highlight-pulse'), 1500);
                return;
            }
        }

        const checkIn = document.getElementById('modalCheckInInput')?.value || '';
        const checkOut = document.getElementById('modalCheckOutInput')?.value || '';
        let url = `room-detail.php?id=${roomId}`;
        if (checkIn && checkOut) {
            url += `&check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`;
        }
        window.location.href = url;
        return;
    }

    const modalEl = document.getElementById('roomInspectorModal');
    if (!modalEl) return;

    const modalTitle = document.getElementById('inspectorRoomTitle');
    const modalSub = document.getElementById('inspectorRoomSub');
    const modalBody = document.getElementById('roomInspectorModalBody');

    if (modalTitle) modalTitle.innerHTML = `<i class="bi bi-door-open me-2"></i>Room #${roomNumber}`;
    if (modalSub) modalSub.textContent = `${roomType} — Floor ${Math.floor(roomId / 100) || 1} (₱${price}/night)`;

    if (modalBody) {
        modalBody.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading...</span></div>
                <p class="text-muted small mt-2">Loading reservation & guest details for Room #${roomNumber}...</p>
            </div>
        `;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    try {
        let endpoint = 'room-details-api.php';
        if (window.location.pathname.includes('/site/')) {
            endpoint = '../admin/room-details-api.php';
        } else if (!window.location.pathname.includes('/admin/')) {
            endpoint = 'admin/room-details-api.php';
        }

        const res = await fetch(`${endpoint}?room_id=${roomId}`);
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load room details');
        }

        const room = data.room;
        const reservation = data.reservation;
        const isAdmin = window.location.pathname.includes('/admin/');

        let html = `
            <div class="d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 inspector-info-box">
                <div>
                    <span class="text-muted small d-block">Room Category & Floor</span>
                    <strong class="fs-6">${room.room_type} (Floor ${room.floor})</strong>
                </div>
                <div class="text-end">
                    <span class="text-muted small d-block">Daily Rate</span>
                    <strong class="text-warning fs-6">₱${Number(room.price_per_night).toLocaleString()}/night</strong>
                </div>
            </div>
        `;

        if (data.has_reservation && reservation) {
            html += `
                <div class="panel-card p-3 mb-3 border rounded-3 inspector-booking-card">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="m-0 font-serif fw-bold"><i class="bi bi-person-badge me-2 text-warning"></i>Active Guest Booking</h6>
                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold">${reservation.status}</span>
                    </div>
                    <hr class="my-2 opacity-25">
                    <div class="row g-2 text-sm inspector-booking-text">
                        <div class="col-6"><strong>Guest:</strong> ${reservation.guest_name}</div>
                        <div class="col-6"><strong>Phone:</strong> ${reservation.guest_phone}</div>
                        <div class="col-12"><strong>Email:</strong> ${reservation.guest_email}</div>
                        <div class="col-6"><strong>Check-In:</strong> ${reservation.check_in}</div>
                        <div class="col-6"><strong>Check-Out:</strong> ${reservation.check_out}</div>
                        <div class="col-6"><strong>Total Amount:</strong> ${reservation.formatted_total}</div>
                        <div class="col-6"><strong>Payment:</strong> ${reservation.payment_method} (${reservation.payment_status})</div>
                    </div>
                    ${isAdmin ? `
                    <div class="d-flex gap-2 mt-3">
                        <a href="reservations.php?search=${encodeURIComponent(room.room_number)}" class="btn btn-warning btn-sm fw-bold flex-grow-1"><i class="bi bi-sliders me-1"></i>Manage Reservation</a>
                        <a href="payments.php?search=${encodeURIComponent(room.room_number)}" class="btn btn-outline-warning btn-sm fw-semibold"><i class="bi bi-credit-card me-1"></i>Payments</a>
                    </div>
                    ` : ''}
                </div>
            `;
        } else {
            html += `
                <div class="alert alert-success border-success text-center p-3 mb-3 rounded-3">
                    <i class="bi bi-check-circle-fill fs-4 text-success d-block mb-1"></i>
                    <strong>Room #${room.room_number} is Currently Available</strong>
                    <p class="small m-0 mt-1 opacity-75">No active reservations exist for this room.</p>
                </div>
                ${isAdmin ? `
                <a href="create-reservation.php?room_id=${room.room_id}" class="btn btn-warning fw-bold w-100 py-2 mb-3 shadow-sm"><i class="bi bi-plus-circle me-1"></i>Create Reservation for Room #${room.room_number}</a>
                ` : `
                <a href="user-booking.php?room_id=${room.room_id}" class="btn btn-warning fw-bold w-100 py-2 mb-3 shadow-sm"><i class="bi bi-calendar-plus me-1"></i>Book Room #${room.room_number}</a>
                `}
            `;
        }

        if (isAdmin) {
            html += `
                <form method="post" action="rooms.php" class="border-top pt-3 mt-2">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="room_id" value="${room.room_id}">
                    <label class="form-label fw-bold small opacity-75">Change Room Operational Status</label>
                    <div class="input-group">
                        <select name="status" class="form-select form-select-sm inspector-status-select">
                            <option value="Available" ${room.status === 'Available' ? 'selected' : ''}>Available</option>
                            <option value="Reserved" ${room.status === 'Reserved' ? 'selected' : ''}>Reserved</option>
                            <option value="Occupied" ${room.status === 'Occupied' ? 'selected' : ''}>Occupied</option>
                            <option value="Cleaning" ${room.status === 'Cleaning' ? 'selected' : ''}>Cleaning</option>
                            <option value="Maintenance" ${room.status === 'Maintenance' ? 'selected' : ''}>Maintenance</option>
                        </select>
                        <button type="submit" class="btn btn-outline-warning btn-sm fw-semibold">Save Status</button>
                    </div>
                </form>
            `;
        }

        modalBody.innerHTML = html;
    } catch (err) {
        modalBody.innerHTML = `
            <div class="alert alert-danger p-3 mb-0">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>${err.message || 'Unable to load room inspection details.'}
            </div>
        `;
    }
}

async function updateHotelMapAvailability(checkIn, checkOut) {
    if (!checkIn || !checkOut) return;
    try {
        let endpoint = 'map_availability.php';
        if (window.location.pathname.includes('/admin/')) {
            endpoint = '../site/map_availability.php';
        } else if (window.location.pathname.includes('/site/')) {
            endpoint = 'map_availability.php';
        }
        const response = await fetch(`${endpoint}?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`);
        const data = await response.json();
        if (!data.success || !Array.isArray(data.rooms)) return;

        data.rooms.forEach(r => {
            const card = document.querySelector(`.room-map-card[data-room-id="${r.room_id}"]`);
            if (card) {
                card.setAttribute('data-room-status', r.status);
                card.className = `card h-100 room-map-card room-map-card--${r.status.toLowerCase()} rounded-4 p-2 transition-all shadow`;
                const badge = card.querySelector('.room-status-badge');
                if (badge) {
                    badge.textContent = r.status;
                }
            }
        });

        const activeSub = document.getElementById('mapActiveRangeSubtitle');
        if (activeSub) {
            activeSub.innerHTML = `<span class="badge bg-gold text-dark px-2 py-1 me-1" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10;"><i class="bi bi-calendar-check me-1"></i>${checkIn} to ${checkOut}</span> Showing live room availability for stay dates.`;
        }
    } catch (err) {
        console.error("Map availability update failed:", err);
    }
}
</script>
<?php
}
?>
