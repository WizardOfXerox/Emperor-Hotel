<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

function minimumExtensionCheckOut(string $currentCheckOut): string
{
    $currentCheckOutDate = DateTimeImmutable::createFromFormat('!Y-m-d', $currentCheckOut);
    $minimumDate = $currentCheckOutDate ? $currentCheckOutDate->modify('+1 day') : new DateTimeImmutable('tomorrow');
    $today = new DateTimeImmutable('today');

    if ($minimumDate < $today) {
        return $today->format('Y-m-d');
    }

    return $minimumDate->format('Y-m-d');
}

$db = Database::connect();
$currentAdmin = currentUser();
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'delete') {
            $reservationModel->delete((int) ($_POST['reservation_id'] ?? 0));
            setFlash('success', 'Reservation deleted.');
            redirect('reservations.php');
        }

        if (in_array($action, ['confirm', 'check_in', 'check_out', 'cancel', 'resolve_conflict'], true)) {
            $newStatus = $reservationModel->applyFrontDeskAction((int) ($_POST['reservation_id'] ?? 0), $action);
            setFlash('success', 'Reservation status changed to ' . $newStatus . '.');
            redirect('reservations.php');
        }

        if ($action === 'flag_conflicts') {
            $flagged = $reservationModel->flagOverlappingConflicts();
            if ($flagged > 0) {
                setFlash('success', $flagged . ' overlapping reservation(s) flagged as Conflict.');
            } else {
                setFlash('success', 'No new overlapping conflicts detected.');
            }
            redirect('reservations.php');
        }

        if ($action === 'extend_stay') {
            $extension = $reservationModel->extendStay(
                (int) ($_POST['reservation_id'] ?? 0),
                (string) ($_POST['new_check_out'] ?? '')
            );
            setFlash(
                'success',
                'Stay extended to ' . $extension['new_check_out']
                . '. Added ' . $extension['extra_nights'] . ' night(s), additional balance '
                . formatMoney((float) $extension['additional_amount'])
                . '. New total is ' . formatMoney((float) $extension['new_total'])
                . '. Use the Payment button to collect the added balance.'
            );
            redirect('reservations.php');
        }

        if ($action === 'reassign_room') {
            $reassignment = $reservationModel->reassignRoom(
                (int) ($_POST['reservation_id'] ?? 0),
                (int) ($_POST['new_room_id'] ?? 0)
            );
            setFlash(
                'success',
                "🔄 Room reassigned successfully! Guest transferred to Room {$reassignment['new_room_number']} ({$reassignment['new_room_type']}). New total: " . formatMoney((float) $reassignment['new_total'])
            );
            redirect('reservations.php');
        }

        if ($action === 'notify_guest') {
            $reservationId = (int) ($_POST['reservation_id'] ?? 0);
            $emailSubject = trim((string) ($_POST['email_subject'] ?? ''));
            $noticeMessage = trim((string) ($_POST['notice_message'] ?? ''));

            $targetRes = $reservationModel->find($reservationId);
            if (!$targetRes) {
                throw new RuntimeException('Reservation not found.');
            }

            if ($emailSubject === '' || $noticeMessage === '') {
                throw new RuntimeException('Email subject and message body are required.');
            }

            $guestEmail = trim((string) ($targetRes['guest_email'] ?? $targetRes['email'] ?? ''));
            if ($guestEmail === '') {
                throw new RuntimeException('Guest email address is missing for this reservation.');
            }
            $guestName = trim($targetRes['first_name'] . ' ' . $targetRes['last_name']);

            $html = "
            <div style='background: #020617; color: #f8fafc; font-family: sans-serif; padding: 40px 20px; text-align: center;'>
                <div style='max-width: 580px; margin: 0 auto; background: #0b1120; border: 1px solid #d4af37; border-radius: 16px; padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: left;'>
                    <div style='text-align: center; margin-bottom: 25px;'>
                        <h1 style='color: #ffdf73; font-family: serif; margin: 0; font-size: 24px; letter-spacing: 2px; text-transform: uppercase;'>THE EMPEROR HOTEL</h1>
                        <p style='color: #94a3b8; font-size: 12px; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px;'>Booking Notice & Concierge Alert</p>
                    </div>
                    <div style='border-top: 1px solid rgba(212,175,55,0.3); border-bottom: 1px solid rgba(212,175,55,0.3); padding: 20px 0; margin-bottom: 20px;'>
                        <p style='color: #cbd5e1; font-size: 15px; margin-bottom: 15px;'>Dear <strong>" . e($guestName) . "</strong>,</p>
                        <div style='background: rgba(253,215,0,0.08); border: 1px solid rgba(253,215,0,0.3); border-radius: 10px; padding: 18px; font-size: 14px; color: #fffdf0; line-height: 1.6;'>
                            " . nl2br(e($noticeMessage)) . "
                        </div>
                    </div>
                    <p style='color: #64748b; font-size: 12px; margin: 0; text-align: center;'>Front Desk Concierge Desk | Royal Bay Boulevard, Metro Manila, Philippines</p>
                </div>
            </div>
            ";

            sendSmtpEmail($guestEmail, $emailSubject, $html);

            // Log in contact_messages
            $contactMsgModel = new ContactMessage($db);
            $contactMsgModel->create([
                'user_id' => $targetRes['user_id'] ?? null,
                'full_name' => $guestName,
                'email' => $guestEmail,
                'phone' => $targetRes['phone'] ?? null,
                'inquiry_type' => 'Front Desk Booking Notice',
                'subject' => $emailSubject,
                'message' => "[Admin Email Notice for Booking #{$reservationId}]\n\n" . $noticeMessage,
            ]);

            setFlash('success', "Email notice dispatched to {$guestName} ({$guestEmail}).");
            redirect('reservations.php');
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('reservations.php');
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$roomTypeFilter = trim((string) ($_GET['room_type'] ?? ''));
$page = (int) ($_GET['page'] ?? 1);
$perPage = (int) ($_GET['per_page'] ?? 10);

$resData = $reservationModel->paginatedLogs([
    'search' => $search,
    'status' => $statusFilter,
    'room_type' => $roomTypeFilter,
], $page, $perPage);

$reservations = $resData['rows'];
$roomTypes = Room::types();
$paymentTotals = $paymentModel->totalsByReservation();

// Detect active overlap conflict IDs for visual highlighting (even before flagging)
$conflictingIds = $reservationModel->getConflictingReservationIds();
$conflictIdSet = array_flip($conflictingIds);

renderAdminLayoutStart('Manage Reservations', 'reservations', $currentAdmin, ['../assets/css/admin/reservations.css?v=20260530-manage-reservations']);
?>
<section class="panel-card p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <p class="eyebrow mb-1">Reservations</p>
            <h3 class="mb-0">Reservation Records</h3>
            <p class="muted-copy mb-0">Review reservation details, update front desk status, extend stays, collect payments, and print receipts.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge-soft"><?php echo e($resData['total']); ?> reservation(s)</span>
            <a class="btn btn-warning btn-sm fw-semibold" href="create-reservation.php"><i class="bi bi-plus-circle me-1"></i>Create Reservation</a>
        </div>
    </div>

    <form method="get" class="row g-2 mb-4 align-items-center">
        <div class="col-md-4 col-lg-4">
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-warning"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control bg-dark text-light border-secondary" placeholder="Search guest name, room #, ID..." value="<?php echo e($search); ?>">
            </div>
        </div>
        <div class="col-md-3 col-lg-3">
            <select name="status" class="form-select bg-dark text-light border-secondary" onchange="this.form.submit()">
                <option value="all" <?php echo $statusFilter === 'all' || $statusFilter === '' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Confirmed" <?php echo $statusFilter === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="Checked-in" <?php echo $statusFilter === 'Checked-in' ? 'selected' : ''; ?>>Checked-in</option>
                <option value="Checked-out" <?php echo $statusFilter === 'Checked-out' ? 'selected' : ''; ?>>Checked-out</option>
                <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                <option value="Conflict" <?php echo $statusFilter === 'Conflict' ? 'selected' : ''; ?>>⚠ Conflict</option>
            </select>
        </div>
        <div class="col-md-3 col-lg-3">
            <select name="room_type" class="form-select bg-dark text-light border-secondary" onchange="this.form.submit()">
                <option value="all" <?php echo $roomTypeFilter === 'all' || $roomTypeFilter === '' ? 'selected' : ''; ?>>All Room Types</option>
                <?php foreach ($roomTypes as $type): ?>
                    <option value="<?php echo e($type); ?>" <?php echo $roomTypeFilter === $type ? 'selected' : ''; ?>><?php echo e($type); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 col-lg-2 d-flex gap-2">
            <select name="per_page" class="form-select bg-dark text-light border-secondary" onchange="this.form.submit()">
                <?php foreach ([10, 25, 50, 100] as $limit): ?>
                    <option value="<?php echo $limit; ?>" <?php echo $perPage === $limit ? 'selected' : ''; ?>><?php echo $limit; ?> / page</option>
                <?php endforeach; ?>
            </select>
            <?php if ($search !== '' || ($statusFilter !== '' && $statusFilter !== 'all') || ($roomTypeFilter !== '' && $roomTypeFilter !== 'all')): ?>
                <a href="reservations.php" class="btn btn-outline-light" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!empty($conflictingIds)): ?>
        <div class="conflict-alert-banner mb-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                <strong class="text-danger"><?php echo e(count($conflictingIds)); ?> reservation(s) have overlapping dates</strong>
                <span class="text-light-emphasis small">— Reservations for the same room with clashing check-in/check-out dates.</span>
                <form method="post" class="ms-auto">
                    <input type="hidden" name="action" value="flag_conflicts">
                    <button class="btn btn-sm btn-outline-danger fw-semibold" type="submit">
                        <i class="bi bi-flag-fill me-1"></i>Flag All as Conflict
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-dark-soft align-middle mb-0 booking-records-table">
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>Room</th>
                    <th>Stay</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th class="reservation-actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$reservations): ?>
                    <tr>
                        <td colspan="6" class="text-light-emphasis text-center py-4">No reservation records match the selected filters.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($reservations as $reservation): ?>
                    <?php
                        $resId = (int) $reservation['reservation_id'];
                        $isConflicting = isset($conflictIdSet[$resId]);
                        $isConflictStatus = $reservation['status'] === 'Conflict';
                        $rowClass = ($isConflicting || $isConflictStatus) ? 'conflict-row' : '';
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                        <td><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></td>
                        <td>
                            <?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?>
                            <?php if ($isConflicting && !$isConflictStatus): ?>
                                <i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Date overlap detected"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isConflictStatus): ?>
                                <span class="badge-conflict"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo e($reservation['status']); ?></span>
                            <?php else: ?>
                                <span class="badge-soft"><?php echo e($reservation['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e(formatMoney((float) $reservation['total_amount'])); ?></td>
                        <td class="reservation-actions-cell">
                            <button
                                class="btn btn-sm <?php echo $isConflictStatus ? 'btn-danger' : 'btn-warning'; ?> reservation-manage-button"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#reservationActionsModal<?php echo e($reservation['reservation_id']); ?>"
                            >
                                <?php echo $isConflictStatus ? 'Resolve' : 'Manage'; ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php renderPaginationControl($resData['total'], $resData['page'], $resData['per_page']); ?>

    <?php foreach ($reservations as $reservation): ?>
        <?php
            $reservationId = (int) $reservation['reservation_id'];
            $frontDeskActions = $reservationModel->availableFrontDeskActions($reservation);
            $canExtendStay = in_array($reservation['status'], ['Pending', 'Confirmed', 'Checked-in'], true);
            $minimumExtensionDate = minimumExtensionCheckOut((string) $reservation['check_out']);
            $reservationTotal = (float) $reservation['total_amount'];
            $totals = $paymentTotals[$reservationId] ?? [
                'confirmed_amount' => 0.0,
                'pending_amount' => 0.0,
                'logged_amount' => 0.0,
            ];
            $confirmedAmount = (float) $totals['confirmed_amount'];
            $pendingAmount = (float) $totals['pending_amount'];
            $balanceDue = max(0.0, $reservationTotal - $confirmedAmount);
        ?>
        <div
            class="modal fade reservation-action-modal"
            id="reservationActionsModal<?php echo e($reservationId); ?>"
            tabindex="-1"
            aria-labelledby="reservationActionsModalLabel<?php echo e($reservationId); ?>"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow mb-1">Reservation #<?php echo e($reservationId); ?></p>
                            <h5 class="modal-title" id="reservationActionsModalLabel<?php echo e($reservationId); ?>">
                                <?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?>
                            </h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($reservation['status'] === 'Conflict'): ?>
                            <div class="conflict-modal-banner">
                                <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                                <div>
                                    <strong class="text-danger d-block mb-1">Overlap Conflict Detected</strong>
                                    <small class="text-light-emphasis">This reservation overlaps with another active booking for the same room. Resolve by cancelling one reservation, changing the room assignment, or adjusting dates.</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="reservation-modal-summary" aria-label="Reservation details">
                            <div>
                                <span>Guest</span>
                                <strong><?php echo e($reservation['first_name'] . ' ' . $reservation['last_name']); ?></strong>
                            </div>
                            <div>
                                <span>Room</span>
                                <strong><?php echo e($reservation['room_number'] . ' • ' . $reservation['room_type']); ?></strong>
                            </div>
                            <div>
                                <span>Stay</span>
                                <strong><?php echo e($reservation['check_in']); ?> to <?php echo e($reservation['check_out']); ?></strong>
                            </div>
                            <div>
                                <span>Status</span>
                                <strong><?php echo e($reservation['status']); ?></strong>
                            </div>
                            <div>
                                <span>Total</span>
                                <strong><?php echo e(formatMoney($reservationTotal)); ?></strong>
                            </div>
                            <div>
                                <span>Confirmed Paid</span>
                                <strong><?php echo e(formatMoney($confirmedAmount)); ?></strong>
                            </div>
                            <div>
                                <span>Pending Payment Logs</span>
                                <strong><?php echo e(formatMoney($pendingAmount)); ?></strong>
                            </div>
                            <div>
                                <span>Balance Due</span>
                                <strong><?php echo e(formatMoney($balanceDue)); ?></strong>
                            </div>
                        </div>

                        <div class="reservation-modal-section">
                            <h6>Front Desk Actions</h6>
                            <div class="reservation-modal-actions">
                                <?php if ($frontDeskActions): ?>
                                    <?php foreach ($frontDeskActions as $actionKey => $actionLabel): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="<?php echo e($actionKey); ?>">
                                            <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">
                                            <button class="btn btn-sm <?php echo $actionKey === 'cancel' ? 'btn-outline-danger' : 'btn-outline-warning'; ?>" type="submit"><?php echo e($actionLabel); ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-light-emphasis small">No front desk status action is available for this reservation.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="reservation-modal-section">
                            <h6>Records and Payments</h6>
                            <div class="reservation-modal-actions">
                                <a class="btn btn-sm btn-outline-warning fw-semibold" href="receipt.php?reservation_id=<?php echo e($reservationId); ?>"><i class="bi bi-receipt me-1"></i>Receipt</a>
                                <a class="btn btn-sm btn-warning fw-semibold" href="payments.php?reservation_id=<?php echo e($reservationId); ?>"><i class="bi bi-credit-card me-1"></i>Payments &amp; Refunds</a>
                            </div>
                        </div>

                        <?php if ($canExtendStay): ?>
                            <?php
                                $bookedRanges = $reservationModel->getBookedDateRangesForRoom((int) $reservation['room_id'], $reservationId);
                                $bookedDatesMap = [];
                                foreach ($bookedRanges as $bRange) {
                                    $bStart = new DateTimeImmutable((string) $bRange['check_in']);
                                    $bEnd = new DateTimeImmutable((string) $bRange['check_out']);
                                    $curr = $bStart;
                                    while ($curr < $bEnd) {
                                        $bookedDatesMap[$curr->format('Y-m-d')] = true;
                                        $curr = $curr->modify('+1 day');
                                    }
                                }
                                
                                $checkInStr = (string) $reservation['check_in'];
                                $checkOutStr = (string) $reservation['check_out'];
                                $currentCheckOutObj = new DateTimeImmutable($checkOutStr);
                                $pricePerNight = (float) ($reservation['price_per_night'] ?? 4500.0);

                                // Build 3-month 7-column calendar grid view starting from check-out month
                                $monthsData = [];
                                $hasFirstBlockedDate = false;

                                for ($m = 0; $m < 3; $m++) {
                                    $monthStart = $currentCheckOutObj->modify("first day of this month +{$m} month");
                                    $monthTitle = $monthStart->format('F Y');
                                    $daysInMonth = (int) $monthStart->format('t');
                                    $startDayOfWeek = (int) $monthStart->format('w'); // 0 = Sun, 6 = Sat

                                    $gridCells = [];
                                    for ($pad = 0; $pad < $startDayOfWeek; $pad++) {
                                        $gridCells[] = ['type' => 'empty'];
                                    }

                                    for ($day = 1; $day <= $daysInMonth; $day++) {
                                        $dateStr = sprintf('%s-%02d', $monthStart->format('Y-m'), $day);
                                        $isPast = $dateStr < $checkInStr;
                                        $isCurrentStay = ($dateStr >= $checkInStr && $dateStr < $checkOutStr);
                                        $isCheckOutDay = ($dateStr === $checkOutStr);
                                        $isAfterCheckOut = ($dateStr > $checkOutStr);

                                        $isBooked = isset($bookedDatesMap[$dateStr]);
                                        if ($isAfterCheckOut && $isBooked) {
                                            $hasFirstBlockedDate = true;
                                        }

                                        $gridCells[] = [
                                            'type' => 'day',
                                            'day_num' => $day,
                                            'date' => $dateStr,
                                            'is_current_stay' => $isCurrentStay,
                                            'is_checkout' => $isCheckOutDay,
                                            'is_available' => ($isAfterCheckOut && !$isBooked && !$hasFirstBlockedDate),
                                            'is_reserved' => ($isAfterCheckOut && $isBooked),
                                            'is_blocked_beyond' => ($isAfterCheckOut && !$isBooked && $hasFirstBlockedDate),
                                            'is_disabled' => ($isPast || $isCurrentStay || $isCheckOutDay || $isBooked || $hasFirstBlockedDate),
                                        ];
                                    }

                                    $monthsData[] = [
                                        'title' => $monthTitle,
                                        'cells' => $gridCells,
                                    ];
                                }
                            ?>
                            <div class="reservation-modal-section border border-warning border-opacity-30 rounded-4 p-3 my-3 bg-dark" style="box-shadow: 0 8px 25px rgba(0,0,0,0.5);">
                                <div class="d-flex flex-wrap align-items-center justify-content-between pb-2 mb-3 border-bottom border-warning border-opacity-25">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-calendar3 text-warning fs-5"></i>
                                        <h6 class="text-warning font-serif fw-bold m-0">Extend Stay Calendar Grid</h6>
                                    </div>
                                    <span class="badge bg-dark border border-gold text-gold font-sans text-xs">Room <?php echo e($reservation['room_number']); ?> (<?php echo e($reservation['room_type']); ?>)</span>
                                </div>

                                <!-- Month Navigation Toolbar -->
                                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 px-1 gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2.5 py-0.5 text-xs font-serif fw-bold" id="cal_prev_btn_<?php echo e($reservationId); ?>" onclick="changeExtendMonth('<?php echo e($reservationId); ?>', -1)" disabled>
                                            <i class="bi bi-chevron-left me-1"></i>Prev Month
                                        </button>
                                        <span class="text-white font-serif fw-bold fs-6" id="cal_month_title_<?php echo e($reservationId); ?>">
                                            <i class="bi bi-calendar-month text-warning me-1"></i><?php echo e($monthsData[0]['title']); ?>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2.5 py-0.5 text-xs font-serif fw-bold" id="cal_next_btn_<?php echo e($reservationId); ?>" onclick="changeExtendMonth('<?php echo e($reservationId); ?>', 1)">
                                            Next Month <i class="bi bi-chevron-right ms-1"></i>
                                        </button>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 text-xs">
                                        <span class="d-inline-flex align-items-center gap-1 text-white-50"><span style="width:9px;height:9px;border-radius:50%;background:#eab308;display:inline-block;"></span> Current Stay</span>
                                        <span class="d-inline-flex align-items-center gap-1 text-white-50"><span style="width:9px;height:9px;border-radius:50%;background:#22c55e;display:inline-block;"></span> 🟢 Available</span>
                                        <span class="d-inline-flex align-items-center gap-1 text-white-50"><span style="width:9px;height:9px;border-radius:50%;background:#ef4444;display:inline-block;"></span> 🔴 ❌ Reserved</span>
                                    </div>
                                </div>

                                <!-- Month Grids Container -->
                                <?php foreach ($monthsData as $mIndex => $mGroup): ?>
                                    <div id="extend_cal_month_<?php echo e($reservationId); ?>_<?php echo $mIndex; ?>" class="extend-cal-month-view <?php echo $mIndex > 0 ? 'd-none' : ''; ?>" data-title="<?php echo e($mGroup['title']); ?>">
                                        <!-- 7-Column Weekday Header -->
                                        <div class="extend-cal-grid-header mb-2 text-center text-uppercase font-serif fw-bold text-xs text-warning" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px;">
                                            <div>SUN</div><div>MON</div><div>TUE</div><div>WED</div><div>THU</div><div>FRI</div><div>SAT</div>
                                        </div>

                                        <!-- 7-Column Days Grid -->
                                        <div class="extend-cal-grid-days text-center mb-3" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px;">
                                            <?php foreach ($mGroup['cells'] as $cell): ?>
                                                <?php if ($cell['type'] === 'empty'): ?>
                                                    <div style="aspect-ratio: 1/1;"></div>
                                                <?php elseif ($cell['is_current_stay'] || $cell['is_checkout']): ?>
                                                    <div class="extend-day-cell current-stay-cell d-flex flex-column align-items-center justify-content-center rounded-3 p-1" title="Current Stay (Check-Out: <?php echo e($checkOutStr); ?>)" style="aspect-ratio: 1/1; background: rgba(212, 175, 55, 0.25); border: 1.5px solid #D4AF37; color: #FFDF73; font-weight: 700; font-size: 12px;">
                                                        <span><?php echo e($cell['day_num']); ?></span>
                                                        <small style="font-size: 7.5px;" class="text-uppercase"><?php echo $cell['is_checkout'] ? 'OUT' : 'STAY'; ?></small>
                                                    </div>
                                                <?php elseif ($cell['is_available']): ?>
                                                    <button type="button" class="btn p-0 extend-day-cell available-cell d-flex flex-column align-items-center justify-content-center rounded-3 date-extend-option-grid-<?php echo e($reservationId); ?>" data-date="<?php echo e($cell['date']); ?>" onclick="selectGridCheckOut('<?php echo e($reservationId); ?>', '<?php echo e($cell['date']); ?>', this, <?php echo e($pricePerNight); ?>, '<?php echo e($checkOutStr); ?>', <?php echo e($reservationTotal); ?>)" title="🟢 Available for Extension" style="aspect-ratio: 1/1; background: rgba(34, 197, 94, 0.2); border: 1.5px solid #22c55e; color: #4ade80; font-weight: 700; font-size: 12px;">
                                                        <span><?php echo e($cell['day_num']); ?></span>
                                                        <small style="font-size: 7.5px;">🟢 FREE</small>
                                                    </button>
                                                <?php elseif ($cell['is_reserved']): ?>
                                                    <div class="extend-day-cell reserved-cell d-flex flex-column align-items-center justify-content-center rounded-3 opacity-75" title="🔴 ❌ Reserved by another guest" style="aspect-ratio: 1/1; background: rgba(239, 68, 68, 0.25); border: 1.5px solid #ef4444; color: #f87171; font-weight: 700; font-size: 12px; cursor: not-allowed;">
                                                        <span><?php echo e($cell['day_num']); ?></span>
                                                        <small style="font-size: 7.5px;">❌ BUSY</small>
                                                    </div>
                                                <?php elseif ($cell['is_blocked_beyond']): ?>
                                                    <div class="extend-day-cell blocked-beyond-cell d-flex flex-column align-items-center justify-content-center rounded-3 opacity-40" title="🚫 Disabled - Blocked by prior reservation" style="aspect-ratio: 1/1; background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255, 255, 255, 0.08); color: #475569; font-weight: 700; font-size: 12px; cursor: not-allowed;">
                                                        <span><?php echo e($cell['day_num']); ?></span>
                                                        <small style="font-size: 7.5px;">🚫 OFF</small>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="extend-day-cell text-muted opacity-25 d-flex align-items-center justify-content-center rounded-3" style="aspect-ratio: 1/1; border: 1px solid rgba(255,255,255,0.05); font-size: 12px;">
                                                        <span><?php echo e($cell['day_num']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Selected Extension Summary & Form -->
                                <form method="post" class="extend-stay-form mt-2 p-2.5 rounded-3 bg-dark border border-warning border-opacity-25">
                                    <input type="hidden" name="action" value="extend_stay">
                                    <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">
                                    <input type="hidden" id="modal_new_check_out_<?php echo e($reservationId); ?>" name="new_check_out" value="<?php echo e($minimumExtensionDate); ?>">

                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div class="text-xs">
                                            <span class="text-muted">New Check-Out:</span> <strong class="text-warning font-monospace fs-6" id="summary_new_checkout_<?php echo e($reservationId); ?>"><?php echo e($minimumExtensionDate); ?></strong>
                                            <span class="badge bg-gold text-dark font-sans fw-bold ms-2" id="summary_extra_nights_<?php echo e($reservationId); ?>">+1 Night</span>
                                            <span class="text-success fw-bold ms-2" id="summary_extra_cost_<?php echo e($reservationId); ?>">+<?php echo e(formatMoney($pricePerNight)); ?></span>
                                        </div>
                                        <button class="btn btn-sm btn-warning px-4 font-serif fw-bold shadow-sm" type="submit"><i class="bi bi-check-circle-fill me-1"></i>Confirm Extension</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="reservation-modal-section">
                                <h6>Extend Stay</h6>
                                <p class="text-muted text-xs mb-0"><i class="bi bi-info-circle text-warning me-1"></i>Stay extension is unavailable for <strong><?php echo e($reservation['status']); ?></strong> reservations.</p>
                            </div>
                        <?php endif; ?>

                        <?php if (in_array($reservation['status'], ['Pending', 'Confirmed', 'Checked-in'], true)): ?>
                            <?php
                                $allRoomsWithAvail = $reservationModel->roomsWithDateAvailability((string) $reservation['check_in'], (string) $reservation['check_out'], $reservationId);
                            ?>
                            <div class="reservation-modal-section border border-info border-opacity-30 rounded-4 p-3 my-3 bg-dark" style="box-shadow: 0 8px 25px rgba(0,0,0,0.5);">
                                <div class="d-flex flex-wrap align-items-center justify-content-between pb-2 mb-3 border-bottom border-info border-opacity-25">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-arrow-left-right text-info fs-5"></i>
                                        <h6 class="text-info font-serif fw-bold m-0">Reassign / Transfer Room Suite</h6>
                                    </div>
                                    <span class="badge bg-dark border border-gold text-gold font-sans text-xs">Current: Room <?php echo e($reservation['room_number']); ?></span>
                                </div>
                                <p class="text-muted text-xs mb-3">Room availability for stay dates <strong class="text-white"><?php echo e($reservation['check_in']); ?></strong> &rarr; <strong class="text-white"><?php echo e($reservation['check_out']); ?></strong>:</p>

                                <!-- Visual Room Inventory Availability Badges -->
                                <div class="d-flex flex-column gap-2 mb-3" style="max-height: 180px; overflow-y: auto; padding: 4px;">
                                    <?php foreach ($allRoomsWithAvail as $altRoom): ?>
                                        <?php
                                            $isCurrent = (int) $altRoom['room_id'] === (int) $reservation['room_id'];
                                            $isMaint = ($altRoom['status'] ?? '') === 'Maintenance';
                                            $isAvailable = !empty($altRoom['is_available_for_dates']) && !$isMaint;
                                        ?>
                                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border text-xs" style="background: rgba(15, 23, 42, 0.7); border-color: <?php echo $isCurrent ? 'rgba(212, 175, 55, 0.4)' : ($isAvailable ? 'rgba(34, 197, 94, 0.3)' : 'rgba(239, 68, 68, 0.2)'); ?> !important;">
                                            <div class="d-flex align-items-center gap-2">
                                                <strong class="text-white font-serif fs-6">Room <?php echo e($altRoom['room_number']); ?></strong>
                                                <span class="text-muted">&mdash; <?php echo e($altRoom['room_type']); ?> (Fl. <?php echo e($altRoom['floor']); ?>)</span>
                                                <span class="text-warning font-mono fw-bold"><?php echo formatMoney((float)$altRoom['price_per_night']); ?>/night</span>
                                            </div>

                                            <div>
                                                <?php if ($isCurrent): ?>
                                                    <span class="badge bg-gold text-dark font-serif fw-bold px-2 py-1"><i class="bi bi-pin-angle-fill me-1"></i>Current Room</span>
                                                <?php elseif ($isMaint): ?>
                                                    <span class="badge bg-danger bg-opacity-25 text-danger border border-danger font-sans text-xs px-2 py-1"><i class="bi bi-tools me-1"></i>Maintenance Hold</span>
                                                <?php elseif ($isAvailable): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="reassign_room">
                                                        <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">
                                                        <input type="hidden" name="new_room_id" value="<?php echo e($altRoom['room_id']); ?>">
                                                        <button class="btn btn-xs btn-success font-serif fw-bold px-3 text-nowrap" type="submit" onclick="return confirm('Reassign guest to Room <?php echo e($altRoom['room_number']); ?>?')">
                                                            🟢 Transfer to Room <?php echo e($altRoom['room_number']); ?>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-25 text-white-50 border border-secondary font-sans text-xs px-2 py-1"><i class="bi bi-x-circle me-1"></i>Occupied for Dates</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Direct Email Notification Section -->
                        <?php $resGuestEmail = (string) ($reservation['guest_email'] ?? $reservation['email'] ?? ''); ?>
                        <div class="reservation-modal-section border border-warning border-opacity-30 rounded-4 p-3.5 my-3 bg-dark" style="box-shadow: 0 8px 25px rgba(0,0,0,0.5);">
                            <div class="d-flex align-items-center gap-2 pb-2 mb-3 border-bottom border-warning border-opacity-25">
                                <i class="bi bi-envelope-exclamation-fill text-warning fs-5"></i>
                                <h6 class="text-warning font-serif fw-bold m-0">Send Direct Email Notice to Guest</h6>
                            </div>
                            <form method="post" action="reservations.php">
                                <input type="hidden" name="action" value="notify_guest">
                                <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">

                                <div class="row g-2.5 mb-3">
                                    <div class="col-12 col-md-7">
                                        <label class="form-label text-xs text-light fw-semibold mb-1">Email Subject</label>
                                        <input type="text" name="email_subject" class="form-control form-control-sm bg-dark text-light border-secondary text-xs" value="👑 [The Emperor Hotel] Important Booking Notice regarding Reservation #<?php echo e($reservationId); ?>" required>
                                    </div>
                                    <div class="col-12 col-md-5">
                                        <label class="form-label text-xs text-light fw-semibold mb-1">Message Category</label>
                                        <select class="form-select form-select-sm bg-dark text-light border-secondary text-xs" onchange="
                                            const textarea = document.getElementById('notice_msg_<?php echo e($reservationId); ?>');
                                            const val = this.value;
                                            if (val === 'conflict') {
                                                textarea.value = 'Dear <?php echo e($reservation['first_name']); ?>,\n\nWe are writing to notify you regarding an operational schedule update for your upcoming stay reservation #<?php echo e($reservationId); ?> (Room #<?php echo e($reservation['room_number']); ?>). Please contact our front desk concierge team at your earliest convenience to review your booking preferences.\n\nWarm regards,\nEmperor Hotel Concierge Desk';
                                            } else if (val === 'payment') {
                                                textarea.value = 'Dear <?php echo e($reservation['first_name']); ?>,\n\nThis is a friendly notice regarding the balance due for your reservation #<?php echo e($reservationId); ?> (Room #<?php echo e($reservation['room_number']); ?>). Outstanding balance: <?php echo formatMoney($balanceDue); ?>. Kindly settle this balance via your guest dashboard or at front desk check-in.\n\nThank you,\nEmperor Hotel Guest Relations';
                                            } else if (val === 'transfer') {
                                                textarea.value = 'Dear <?php echo e($reservation['first_name']); ?>,\n\nWe have updated your suite allocation for reservation #<?php echo e($reservationId); ?> to enhance your luxury stay experience. Please log into your guest dashboard to view your suite details.\n\nWarm regards,\nEmperor Hotel Guest Relations';
                                            }
                                        ">
                                            <option value="conflict">⚠️ Booking Issue / Schedule Adjustment</option>
                                            <option value="payment">💳 Payment & Balance Reminder</option>
                                            <option value="transfer">🔄 Room Suite Update / Transfer Notice</option>
                                            <option value="custom">📝 Custom Instruction</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-xs text-light fw-semibold mb-1">Message Content</label>
                                    <textarea name="notice_message" id="notice_msg_<?php echo e($reservationId); ?>" rows="4" class="form-control form-control-sm bg-dark text-light border-warning rounded-3 text-xs" style="padding: 10px 14px; min-height: 110px; line-height: 1.5; resize: vertical;" placeholder="Type custom message to guest..." required>Dear <?php echo e($reservation['first_name']); ?>,

We are writing to notify you regarding an operational schedule update for your upcoming stay reservation #<?php echo e($reservationId); ?> (Room #<?php echo e($reservation['room_number']); ?>). Please contact our front desk concierge team at your earliest convenience to review your booking preferences.

Warm regards,
Emperor Hotel Concierge Desk</textarea>
                                </div>

                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 pt-2 border-top border-secondary-subtle">
                                    <small class="text-muted text-xs me-2"><i class="bi bi-send-check text-warning me-1"></i>Sends instant SMTP email to <strong class="text-light"><?php echo e($resGuestEmail); ?></strong></small>
                                    <button type="submit" class="btn btn-sm btn-warning font-serif fw-bold px-3.5 py-2 text-dark text-nowrap shadow-sm ms-auto me-0">
                                        <i class="bi bi-send-fill me-1.5"></i>Send Email Notice to Guest
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="reservation-modal-section reservation-modal-section--danger">
                            <h6>Danger Zone</h6>
                            <div class="reservation-modal-actions">
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="reservation_id" value="<?php echo e($reservationId); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete Reservation</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</section>
<script>
document.querySelectorAll(".reservation-action-modal").forEach((modal) => {
    document.body.appendChild(modal);
});

function selectGridCheckOut(resId, dateStr, btn, ratePerNight, currentCheckOut, currentTotal) {
    const input = document.getElementById('modal_new_check_out_' + resId);
    if (input) {
        input.value = dateStr;
    }
    
    // Highlight selected grid cell
    document.querySelectorAll('.date-extend-option-grid-' + resId).forEach(b => {
        b.style.background = 'rgba(34, 197, 94, 0.2)';
        b.style.borderColor = '#22c55e';
        b.style.color = '#4ade80';
        b.style.boxShadow = 'none';
    });
    
    btn.style.background = '#22c55e';
    btn.style.borderColor = '#ffffff';
    btn.style.color = '#020617';
    btn.style.boxShadow = '0 0 14px rgba(34, 197, 94, 0.9)';

    // Calculate extra nights and extra cost
    const dOut = new Date(currentCheckOut);
    const dNew = new Date(dateStr);
    const diffTime = Math.abs(dNew - dOut);
    const extraNights = Math.round(diffTime / (1000 * 60 * 60 * 24));
    const extraCost = extraNights * ratePerNight;
    
    const checkoutEl = document.getElementById('summary_new_checkout_' + resId);
    const nightsEl = document.getElementById('summary_extra_nights_' + resId);
    const costEl = document.getElementById('summary_extra_cost_' + resId);

    if (checkoutEl) checkoutEl.textContent = dateStr;
    if (nightsEl) nightsEl.textContent = '+' + extraNights + (extraNights === 1 ? ' Night' : ' Nights');
    if (costEl) costEl.textContent = '+' + new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(extraCost);
}

window.extendCalCurrentMonthIndex = window.extendCalCurrentMonthIndex || {};

function changeExtendMonth(resId, dir) {
    if (window.extendCalCurrentMonthIndex[resId] === undefined) {
        window.extendCalCurrentMonthIndex[resId] = 0;
    }
    const totalMonths = 3;
    let newIdx = window.extendCalCurrentMonthIndex[resId] + dir;
    if (newIdx < 0) newIdx = 0;
    if (newIdx >= totalMonths) newIdx = totalMonths - 1;

    window.extendCalCurrentMonthIndex[resId] = newIdx;

    for (let i = 0; i < totalMonths; i++) {
        const monthDiv = document.getElementById(`extend_cal_month_${resId}_${i}`);
        if (monthDiv) {
            if (i === newIdx) {
                monthDiv.classList.remove('d-none');
                const titleSpan = document.getElementById(`cal_month_title_${resId}`);
                if (titleSpan) {
                    titleSpan.innerHTML = `<i class="bi bi-calendar-month text-warning me-1"></i>${monthDiv.dataset.title}`;
                }
            } else {
                monthDiv.classList.add('d-none');
            }
        }
    }

    const prevBtn = document.getElementById(`cal_prev_btn_${resId}`);
    const nextBtn = document.getElementById(`cal_next_btn_${resId}`);

    if (prevBtn) prevBtn.disabled = (newIdx === 0);
    if (nextBtn) nextBtn.disabled = (newIdx === totalMonths - 1);
}
</script>
<?php renderAdminLayoutEnd(); ?>
