<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

$db = Database::connect();
$currentAdmin = currentUser();
$contactMessageModel = new ContactMessage($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'reply') {
            $messageId = (int) ($_POST['message_id'] ?? 0);
            $replyMessage = trim((string) ($_POST['reply_message'] ?? ''));

            $targetMsg = $contactMessageModel->find($messageId);
            if (!$targetMsg) {
                throw new RuntimeException('Target guest message was not found.');
            }

            if ($replyMessage === '') {
                throw new RuntimeException('Reply message content cannot be empty.');
            }

            $contactMessageModel->reply($messageId, $replyMessage);

            // Dispatch Email Reply to Customer via SMTP
            $guestName = e($targetMsg['full_name']);
            $guestEmail = $targetMsg['email'];
            $inquiryType = e($targetMsg['inquiry_type']);

            $subject = "👑 [The Emperor Hotel] Response to Your Inquiry: {$inquiryType}";
            $emailHtml = "
            <div style='background: #020617; color: #f8fafc; font-family: sans-serif; padding: 40px 20px; text-align: center;'>
                <div style='max-width: 580px; margin: 0 auto; background: #0b1120; border: 1px solid #d4af37; border-radius: 16px; padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: left;'>
                    <div style='text-align: center; margin-bottom: 25px;'>
                        <h1 style='color: #ffdf73; font-family: serif; margin: 0; font-size: 24px; letter-spacing: 2px; text-transform: uppercase;'>THE EMPEROR HOTEL</h1>
                        <p style='color: #94a3b8; font-size: 12px; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px;'>Guest Relations & Concierge Response</p>
                    </div>
                    <div style='border-top: 1px solid rgba(212,175,55,0.3); border-bottom: 1px solid rgba(212,175,55,0.3); padding: 25px 0; margin-bottom: 25px;'>
                        <p style='color: #cbd5e1; font-size: 15px; margin-bottom: 15px;'>Dear <strong>{$guestName}</strong>,</p>
                        <p style='color: #cbd5e1; font-size: 14px; line-height: 1.6;'>Thank you for contacting The Emperor Hotel. Here is the response from our guest relations team regarding your inquiry:</p>
                        
                        <div style='background: rgba(253,215,0,0.08); border: 1px solid rgba(253,215,0,0.3); border-radius: 10px; padding: 18px; margin: 20px 0; font-size: 14px; color: #fffdf0; line-height: 1.6;'>
                            " . nl2br(e($replyMessage)) . "
                        </div>

                        <div style='background: rgba(15,23,42,0.6); border-radius: 8px; padding: 12px; font-size: 12px; color: #94a3b8;'>
                            <strong>Original Message (" . e($inquiryType) . "):</strong><br>
                            \"" . e($targetMsg['message']) . "\"
                        </div>
                    </div>
                    <p style='color: #64748b; font-size: 12px; margin: 0; text-align: center;'>Royal Bay Boulevard, Metro Manila, Philippines | Front Desk Concierge Desk 24/7</p>
                </div>
            </div>
            ";

            sendSmtpEmail($guestEmail, $subject, $emailHtml);

            setFlash('success', "Reply dispatched via SMTP to {$guestName} ({$guestEmail}).");
            redirect('messages.php');
        }

        if ($action === 'send_direct_email') {
            $recipientEmail = trim((string) ($_POST['recipient_email'] ?? ''));
            $recipientName = trim((string) ($_POST['recipient_name'] ?? 'Guest'));
            $emailSubject = trim((string) ($_POST['email_subject'] ?? ''));
            $emailBody = trim((string) ($_POST['email_body'] ?? ''));

            if ($recipientEmail === '' || $emailSubject === '' || $emailBody === '') {
                throw new RuntimeException('Recipient email, subject, and message content are required.');
            }

            $html = "
            <div style='background: #020617; color: #f8fafc; font-family: sans-serif; padding: 40px 20px; text-align: center;'>
                <div style='max-width: 580px; margin: 0 auto; background: #0b1120; border: 1px solid #d4af37; border-radius: 16px; padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: left;'>
                    <div style='text-align: center; margin-bottom: 25px;'>
                        <h1 style='color: #ffdf73; font-family: serif; margin: 0; font-size: 24px; letter-spacing: 2px; text-transform: uppercase;'>THE EMPEROR HOTEL</h1>
                        <p style='color: #94a3b8; font-size: 12px; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px;'>Guest Relations & Concierge Notice</p>
                    </div>
                    <div style='border-top: 1px solid rgba(212,175,55,0.3); border-bottom: 1px solid rgba(212,175,55,0.3); padding: 25px 0; margin-bottom: 25px;'>
                        <p style='color: #cbd5e1; font-size: 15px; margin-bottom: 15px;'>Dear <strong>" . e($recipientName) . "</strong>,</p>
                        <div style='background: rgba(253,215,0,0.08); border: 1px solid rgba(253,215,0,0.3); border-radius: 10px; padding: 18px; margin: 20px 0; font-size: 14px; color: #fffdf0; line-height: 1.6;'>
                            " . nl2br(e($emailBody)) . "
                        </div>
                    </div>
                    <p style='color: #64748b; font-size: 12px; margin: 0; text-align: center;'>Royal Bay Boulevard, Metro Manila, Philippines | Front Desk Concierge Desk 24/7</p>
                </div>
            </div>
            ";

            sendSmtpEmail($recipientEmail, $emailSubject, $html);

            // Log as outbound notification in contact_messages
            $contactMessageModel->create([
                'full_name' => $recipientName,
                'email' => $recipientEmail,
                'inquiry_type' => 'Admin Direct Email Notice',
                'subject' => $emailSubject,
                'message' => "[Outbound Direct Email Notice]\n\n" . $emailBody,
            ]);

            setFlash('success', "Direct email notice dispatched to {$recipientName} ({$recipientEmail}).");
            redirect('messages.php');
        }

        if ($action === 'delete') {
            $messageId = (int) ($_POST['message_id'] ?? 0);
            $contactMessageModel->delete($messageId);
            setFlash('success', 'Guest message deleted from inbox.');
            redirect('messages.php');
        }

        if ($action === 'mark_read') {
            $messageId = (int) ($_POST['message_id'] ?? 0);
            $contactMessageModel->markAsRead($messageId);
            setFlash('success', 'Message marked as read.');
            redirect('messages.php');
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('messages.php');
    }
}

// Auto-mark as read if viewing specific message via GET
if (isset($_GET['view'])) {
    $viewId = (int) $_GET['view'];
    $contactMessageModel->markAsRead($viewId);
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$inquiryFilter = trim((string) ($_GET['inquiry_type'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(1, (int) ($_GET['per_page'] ?? 10));

$filters = [
    'search' => $search,
    'status' => $statusFilter,
    'inquiry_type' => $inquiryFilter,
];

$userModel = new User($db);
$registeredUsers = $userModel->all();

$messageData = $contactMessageModel->paginated($filters, $page, $perPage);
$messages = $messageData['rows'];
$summary = $contactMessageModel->statusSummary();

renderAdminLayoutStart('Guest Messages', 'messages', $currentAdmin, ['../assets/css/admin/rooms.css']);
?>
<section class="stats-grid mb-4">
    <article class="stat-tile">
        <p class="eyebrow mb-2">Unread Inquiries</p>
        <div class="stat-value text-warning"><?php echo e($summary['unread']); ?></div>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Replied Inquiries</p>
        <div class="stat-value text-success"><?php echo e($summary['replied']); ?></div>
    </article>
    <article class="stat-tile">
        <p class="eyebrow mb-2">Total Inquiries</p>
        <div class="stat-value"><?php echo e($summary['total']); ?></div>
    </article>
</section>

<section class="panel-card p-4 h-100 d-flex flex-column justify-content-between">
    <div>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div>
                <p class="eyebrow mb-1">Guest Concierge Inbox</p>
                <h3 class="mb-0">Customer Inquiries & Messages</h3>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge-soft"><?php echo e($messageData['total']); ?> message(s)</span>
                <button type="button" class="btn btn-sm btn-warning font-serif fw-semibold" data-bs-toggle="modal" data-bs-target="#composeEmailModal">
                    <i class="bi bi-pencil-square me-1"></i>Compose Direct Email
                </button>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <form method="get" class="row g-2 mb-3 align-items-center">
            <div class="col-12 col-sm-6 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                    <input type="search" name="search" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Search name, email, phone, or text..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-6 col-sm-3 col-md-3">
                <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="Unread" <?php echo $statusFilter === 'Unread' ? 'selected' : ''; ?>>Unread (<?php echo e($summary['unread']); ?>)</option>
                    <option value="Read" <?php echo $statusFilter === 'Read' ? 'selected' : ''; ?>>Read</option>
                    <option value="Replied" <?php echo $statusFilter === 'Replied' ? 'selected' : ''; ?>>Replied</option>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <select name="inquiry_type" class="form-select form-select-sm bg-dark text-light border-secondary" onchange="this.form.submit()">
                    <option value="">All Inquiry Types</option>
                    <option value="General Inquiry" <?php echo $inquiryFilter === 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                    <option value="Suite Reservation" <?php echo $inquiryFilter === 'Suite Reservation' ? 'selected' : ''; ?>>Suite Reservation</option>
                    <option value="Concierge Assistance" <?php echo $inquiryFilter === 'Concierge Assistance' ? 'selected' : ''; ?>>Concierge Assistance</option>
                    <option value="Dining & Events" <?php echo $inquiryFilter === 'Dining & Events' ? 'selected' : ''; ?>>Dining & Events</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-outline-warning w-100">Filter</button>
                <?php if ($search !== '' || $statusFilter !== '' || $inquiryFilter !== ''): ?>
                    <a href="messages.php" class="btn btn-sm btn-outline-secondary" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-dark-soft align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Guest Details</th>
                        <th>Inquiry Type</th>
                        <th>Message Preview</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$messages): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No customer messages match your criteria.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($messages as $msg):
                        $msgId = (int) $msg['message_id'];
                        $statusBadgeClass = match ($msg['status']) {
                            'Unread' => 'bg-danger text-white border border-danger',
                            'Replied' => 'bg-success text-white border border-success',
                            default => 'bg-secondary text-light',
                        };
                    ?>
                        <tr class="<?php echo $msg['status'] === 'Unread' ? 'fw-bold bg-dark bg-opacity-50' : ''; ?>">
                            <td class="small text-muted text-nowrap"><?php echo e(date('M d, Y H:i', strtotime($msg['created_at']))); ?></td>
                            <td>
                                <strong class="d-block text-white"><?php echo e($msg['full_name']); ?></strong>
                                <small class="text-warning d-block"><?php echo e($msg['email']); ?></small>
                                <?php if (!empty($msg['phone'])): ?>
                                    <small class="text-muted text-xs"><i class="bi bi-telephone me-1"></i><?php echo e($msg['phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-soft"><?php echo e($msg['inquiry_type']); ?></span></td>
                            <td style="max-width: 260px;">
                                <span class="text-truncate d-block small" title="<?php echo e($msg['message']); ?>">
                                    <?php echo e(mb_strimwidth($msg['message'], 0, 75, '...')); ?>
                                </span>
                            </td>
                            <td><span class="badge rounded-pill px-2.5 py-1 text-xs <?php echo $statusBadgeClass; ?>"><?php echo e($msg['status']); ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-warning fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#messageModal_<?php echo $msgId; ?>">
                                    <i class="bi bi-reply-fill me-1"></i>View & Reply
                                </button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this guest message?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="message_id" value="<?php echo $msgId; ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>

                        <!-- VIEW & REPLY MODAL FOR EACH MESSAGE -->
                        <div class="modal fade" id="messageModal_<?php echo $msgId; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content bg-dark text-light border-gold-glow rounded-4 p-3 shadow-lg" style="background: rgba(15, 23, 42, 0.98) !important; border: 1px solid rgba(212, 175, 55, 0.45) !important;">
                                    <div class="modal-header border-secondary">
                                        <div>
                                            <p class="eyebrow mb-1 text-warning"><i class="bi bi-envelope-open-fill me-1"></i>Guest Inquiry #<?php echo $msgId; ?></p>
                                            <h5 class="modal-title font-serif text-white fw-bold"><?php echo e($msg['inquiry_type']); ?> from <?php echo e($msg['full_name']); ?></h5>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <!-- Guest Contact Card -->
                                        <div class="p-3 rounded-3 mb-3 border border-secondary" style="background: rgba(30, 41, 59, 0.7);">
                                            <div class="row g-2 text-xs">
                                                <div class="col-sm-6">
                                                    <span class="text-muted d-block">Guest Full Name:</span>
                                                    <strong class="text-white fs-6"><?php echo e($msg['full_name']); ?></strong>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted d-block">Email Address:</span>
                                                    <strong class="text-warning fs-6"><?php echo e($msg['email']); ?></strong>
                                                </div>
                                                <div class="col-sm-6 mt-2">
                                                    <span class="text-muted d-block">Contact Phone:</span>
                                                    <span class="text-light fw-bold"><?php echo e(!empty($msg['phone']) ? $msg['phone'] : 'N/A'); ?></span>
                                                </div>
                                                <div class="col-sm-6 mt-2">
                                                    <span class="text-muted d-block">Submitted On:</span>
                                                    <span class="text-light fw-bold"><?php echo e(date('F d, Y \a\t h:i A', strtotime($msg['created_at']))); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Message Content -->
                                        <div class="mb-4">
                                            <label class="form-label text-xs text-uppercase tracking-wider text-warning font-serif fw-bold"><i class="bi bi-chat-left-quote-fill me-1"></i>Customer Inquiry Message:</label>
                                            <div class="p-3 rounded-3 border border-secondary text-sm text-light" style="background: rgba(15, 23, 42, 0.9); line-height: 1.6; white-space: pre-wrap;"><?php echo e($msg['message']); ?></div>
                                        </div>

                                        <!-- Previous Reply (if already replied) -->
                                        <?php if (!empty($msg['reply_message'])): ?>
                                            <div class="mb-4">
                                                <label class="form-label text-xs text-uppercase tracking-wider text-success font-serif fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Admin Sent Reply (<?php echo e(date('M d, Y H:i', strtotime($msg['replied_at']))); ?>):</label>
                                                <div class="p-3 rounded-3 border border-success text-sm text-light" style="background: rgba(16, 185, 129, 0.1); line-height: 1.6; white-space: pre-wrap;"><?php echo e($msg['reply_message']); ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Reply Form -->
                                        <form method="post" action="messages.php">
                                            <input type="hidden" name="action" value="reply">
                                            <input type="hidden" name="message_id" value="<?php echo $msgId; ?>">

                                            <div class="mb-3">
                                                <label class="form-label text-xs text-uppercase tracking-wider text-warning font-serif fw-bold" for="reply_message_<?php echo $msgId; ?>"><i class="bi bi-reply-all-fill me-1"></i>Type Email Reply to Guest:</label>
                                                <textarea name="reply_message" id="reply_message_<?php echo $msgId; ?>" rows="4" class="form-control form-control-sm bg-dark text-light border-warning rounded-3" placeholder="Type your response to <?php echo e($msg['full_name']); ?> here... This will be sent directly to their email inbox via SMTP." required><?php echo e($msg['reply_message'] ?? ''); ?></textarea>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary">
                                                <span class="text-xs text-muted"><i class="bi bi-shield-check text-warning me-1"></i>Sends instant SMTP email response to <?php echo e($msg['email']); ?></span>
                                                <button type="submit" class="btn btn-warning rounded-pill px-4 font-serif fw-bold text-dark shadow">
                                                    <i class="bi bi-send-fill me-2"></i>Send Email Reply to Guest
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php renderPaginationControl($messageData['total'], $messageData['page'], $messageData['per_page']); ?>
</section>

<!-- COMPOSE DIRECT EMAIL MODAL -->
<div class="modal fade" id="composeEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-light border-gold-glow rounded-4 p-3 shadow-lg" style="background: rgba(15, 23, 42, 0.98) !important; border: 1px solid rgba(212, 175, 55, 0.45) !important;">
            <div class="modal-header border-secondary">
                <div>
                    <p class="eyebrow mb-1 text-warning"><i class="bi bi-envelope-plus-fill me-1"></i>Outbound Concierge Email</p>
                    <h5 class="modal-title font-serif text-white fw-bold">Compose Direct Email to Guest</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="messages.php">
                <input type="hidden" name="action" value="send_direct_email">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-xs text-light fw-bold mb-1">Select Registered Guest (Optional)</label>
                            <select class="form-select form-select-sm bg-dark text-light border-secondary text-xs" onchange="
                                const selectedOpt = this.options[this.selectedIndex];
                                if (selectedOpt.value) {
                                    document.getElementById('compose_email').value = selectedOpt.dataset.email || '';
                                    document.getElementById('compose_name').value = selectedOpt.dataset.name || '';
                                }
                            ">
                                <option value="">-- Choose from Registered Accounts --</option>
                                <?php foreach ($registeredUsers as $u): ?>
                                    <option value="<?php echo e($u['user_id']); ?>" data-email="<?php echo e($u['email']); ?>" data-name="<?php echo e($u['full_name']); ?>">
                                        <?php echo e($u['full_name']); ?> (<?php echo e($u['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-xs text-light fw-bold mb-1">Guest Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_name" id="compose_name" class="form-control form-control-sm bg-dark text-light border-secondary text-xs" placeholder="e.g. Jane Smith" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-xs text-light fw-bold mb-1">Recipient Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="recipient_email" id="compose_email" class="form-control form-control-sm bg-dark text-light border-secondary text-xs" placeholder="guest@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-xs text-light fw-bold mb-1">Email Subject <span class="text-danger">*</span></label>
                            <input type="text" name="email_subject" class="form-control form-control-sm bg-dark text-light border-secondary text-xs" value="👑 [The Emperor Hotel] Important Concierge Notice regarding Your Booking" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-xs text-light fw-bold mb-1">Message Content <span class="text-danger">*</span></label>
                        <textarea name="email_body" rows="5" class="form-control form-control-sm bg-dark text-light border-warning text-xs rounded-3" placeholder="Type custom email message to guest..." required>Dear Guest,

We are writing from The Emperor Hotel Front Desk & Concierge Services regarding your stay reservation.

Please contact our concierge team at your earliest convenience if you require any assistance.

Warm regards,
Emperor Hotel Guest Relations</textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-sm btn-warning rounded-pill px-4 font-serif fw-bold text-dark shadow">
                        <i class="bi bi-send-fill me-1"></i>Send Direct Email Notice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php renderAdminLayoutEnd(); ?>
