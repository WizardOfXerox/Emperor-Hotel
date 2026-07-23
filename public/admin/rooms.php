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
    $allowedKeys = ['search', 'room_type', 'status', 'floor', 'sort', 'direction', 'page', 'per_page', 'edit'];
    $query = [];

    foreach ($allowedKeys as $key) {
        if (array_key_exists($key, $params) && $params[$key] !== '' && $params[$key] !== null) {
            $query[$key] = $params[$key];
        }
    }

    foreach (['page', 'edit'] as $stripKey) {
        unset($query[$stripKey]);
    }

    return $query ? '?' . http_build_query($query) : '';
}

$db = Database::connect();
$currentAdmin = currentUser();
$roomModel = new Room($db);
$editRoom = null;
$roomTypes = Room::types();
$roomStatuses = Room::statuses();
$roomData = null;

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
            $roomModel->create($_POST);
            setFlash('success', 'Room record created.');
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
            $roomModel->update((int) ($_POST['room_id'] ?? 0), $_POST);
            setFlash('success', 'Room record updated.');
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

if (isset($_GET['edit'])) {
    $editRoom = $roomModel->find((int) $_GET['edit']);
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
            <a class="btn btn-warning btn-sm fw-semibold" href="rooms.php?export=xml">Export XML</a>
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
            <p class="eyebrow mb-1"><?php echo $editRoom ? 'Update room' : 'Create room'; ?></p>
            <h3 class="mb-2"><?php echo $editRoom ? 'Edit Room' : 'New Room'; ?></h3>
            <div class="p-2.5 rounded-3 mb-3 text-xs font-serif border" style="background: rgba(212, 175, 55, 0.08); border-color: rgba(212, 175, 55, 0.25) !important;">
                <i class="bi bi-info-circle-fill text-warning me-1"></i>
                <strong>Catalog Policy:</strong> Manage Room Number, Suite Type, Floor, Nightly Rate, and Status here. Room photos and media assets are resolved automatically from the system catalog.
            </div>
            <form method="post" class="d-grid gap-3">
                <input type="hidden" name="action" value="<?php echo $editRoom ? 'update' : 'create'; ?>">
                <?php if ($editRoom): ?>
                    <input type="hidden" name="room_id" value="<?php echo e($editRoom['room_id']); ?>">
                <?php endif; ?>
                <div>
                    <label class="form-label" for="room_number">Room Number</label>
                    <input class="form-control" id="room_number" name="room_number" type="text" value="<?php echo e($editRoom['room_number'] ?? ''); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="room_type">Room Type</label>
                    <select class="form-select" id="room_type" name="room_type">
                        <?php foreach ($roomTypes as $type): ?>
                            <option value="<?php echo e($type); ?>" <?php echo (($editRoom['room_type'] ?? '') === $type) ? 'selected' : ''; ?>><?php echo e($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="floor">Floor</label>
                        <input class="form-control" id="floor" name="floor" type="number" value="<?php echo e($editRoom['floor'] ?? 1); ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="price_per_night">Price / Night</label>
                        <input class="form-control" id="price_per_night" name="price_per_night" type="number" min="0.01" step="0.01" value="<?php echo e($editRoom['price_per_night'] ?? '0.00'); ?>" required>
                    </div>
                </div>
                <div>
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <?php foreach ($roomStatuses as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo (($editRoom['status'] ?? 'Available') === $status) ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-warning fw-semibold" type="submit"><?php echo $editRoom ? 'Save Room' : 'Create Room'; ?></button>
                <?php if ($editRoom): ?>
                    <a class="btn btn-outline-light" href="rooms.php<?php echo e($paginationBase); ?>">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

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
                            <tr>
                                <td><?php echo e($room['room_number']); ?></td>
                                <td><?php echo e($room['room_type']); ?></td>
                                <td><?php echo e($room['floor']); ?></td>
                                <td><?php echo e(formatMoney((float) $room['price_per_night'])); ?></td>
                                <td><span class="badge-soft"><?php echo e($room['status']); ?></span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-light" href="rooms.php<?php echo e($paginationBase . ($paginationBase === '' ? '?' : '&') . 'edit=' . urlencode((string) $room['room_id'])); ?>">Edit</a>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="room_id" value="<?php echo e($room['room_id']); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
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
<?php renderAdminLayoutEnd(); ?>
