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

renderHeader('Verify Email OTP - Emperor Hotel', ['../assets/css/auth/login.css']);
?>
<div class="auth-wrapper d-flex align-items-center justify-content-center min-vh-100 py-5 bg-dark">
    <div class="card bg-dark text-light border-gold-glow rounded-4 p-4 shadow-lg style-auth-card" style="max-width: 440px; width: 100%;">
        <div class="text-center mb-4">
            <div class="brand-logo mb-2 fs-1 text-gold"><i class="bi bi-shield-lock-fill"></i></div>
            <h3 class="font-serif text-gold fw-bold m-0">Two-Factor Security</h3>
            <p class="text-muted small">Enter the 6-digit SMTP OTP verification code sent to your email.</p>
        </div>

        <?php renderFlashBlock(); ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="verify-otp.php">
            <div class="d-flex justify-content-between gap-2 mb-4">
                <input type="text" name="d1" maxlength="1" class="form-control form-control-dark text-center fw-bold fs-4 border-gold text-gold" style="width: 50px; height: 55px;" required autofocus>
                <input type="text" name="d2" maxlength="1" class="form-control form-control-dark text-center fw-bold fs-4 border-gold text-gold" style="width: 50px; height: 55px;" required>
                <input type="text" name="d3" maxlength="1" class="form-control form-control-dark text-center fw-bold fs-4 border-gold text-gold" style="width: 50px; height: 55px;" required>
                <input type="text" name="d4" maxlength="1" class="form-control form-control-dark text-center fw-bold fs-4 border-gold text-gold" style="width: 50px; height: 55px;" required>
                <input type="text" name="d5" maxlength="1" class="form-control form-control-dark text-center fw-bold fs-4 border-gold text-gold" style="width: 50px; height: 55px;" required>
                <input type="text" name="d6" maxlength="1" class="form-control form-control-dark text-center fw-bold fs-4 border-gold text-gold" style="width: 50px; height: 55px;" required>
            </div>

            <button type="submit" class="btn btn-gold w-100 rounded-pill py-3 font-serif fw-bold shadow">
                Verify Code & Continue
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="login.php" class="text-gold text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
        </div>
    </div>
</div>
<?php
