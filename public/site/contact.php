<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$db = Database::connect();
$hotelConfig = require APP_ROOT . '/app/config/hotel.php';
$user = isLoggedIn() ? currentUser() : null;
$dashboardHref = $user ? ($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php') : '../auth/login.php';
$dashboardLabel = $user ? ($user['role'] === 'admin' ? 'ADMIN PANEL' : 'MY DASHBOARD') : 'LOG IN';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $inquiryType = trim((string) ($_POST['inquiry_type'] ?? 'General Inquiry'));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($fullName === '' || $email === '' || $message === '') {
        setFlash('error', 'Please fill in all required fields.');
        redirect('contact.php');
    }

    // Send Concierge Confirmation Email via SMTP
    $subject = "👑 [The Emperor Hotel] We Received Your Inquiry: {$inquiryType}";
    $html = "
    <div style='background: #020617; color: #f8fafc; font-family: sans-serif; padding: 40px 20px; text-align: center;'>
        <div style='max-width: 580px; margin: 0 auto; background: #0b1120; border: 1px solid #d4af37; border-radius: 16px; padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: left;'>
            <div style='text-align: center; margin-bottom: 25px;'>
                <h1 style='color: #ffdf73; font-family: serif; margin: 0; font-size: 24px; letter-spacing: 2px; text-transform: uppercase;'>THE EMPEROR HOTEL</h1>
                <p style='color: #94a3b8; font-size: 12px; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px;'>Guest Concierge Services</p>
            </div>
            <div style='border-top: 1px solid rgba(212,175,55,0.3); border-bottom: 1px solid rgba(212,175,55,0.3); padding: 25px 0; margin-bottom: 25px;'>
                <p style='color: #cbd5e1; font-size: 15px; margin-bottom: 15px;'>Dear <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
                <p style='color: #cbd5e1; font-size: 14px; line-height: 1.6;'>Thank you for reaching out to The Emperor Hotel Concierge. We have received your <strong>" . htmlspecialchars($inquiryType) . "</strong> and our dedicated guest services team will review your message promptly.</p>
                
                <div style='background: rgba(15,23,42,0.8); border: 1px solid rgba(212,175,55,0.3); border-radius: 10px; padding: 15px; margin: 20px 0; font-size: 13px; color: #e2e8f0;'>
                    <div style='margin-bottom: 6px;'><strong>Inquiry Type:</strong> " . htmlspecialchars($inquiryType) . "</div>
                    <div style='margin-bottom: 6px;'><strong>Contact Phone:</strong> " . htmlspecialchars($phone ?: 'N/A') . "</div>
                    <div><strong>Message Summary:</strong> " . htmlspecialchars($message) . "</div>
                </div>
            </div>
            <p style='color: #64748b; font-size: 12px; margin: 0; text-align: center;'>Royal Bay Boulevard, Metro Manila, Philippines | Tel: +63 2 8888 7777</p>
        </div>
    </div>
    ";

    sendSmtpEmail($email, $subject, $html);

    setFlash('success', "Thank you, " . e($fullName) . "! Your message has been sent to our Concierge Desk. A confirmation email was sent to " . e($email) . ".");
    redirect('contact.php');
}

renderHeader('Contact Us | Emperor Hotel', ['../assets/css/site/home.css', '../assets/css/site/rooms.css'], 'contact-page');
?>

<nav class="home-nav" aria-label="Primary navigation">
    <div class="home-nav__container">
        <a class="home-nav__logo" href="home.php" aria-label="Emperor Hotel home">
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </a>

        <div class="home-nav__links">
            <a class="home-nav__link" href="home.php">HOME</a>
            <a class="home-nav__link" href="rooms.php">ROOMS</a>
            <a class="home-nav__link" href="suites.php">SUITES</a>
            <a class="home-nav__link home-nav__link--active" href="contact.php">CONTACT</a>
        </div>

        <div class="home-nav__auth">
            <button type="button" class="btn btn-sm btn-outline-warning theme-toggle-btn rounded-circle me-2 d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; padding: 0;" onclick="toggleEmperorTheme()" title="Switch to Light Mode" aria-label="Switch to Light Mode"><i class="bi bi-sun-fill fs-5"></i></button>
            <?php if ($user): ?>
                <a class="home-nav__cta home-nav__cta--primary" href="<?php echo e($dashboardHref); ?>"><?php echo e($dashboardLabel); ?></a>
                <a class="home-nav__cta home-nav__cta--secondary" href="../auth/logout.php" title="Log Out"><i class="bi bi-box-arrow-right d-sm-none"></i><span class="d-none d-sm-inline">LOG OUT</span></a>
            <?php else: ?>
                <a class="home-nav__cta home-nav__cta--primary" href="../auth/login.php">LOG IN</a>
                <a class="home-nav__cta home-nav__cta--secondary" href="../auth/register.php">REGISTER</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="py-4 py-md-5">
    <div class="container" style="max-width: 1200px;">
        <div class="home-flash mb-4">
            <?php renderFlashBlock(); ?>
        </div>

        <!-- Header Hero Block -->
        <section class="text-center py-4 mb-4">
            <p class="eyebrow mb-2"><i class="bi bi-geo-alt-fill text-warning me-1"></i>Concierge & Guest Relations</p>
            <h1 class="font-serif display-brand fw-bold text-white mb-2">Get in Touch with The Emperor Hotel</h1>
            <p class="muted-copy mx-auto" style="max-width: 680px;">Whether you require custom suite reservations, room assistance, or dining concierge arrangements, our guest relations team is available 24/7.</p>
        </section>

        <div class="row g-4">
            <!-- Left Column: Hotel Information & Location -->
            <div class="col-lg-5">
                <div class="panel-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="border-bottom border-secondary border-opacity-25 pb-3 mb-4">
                            <h4 class="font-serif fw-bold text-warning mb-1">Hotel Location & Information</h4>
                            <p class="text-xs text-muted mb-0">The Emperor Hotel & Suites - Cultural Center Complex</p>
                        </div>

                        <div class="d-flex flex-column gap-4 mb-4">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle p-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning">
                                    <i class="bi bi-geo-alt fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 text-light">Address</h6>
                                    <p class="small text-muted mb-0"><?php echo e($hotelConfig['address']); ?></p>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle p-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning">
                                    <i class="bi bi-telephone fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 text-light">Reservations & Concierge</h6>
                                    <p class="small text-muted mb-0"><?php echo e($hotelConfig['support_phone']); ?></p>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle p-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning">
                                    <i class="bi bi-envelope-at fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 text-light">Email Desk</h6>
                                    <p class="small text-muted mb-0"><?php echo e($hotelConfig['support_email']); ?></p>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle p-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning">
                                    <i class="bi bi-clock-history fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 text-light">Front Desk & Security</h6>
                                    <p class="small text-muted mb-0">Open 24 Hours Daily | Check-In: 2:00 PM | Check-Out: 12:00 PM</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Contact Inquiry Form -->
            <div class="col-lg-7">
                <div class="panel-card p-4 p-md-5 h-100">
                    <div class="border-bottom border-secondary border-opacity-25 pb-3 mb-4">
                        <h4 class="font-serif fw-bold text-white mb-1">Send a Message to Concierge</h4>
                        <p class="text-xs text-muted mb-0">Fill out the form below and an email confirmation will be sent to your inbox.</p>
                    </div>

                    <form method="post" class="d-grid gap-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold text-warning" for="full_name">Your Full Name *</label>
                                <input class="form-control bg-dark text-light border-secondary" id="full_name" name="full_name" type="text" placeholder="e.g. Sir Alex Ferguson" value="<?php echo e($user['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold text-warning" for="email">Email Address *</label>
                                <input class="form-control bg-dark text-light border-secondary" id="email" name="email" type="email" placeholder="e.g. alex@example.com" value="<?php echo e($user['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold text-warning" for="phone">Contact Number</label>
                                <input class="form-control bg-dark text-light border-secondary" id="phone" name="phone" type="tel" placeholder="+63 917 123 4567">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold text-warning" for="inquiry_type">Inquiry Type</label>
                                <select class="form-select bg-dark text-light border-secondary" id="inquiry_type" name="inquiry_type">
                                    <option value="General Inquiry">General Inquiry</option>
                                    <option value="Suite Reservation">Suite Reservation</option>
                                    <option value="Banquet & Event Booking">Banquet & Event Booking</option>
                                    <option value="Special Guest Services">Special Guest Services</option>
                                    <option value="Feedback & Support">Feedback & Support</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs fw-semibold text-warning" for="message">Your Message *</label>
                            <textarea class="form-control bg-dark text-light border-secondary" id="message" name="message" rows="5" placeholder="How can our concierge desk assist you today?" required></textarea>
                        </div>

                        <button class="btn btn-warning fw-bold py-3 mt-2 shadow-sm" type="submit">
                            <i class="bi bi-send-fill me-2"></i>Send Concierge Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php renderSupportWidget('customer'); ?>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/support-widget.js?v=<?= time() ?>" defer></script>
</body>
</html>
