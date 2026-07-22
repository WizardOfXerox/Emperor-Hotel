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

            setFlash('info', "📩 Password reset verification code generated for {$email}.");
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
                throw new RuntimeException('Invalid verification code.');
            }

            if (!empty($user['otp_expires_at']) && strtotime($user['otp_expires_at']) < time()) {
                throw new RuntimeException('Verification code has expired. Please try again.');
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare(
                "UPDATE users SET password_hash = :hash, otp_code = NULL, otp_expires_at = NULL WHERE user_id = :uid"
            );
            $updateStmt->execute(['hash' => $hash, 'uid' => $user['user_id']]);

            unset($_SESSION['reset_email']);
            setFlash('success', 'Password successfully reset! You can now log in with your new password.');
            redirect('login.php');
        } catch (Exception $ex) {
            $error = $ex->getMessage();
            $step = 2;
        }
    }
}

renderHeader('Forgot Password - Emperor Hotel', ['../assets/css/auth/login.css']);
?>
<div class="auth-wrapper d-flex align-items-center justify-content-center min-vh-100 py-5 bg-dark">
    <div class="card bg-dark text-light border-gold-glow rounded-4 p-4 shadow-lg style-auth-card" style="max-width: 440px; width: 100%;">
        <div class="text-center mb-4">
            <div class="brand-logo mb-2 fs-1 text-gold"><i class="bi bi-key-fill"></i></div>
            <h3 class="font-serif text-gold fw-bold m-0">Reset Password</h3>
            <p class="text-muted small">Verify your email address via 6-digit SMTP OTP code</p>
        </div>

        <?php renderFlashBlock(); ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" action="forgot-password.php">
                <input type="hidden" name="step" value="1">
                <div class="mb-4">
                    <label class="form-label text-xs text-uppercase tracking-wider text-muted">Email Address</label>
                    <input type="email" name="email" class="form-control form-control-dark border-gold bg-dark text-light" placeholder="guest@example.com" required>
                </div>
                <button type="submit" class="btn btn-gold w-100 rounded-pill py-3 font-serif fw-bold shadow">
                    Send Reset Code
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="forgot-password.php">
                <input type="hidden" name="step" value="2">
                <div class="mb-3">
                    <label class="form-label text-xs text-uppercase tracking-wider text-muted">6-Digit Verification Code</label>
                    <input type="text" name="otp" maxlength="6" class="form-control form-control-dark border-gold bg-dark text-gold fw-bold text-center fs-5" placeholder="123456" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-xs text-uppercase tracking-wider text-muted">New Password</label>
                    <input type="password" name="new_password" class="form-control form-control-dark border-gold bg-dark text-light" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-xs text-uppercase tracking-wider text-muted">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control form-control-dark border-gold bg-dark text-light" required>
                </div>
                <button type="submit" class="btn btn-gold w-100 rounded-pill py-3 font-serif fw-bold shadow">
                    Update Password
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="login.php" class="text-gold text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
        </div>
    </div>
</div>
<?php
