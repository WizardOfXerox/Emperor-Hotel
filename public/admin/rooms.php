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

        if ($action === 'update_type_price') {
            $roomType = (string) ($_POST['room_type'] ?? '');
            $pricePerNight = (float) ($_POST['price_per_night'] ?? -1);
            $roomModel->updateTypePrice($roomType, $pricePerNight);
            $updatedTypeSummary = $roomModel->typeSummary();
            $totalRooms = $updatedTypeSummary[$roomType]['total'] ?? 0;
            setFlash('success', "{$roomType} rate updated to " . formatMoney($pricePerNight) . " for {$totalRooms} room record(s).");
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
            <p class="eyebrow mb-1">Room Rates</p>
            <h3 class="mb-2">Bulk Update Prices</h3>
            <p class="muted-copy">Set one price for every room under the selected room type.</p>
            <div class="d-grid gap-3">
                <?php foreach ($roomTypes as $type): ?>
                    <?php
                        $rateInfo = $typeSummary[$type] ?? [
                            'total' => 0,
                            'lowest_price' => 0.0,
                            'highest_price' => 0.0,
                        ];
                        $currentPrice = (float) $rateInfo['lowest_price'];
                        $priceLabel = $rateInfo['lowest_price'] === $rateInfo['highest_price']
                            ? formatMoney($currentPrice)
                            : formatMoney((float) $rateInfo['lowest_price']) . ' - ' . formatMoney((float) $rateInfo['highest_price']);
                    ?>
                    <form method="post" class="p-3 rounded-3 border border-secondary-subtle">
                        <input type="hidden" name="action" value="update_type_price">
                        <input type="hidden" name="room_type" value="<?php echo e($type); ?>">
                        <label class="form-label" for="rate_<?php echo e($rateInfo['total'] . '_' . str_replace(' ', '_', strtolower($type))); ?>">
                            <?php echo e($type); ?>
                        </label>
                        <p class="form-text mb-2">
                            <?php echo e($rateInfo['total']); ?> rooms currently at <?php echo e($priceLabel); ?>.
                        </p>
                        <div class="input-group">
                            <span class="input-group-text">PHP</span>
                            <input
                                class="form-control"
                                id="rate_<?php echo e($rateInfo['total'] . '_' . str_replace(' ', '_', strtolower($type))); ?>"
                                name="price_per_night"
                                type="number"
                                min="0.01"
                                step="0.01"
                                value="<?php echo e(number_format($currentPrice, 2, '.', '')); ?>"
                                required
                            >
                            <button class="btn btn-warning fw-semibold" type="submit">Set Rate</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel-card p-4">
            <p class="eyebrow mb-1">XML + DOM</p>
            <h3 class="mb-3">Import or Export Rooms</h3>
            <div class="d-grid gap-3">
                <a class="btn btn-outline-light" href="rooms.php?export=xml">Export Rooms to XML</a>
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

            <?php if ($roomData['total_pages'] > 1): ?>
                <nav class="mt-4" aria-label="Room pagination">
                    <ul class="pagination pagination-sm flex-wrap gap-1 mb-0">
                        <li class="page-item <?php echo $roomData['page'] <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="rooms.php<?php echo e($paginationBase . ($paginationBase === '' ? '?' : '&') . 'page=' . max(1, $roomData['page'] - 1)); ?>">Previous</a>
                        </li>
                        <?php for ($index = 1; $index <= $roomData['total_pages']; $index++): ?>
                            <li class="page-item <?php echo $index === $roomData['page'] ? 'active' : ''; ?>">
                                <a class="page-link" href="rooms.php<?php echo e($paginationBase . ($paginationBase === '' ? '?' : '&') . 'page=' . $index); ?>"><?php echo e((string) $index); ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $roomData['page'] >= $roomData['total_pages'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="rooms.php<?php echo e($paginationBase . ($paginationBase === '' ? '?' : '&') . 'page=' . min($roomData['total_pages'], $roomData['page'] + 1)); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
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
                        <p class="eyebrow mb-1">Create Room</p>
                        <h5 class="modal-title" id="createRoomModalLabel">New Room Record</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-grid gap-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="create_room_number">Room Number</label>
                            <input class="form-control bg-dark text-light border-secondary" id="create_room_number" name="room_number" type="text" placeholder="e.g. 105" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="create_room_type">Room Type</label>
                            <select class="form-select bg-dark text-light border-secondary" id="create_room_type" name="room_type">
                                <?php foreach ($roomTypes as $type): ?>
                                    <option value="<?php echo e($type); ?>"><?php echo e($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="create_floor">Floor</label>
                            <input class="form-control bg-dark text-light border-secondary" id="create_floor" name="floor" type="number" min="1" value="1" required>
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
                    <div class="p-3 rounded-3 border border-secondary" style="background: rgba(15, 23, 42, 0.6);">
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
                                <input class="form-control bg-dark text-light border-secondary" id="edit_room_number_<?php echo $roomId; ?>" name="room_number" type="text" value="<?php echo e($room['room_number']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="edit_room_type_<?php echo $roomId; ?>">Room Type</label>
                                <select class="form-select bg-dark text-light border-secondary" id="edit_room_type_<?php echo $roomId; ?>" name="room_type">
                                    <?php foreach ($roomTypes as $type): ?>
                                        <option value="<?php echo e($type); ?>" <?php echo $room['room_type'] === $type ? 'selected' : ''; ?>><?php echo e($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="edit_floor_<?php echo $roomId; ?>">Floor</label>
                                <input class="form-control bg-dark text-light border-secondary" id="edit_floor_<?php echo $roomId; ?>" name="floor" type="number" min="1" value="<?php echo e($room['floor']); ?>" required>
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
                        <div class="p-3 rounded-3 border border-secondary" style="background: rgba(15, 23, 42, 0.6);">
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
</script>

<?php renderAdminLayoutEnd(); ?>
