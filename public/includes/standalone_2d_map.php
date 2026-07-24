<?php

declare(strict_types=1);

require_once __DIR__ . '/room_catalog.php';

/**
 * Standalone 2D Architectural Blueprint Floor Plan Component
 * Self-contained renderer for map.php (does NOT alter hotel_map.php)
 * 
 * @param PDO $db
 * @param string $mode 'public' or 'admin'
 * @param string $checkIn
 * @param string $checkOut
 */
function renderStandalone2DMap(PDO $db, string $mode = 'public', string $checkIn = '', string $checkOut = ''): void
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
<div class="aurora-blueprint-wrapper w-100 my-0 p-3 p-lg-4 rounded-4 shadow-2xl" id="auroraBlueprintWrapper">
    <!-- Top Blueprint Toolbar Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3 border-bottom border-secondary border-opacity-25 pb-3">
        <!-- Floor Selector Tabs (Floor 1, Floor 2, Floor 3) -->
        <div class="d-flex align-items-center gap-3">
            <span class="font-serif fs-5 fw-bold text-gold me-2 mb-0">Floor</span>
            <div class="nav nav-pills gap-2 align-items-center" id="auroraFloorTabs" role="tablist">
                <?php foreach ($floors as $floorNum => $floorRooms): 
                    $isActive = ($floorNum === 1);
                ?>
                    <button class="nav-link aurora-floor-pill <?= $isActive ? 'active' : '' ?> rounded-3 px-3 py-1 fw-bold text-sm" 
                            id="aurora-tab-floor-<?= $floorNum ?>" 
                            type="button" 
                            role="tab"
                            onclick="switchAuroraFloorTab(<?= $floorNum ?>)">
                        <?= $floorNum ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($checkIn) && !empty($checkOut)): ?>
                <span class="badge bg-gold text-dark px-3 py-2 rounded-pill font-mono text-xs shadow-sm ms-2" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10;">
                    <i class="bi bi-calendar-check me-1"></i><?= e($checkIn) ?> &rarr; <?= e($checkOut) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Status Legend -->
        <div class="d-flex flex-wrap align-items-center gap-3 text-xs fw-bold font-sans">
            <span class="d-inline-flex align-items-center gap-2"><span class="legend-dot legend-dot--available"></span> Available</span>
            <span class="d-inline-flex align-items-center gap-2"><span class="legend-dot legend-dot--reserved"></span> Reserved</span>
            <span class="d-inline-flex align-items-center gap-2"><span class="legend-dot legend-dot--occupied"></span> Occupied</span>
            <span class="d-inline-flex align-items-center gap-2"><span class="legend-dot legend-dot--cleaning"></span> Cleaning</span>
            <span class="d-inline-flex align-items-center gap-2"><span class="legend-dot legend-dot--maintenance"></span> Maintenance</span>
        </div>
    </div>

    <!-- Floor Tab Panes -->
    <div class="tab-content" id="auroraFloorContent">
        <?php foreach ($floors as $floorNum => $floorRooms): ?>
            <div class="tab-pane fade <?= $floorNum === 1 ? 'show active' : '' ?>" id="aurora-pane-floor-<?= $floorNum ?>" role="tabpanel">
                
                <!-- Interactive SVG Architectural Blueprint Canvas -->
                <div class="blueprint-canvas-container position-relative rounded-4 p-2 p-lg-3 border border-secondary border-opacity-30 overflow-hidden" style="background: #0f141d;">
                    <svg viewBox="0 0 1000 560" class="w-100 h-auto aurora-svg-blueprint" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <!-- Neon Glow Filters -->
                            <filter id="glowGreen_<?= $floorNum ?>" x="-20%" y="-20%" width="140%" height="140%">
                                <feGaussianBlur stdDeviation="3" result="blur" />
                                <feComposite in="SourceGraphic" in2="blur" operator="over" />
                            </filter>
                            <filter id="glowBlue_<?= $floorNum ?>" x="-20%" y="-20%" width="140%" height="140%">
                                <feGaussianBlur stdDeviation="3" result="blur" />
                                <feComposite in="SourceGraphic" in2="blur" operator="over" />
                            </filter>
                            <filter id="glowRed_<?= $floorNum ?>" x="-20%" y="-20%" width="140%" height="140%">
                                <feGaussianBlur stdDeviation="3" result="blur" />
                                <feComposite in="SourceGraphic" in2="blur" operator="over" />
                            </filter>

                            <!-- Fine Blueprint Grid Background -->
                            <pattern id="blueprintGridFine_<?= $floorNum ?>" width="20" height="20" patternUnits="userSpaceOnUse">
                                <path d="M 20 0 L 0 0 0 20" fill="none" stroke="rgba(212, 175, 55, 0.05)" stroke-width="0.7"/>
                            </pattern>
                        </defs>

                        <!-- Outer Blueprint Frame -->
                        <rect x="15" y="15" width="970" height="530" rx="12" fill="#0d111a" stroke="rgba(212, 175, 55, 0.4)" stroke-width="2"/>
                        <rect x="22" y="22" width="956" height="516" rx="8" fill="url(#blueprintGridFine_<?= $floorNum ?>)" stroke="rgba(212, 175, 55, 0.15)" stroke-width="1" stroke-dasharray="4 4"/>

                        <!-- Left Side Elevator / Stairwell Core -->
                        <g class="building-core-left" stroke="rgba(212, 175, 55, 0.35)" stroke-width="1" fill="none">
                            <!-- Stairwell -->
                            <rect x="40" y="40" width="85" height="150" rx="4" fill="rgba(15, 23, 42, 0.5)"/>
                            <line x1="40" y1="65" x2="125" y2="65"/><line x1="40" y1="90" x2="125" y2="90"/>
                            <line x1="40" y1="115" x2="125" y2="115"/><line x1="40" y1="140" x2="125" y2="140"/>
                            <line x1="40" y1="165" x2="125" y2="165"/>
                            <line x1="82" y1="40" x2="82" y2="190" stroke-dasharray="3 3"/>

                            <!-- Entrance Arc -->
                            <path d="M 40 260 Q 20 280 40 300" stroke="#FFDF73" stroke-width="1.5" stroke-dasharray="4 3"/>
                            <text x="32" y="285" fill="#FFDF73" font-size="9" font-family="serif" font-weight="bold" transform="rotate(-90 32 285)" text-anchor="middle">ENTRANCE</text>

                            <!-- Elevators -->
                            <rect x="40" y="330" width="85" height="75" rx="4" fill="rgba(15, 23, 42, 0.5)"/>
                            <rect x="40" y="420" width="85" height="75" rx="4" fill="rgba(15, 23, 42, 0.5)"/>
                            <text x="82" y="373" text-anchor="middle" fill="#FFDF73" font-size="14" font-family="serif">ELEVATOR</text>
                            <text x="82" y="463" text-anchor="middle" fill="#FFDF73" font-size="14" font-family="serif">ELEVATOR</text>
                        </g>

                        <!-- Central Corridor -->
                        <g class="building-corridor">
                            <rect x="140" y="260" width="675" height="40" fill="rgba(212, 175, 55, 0.03)" stroke="rgba(212, 175, 55, 0.3)" stroke-width="1"/>
                            <text x="475" y="284" text-anchor="middle" fill="rgba(212, 175, 55, 0.6)" font-size="11" font-weight="bold" font-family="serif" letter-spacing="4">CORRIDOR</text>
                        </g>

                        <!-- Right Side Lounge Area -->
                        <g class="building-lounge" stroke="rgba(212, 175, 55, 0.35)" stroke-width="1" fill="none">
                            <rect x="830" y="40" width="135" height="485" rx="6" fill="rgba(15, 23, 42, 0.4)"/>
                            <!-- Lounge Sofas & Coffee Tables -->
                            <rect x="850" y="80" width="70" height="30" rx="4"/>
                            <circle cx="885" cy="140" r="16"/>
                            <rect x="850" y="180" width="70" height="30" rx="4"/>

                            <rect x="850" y="340" width="70" height="30" rx="4"/>
                            <circle cx="885" cy="400" r="16"/>
                            <rect x="850" y="440" width="70" height="30" rx="4"/>
                            <text x="897" y="280" text-anchor="middle" fill="#FFDF73" font-size="11" font-family="serif" font-weight="bold" transform="rotate(90 897 280)" letter-spacing="2">EXECUTIVE LOUNGE</text>
                        </g>

                        <!-- North & South Wing Floor Direction Markers -->
                        <text x="475" y="32" text-anchor="middle" fill="rgba(212, 175, 55, 0.5)" font-size="10" font-family="serif" letter-spacing="3">NORTH SUITES</text>
                        <text x="475" y="528" text-anchor="middle" fill="rgba(212, 175, 55, 0.5)" font-size="10" font-family="serif" letter-spacing="3">SOUTH SUITES</text>

                        <!-- Render Architectural Room Vectors -->
                        <?php
                        $northWing = array_slice($floorRooms, 0, 6);
                        $southWing = array_slice($floorRooms, 6, 6);

                        // X offsets for 6 room columns
                        $xCoords = [140, 255, 370, 485, 600, 715];

                        $renderArchitecturalRoom = function($room, $x, $y, $isNorth = true) use ($mode, $floorNum) {
                            $status = $room['status'];
                            $catalog = getRoomCatalogData($room);

                            $styleMap = [
                                'Available'   => ['stroke' => '#10B981', 'fill' => 'rgba(16, 185, 129, 0.12)', 'badgeBg' => 'rgba(16, 185, 129, 0.25)', 'badgeText' => '#6EE7B7', 'glow' => 'glowGreen_' . $floorNum],
                                'Reserved'    => ['stroke' => '#3B82F6', 'fill' => 'rgba(59, 130, 246, 0.12)', 'badgeBg' => 'rgba(59, 130, 246, 0.25)', 'badgeText' => '#93C5FD', 'glow' => 'glowBlue_' . $floorNum],
                                'Occupied'    => ['stroke' => '#EF4444', 'fill' => 'rgba(239, 68, 68, 0.12)', 'badgeBg' => 'rgba(239, 68, 68, 0.25)', 'badgeText' => '#FCA5A5', 'glow' => 'glowRed_' . $floorNum],
                                'Cleaning'    => ['stroke' => '#F59E0B', 'fill' => 'rgba(245, 158, 11, 0.12)', 'badgeBg' => 'rgba(245, 158, 11, 0.25)', 'badgeText' => '#FDE68A', 'glow' => 'none'],
                                'Maintenance' => ['stroke' => '#64748B', 'fill' => 'rgba(100, 116, 139, 0.12)', 'badgeBg' => 'rgba(100, 116, 139, 0.25)', 'badgeText' => '#CBD5E1', 'glow' => 'none'],
                            ];
                            $st = $styleMap[$status] ?? $styleMap['Available'];
                            $filterAttr = $st['glow'] !== 'none' ? 'filter="url(#' . $st['glow'] . ')"' : '';
                            ?>
                            <g class="aurora-room-zone" 
                               style="cursor: pointer;" 
                               data-room-id="<?= (int)$room['room_id'] ?>"
                               data-room-number="<?= e($room['room_number']) ?>"
                               data-room-type="<?= e($room['room_type']) ?>"
                               data-room-price="<?= number_format((float)$room['price_per_night'], 2) ?>"
                               data-room-status="<?= $status ?>"
                               data-room-capacity="<?= (int)($catalog['max_capacity'] ?? 2) ?>"
                               data-room-bed="<?= e($catalog['bed_type'] ?? 'King Bed') ?>"
                               data-room-view="<?= e($catalog['view_type'] ?? 'City View') ?>"
                               data-mode="<?= e($mode) ?>"
                               onclick="onStandaloneMapRoomClick(<?= (int)$room['room_id'] ?>, '<?= e($room['room_number']) ?>', '<?= e($room['room_type']) ?>', '<?= number_format((float)$room['price_per_night'], 2) ?>', '<?= $status ?>', '<?= e($mode) ?>')">
                                
                                <!-- Room Outer Perimeter Rect -->
                                <rect x="<?= $x ?>" y="<?= $y ?>" width="105" height="210" rx="4" 
                                      fill="<?= $st['fill'] ?>" 
                                      stroke="<?= $st['stroke'] ?>" 
                                      stroke-width="1.5" 
                                      <?= $filterAttr ?>
                                      class="room-perimeter-rect"/>

                                <!-- Inner Architectural Wireframes -->
                                <g stroke="<?= $st['stroke'] ?>" stroke-width="0.8" opacity="0.6" fill="none">
                                    <?php if ($isNorth): ?>
                                        <rect x="<?= $x + 22 ?>" y="<?= $y + 12 ?>" width="61" height="6" rx="1"/>
                                        <rect x="<?= $x + 22 ?>" y="<?= $y + 20 ?>" width="61" height="65" rx="3"/>
                                        <rect x="<?= $x + 27 ?>" y="<?= $y + 24 ?>" width="22" height="14" rx="2"/>
                                        <rect x="<?= $x + 56 ?>" y="<?= $y + 24 ?>" width="22" height="14" rx="2"/>
                                        <line x1="<?= $x + 30 ?>" y1="<?= $y + 115 ?>" x2="<?= $x + 75 ?>" y2="<?= $y + 115 ?>" stroke-width="2"/>
                                        <line x1="<?= $x ?>" y1="<?= $y + 140 ?>" x2="<?= $x + 105 ?>" y2="<?= $y + 140 ?>"/>
                                        <circle cx="<?= $x + 22 ?>" cy="<?= $y + 175 ?>" r="10"/>
                                        <rect x="<?= $x + 65 ?>" y="<?= $y + 160 ?>" width="25" height="30" rx="3"/>
                                        <path d="M <?= $x + 20 ?> <?= $y + 210 ?> A 25 25 0 0 1 <?= $x + 45 ?> <?= $y + 185 ?>" stroke-dasharray="2 2"/>
                                    <?php else: ?>
                                        <line x1="<?= $x ?>" y1="<?= $y + 70 ?>" x2="<?= $x + 105 ?>" y2="<?= $y + 70 ?>"/>
                                        <circle cx="<?= $x + 22 ?>" cy="<?= $y + 35 ?>" r="10"/>
                                        <rect x="<?= $x + 65 ?>" y="<?= $y + 20 ?>" width="25" height="30" rx="3"/>
                                        <rect x="<?= $x + 22 ?>" y="<?= $y + 192 ?>" width="61" height="6" rx="1"/>
                                        <rect x="<?= $x + 22 ?>" y="<?= $y + 125 ?>" width="61" height="65" rx="3"/>
                                        <rect x="<?= $x + 27 ?>" y="<?= $y + 170 ?>" width="22" height="14" rx="2"/>
                                        <rect x="<?= $x + 56 ?>" y="<?= $y + 170 ?>" width="22" height="14" rx="2"/>
                                        <line x1="<?= $x + 30 ?>" y1="<?= $y + 95 ?>" x2="<?= $x + 75 ?>" y2="<?= $y + 95 ?>" stroke-width="2"/>
                                        <path d="M <?= $x + 20 ?> <?= $y ?>" A 25 25 0 0 0 <?= $x + 45 ?> <?= $y + 25 ?>" stroke-dasharray="2 2"/>
                                    <?php endif; ?>
                                </g>

                                <!-- Status Badge Pill -->
                                <?php $badgeY = $isNorth ? $y + 22 : $y + 168; ?>
                                <rect x="<?= $x + 15 ?>" y="<?= $badgeY ?>" width="75" height="18" rx="9" fill="<?= $st['badgeBg'] ?>" stroke="<?= $st['stroke'] ?>" stroke-width="0.8"/>
                                <text x="<?= $x + 52.5 ?>" y="<?= $badgeY + 12 ?>" text-anchor="middle" fill="<?= $st['badgeText'] ?>" font-size="8" font-weight="bold" font-family="sans-serif" letter-spacing="1"><?= strtoupper($status) ?></text>

                                <!-- Room Number Label -->
                                <text x="<?= $x + 52.5 ?>" y="<?= $isNorth ? $y + 106 : $y + 114 ?>" text-anchor="middle" fill="<?= $st['stroke'] ?>" font-size="11" font-weight="bold" font-family="serif" letter-spacing="0.5">ROOM <?= e($room['room_number']) ?></text>
                            </g>
                            <?php
                        };

                        // Render North Wing (Y = 40)
                        foreach ($northWing as $idx => $rData) {
                            if (isset($xCoords[$idx])) {
                                $renderArchitecturalRoom($rData, $xCoords[$idx], 40, true);
                            }
                        }

                        // Render South Wing (Y = 310)
                        foreach ($southWing as $idx => $rData) {
                            if (isset($xCoords[$idx])) {
                                $renderArchitecturalRoom($rData, $xCoords[$idx], 310, false);
                            }
                        }
                        ?>
                    </svg>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Floating Dark Glassmorphism Tooltip -->
<div id="standaloneRoomTooltip" class="aurora-room-tooltip p-3 rounded-3 shadow-2xl position-fixed d-none" style="z-index: 1090; pointer-events: none; width: 290px; background: rgba(20, 26, 38, 0.95); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); border: 1px solid rgba(212, 175, 55, 0.4); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.8);">
    <div class="d-flex align-items-center justify-content-between mb-1">
        <h6 class="font-serif fw-bold text-gold mb-0 fs-6" id="stTtRoomNumber">ROOM 104</h6>
        <span class="badge text-xs px-2 py-1 rounded-pill fw-bold" id="stTtRoomStatus">Available</span>
    </div>
    <div class="small text-light-emphasis font-serif mb-2 pb-2 border-bottom border-secondary border-opacity-25" id="stTtRoomSub">King Suite | City View</div>

    <div class="mb-2">
        <div class="fs-5 font-serif fw-bold text-gold" id="stTtRoomPrice">PHP 4,500 <span class="fs-6 font-sans fw-normal text-muted">/ night</span></div>
    </div>

    <div class="text-xs text-muted" id="stTtRoomPerks">
        <strong class="text-light-emphasis">Amenities:</strong> King Bed, Smart TV, Wi-Fi, Minibar, Nespresso
    </div>
</div>

<style>
.aurora-blueprint-wrapper {
    background: #121620 !important;
    border: 1px solid rgba(212, 175, 55, 0.35) !important;
    color: #f8fafc !important;
}

.aurora-floor-pill {
    background: transparent !important;
    color: rgba(212, 175, 55, 0.7) !important;
    border: 1px solid rgba(212, 175, 55, 0.3) !important;
    transition: all 0.2s ease !important;
}

.aurora-floor-pill:hover,
.aurora-floor-pill.active {
    background: #d4af37 !important;
    color: #0d111a !important;
    border-color: #d4af37 !important;
    box-shadow: 0 0 12px rgba(212, 175, 55, 0.4) !important;
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.legend-dot--available   { background: #10b981; box-shadow: 0 0 8px #10b981; }
.legend-dot--reserved    { background: #3b82f6; box-shadow: 0 0 8px #3b82f6; }
.legend-dot--occupied    { background: #ef4444; box-shadow: 0 0 8px #ef4444; }
.legend-dot--cleaning    { background: #f59e0b; box-shadow: 0 0 8px #f59e0b; }
.legend-dot--maintenance { background: #64748b; }

.aurora-room-zone {
    transition: transform 0.2s ease, filter 0.2s ease;
}

.aurora-room-zone:hover {
    transform: translateY(-2px);
    filter: drop-shadow(0 0 10px rgba(253, 215, 0, 0.5));
}

.aurora-room-zone:hover .room-perimeter-rect {
    stroke: #FFDF73 !important;
    stroke-width: 2.5px !important;
}
</style>

<script>
function switchAuroraFloorTab(floorNum) {
    document.querySelectorAll('#auroraFloorTabs .aurora-floor-pill').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('#auroraFloorContent .tab-pane').forEach(pane => {
        pane.classList.remove('show', 'active');
    });

    const activeBtn = document.getElementById(`aurora-tab-floor-${floorNum}`);
    const activePane = document.getElementById(`aurora-pane-floor-${floorNum}`);
    if (activeBtn) activeBtn.classList.add('active');
    if (activePane) activePane.classList.add('show', 'active');
}

function bindStandaloneBlueprintTooltips() {
    const tooltip = document.getElementById('standaloneRoomTooltip');
    if (!tooltip) return;

    const ttNum = document.getElementById('stTtRoomNumber');
    const ttStatus = document.getElementById('stTtRoomStatus');
    const ttSub = document.getElementById('stTtRoomSub');
    const ttPrice = document.getElementById('stTtRoomPrice');
    const ttPerks = document.getElementById('stTtRoomPerks');

    document.querySelectorAll('.aurora-room-zone').forEach(zone => {
        if (zone.dataset.standaloneTooltipBound === "true") return;
        zone.dataset.standaloneTooltipBound = "true";

        zone.addEventListener('mouseenter', (e) => {
            const ds = zone.dataset;

            if (ttNum) ttNum.textContent = `ROOM ${ds.roomNumber}`;
            if (ttStatus) {
                ttStatus.textContent = ds.roomStatus || 'Available';
                const statusBadgeStyle = {
                    'Available':   'bg-success text-white',
                    'Reserved':    'bg-primary text-white',
                    'Occupied':    'bg-danger text-white',
                    'Cleaning':    'bg-warning text-dark',
                    'Maintenance': 'bg-secondary text-white'
                }[ds.roomStatus] || 'bg-success text-white';
                ttStatus.className = `badge text-xs px-2 py-1 rounded-pill fw-bold ${statusBadgeStyle}`;
            }
            if (ttSub) ttSub.textContent = `${ds.roomType} | ${ds.roomView || 'City View'}`;
            if (ttPrice) ttPrice.innerHTML = `PHP ${ds.roomPrice} <span class="fs-6 font-sans fw-normal text-muted">/ night</span>`;
            if (ttPerks) ttPerks.innerHTML = `<strong class="text-light-emphasis">Amenities:</strong> ${ds.roomBed || 'King Bed'}, Smart TV, Wi-Fi, Minibar`;

            tooltip.classList.remove('d-none');
        });

        zone.addEventListener('mousemove', (e) => {
            const x = e.clientX + 16;
            const y = e.clientY + 16;
            tooltip.style.left = `${x}px`;
            tooltip.style.top = `${y}px`;
        });

        zone.addEventListener('mouseleave', () => {
            tooltip.classList.add('d-none');
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindStandaloneBlueprintTooltips);
} else {
    bindStandaloneBlueprintTooltips();
}

function onStandaloneMapRoomClick(roomId, roomNumber, roomType, price, status, mode = 'public') {
    if (mode !== 'admin') {
        const checkIn = document.getElementById('siteMapCheckIn')?.value || '';
        const checkOut = document.getElementById('siteMapCheckOut')?.value || '';
        let url = `room-detail.php?id=${roomId}`;
        if (checkIn && checkOut) {
            url += `&check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`;
        }
        window.location.href = url;
        return;
    }

    window.location.href = `rooms.php?search=${encodeURIComponent(roomNumber)}`;
}
</script>
<?php
}
