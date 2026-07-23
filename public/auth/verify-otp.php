<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$db = Database::connect();
$userModel = new User($db);

$pendingUserId = (int) ($_SESSION['pending_otp_user_id'] ?? 0);
if ($pendingUserId <= 0) {
    setFlash('warning', 'No pending verification request. Please log in or register.');
    redirect('login.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $digit1 = trim((string) ($_POST['d1'] ?? ''));
    $digit2 = trim((string) ($_POST['d2'] ?? ''));
    $digit3 = trim((string) ($_POST['d3'] ?? ''));
    $digit4 = trim((string) ($_POST['d4'] ?? ''));
    $digit5 = trim((string) ($_POST['d5'] ?? ''));
    $digit6 = trim((string) ($_POST['d6'] ?? ''));

    $inputOtp = $digit1 . $digit2 . $digit3 . $digit4 . $digit5 . $digit6;

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :uid LIMIT 1");
        $stmt->execute(['uid' => $pendingUserId]);
        $user = $stmt->fetch();

        if (!$user || empty($user['otp_code'])) {
            throw new RuntimeException('Invalid verification session.');
        }

        if ($user['otp_code'] !== $inputOtp) {
            throw new RuntimeException('Invalid 6-digit verification code. Please check and try again.');
        }

        if (!empty($user['otp_expires_at']) && strtotime($user['otp_expires_at']) < time()) {
            throw new RuntimeException('Verification code has expired. Please request a new code.');
        }

        // Clear OTP and mark email verified
        $clearStmt = $db->prepare(
            "UPDATE users SET email_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE user_id = :uid"
        );
        $clearStmt->execute(['uid' => $pendingUserId]);

        unset($_SESSION['pending_otp_user_id']);
        loginUser($user);

        setFlash('success', 'Email successfully verified! Welcome back.');
        if ($user['role'] === 'admin') {
            redirect('../admin/dashboard.php');
        } else {
            redirect('../user/dashboard.php');
        }
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

renderHeader('Verify Email OTP - Emperor Hotel', ['../assets/css/site/home.css']);
?>

<!-- Header Navigation Bar -->
<nav class="home-nav" aria-label="Primary navigation">
    <div class="home-nav__container">
        <a class="home-nav__logo" href="../site/home.php" aria-label="Emperor Hotel home">
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </a>

        <div class="home-nav__links">
            <a class="home-nav__link" href="../site/home.php">HOME</a>
            <a class="home-nav__link" href="../site/rooms.php">ROOMS</a>
            <a class="home-nav__link" href="../site/suites.php">SUITES</a>
        </div>

        <div class="home-nav__auth">
            <button type="button" class="btn btn-sm btn-outline-warning theme-toggle-btn rounded-circle me-2 d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; padding: 0;" onclick="toggleEmperorTheme()" title="Switch to Light Mode" aria-label="Switch to Light Mode"><i class="bi bi-sun-fill fs-5"></i></button>
            <a class="home-nav__cta home-nav__cta--primary" href="login.php">LOG IN</a>
            <a class="home-nav__cta home-nav__cta--secondary" href="register.php">REGISTER</a>
        </div>
    </div>
</nav>

<main class="auth-wrapper min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="card auth-split-card border-0 rounded-4 overflow-hidden shadow-lg mx-auto" style="max-width: 960px;">
            <div class="row g-0">
                <!-- Left Column: Hotel Branding Showcase -->
                <div class="col-lg-6 auth-hero-col d-flex flex-column justify-content-between text-white">
                    <div>
                        <a href="../site/home.php" class="d-inline-flex align-items-center gap-2 text-decoration-none mb-4">
                            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo" style="width: 42px; height: 42px;">
                            <span class="font-serif fw-bold fs-5 tracking-wider text-warning">EMPEROR HOTEL</span>
                        </a>
                        <div>
                            <span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-30 rounded-pill px-3 py-2 text-xs text-uppercase font-serif fw-semibold mb-3 d-inline-block">
                                <i class="bi bi-shield-check me-1"></i>2FA Verification Required
                            </span>
                        </div>
                        <h2 class="display-6 font-serif fw-bold mb-3 text-white" style="line-height: 1.25;">
                            Security Checkpoint
                        </h2>
                        <p class="text-white-50 font-serif lead fs-6 mb-4">
                            Please enter the 6-digit OTP verification code sent to your registered email address.
                        </p>

                        <div class="d-flex flex-column gap-3 mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-envelope-check-fill"></i>
                                </div>
                                <span class="small font-serif fw-medium text-white-90">Instant SMTP Email Delivery</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <span class="small font-serif fw-medium text-white-90">Valid for 15 Minutes</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 mt-4" style="background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.15);">
                        <p class="small font-serif italic mb-1 text-white-50">&ldquo;Protecting your personal reservation privacy with bank-level encryption.&rdquo;</p>
                        <small class="text-warning font-serif fw-bold">&mdash; Emperor Security Protocol</small>
                    </div>
                </div>

                <!-- Right Column: OTP Code Form -->
                <div class="col-lg-6 auth-form-col d-flex flex-column justify-content-center">
                    <div class="mb-4 text-center text-lg-start">
                        <span class="text-xs text-uppercase tracking-wider text-warning font-serif fw-bold d-block mb-1">
                            Two-Factor Security
                        </span>
                        <h3 class="font-serif fw-bold fs-2 m-0" style="color: var(--gold-primary, #D4AF37);">
                            Verify Email OTP
                        </h3>
                        <p class="text-muted font-serif small m-0 mt-1">
                            Enter the 6-digit code sent to your inbox to complete your verification.
                        </p>
                    </div>

                    <?php renderFlashBlock(); ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 shadow-sm py-2 px-3 small rounded-3 mb-4 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 fs-5"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="verify-otp.php">
                        <div class="d-flex justify-content-between gap-2 mb-4">
                            <input type="text" name="d1" maxlength="1" class="form-control text-center font-monospace fw-bold fs-4 text-warning border-warning bg-dark rounded-3" style="height: 58px;" required autofocus>
                            <input type="text" name="d2" maxlength="1" class="form-control text-center font-monospace fw-bold fs-4 text-warning border-warning bg-dark rounded-3" style="height: 58px;" required>
                            <input type="text" name="d3" maxlength="1" class="form-control text-center font-monospace fw-bold fs-4 text-warning border-warning bg-dark rounded-3" style="height: 58px;" required>
                            <input type="text" name="d4" maxlength="1" class="form-control text-center font-monospace fw-bold fs-4 text-warning border-warning bg-dark rounded-3" style="height: 58px;" required>
                            <input type="text" name="d5" maxlength="1" class="form-control text-center font-monospace fw-bold fs-4 text-warning border-warning bg-dark rounded-3" style="height: 58px;" required>
                            <input type="text" name="d6" maxlength="1" class="form-control text-center font-monospace fw-bold fs-4 text-warning border-warning bg-dark rounded-3" style="height: 58px;" required>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 rounded-pill py-3 font-serif fw-bold fs-6 shadow text-uppercase tracking-wider mt-2" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; border: none; box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);">
                            <i class="bi bi-shield-check me-2"></i>Verify Code &amp; Continue
                        </button>
                    </form>

                    <div class="text-center mt-4 font-serif small">
                        <a href="login.php" class="text-warning font-serif fw-bold text-decoration-none d-inline-flex align-items-center gap-1">
                            <i class="bi bi-arrow-left"></i> Return to Sign In
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
