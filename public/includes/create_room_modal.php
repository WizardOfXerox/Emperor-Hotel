<?php

declare(strict_types=1);

// Shared Create New Room / Suite Modal Component
if (!isset($roomTypes) || empty($roomTypes)) {
    $roomTypes = ['Imperial Deluxe', 'Royal Suite', 'Executive Suite', 'Presidential Suite', 'Ambassador Villa'];
}
if (!isset($roomStatuses) || empty($roomStatuses)) {
    $roomStatuses = ['Available', 'Reserved', 'Occupied', 'Cleaning', 'Maintenance'];
}
?>
<!-- CREATE ROOM MODAL -->
<div class="modal fade" id="createRoomModal" tabindex="-1" aria-labelledby="createRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <form method="post" action="rooms.php" enctype="multipart/form-data">
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
