<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';
require_once __DIR__ . '/../includes/hotel_map.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

function roomFiltersQuery(array $params = []): string
{
    $allowedKeys = ['search', 'room_type', 'status', 'floor', 'sort', 'direction', 'page', 'per_page'];
    $query = [];

    foreach ($allowedKeys as $key) {
        if (array_key_exists($key, $params) && $params[$key] !== '' && $params[$key] !== null) {
            $query[$key] = $params[$key];
        }
    }

    unset($query['page']);

    return $query ? '?' . http_build_query($query) : '';
}

function processRoomImageUploads(?array $existingRoom = null): ?string
{
    $imagePaths = [];

    // Keep existing images unless user asked to clear them
    if ($existingRoom && !empty($existingRoom['image_url']) && empty($_POST['clear_custom_images'])) {
        $rawImg = trim((string)$existingRoom['image_url']);
        if (str_starts_with($rawImg, '[')) {
            $decoded = json_decode($rawImg, true);
            if (is_array($decoded)) {
                $imagePaths = array_values(array_filter(array_map('trim', $decoded)));
            }
        }
        if (empty($imagePaths)) {
            $imagePaths = array_values(array_filter(array_map('trim', explode(',', $rawImg))));
        }
    }

    // Process new uploaded files
    if (isset($_FILES['room_images']) && is_array($_FILES['room_images']['name'])) {
        $targetDir = __DIR__ . '/../assets/images/rooms/uploads/';
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $count = count($_FILES['room_images']['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($_FILES['room_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['room_images']['tmp_name'][$i];
                $name = basename($_FILES['room_images']['name'][$i]);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
                    $newFileName = 'room_' . time() . '_' . uniqid() . '.' . $ext;
                    $destination = $targetDir . $newFileName;
                    if (move_uploaded_file($tmpName, $destination)) {
                        $imagePaths[] = '../assets/images/rooms/uploads/' . $newFileName;
                    }
                }
            }
        }
    }

    // Process custom image URL text input if provided
    if (!empty($_POST['custom_image_url'])) {
        $urls = array_filter(array_map('trim', explode(',', (string)$_POST['custom_image_url'])));
        foreach ($urls as $url) {
            if (!in_array($url, $imagePaths, true)) {
                $imagePaths[] = $url;
            }
        }
    }

    return !empty($imagePaths) ? json_encode(array_values(array_unique($imagePaths))) : null;
}

$db = Database::connect();
$currentAdmin = currentUser();
$roomModel = new Room($db);
$roomTypes = Room::types();
$roomStatuses = Room::statuses();

if (isset($_GET['export']) && $_GET['export'] === 'xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rooms-export.xml"');
    echo $roomModel->exportToXml();
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? (getenv('ROOMS_PER_PAGE') ?: 10));
$perPage = max(5, min(50, $perPage));

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'room_type' => trim((string) ($_GET['room_type'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'floor' => trim((string) ($_GET['floor'] ?? '')),
    'sort' => trim((string) ($_GET['sort'] ?? 'floor')),
    'direction' => trim((string) ($_GET['direction'] ?? 'asc')),
    'per_page' => $perPage,
];
$querySuffix = roomFiltersQuery($_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'import_xml') {
            if (!isset($_FILES['xml_file']) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Please upload a valid XML file.');
            }

            $result = $roomModel->importFromXml($_FILES['xml_file']['tmp_name']);
            setFlash('success', "XML import finished. Created: {$result['created']}, Updated: {$result['updated']}.");
            redirect('rooms.php' . $querySuffix);
        }

        if ($action === 'create') {
            $data = $_POST;
            $data['image_url'] = processRoomImageUploads(null);
            $roomModel->create($data);
            setFlash('success', 'Room #' . e($data['room_number']) . ' created successfully.');
            redirect('rooms.php' . $querySuffix);
        }

        if ($action === 'reset_standard') {
            $targetVal = $_POST['target_value'] ?? 'all';
            $updatedCount = $roomModel->resetToStandardPrices($targetVal);
            setFlash('success', "Baseline standard pricing restored for {$updatedCount} room record(s).");
            redirect('rooms.php' . $querySuffix);
        }

        if ($action === 'smart_bulk_price' || $action === 'update_type_price') {
            $updatedCount = $roomModel->smartBulkUpdatePrice([
                'target_type' => $_POST['target_type'] ?? ($_POST['room_type'] ? 'suite' : 'all'),
                'target_value' => $_POST['target_value'] ?? ($_POST['room_type'] ?? ''),
                'adjustment_mode' => $_POST['adjustment_mode'] ?? 'fixed',
                'adjustment_value' => (float) ($_POST['adjustment_value'] ?? ($_POST['price_per_night'] ?? 0)),
                'save_as_base_price' => !empty($_POST['save_as_base_price']),
            ]);
            $msgSuffix = !empty($_POST['save_as_base_price']) ? ' Saved as new official suite base rate.' : '';
            setFlash('success', "Smart Bulk Price Update complete! Updated rates for {$updatedCount} room(s).{$msgSuffix}");
            redirect('rooms.php' . $querySuffix);
        }

        if ($action === 'update') {
            $roomId = (int) ($_POST['room_id'] ?? 0);
            $existing = $roomModel->find($roomId);
            if (!$existing) {
                throw new RuntimeException('Room not found.');
            }
            $data = $_POST;
            $data['image_url'] = processRoomImageUploads($existing);
            $roomModel->update($roomId, $data);
            setFlash('success', 'Room #' . e($data['room_number']) . ' updated successfully.');
            redirect('rooms.php' . $querySuffix);
        }

        if ($action === 'delete') {
            $roomModel->delete((int) ($_POST['room_id'] ?? 0));
            setFlash('success', 'Room record deleted.');
            redirect('rooms.php' . $querySuffix);
        }

        if ($action === 'update_status') {
            $roomId = (int) ($_POST['room_id'] ?? 0);
            $newStatus = (string) ($_POST['status'] ?? '');
            $room = $roomModel->find($roomId);
            if ($room) {
                $roomModel->update($roomId, array_merge($room, ['status' => $newStatus]));
                setFlash('success', "Room #{$room['room_number']} status set to {$newStatus}.");
            }
            redirect('rooms.php' . $querySuffix);
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('rooms.php' . $querySuffix);
    }
}

$roomTypes = $roomModel->getTypes();
$roomStatuses = Room::statuses();
$roomData = $roomModel->paginated($filters, $page, $perPage);
$rooms = $roomData['rows'];
$summary = $roomModel->statusSummary();
$typeSummary = $roomModel->typeSummary();
$filterActiveCount = count(array_filter([
    $filters['search'],
    $filters['room_type'],
    $filters['status'],
    $filters['floor'],
]));
$paginationBase = roomFiltersQuery($filters);

renderAdminLayoutStart('Rooms', 'rooms', $currentAdmin, ['../assets/css/admin/rooms.css']);
?>

<section class="stats-grid mb-4">
    <article class="stat-tile">
        <p class="eyebrow mb-2">Available</p>
        <div class="stat-value"><?php echo e($summary['available']); ?></div>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Not Available</p>
        <div class="stat-value"><?php echo e($summary['not_available']); ?></div>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Filtered Rooms</p>
        <div class="stat-value"><?php echo e($roomData['total']); ?></div>
    </article>
</section>

<!-- Interactive 2D Hotel Floor Map Section -->
<section class="mb-4">
    <?php renderHotelFloorMap($db, 'admin'); ?>
</section>

<section class="panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <p class="eyebrow mb-1">Browse Rooms</p>
            <h3 class="mb-0">Filters and Search</h3>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge-soft"><?php echo e($filterActiveCount); ?> active filter(s)</span>
            <a class="btn btn-outline-light btn-sm" href="rooms.php">Clear Filters</a>
            <a class="btn btn-outline-warning btn-sm fw-semibold" href="rooms.php?export=xml">Export XML</a>
            <button class="btn btn-warning btn-sm fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#createRoomModal">
                <i class="bi bi-plus-circle me-1"></i>New Room
            </button>
        </div>
    </div>
    <form method="get" class="row g-3">
        <div class="col-lg-4">
            <label class="form-label" for="search">Search</label>
            <input class="form-control" id="search" name="search" type="search" value="<?php echo e($filters['search']); ?>" placeholder="Room number, type, or status">
        </div>
        <div class="col-lg-2">
            <label class="form-label" for="room_type">Room Type</label>
            <select class="form-select" id="room_type" name="room_type">
                <option value="">All</option>
                <?php foreach ($roomTypes as $type): ?>
                    <option value="<?php echo e($type); ?>" <?php echo $filters['room_type'] === $type ? 'selected' : ''; ?>><?php echo e($type); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All</option>
                <?php foreach ($roomStatuses as $status): ?>
                    <option value="<?php echo e($status); ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label" for="floor">Floor</label>
            <input class="form-control" id="floor" name="floor" type="number" min="1" step="1" value="<?php echo e($filters['floor']); ?>" placeholder="Any">
        </div>
        <div class="col-lg-2">
            <label class="form-label" for="per_page">Per Page</label>
            <select class="form-select" id="per_page" name="per_page">
                <?php foreach ([5, 10, 20, 50] as $option): ?>
                    <option value="<?php echo e((string) $option); ?>" <?php echo $perPage === $option ? 'selected' : ''; ?>><?php echo e((string) $option); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-4">
            <label class="form-label" for="sort">Sort By</label>
            <div class="input-group">
                <select class="form-select" id="sort" name="sort">
                    <?php foreach (['floor' => 'Floor', 'room_number' => 'Room Number', 'room_type' => 'Room Type', 'price_per_night' => 'Rate', 'status' => 'Status', 'created_at' => 'Created'] as $sortKey => $sortLabel): ?>
                        <option value="<?php echo e($sortKey); ?>" <?php echo $filters['sort'] === $sortKey ? 'selected' : ''; ?>><?php echo e($sortLabel); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select" id="direction" name="direction">
                    <option value="asc" <?php echo strtolower($filters['direction']) === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                    <option value="desc" <?php echo strtolower($filters['direction']) === 'desc' ? 'selected' : ''; ?>>Descending</option>
                </select>
            </div>
        </div>
        <div class="col-lg-8 d-flex align-items-end justify-content-end gap-2">
            <button class="btn btn-warning fw-semibold" type="submit">Apply Filters</button>
        </div>
    </form>
</section>

<section class="row g-4">
    <div class="col-xl-4">
        <div class="panel-card p-4 mb-4">
            <div class="mb-3 border-bottom border-secondary border-opacity-25 pb-3">
                <p class="eyebrow mb-1"><i class="bi bi-tag-fill me-1 text-warning"></i>Suite Pricing</p>
                <h3 class="mb-1">Update Suite Rate</h3>
                <p class="muted-copy text-xs mb-0">Select a suite or floor and enter its price per night.</p>
            </div>

            <form method="post" id="simpleSuiteRateForm">
                <input type="hidden" name="action" value="smart_bulk_price">
                <input type="hidden" name="adjustment_mode" value="fixed">
                <input type="hidden" name="target_type" value="suite">
                <input type="hidden" name="save_as_base_price" value="1">

                <div class="mb-3">
                    <label class="form-label text-xs fw-semibold" for="simpleSuiteSelect">Select Suite / Floor</label>
                    <select class="form-select bg-dark text-light border-secondary" id="simpleSuiteSelect" name="target_value" onchange="onSimpleSuiteSelectChange(this)">
                        <option value="all" data-price="">Entire Hotel (All Suites)</option>
                        <?php
                            $suiteBaseRates = $roomModel->getSuiteBaseRates();
                            foreach ($suiteBaseRates as $sRate):
                                $currentP = number_format((float)$sRate['current_min_price'], 2, '.', '');
                        ?>
                            <option value="<?php echo e($sRate['room_type']); ?>" data-price="<?php echo e($currentP); ?>">
                                <?php echo e($sRate['room_type']); ?> (PHP <?php echo e(number_format((float)$sRate['current_min_price'], 2)); ?>/night)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-xs fw-semibold" for="simplePriceInput">Price / Night (PHP)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark text-warning border-secondary">PHP</span>
                        <input class="form-control bg-dark text-light border-secondary" id="simplePriceInput" name="adjustment_value" type="number" step="0.01" min="1" placeholder="e.g. 4500.00" required>
                    </div>
                </div>

                <button class="btn btn-warning fw-bold w-100 py-2 shadow-sm" type="submit">
                    <i class="bi bi-check-circle-fill me-1"></i>Update Suite Price
                </button>
            </form>
        </div>

        <div class="panel-card p-4">
            <p class="eyebrow mb-1">XML + DOM</p>
            <h3 class="mb-3">Import or Export Rooms</h3>
            <div class="d-grid gap-3">
                <a class="btn btn-outline-warning fw-bold py-2 shadow-sm" href="rooms.php?export=xml"><i class="bi bi-file-earmark-code-fill me-2"></i>Export Rooms to XML</a>
                <form method="post" enctype="multipart/form-data" class="d-grid gap-3">
                    <input type="hidden" name="action" value="import_xml">
                    <div>
                        <label class="form-label" for="xml_file">Import XML File</label>
                        <input class="form-control" id="xml_file" name="xml_file" type="file" accept=".xml" required>
                    </div>
                    <button class="btn btn-warning fw-semibold" type="submit">Import Rooms</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Inventory</p>
                    <h3 class="mb-0">Room Records</h3>
                </div>
                <span class="badge-soft"><?php echo e($roomData['total']); ?> room(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Floor</th>
                            <th>Rate</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rooms): ?>
                            <tr>
                                <td colspan="6" class="text-light-emphasis">No rooms match the current filters.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($rooms as $room): ?>
                            <?php
                                $catalogData = getRoomCatalogData($room);
                                $customImgCount = !empty($room['image_url']) ? 1 : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo e($catalogData['hero']); ?>" class="rounded-2 object-fit-cover border border-secondary" style="width: 42px; height: 32px;" alt="Room">
                                        <strong>#<?php echo e($room['room_number']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo e($room['room_type']); ?></td>
                                <td><?php echo e($room['floor']); ?></td>
                                <td><?php echo e(formatMoney((float) $room['price_per_night'])); ?></td>
                                <td><span class="badge-soft"><?php echo e($room['status']); ?></span></td>
                                <td class="text-end">
                                    <button
                                        class="btn btn-sm btn-warning fw-semibold"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editRoomModal_<?php echo e($room['room_id']); ?>"
                                    >
                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                    </button>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="room_id" value="<?php echo e($room['room_id']); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete Room #<?php echo e($room['room_number']); ?>?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php renderPaginationControl($roomData['total'], $roomData['page'], $roomData['per_page']); ?>
        </div>
    </div>
</section>

<!-- CREATE ROOM MODAL -->
<div class="modal fade" id="createRoomModal" tabindex="-1" aria-labelledby="createRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-secondary">
                    <div>
                        <p class="eyebrow mb-1">Room & Suite Inventory</p>
                        <h5 class="modal-title" id="createRoomModalLabel">Create New Room / Suite</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-grid gap-3">
                    <p class="text-xs text-light-emphasis mb-0">Assign room number, select or type a Suite name, and specify the floor. Adding rooms to a new floor automatically creates an interactive floor map tab.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="create_room_number">Room / Unit Number</label>
                            <input class="form-control bg-dark text-light border-secondary" id="create_room_number" name="room_number" type="number" min="100" max="199" placeholder="e.g. 101" required>
                            <small class="text-warning text-xs mt-1 d-block" id="create_room_range_hint"><i class="bi bi-info-circle me-1"></i>Floor 1 valid range: 100 – 199</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="create_room_type_select">Suite / Room Type</label>
                            <select class="form-select bg-dark text-light border-secondary" id="create_room_type_select" onchange="toggleCustomSuiteInput(this, 'create_custom_suite_box', 'create_room_type_hidden')">
                                <?php foreach ($roomTypes as $type): ?>
                                    <option value="<?php echo e($type); ?>"><?php echo e($type); ?></option>
                                <?php endforeach; ?>
                                <option value="__NEW_CUSTOM_SUITE__">+ Add New Custom Suite Type...</option>
                            </select>
                            <input type="hidden" id="create_room_type_hidden" name="room_type" value="<?php echo e($roomTypes[0] ?? 'Imperial Deluxe'); ?>">
                            
                            <div id="create_custom_suite_box" class="mt-2 d-none">
                                <input class="form-control bg-dark text-light border-secondary" type="text" id="create_custom_suite_input" placeholder="Type new custom suite name (e.g. Penthouse Suite)..." oninput="updateCustomSuiteValue(this, 'create_room_type_hidden')">
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="create_floor">Floor Number</label>
                            <input class="form-control bg-dark text-light border-secondary" id="create_floor" name="floor" type="number" min="1" value="1" oninput="updateRoomNumberRangeHint(this, 'create_room_number', 'create_room_range_hint')" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="create_price">Price / Night (PHP)</label>
                            <input class="form-control bg-dark text-light border-secondary" id="create_price" name="price_per_night" type="number" min="0.01" step="0.01" placeholder="4500.00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="create_status">Status</label>
                            <select class="form-select bg-dark text-light border-secondary" id="create_status" name="status">
                                <?php foreach ($roomStatuses as $status): ?>
                                    <option value="<?php echo e($status); ?>"><?php echo e($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="create_bed_type">Bed Configuration</label>
                            <input class="form-control bg-dark text-light border-secondary" id="create_bed_type" name="bed_type" type="text" value="King Bed">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="create_max_capacity">Max Capacity</label>
                            <input class="form-control bg-dark text-light border-secondary" id="create_max_capacity" name="max_capacity" type="number" min="1" value="2">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="create_view_type">View Type</label>
                            <input class="form-control bg-dark text-light border-secondary" id="create_view_type" name="view_type" type="text" value="City Skyline View">
                        </div>
                    </div>
                    <div class="p-3 rounded-3 room-media-box">
                        <label class="form-label font-serif fw-bold text-warning mb-1"><i class="bi bi-image me-1"></i>Room Custom Image(s)</label>
                        <p class="text-xs text-light-emphasis mb-2">Upload custom room photos. Uploading 1 image sets a static photo (disables carousel controls); uploading 2+ images creates an image gallery.</p>
                        <input class="form-control bg-dark text-light border-secondary mb-2" name="room_images[]" type="file" accept="image/*" multiple>
                        <input class="form-control bg-dark text-light border-secondary" name="custom_image_url" type="text" placeholder="Or enter image URL(s), comma-separated...">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-semibold"><i class="bi bi-plus-circle me-1"></i>Create Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT ROOM MODALS FOR EACH ROOM -->
<?php foreach ($rooms as $room): ?>
    <?php
        $roomId = (int) $room['room_id'];
        $catalogData = getRoomCatalogData($room);
        $customImages = [];
        if (!empty($room['image_url'])) {
            $rawImg = trim((string)$room['image_url']);
            if (str_starts_with($rawImg, '[')) {
                $decoded = json_decode($rawImg, true);
                if (is_array($decoded)) $customImages = array_filter(array_map('trim', $decoded));
            }
            if (empty($customImages)) {
                $customImages = array_filter(array_map('trim', explode(',', $rawImg)));
            }
        }
    ?>
    <div class="modal fade" id="editRoomModal_<?php echo $roomId; ?>" tabindex="-1" aria-labelledby="editRoomModalLabel_<?php echo $roomId; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-light border-secondary">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="room_id" value="<?php echo $roomId; ?>">
                    <div class="modal-header border-secondary">
                        <div>
                            <p class="eyebrow mb-1">Room #<?php echo e($room['room_number']); ?></p>
                            <h5 class="modal-title" id="editRoomModalLabel_<?php echo $roomId; ?>">Edit Room Record</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-grid gap-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="edit_room_number_<?php echo $roomId; ?>">Room Number</label>
                                <input class="form-control bg-dark text-light border-secondary" id="edit_room_number_<?php echo $roomId; ?>" name="room_number" type="number" min="<?php echo (int)$room['floor'] * 100; ?>" max="<?php echo ((int)$room['floor'] * 100) + 99; ?>" value="<?php echo e($room['room_number']); ?>" required>
                                <small class="text-warning text-xs mt-1 d-block" id="edit_room_range_hint_<?php echo $roomId; ?>"><i class="bi bi-info-circle me-1"></i>Floor <?php echo (int)$room['floor']; ?> range: <?php echo (int)$room['floor'] * 100; ?> – <?php echo ((int)$room['floor'] * 100) + 99; ?></small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="edit_room_type_select_<?php echo $roomId; ?>">Suite / Room Type</label>
                                <select class="form-select bg-dark text-light border-secondary" id="edit_room_type_select_<?php echo $roomId; ?>" onchange="toggleCustomSuiteInput(this, 'edit_custom_suite_box_<?php echo $roomId; ?>', 'edit_room_type_hidden_<?php echo $roomId; ?>')">
                                    <?php foreach ($roomTypes as $type): ?>
                                        <option value="<?php echo e($type); ?>" <?php echo $room['room_type'] === $type ? 'selected' : ''; ?>><?php echo e($type); ?></option>
                                    <?php endforeach; ?>
                                    <?php if (!in_array($room['room_type'], $roomTypes, true)): ?>
                                        <option value="<?php echo e($room['room_type']); ?>" selected><?php echo e($room['room_type']); ?></option>
                                    <?php endif; ?>
                                    <option value="__NEW_CUSTOM_SUITE__">+ Add New Custom Suite Type...</option>
                                </select>
                                <input type="hidden" id="edit_room_type_hidden_<?php echo $roomId; ?>" name="room_type" value="<?php echo e($room['room_type']); ?>">
                                
                                <div id="edit_custom_suite_box_<?php echo $roomId; ?>" class="mt-2 d-none">
                                    <input class="form-control bg-dark text-light border-secondary" type="text" placeholder="Type custom suite name..." oninput="updateCustomSuiteValue(this, 'edit_room_type_hidden_<?php echo $roomId; ?>')">
                                </div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="edit_floor_<?php echo $roomId; ?>">Floor</label>
                                <input class="form-control bg-dark text-light border-secondary" id="edit_floor_<?php echo $roomId; ?>" name="floor" type="number" min="1" value="<?php echo e($room['floor']); ?>" oninput="updateRoomNumberRangeHint(this, 'edit_room_number_<?php echo $roomId; ?>', 'edit_room_range_hint_<?php echo $roomId; ?>')" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="edit_price_<?php echo $roomId; ?>">Price / Night (PHP)</label>
                                <input class="form-control bg-dark text-light border-secondary" id="edit_price_<?php echo $roomId; ?>" name="price_per_night" type="number" min="0.01" step="0.01" value="<?php echo e(number_format((float)$room['price_per_night'], 2, '.', '')); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="edit_status_<?php echo $roomId; ?>">Status</label>
                                <select class="form-select bg-dark text-light border-secondary" id="edit_status_<?php echo $roomId; ?>" name="status">
                                    <?php foreach ($roomStatuses as $status): ?>
                                        <option value="<?php echo e($status); ?>" <?php echo $room['status'] === $status ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="edit_bed_type_<?php echo $roomId; ?>">Bed Configuration</label>
                                <input class="form-control bg-dark text-light border-secondary" id="edit_bed_type_<?php echo $roomId; ?>" name="bed_type" type="text" value="<?php echo e($room['bed_type'] ?? 'King Bed'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="edit_max_capacity_<?php echo $roomId; ?>">Max Capacity</label>
                                <input class="form-control bg-dark text-light border-secondary" id="edit_max_capacity_<?php echo $roomId; ?>" name="max_capacity" type="number" min="1" value="<?php echo e($room['max_capacity'] ?? 2); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="edit_view_type_<?php echo $roomId; ?>">View Type</label>
                                <input class="form-control bg-dark text-light border-secondary" id="edit_view_type_<?php echo $roomId; ?>" name="view_type" type="text" value="<?php echo e($room['view_type'] ?? 'City View'); ?>">
                            </div>
                        </div>

                        <!-- ROOM IMAGES & MEDIA SECTION -->
                        <div class="p-3 rounded-3 room-media-box">
                            <label class="form-label font-serif fw-bold text-warning mb-1"><i class="bi bi-images me-1"></i>Room Image Gallery & Media</label>
                            <p class="text-xs text-light-emphasis mb-3">Upload custom photos for Room #<?php echo e($room['room_number']); ?>. If only 1 image exists, carousel controls are automatically hidden on public views.</p>
                            
                            <?php if (!empty($customImages)): ?>
                                <div class="mb-3">
                                    <span class="d-block text-xs font-serif text-warning fw-bold mb-2">Current Custom Photos (<?php echo count($customImages); ?>):</span>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <?php foreach ($customImages as $idx => $imgSrc): ?>
                                            <div class="position-relative">
                                                <img src="<?php echo e($imgSrc); ?>" class="rounded-2 border border-warning object-fit-cover" style="width: 80px; height: 60px;" alt="Photo <?php echo $idx+1; ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="form-check text-xs">
                                        <input class="form-check-input" type="checkbox" name="clear_custom_images" value="1" id="clear_img_<?php echo $roomId; ?>">
                                        <label class="form-check-label text-danger fw-semibold" for="clear_img_<?php echo $roomId; ?>">
                                            <i class="bi bi-trash me-1"></i>Clear custom images &amp; revert to suite defaults
                                        </label>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mb-3 p-2 rounded border border-secondary text-xs text-light-emphasis">
                                    <i class="bi bi-info-circle me-1 text-warning"></i>Currently using default suite photos from system catalog.
                                </div>
                            <?php endif; ?>

                            <div class="mb-2">
                                <label class="form-label text-xs fw-semibold" for="upload_img_<?php echo $roomId; ?>">Upload New Photo(s)</label>
                                <input class="form-control bg-dark text-light border-secondary" id="upload_img_<?php echo $roomId; ?>" name="room_images[]" type="file" accept="image/*" multiple>
                            </div>
                            <div>
                                <label class="form-label text-xs fw-semibold" for="custom_url_<?php echo $roomId; ?>">Or Add Image URL(s)</label>
                                <input class="form-control bg-dark text-light border-secondary" id="custom_url_<?php echo $roomId; ?>" name="custom_image_url" type="text" placeholder="e.g. ../assets/images/rooms/... (comma separated)">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-semibold"><i class="bi bi-check-circle me-1"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.querySelectorAll(".modal").forEach((modal) => {
    document.body.appendChild(modal);
});

function toggleCustomSuiteInput(selectEl, customBoxId, hiddenInputId) {
    const customBox = document.getElementById(customBoxId);
    const hiddenInput = document.getElementById(hiddenInputId);
    if (!customBox || !hiddenInput) return;

    if (selectEl.value === '__NEW_CUSTOM_SUITE__') {
        customBox.classList.remove('d-none');
        const customInput = customBox.querySelector('input');
        if (customInput) {
            customInput.focus();
            hiddenInput.value = customInput.value.trim();
        }
    } else {
        customBox.classList.add('d-none');
        hiddenInput.value = selectEl.value;
    }
}

function updateCustomSuiteValue(inputEl, hiddenInputId) {
    const hiddenInput = document.getElementById(hiddenInputId);
    if (hiddenInput) {
        hiddenInput.value = inputEl.value.trim();
    }
}

const roomDataStats = <?php echo json_encode(array_map(static fn(array $r): array => [
    'id' => (int)$r['room_id'],
    'room_number' => (string)$r['room_number'],
    'room_type' => (string)$r['room_type'],
    'floor' => (int)$r['floor'],
    'price' => (float)$r['price_per_night'],
    'base_price' => (float)($r['base_price_per_night'] ?? $r['price_per_night']),
], $roomModel->all()), JSON_THROW_ON_ERROR); ?>;

function updateBulkTargetOptions() {
    const targetTypeSelect = document.getElementById('bulkTargetType');
    const targetValueSelect = document.getElementById('bulkTargetValue');
    if (!targetTypeSelect || !targetValueSelect) return;

    const type = targetTypeSelect.value;
    targetValueSelect.innerHTML = '';

    if (type === 'all') {
        targetValueSelect.classList.add('d-none');
    } else {
        targetValueSelect.classList.remove('d-none');
        targetValueSelect.innerHTML = '<option value="all">All Suites & Floors</option>';
        
        const suiteFloorMap = {};
        roomDataStats.forEach(r => {
            if (!suiteFloorMap[r.room_type]) {
                suiteFloorMap[r.room_type] = new Set();
            }
            suiteFloorMap[r.room_type].add(r.floor);
        });

        Object.keys(suiteFloorMap).forEach(st => {
            const floorsArr = Array.from(suiteFloorMap[st]).sort((a, b) => a - b);
            const floorLabel = floorsArr.length === 1 ? `Floor ${floorsArr[0]}` : `Floors ${floorsArr.join(', ')}`;
            targetValueSelect.innerHTML += `<option value="${st}">${st} (${floorLabel})</option>`;
        });
    }
    calculateSmartPreview();
}

function calculateSmartPreview() {
    const targetTypeSelect = document.getElementById('bulkTargetType');
    const targetValueSelect = document.getElementById('bulkTargetValue');
    const modeSelect = document.getElementById('bulkAdjustmentMode');
    const valInput = document.getElementById('bulkAdjustmentValue');

    if (!targetTypeSelect || !modeSelect || !valInput) return;

    const targetType = targetTypeSelect.value;
    const targetValue = targetValueSelect ? targetValueSelect.value : '';
    const mode = modeSelect.value;
    const rawVal = parseFloat(valInput.value) || 0;
    
    const prefixEl = document.getElementById('bulkValuePrefix');
    const labelEl = document.getElementById('bulkValueLabel');
    const previewText = document.getElementById('bulkPreviewText');
    const previewBadge = document.getElementById('bulkPreviewBadge');

    if (mode === 'fixed') {
        if (prefixEl) prefixEl.innerText = 'PHP';
        if (labelEl) labelEl.innerText = 'Set Exact Rate (PHP)';
    } else if (mode === 'percentage') {
        if (prefixEl) prefixEl.innerText = '%';
        if (labelEl) labelEl.innerText = 'Percentage Surge / Discount (%)';
    } else if (mode === 'offset') {
        if (prefixEl) prefixEl.innerText = '±PHP';
        if (labelEl) labelEl.innerText = 'Offset Rate (+/- PHP)';
    }

    const affected = roomDataStats.filter(r => {
        if (targetType === 'suite' && targetValue !== 'all' && targetValue !== '') return r.room_type === targetValue;
        if (targetType === 'floor' && targetValue !== 'all' && targetValue !== '') return r.floor === parseInt(targetValue, 10);
        return true;
    });

    const totalCount = affected.length;
    if (totalCount === 0) {
        if (previewBadge) previewBadge.innerText = '0 rooms selected';
        if (previewText) previewText.innerText = 'No rooms match the selected target scope.';
        return;
    }

    const currentAvg = affected.reduce((sum, r) => sum + r.price, 0) / totalCount;
    const baseAvg = affected.reduce((sum, r) => sum + (r.base_price || r.price), 0) / totalCount;
    let newAvg = currentAvg;

    if (mode === 'fixed') {
        newAvg = rawVal > 0 ? rawVal : currentAvg;
    } else if (mode === 'percentage') {
        newAvg = Math.max(1, currentAvg * (1 + rawVal / 100));
    } else if (mode === 'offset') {
        newAvg = Math.max(1, currentAvg + rawVal);
    }

    const diff = newAvg - currentAvg;
    const diffSign = diff > 0 ? '+' : '';
    const diffFormatted = diff !== 0 ? ` (${diffSign}PHP ${Math.abs(diff).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})})` : '';

    if (previewBadge) previewBadge.innerText = `${totalCount} room(s) targeted`;
    if (previewText) {
        previewText.innerHTML = `Base rate: <span class="text-light-emphasis">PHP ${baseAvg.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span> | Current avg: <strong>PHP ${currentAvg.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong> ➔ New avg: <strong class="text-warning">PHP ${newAvg.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>${diffFormatted}`;
    }
}

function updateRoomNumberRangeHint(floorInput, roomNumInputId, hintElId) {
    const floor = parseInt(floorInput.value, 10) || 1;
    const minRoom = floor * 100;
    const maxRoom = (floor * 100) + 99;
    
    const roomInput = document.getElementById(roomNumInputId);
    const hintEl = document.getElementById(hintElId);

    if (roomInput) {
        roomInput.min = minRoom;
        roomInput.max = maxRoom;
        roomInput.placeholder = `e.g. ${minRoom + 1}`;
    }

    if (hintEl) {
        hintEl.innerHTML = `<i class="bi bi-info-circle me-1"></i>Floor ${floor} valid range: ${minRoom} – ${maxRoom}`;
    }
}

function onSimpleSuiteSelectChange(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const price = selectedOption ? selectedOption.getAttribute('data-price') : '';
    const priceInput = document.getElementById('simplePriceInput');
    if (priceInput) {
        if (price) {
            priceInput.value = price;
        } else {
            priceInput.placeholder = 'e.g. 4500.00';
        }
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const simpleSelect = document.getElementById('simpleSuiteSelect');
    if (simpleSelect) {
        onSimpleSuiteSelectChange(simpleSelect);
    }
});
</script>

<?php renderAdminLayoutEnd(); ?>
