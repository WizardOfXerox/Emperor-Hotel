<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

if (isLoggedIn()) {
    $user = currentUser();
    redirect($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php');
}

$userModel = new User(Database::connect());
$allowAdminRole = $userModel->countUsers() === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $role = $allowAdminRole ? (string) ($_POST['role'] ?? 'user') : 'user';

    if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        setFlash('error', 'All fields are required.');
        redirect('register.php');
    }

    if ($password !== $confirmPassword) {
        setFlash('error', 'Password confirmation does not match.');
        redirect('register.php');
    }

    if ($userModel->findByEmail($email)) {
        setFlash('error', 'That email is already registered.');
        redirect('register.php');
    }

    $otpCode = sprintf('%06d', random_int(100000, 999999));
    $otpExpiresAt = date('Y-m-d H:i:s', time() + 600);

    $userId = $userModel->create([
        'full_name' => $fullName,
        'email' => $email,
        'password' => $password,
        'role' => $role,
        'email_verified' => 0,
        'otp_code' => $otpCode,
        'otp_expires_at' => $otpExpiresAt,
    ]);

    if ($userId > 0) {
        $guestModel = new Guest(Database::connect());
        $nameParts = splitFullName($fullName);
        $guestModel->create([
            'user_id' => $userId,
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'phone' => $phone,
            'email' => $email,
        ]);

        sendRegistrationOtpEmail($email, $fullName, $otpCode);
        $_SESSION['pending_otp_user_id'] = $userId;

        setFlash('info', "📩 Verification email sent to {$email}. Please enter your 6-digit verification code below.");
        redirect('verify-otp.php');
    }
}

renderHeader('Create Account - Emperor Hotel', ['../assets/css/site/home.css'], '');
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

<main class="auth-wrapper py-4 py-md-5">
    <div class="container" style="max-width: 1050px;">
        <div class="card auth-split-card border-0 shadow-lg overflow-hidden">
            <div class="row g-0">
                <!-- Left Column: Luxury Showcase Hero -->
                <div class="col-lg-5 auth-hero-col text-white">
                    <div>
                        <span class="badge rounded-pill px-3 py-2 text-uppercase tracking-wider fw-bold font-serif mb-3" style="background: rgba(212, 175, 55, 0.25); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.4);">
                            <i class="bi bi-person-plus-fill me-1"></i>JOIN THE EMPEROR CLUB
                        </span>
                        <h2 class="display-6 font-serif fw-bold mb-3" style="line-height: 1.25; color: #ffffff;">
                            Create Your Guest Account
                        </h2>
                        <p class="opacity-90 font-serif lead fs-6 mb-4">
                            Unlock self-service room reservations, instant stay date management, and exclusive suite perks.
                        </p>

                        <div class="d-flex flex-column gap-3 mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-stars"></i>
                                </div>
                                <span class="small font-serif fw-medium">Exclusive Suite Upgrade Eligibility</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <span class="small font-serif fw-medium">Complete Reservation History & Receipts</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 mt-4" style="background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.15);">
                        <p class="small font-serif italic mb-1 opacity-90">&ldquo;Fast registration with instant booking confirmation!&rdquo;</p>
                        <small class="text-warning font-serif fw-bold">&mdash; Emperor Guest Experience</small>
                    </div>
                </div>

                <!-- Right Column: Registration Form -->
                <div class="col-lg-7 auth-form-col d-flex flex-column justify-content-center">
                    <div class="mb-4">
                        <span class="text-xs text-uppercase tracking-wider text-warning font-serif fw-bold d-block mb-1">
                            <i class="bi bi-card-heading me-1"></i>NEW REGISTRATION
                        </span>
                        <h3 class="h2 font-serif fw-bold mb-2">Create New Account</h3>
                        <p class="text-muted small">Fill in your details below to create your guest account.</p>
                    </div>

                    <?php renderFlashBlock(); ?>

                    <form method="post" class="d-grid gap-3">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label font-serif fw-semibold small mb-1" for="full_name">Full Name</label>
                                <div class="position-relative auth-input-group">
                                    <i class="bi bi-person-fill auth-input-icon"></i>
                                    <input class="form-control" id="full_name" name="full_name" type="text" placeholder="Juan Dela Cruz" pattern="^[A-Za-z][A-Za-z .'-]*$" required>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label font-serif fw-semibold small mb-1" for="phone">Contact Phone</label>
                                <div class="position-relative auth-input-group">
                                    <i class="bi bi-telephone-fill auth-input-icon"></i>
                                    <input class="form-control" id="phone" name="phone" type="tel" placeholder="+63 917 123 4567" required>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label font-serif fw-semibold small mb-1" for="email">Email Address</label>
                            <div class="position-relative auth-input-group">
                                <i class="bi bi-envelope-fill auth-input-icon"></i>
                                <input class="form-control" id="email" name="email" type="email" placeholder="name@example.com" required autocomplete="email">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label font-serif fw-semibold small mb-1" for="password">Password</label>
                                <div class="position-relative auth-input-group">
                                    <i class="bi bi-lock-fill auth-input-icon"></i>
                                    <input class="form-control" id="password" name="password" type="password" placeholder="••••••••" minlength="6" required>
                                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label font-serif fw-semibold small mb-1" for="confirm_password">Confirm Password</label>
                                <div class="position-relative auth-input-group">
                                    <i class="bi bi-check-circle-fill auth-input-icon"></i>
                                    <input class="form-control" id="confirm_password" name="confirm_password" type="password" placeholder="••••••••" minlength="6" required>
                                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirm_password', this)">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php if ($allowAdminRole): ?>
                            <div>
                                <label class="form-label font-serif fw-semibold small mb-1" for="role">Role</label>
                                <div class="position-relative auth-input-group">
                                    <i class="bi bi-shield-lock-fill auth-input-icon"></i>
                                    <select class="form-select" id="role" name="role">
                                        <option value="admin">Admin</option>
                                        <option value="user">User</option>
                                    </select>
                                </div>
                                <div class="form-text text-warning font-serif small mt-1">Only the initial system setup account can select the admin role.</div>
                            </div>
                        <?php endif; ?>

                        <button class="btn btn-warning w-100 rounded-pill py-3 font-serif fw-bold fs-6 shadow text-uppercase tracking-wider mt-2" type="submit" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; border: none; box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);">
                            <i class="bi bi-person-check-fill me-2"></i>Create Account
                        </button>

                        <div class="text-center mt-3 font-serif small">
                            <span class="opacity-75">Already have an account?</span>
                            <a href="login.php" class="text-warning font-serif fw-bold text-decoration-none ms-1">Log In Here</a>
                        </div>
                    </form>
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
