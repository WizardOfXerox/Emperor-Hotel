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
<div class="hotel-map-container h-100 my-0 p-4 rounded-4 shadow-lg border" style="background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5) !important;">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2 border-bottom border-secondary pb-3">
        <div>
            <h4 class="m-0 text-gold font-serif fw-bold" style="color: #FFDF73 !important; text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);"><i class="bi bi-diagram-3-fill me-2"></i>Rooms Availability</h4>
            <small class="text-light opacity-90 fw-semibold" id="mapActiveRangeSubtitle">
                <?php if (!empty($checkIn) && !empty($checkOut)): ?>
                    <span class="badge bg-gold text-dark px-2 py-1 me-1" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10;"><i class="bi bi-calendar-check me-1"></i><?= e($checkIn) ?> to <?= e($checkOut) ?></span> Live availability for stay dates.
                <?php else: ?>
                    Click any room block to inspect details or select for booking
                <?php endif; ?>
            </small>
        </div>
        <!-- Status Legend -->
        <div class="d-flex flex-wrap gap-2 text-xs small">
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold" style="background: rgba(16, 185, 129, 0.28); border: 1.5px solid #10B981; color: #6EE7B7;"><i class="bi bi-circle-fill me-1"></i>Available</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold" style="background: rgba(59, 130, 246, 0.28); border: 1.5px solid #3B82F6; color: #93C5FD;"><i class="bi bi-circle-fill me-1"></i>Reserved</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold" style="background: rgba(245, 158, 11, 0.28); border: 1.5px solid #F59E0B; color: #FDE68A;"><i class="bi bi-circle-fill me-1"></i>Occupied</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold" style="background: rgba(168, 85, 247, 0.28); border: 1.5px solid #A855F7; color: #E9D5FF;"><i class="bi bi-circle-fill me-1"></i>Cleaning</span>
            <span class="badge px-3 py-2 rounded-pill shadow-sm fw-bold" style="background: rgba(244, 63, 94, 0.28); border: 1.5px solid #F43F5E; color: #FECDD3;"><i class="bi bi-circle-fill me-1"></i>Maintenance</span>
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
                        style="<?= $isActive ? 'background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%) !important; color: #070A10 !important; border: none; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.5);' : 'background: rgba(30, 41, 59, 0.8); color: #F1F5F9; border: 1px solid rgba(212, 175, 55, 0.4);' ?>">
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
                            'Available' => 'background: rgba(16, 185, 129, 0.35); border: 1px solid #10B981; color: #A7F3D0;',
                            'Reserved' => 'background: rgba(59, 130, 246, 0.35); border: 1px solid #3B82F6; color: #BFDBFE;',
                            'Occupied' => 'background: rgba(245, 158, 11, 0.35); border: 1px solid #F59E0B; color: #FDE68A;',
                            'Cleaning' => 'background: rgba(168, 85, 247, 0.35); border: 1px solid #A855F7; color: #DDD6FE;',
                            'Maintenance' => 'background: rgba(244, 63, 94, 0.35); border: 1px solid #F43F5E; color: #FECDD3;',
                            default => 'background: rgba(148, 163, 184, 0.3); color: #F1F5F9;',
                        };

                        $cardBgStyle = match ($room['status']) {
                            'Available' => 'background: linear-gradient(145deg, rgba(16, 185, 129, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(16, 185, 129, 0.45);',
                            'Reserved' => 'background: linear-gradient(145deg, rgba(59, 130, 246, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(59, 130, 246, 0.45);',
                            'Occupied' => 'background: linear-gradient(145deg, rgba(245, 158, 11, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(245, 158, 11, 0.45);',
                            'Cleaning' => 'background: linear-gradient(145deg, rgba(168, 85, 247, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(168, 85, 247, 0.45);',
                            'Maintenance' => 'background: linear-gradient(145deg, rgba(244, 63, 94, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(244, 63, 94, 0.45);',
                            default => 'background: rgba(30, 41, 59, 0.85); border: 1px solid rgba(212, 175, 55, 0.3);',
                        };

                        $isSelected = ($selectedRoomId !== null && (int)$room['room_id'] === $selectedRoomId);
                    ?>
                        <div class="col">
                            <div class="card h-100 room-map-card rounded-4 p-2 transition-all shadow <?= $isSelected ? 'border-warning shadow-lg' : '' ?>" 
                                 style="<?= $cardBgStyle ?> cursor: pointer;"
                                 data-room-id="<?= $room['room_id'] ?>"
                                 data-room-number="<?= $room['room_number'] ?>"
                                 data-room-type="<?= e($room['room_type']) ?>"
                                 data-room-price="<?= number_format((float)$room['price_per_night'], 2) ?>"
                                 data-room-status="<?= $room['status'] ?>"
                                 onclick="onHotelMapRoomClick(<?= (int)$room['room_id'] ?>, '<?= e($room['room_number']) ?>', '<?= e($room['room_type']) ?>', '<?= number_format((float)$room['price_per_night'], 2) ?>', '<?= $room['status'] ?>')">
                                <div class="card-body p-2 text-center">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="badge text-xs px-2 py-1 rounded-pill fw-bold" style="<?= $statusBadgeStyle ?>"><?= $room['status'] ?></span>
                                        <small class="fw-bold font-serif" style="color: #FFDF73;">#<?= e($room['room_number']) ?></small>
                                    </div>
                                    <h6 class="card-title font-serif text-white fw-bold mb-1 text-truncate" style="font-size: 0.9rem; text-shadow: 0 1px 4px rgba(0,0,0,0.6);"><?= e($room['room_type']) ?></h6>
                                    <div class="text-xs fw-bold" style="color: #FBBF24;">₱<?= number_format((float)$room['price_per_night']) ?><span class="opacity-75 text-light font-sans fw-normal">/night</span></div>
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
    if (typeof openAdminRoomStatusModal === 'function') {
        openAdminRoomStatusModal(roomId, roomNumber, roomType, status);
        return;
    }

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

        const badgeStyle = {
            'Available': 'background: rgba(16, 185, 129, 0.35); border: 1px solid #10B981; color: #A7F3D0;',
            'Reserved': 'background: rgba(59, 130, 246, 0.35); border: 1px solid #3B82F6; color: #BFDBFE;',
            'Occupied': 'background: rgba(245, 158, 11, 0.35); border: 1px solid #F59E0B; color: #FDE68A;',
            'Cleaning': 'background: rgba(168, 85, 247, 0.35); border: 1px solid #A855F7; color: #DDD6FE;',
            'Maintenance': 'background: rgba(244, 63, 94, 0.35); border: 1px solid #F43F5E; color: #FECDD3;'
        };

        const cardStyle = {
            'Available': 'background: linear-gradient(145deg, rgba(16, 185, 129, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(16, 185, 129, 0.45);',
            'Reserved': 'background: linear-gradient(145deg, rgba(59, 130, 246, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(59, 130, 246, 0.45);',
            'Occupied': 'background: linear-gradient(145deg, rgba(245, 158, 11, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(245, 158, 11, 0.45);',
            'Cleaning': 'background: linear-gradient(145deg, rgba(168, 85, 247, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(168, 85, 247, 0.45);',
            'Maintenance': 'background: linear-gradient(145deg, rgba(244, 63, 94, 0.18) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1.5px solid rgba(244, 63, 94, 0.45);'
        };

        data.rooms.forEach(r => {
            const card = document.querySelector(`.room-map-card[data-room-id="${r.room_id}"]`);
            if (card) {
                card.setAttribute('data-room-status', r.status);
                if (cardStyle[r.status]) {
                    card.style.cssText = cardStyle[r.status] + ' cursor: pointer;';
                }
                const badge = card.querySelector('.badge');
                if (badge) {
                    badge.textContent = r.status;
                    if (badgeStyle[r.status]) {
                        badge.style.cssText = badgeStyle[r.status];
                    }
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
?>
