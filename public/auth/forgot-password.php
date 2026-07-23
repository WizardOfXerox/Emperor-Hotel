<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$db = Database::connect();
$step = isset($_POST['step']) ? (int) $_POST['step'] : 1;
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $email = trim((string) ($_POST['email'] ?? ''));
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new RuntimeException('No account found with that email address.');
            }

            $otp = (string) random_int(100000, 999999);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $updateStmt = $db->prepare(
                "UPDATE users SET otp_code = :otp, otp_expires_at = :exp WHERE user_id = :uid"
            );
            $updateStmt->execute(['otp' => $otp, 'exp' => $expiresAt, 'uid' => $user['user_id']]);

            $_SESSION['reset_email'] = $email;
            $step = 2;

            $subject = "Emperor Hotel Password Reset Code";
            $html = "<h3>Password Reset Code</h3><p>Your 6-digit verification code is: <strong>{$otp}</strong></p><p>This code expires in 15 minutes.</p>";
            sendSmtpEmail($email, $subject, $html, $otp);

            setFlash('info', "📩 Verification code sent to {$email}. Please enter your 6-digit code below.");
        } catch (Exception $ex) {
            $error = $ex->getMessage();
        }
    } elseif ($step === 2) {
        $email = $_SESSION['reset_email'] ?? '';
        $otp = trim((string) ($_POST['otp'] ?? ''));
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        try {
            if ($newPassword === '' || strlen($newPassword) < 6) {
                throw new RuntimeException('Password must be at least 6 characters.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('Passwords do not match.');
            }

            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if (!$user || $user['otp_code'] !== $otp) {
                throw new RuntimeException('Invalid verification code. Please check and try again.');
            }

            if (!empty($user['otp_expires_at']) && strtotime($user['otp_expires_at']) < time()) {
                throw new RuntimeException('Verification code has expired. Please request a new code.');
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare(
                "UPDATE users SET password_hash = :hash, otp_code = NULL, otp_expires_at = NULL WHERE user_id = :uid"
            );
            $updateStmt->execute(['hash' => $hash, 'uid' => $user['user_id']]);

            unset($_SESSION['reset_email']);
            setFlash('success', '🔐 Password successfully updated! You can now log in with your new password.');
            redirect('login.php');
        } catch (Exception $ex) {
            $error = $ex->getMessage();
            $step = 2;
        }
    }
}

renderHeader('Reset Password - Emperor Hotel & Suites', ['../assets/css/auth/login.css']);
?>
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
                                <i class="bi bi-shield-lock-fill me-1"></i>Account Recovery & Security
                            </span>
                        </div>
                        <h2 class="display-6 font-serif fw-bold mb-3 text-white" style="line-height: 1.25;">
                            Reset Your Account Access
                        </h2>
                        <p class="text-white-50 font-serif lead fs-6 mb-4">
                            Safely restore access to your executive room bookings, billing receipts, and account settings.
                        </p>

                        <div class="d-flex flex-column gap-3 mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-key-fill"></i>
                                </div>
                                <span class="small font-serif fw-medium text-white-90">Secure 6-Digit SMTP OTP Verification</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <span class="small font-serif fw-medium text-white-90">Fast 15-Minute Code Expiry Window</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <span class="small font-serif fw-medium text-white-90">256-Bit Cryptographic Hash Storage</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 mt-4" style="background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.15);">
                        <p class="small font-serif italic mb-1 text-white-50">&ldquo;Need assistance? Our concierge support team is available 24/7.&rdquo;</p>
                        <small class="text-warning font-serif fw-bold">&mdash; Emperor Concierge Help Desk</small>
                    </div>
                </div>

                <!-- Right Column: Reset Password Form -->
                <div class="col-lg-6 auth-form-col d-flex flex-column justify-content-center">
                    <div class="mb-4">
                        <span class="text-xs text-uppercase tracking-wider text-warning font-serif fw-bold d-block mb-1">
                            Step <?= $step ?> of 2
                        </span>
                        <h3 class="font-serif fw-bold fs-2 m-0" style="color: var(--gold-primary, #D4AF37);">
                            <?= $step === 1 ? 'Forgot Password?' : 'Enter Verification Code' ?>
                        </h3>
                        <p class="text-muted font-serif small m-0 mt-1">
                            <?= $step === 1 ? 'Enter your registered email address to receive a 6-digit recovery code.' : 'Enter the code sent to your email along with your new password.' ?>
                        </p>
                    </div>

                    <?php renderFlashBlock(); ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 shadow-sm py-2 px-3 small rounded-3 mb-4 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 fs-5"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($step === 1): ?>
                        <form method="POST" action="forgot-password.php" class="d-grid gap-3">
                            <input type="hidden" name="step" value="1">
                            
                            <div>
                                <label class="form-label font-serif fw-semibold small mb-1" for="email">Email Address</label>
                                <div class="position-relative auth-input-group">
                                    <i class="bi bi-envelope-fill auth-input-icon"></i>
                                    <input class="form-control" id="email" name="email" type="email" placeholder="name@example.com" required autocomplete="email">
                                </div>
                            </div>

                            <button class="btn btn-warning w-100 rounded-pill py-3 font-serif fw-bold fs-6 shadow text-uppercase tracking-wider mt-2" type="submit" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; border: none; box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);">
                                <i class="bi bi-send-fill me-2"></i>Send Reset Code
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="forgot-password.php" class="d-grid gap-3">
                            <input type="hidden" name="step" value="2">

                            <div>
                                <label class="form-label font-serif fw-semibold small mb-1" for="otp">6-Digit Verification Code</label>
                                <div class="position-relative auth-input-group">
                                    <i class="bi bi-shield-lock-fill auth-input-icon"></i>
                                    <input class="form-control text-center font-monospace fw-bold fs-4 tracking-widest text-warning" id="otp" name="otp" type="text" maxlength="6" placeholder="123456" required autocomplete="one-time-code">
                                </div>
                            </div>

                            <div>
                                <label class="form-label font-serif fw-semibold small mb-1" for="new_password">New Password</label>
                                <div class="position-relative auth-input-group">
                                    <i class="bi bi-lock-fill auth-input-icon"></i>
                                    <input class="form-control" id="new_password" name="new_password" type="password" placeholder="••••••••" required autocomplete="new-password">
                                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('new_password', this)" title="Show/Hide Password">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="form-label font-serif fw-semibold small mb-1" for="confirm_password">Confirm New Password</label>
                                <div class="position-relative auth-input-group">
                                    <i class="bi bi-check-circle-fill auth-input-icon"></i>
                                    <input class="form-control" id="confirm_password" name="confirm_password" type="password" placeholder="••••••••" required autocomplete="new-password">
                                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirm_password', this)" title="Show/Hide Password">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                </div>
                            </div>

                            <button class="btn btn-warning w-100 rounded-pill py-3 font-serif fw-bold fs-6 shadow text-uppercase tracking-wider mt-2" type="submit" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; border: none; box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);">
                                <i class="bi bi-check-lg me-2"></i>Update Password
                            </button>
                        </form>
                    <?php endif; ?>

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

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash-fill';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}
</script>
</body>
</html>
