<?php

declare(strict_types=1);

/**
 * Renders the Interactive 2D Hotel Floor Map Component
 * 
 * @param PDO $db
 * @param string $mode 'public' (room picker) or 'admin' (status manager)
 * @param array $selectedRoomId
 */
function renderHotelFloorMap(PDO $db, string $mode = 'public', ?int $selectedRoomId = null): void
{
    $roomModel = new Room($db);
    $rooms = $roomModel->all();
    $reviewModel = new Review($db);

    // Group rooms by floor
    $floors = [];
    foreach ($rooms as $room) {
        $floors[$room['floor']][] = $room;
    }
    ksort($floors);
?>
<div class="hotel-map-container my-4 p-4 rounded-4 shadow-sm border" style="background: rgba(15, 23, 42, 0.6); border-color: rgba(212, 175, 55, 0.25) !important;">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
            <h5 class="m-0 text-gold font-serif fw-bold"><i class="bi bi-diagram-3-fill me-2"></i>Interactive Hotel Floor Map</h5>
            <small class="text-muted">Click any room block to inspect details or select for booking</small>
        </div>
        <!-- Status Legend -->
        <div class="d-flex flex-wrap gap-2 text-xs small">
            <span class="badge bg-success bg-opacity-90 px-2 py-1"><i class="bi bi-circle-fill me-1"></i>Available</span>
            <span class="badge bg-primary px-2 py-1"><i class="bi bi-circle-fill me-1"></i>Reserved</span>
            <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-circle-fill me-1"></i>Occupied</span>
            <span class="badge style-purple-badge px-2 py-1" style="background-color: #8b5cf6; color: white;"><i class="bi bi-circle-fill me-1"></i>Cleaning</span>
            <span class="badge bg-danger px-2 py-1"><i class="bi bi-circle-fill me-1"></i>Maintenance</span>
        </div>
    </div>

    <!-- Floor Tabs -->
    <ul class="nav nav-pills mb-3 border-bottom border-secondary pb-2" id="hotelMapFloorTabs" role="tablist">
        <?php foreach ($floors as $floorNum => $floorRooms): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm <?= $floorNum === 1 ? 'active' : '' ?> rounded-pill px-3 py-1 me-2 fw-semibold" 
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
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3">
                    <?php foreach ($floorRooms as $room): 
                        $statusClass = match ($room['status']) {
                            'Available' => 'border-success text-success bg-success-subtle',
                            'Reserved' => 'border-primary text-primary bg-primary-subtle',
                            'Occupied' => 'border-warning text-warning-emphasis bg-warning-subtle',
                            'Cleaning' => 'border-info text-info-emphasis bg-info-subtle',
                            'Maintenance' => 'border-danger text-danger bg-danger-subtle',
                            default => 'border-secondary text-secondary',
                        };

                        $badgeColor = match ($room['status']) {
                            'Available' => 'bg-success',
                            'Reserved' => 'bg-primary',
                            'Occupied' => 'bg-warning text-dark',
                            'Cleaning' => 'bg-info text-white',
                            'Maintenance' => 'bg-danger',
                            default => 'bg-secondary',
                        };

                        $isSelected = ($selectedRoomId !== null && (int)$room['room_id'] === $selectedRoomId);
                    ?>
                        <div class="col">
                            <div class="card h-100 room-map-card <?= $statusClass ?> <?= $isSelected ? 'ring-2 ring-gold' : '' ?> transition-all" 
                                 style="cursor: pointer; border-width: 2px;"
                                 data-room-id="<?= $room['room_id'] ?>"
                                 data-room-number="<?= $room['room_number'] ?>"
                                 data-room-type="<?= e($room['room_type']) ?>"
                                 data-room-price="<?= number_format((float)$room['price_per_night'], 2) ?>"
                                 data-room-status="<?= $room['status'] ?>"
                                 onclick="onHotelMapRoomClick(<?= (int)$room['room_id'] ?>, '<?= e($room['room_number']) ?>', '<?= e($room['room_type']) ?>', '<?= number_format((float)$room['price_per_night'], 2) ?>', '<?= $room['status'] ?>')">
                                <div class="card-body p-2 text-center">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge <?= $badgeColor ?> text-xs px-2 py-0.5 rounded-pill"><?= $room['status'] ?></span>
                                        <small class="text-muted fw-bold">#<?= e($room['room_number']) ?></small>
                                    </div>
                                    <h6 class="card-title font-serif text-gold mb-1 text-truncate" style="font-size: 0.85rem;"><?= e($room['room_type']) ?></h6>
                                    <div class="small fw-semibold">₱<?= number_format((float)$room['price_per_night'], 0) ?><span class="text-muted text-xs">/night</span></div>
                                    <div class="text-xs text-muted mt-1">
                                        <i class="bi bi-square-fill me-1"></i><?= e($room['bed_type'] ?? 'King Bed') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function onHotelMapRoomClick(roomId, roomNumber, roomType, price, status) {
    if (typeof selectRoomFromCard === 'function') {
        selectRoomFromCard(roomId, roomType, price);
    }
    
    const roomCard = document.querySelector(`.room-card[data-room-id="${roomId}"]`);
    if (roomCard) {
        roomCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        roomCard.classList.add('highlight-pulse');
        setTimeout(() => roomCard.classList.remove('highlight-pulse'), 1500);
    }

    if (typeof openAdminRoomStatusModal === 'function') {
        openAdminRoomStatusModal(roomId, roomNumber, roomType, status);
    }
}
</script>
<?php
}
