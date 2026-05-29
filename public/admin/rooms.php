<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

$db = Database::connect();
$currentAdmin = currentUser();
$roomModel = new Room($db);
$editRoom = null;
$roomTypes = Room::types();
$roomStatuses = Room::statuses();

if (isset($_GET['export']) && $_GET['export'] === 'xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rooms-export.xml"');
    echo $roomModel->exportToXml();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'import_xml') {
            if (!isset($_FILES['xml_file']) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Please upload a valid XML file.');
            }

            $result = $roomModel->importFromXml($_FILES['xml_file']['tmp_name']);
            setFlash('success', "XML import finished. Created: {$result['created']}, Updated: {$result['updated']}.");
            redirect('rooms.php');
        }

        if ($action === 'create') {
            $roomModel->create($_POST);
            setFlash('success', 'Room record created.');
            redirect('rooms.php');
        }

        if ($action === 'update_type_price') {
            $roomType = (string) ($_POST['room_type'] ?? '');
            $pricePerNight = (float) ($_POST['price_per_night'] ?? -1);
            $roomModel->updateTypePrice($roomType, $pricePerNight);
            $updatedTypeSummary = $roomModel->typeSummary();
            $totalRooms = $updatedTypeSummary[$roomType]['total'] ?? 0;
            setFlash('success', "{$roomType} rate updated to " . formatMoney($pricePerNight) . " for {$totalRooms} room record(s).");
            redirect('rooms.php');
        }

        if ($action === 'update') {
            $roomModel->update((int) ($_POST['room_id'] ?? 0), $_POST);
            setFlash('success', 'Room record updated.');
            redirect('rooms.php');
        }

        if ($action === 'delete') {
            $roomModel->delete((int) ($_POST['room_id'] ?? 0));
            setFlash('success', 'Room record deleted.');
            redirect('rooms.php');
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('rooms.php');
    }
}

if (isset($_GET['edit'])) {
    $editRoom = $roomModel->find((int) $_GET['edit']);
}

$rooms = $roomModel->all();
$summary = $roomModel->statusSummary();
$typeSummary = $roomModel->typeSummary();

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
        <p class="eyebrow mb-2">Maintenance</p>
        <div class="stat-value"><?php echo e($summary['maintenance']); ?></div>
    </article>
</section>

<section class="row g-4">
    <div class="col-xl-4">
        <div class="panel-card p-4 mb-4">
            <p class="eyebrow mb-1"><?php echo $editRoom ? 'Update room' : 'Create room'; ?></p>
            <h3 class="mb-3"><?php echo $editRoom ? 'Edit Room' : 'New Room'; ?></h3>
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
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="capacity_adults">Adults</label>
                        <input class="form-control" id="capacity_adults" name="capacity_adults" type="number" min="1" value="<?php echo e($editRoom['capacity_adults'] ?? 2); ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="capacity_children">Children</label>
                        <input class="form-control" id="capacity_children" name="capacity_children" type="number" min="0" value="<?php echo e($editRoom['capacity_children'] ?? 1); ?>" required>
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
                <div>
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($editRoom['description'] ?? ''); ?></textarea>
                </div>
                <button class="btn btn-warning fw-semibold" type="submit"><?php echo $editRoom ? 'Save Room' : 'Create Room'; ?></button>
                <?php if ($editRoom): ?>
                    <a class="btn btn-outline-light" href="rooms.php">Cancel Edit</a>
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
                <span class="badge-soft"><?php echo e(count($rooms)); ?> rooms</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Floor</th>
                            <th>Capacity</th>
                            <th>Rate</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td><?php echo e($room['room_number']); ?></td>
                                <td><?php echo e($room['room_type']); ?></td>
                                <td><?php echo e($room['floor']); ?></td>
                                <td><?php echo e($room['capacity_adults']); ?> / <?php echo e($room['capacity_children']); ?></td>
                                <td><?php echo e(formatMoney((float) $room['price_per_night'])); ?></td>
                                <td><span class="badge-soft"><?php echo e($room['status']); ?></span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-light" href="rooms.php?edit=<?php echo e($room['room_id']); ?>">Edit</a>
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
        </div>
    </div>
</section>
<?php renderAdminLayoutEnd(); ?>
