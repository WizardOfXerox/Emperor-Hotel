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
<div class="hotel-map-container my-4 p-4 rounded-4 shadow-lg border" style="background: rgba(7, 10, 16, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(212, 175, 55, 0.35) !important;">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2 border-bottom border-secondary pb-3">
        <div>
            <h4 class="m-0 text-gold font-serif fw-bold"><i class="bi bi-diagram-3-fill me-2"></i>Interactive Hotel Floor Map</h4>
            <small class="text-muted">Click any room block to inspect details or select for booking</small>
        </div>
        <!-- Status Legend -->
        <div class="d-flex flex-wrap gap-2 text-xs small">
            <span class="badge px-3 py-2 rounded-pill shadow-sm" style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.5); color: #34D399;"><i class="bi bi-circle-fill me-1"></i>Available</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm" style="background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.5); color: #60A5FA;"><i class="bi bi-circle-fill me-1"></i>Reserved</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm" style="background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.5); color: #FBBF24;"><i class="bi bi-circle-fill me-1"></i>Occupied</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm" style="background: rgba(168, 85, 247, 0.2); border: 1px solid rgba(168, 85, 247, 0.5); color: #C084FC;"><i class="bi bi-circle-fill me-1"></i>Cleaning</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm" style="background: rgba(244, 63, 94, 0.2); border: 1px solid rgba(244, 63, 94, 0.5); color: #FB7185;"><i class="bi bi-circle-fill me-1"></i>Maintenance</span>
        </div>
    </div>

    <!-- Floor Tabs -->
    <ul class="nav nav-pills mb-4 border-bottom border-secondary pb-3 gap-2" id="hotelMapFloorTabs" role="tablist">
        <?php foreach ($floors as $floorNum => $floorRooms): 
            $isActive = ($floorNum === 1);
        ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm <?= $isActive ? 'active' : '' ?> rounded-pill px-4 py-2 fw-bold font-serif floor-tab-btn" 
                        id="map-tab-floor-<?= $floorNum ?>" 
                        data-bs-toggle="tab" 
                        data-bs-target="#map-pane-floor-<?= $floorNum ?>" 
                        type="button" 
                        role="tab"
                        style="<?= $isActive ? 'background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%) !important; color: #070A10 !important; border: none; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);' : 'background: rgba(255, 255, 255, 0.05); color: #CBD5E1; border: 1px solid rgba(212, 175, 55, 0.25);' ?>">
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
                        $statusBadgeStyle = match ($room['status']) {
                            'Available' => 'background: rgba(16, 185, 129, 0.25); border: 1px solid rgba(16, 185, 129, 0.6); color: #34D399;',
                            'Reserved' => 'background: rgba(59, 130, 246, 0.25); border: 1px solid rgba(59, 130, 246, 0.6); color: #60A5FA;',
                            'Occupied' => 'background: rgba(245, 158, 11, 0.25); border: 1px solid rgba(245, 158, 11, 0.6); color: #FBBF24;',
                            'Cleaning' => 'background: rgba(168, 85, 247, 0.25); border: 1px solid rgba(168, 85, 247, 0.6); color: #C084FC;',
                            'Maintenance' => 'background: rgba(244, 63, 94, 0.25); border: 1px solid rgba(244, 63, 94, 0.6); color: #FB7185;',
                            default => 'background: rgba(148, 163, 184, 0.2); color: #94A3B8;',
                        };

                        $cardBgStyle = match ($room['status']) {
                            'Available' => 'background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3);',
                            'Reserved' => 'background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.3);',
                            'Occupied' => 'background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3);',
                            'Cleaning' => 'background: rgba(168, 85, 247, 0.08); border: 1px solid rgba(168, 85, 247, 0.3);',
                            'Maintenance' => 'background: rgba(244, 63, 94, 0.08); border: 1px solid rgba(244, 63, 94, 0.3);',
                            default => 'background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(212, 175, 55, 0.2);',
                        };

                        $isSelected = ($selectedRoomId !== null && (int)$room['room_id'] === $selectedRoomId);
                    ?>
                        <div class="col">
                            <div class="card h-100 room-map-card rounded-4 p-2 transition-all shadow-sm <?= $isSelected ? 'border-warning shadow-lg' : '' ?>" 
                                 style="<?= $cardBgStyle ?> cursor: pointer;"
                                 data-room-id="<?= $room['room_id'] ?>"
                                 data-room-number="<?= $room['room_number'] ?>"
                                 data-room-type="<?= e($room['room_type']) ?>"
                                 data-room-price="<?= number_format((float)$room['price_per_night'], 2) ?>"
                                 data-room-status="<?= $room['status'] ?>"
                                 onclick="onHotelMapRoomClick(<?= (int)$room['room_id'] ?>, '<?= e($room['room_number']) ?>', '<?= e($room['room_type']) ?>', '<?= number_format((float)$room['price_per_night'], 2) ?>', '<?= $room['status'] ?>')">
                                <div class="card-body p-2 text-center">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="badge text-xs px-2 py-1 rounded-pill" style="<?= $statusBadgeStyle ?>"><?= $room['status'] ?></span>
                                        <small class="text-gold fw-bold font-serif">#<?= e($room['room_number']) ?></small>
                                    </div>
                                    <h6 class="card-title font-serif text-light fw-bold mb-1 text-truncate" style="font-size: 0.88rem;"><?= e($room['room_type']) ?></h6>
                                    <div class="small text-gold fw-bold">₱<?= number_format((float)$room['price_per_night'], 0) ?><span class="text-muted text-xs">/night</span></div>
                                    <div class="text-xs text-muted mt-2">
                                        <i class="bi bi-square-fill text-gold me-1"></i><?= e($room['bed_type'] ?? 'King Bed') ?>
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

<style>
.floor-tab-btn.active {
    background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%) !important;
    color: #070A10 !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4) !important;
}
.room-map-card {
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.room-map-card:hover {
    transform: translateY(-4px);
    border-color: #D4AF37 !important;
    box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3) !important;
}
</style>

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

document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('#hotelMapFloorTabs .nav-link');
    tabButtons.forEach(btn => {
        btn.addEventListener('shown.bs.tab', (e) => {
            tabButtons.forEach(b => {
                b.style.background = 'rgba(255, 255, 255, 0.05)';
                b.style.color = '#CBD5E1';
                b.style.border = '1px solid rgba(212, 175, 55, 0.25)';
                b.style.boxShadow = 'none';
            });
            e.target.style.background = 'linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%)';
            e.target.style.color = '#070A10';
            e.target.style.border = 'none';
            e.target.style.boxShadow = '0 4px 15px rgba(212, 175, 55, 0.4)';
        });
    });
});
</script>
<?php
}
