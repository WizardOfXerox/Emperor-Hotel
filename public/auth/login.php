<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

if (isLoggedIn()) {
    $user = currentUser();
    redirect($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php');
}

$userModel = new User(Database::connect());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        setFlash('error', 'Email and password are required.');
        redirect('login.php');
    }

    $user = $userModel->authenticate($email, $password);

    if (!$user) {
        setFlash('error', 'Invalid login credentials.');
        redirect('login.php');
    }

    loginUser($user);
    setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
    $target = $_SESSION['redirect_after_login'] ?? ($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php');
    unset($_SESSION['redirect_after_login']);
    redirect($target);
}

renderHeader('Log In - Emperor Hotel', ['../assets/css/site/home.css'], '');
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
                <div class="col-lg-6 auth-hero-col text-white">
                    <div>
                        <span class="badge rounded-pill px-3 py-2 text-uppercase tracking-wider fw-bold font-serif mb-3" style="background: rgba(212, 175, 55, 0.25); color: #FFDF73; border: 1px solid rgba(212, 175, 55, 0.4);">
                            <i class="bi bi-award-fill me-1"></i>Emperor Hotel & Suites
                        </span>
                        <h2 class="display-6 font-serif fw-bold mb-3" style="line-height: 1.25; color: #ffffff;">
                            Experience Unrivaled 5-Star Luxury
                        </h2>
                        <p class="opacity-90 font-serif lead fs-6 mb-4">
                            Log in to manage your executive room reservations, view stay invoices, and enjoy personalized concierge privileges.
                        </p>

                        <div class="d-flex flex-column gap-3 mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-shield-lock-fill"></i>
                                </div>
                                <span class="small font-serif fw-medium">Bank-Grade Encrypted Authentication</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-calendar-check-fill"></i>
                                </div>
                                <span class="small font-serif fw-medium">Instant Self-Service Booking & Extensions</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(212, 175, 55, 0.25); color: #FFDF73;">
                                    <i class="bi bi-robot"></i>
                                </div>
                                <span class="small font-serif fw-medium">24/7 AI Concierge Assistance Included</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 mt-4" style="background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.15);">
                        <p class="small font-serif italic mb-1 opacity-90">&ldquo;The finest luxury stay experience in the city with seamless online management.&rdquo;</p>
                        <small class="text-warning font-serif fw-bold">&mdash; Verified Executive Guest</small>
                    </div>
                </div>

                <!-- Right Column: Login Form -->
                <div class="col-lg-6 auth-form-col d-flex flex-column justify-content-center">
                    <div class="mb-4">
                        <span class="text-xs text-uppercase tracking-wider text-warning font-serif fw-bold d-block mb-1">
                            <i class="bi bi-key-fill me-1"></i>MEMBER PORTAL
                        </span>
                        <h3 class="h2 font-serif fw-bold mb-2">Log In to Your Account</h3>
                        <p class="text-muted small">Access your active bookings, invoices, and executive suite controls.</p>
                    </div>

                    <?php renderFlashBlock(); ?>

                    <!-- Quick Fill Pills for Easy Demo -->
                    <div class="mb-3 p-3 rounded-3 border bg-opacity-10 text-xs font-serif" style="background: rgba(212, 175, 55, 0.06); border-color: rgba(212, 175, 55, 0.25) !important;">
                        <span class="fw-bold me-1 text-warning"><i class="bi bi-lightning-charge-fill me-1"></i>Quick Demo Fill:</span>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="badge rounded-pill quick-login-chip px-2.5 py-1.5 border-0" onclick="fillDemoAccount('maria.santos@gmail.com', 'password123')">
                                <i class="bi bi-person-fill me-1"></i>Customer Account
                            </button>
                            <button type="button" class="badge rounded-pill quick-login-chip px-2.5 py-1.5 border-0" onclick="fillDemoAccount('jayjaypantaleon@gmail.com', 'admin123')">
                                <i class="bi bi-shield-fill-check me-1"></i>Admin Account
                            </button>
                        </div>
                    </div>

                    <form method="post" class="d-grid gap-3">
                        <div>
                            <label class="form-label font-serif fw-semibold small mb-1" for="email">Email Address</label>
                            <div class="position-relative auth-input-group">
                                <i class="bi bi-envelope-fill auth-input-icon"></i>
                                <input class="form-control" id="email" name="email" type="email" placeholder="name@example.com" required autocomplete="email">
                            </div>
                        </div>

                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label font-serif fw-semibold small mb-0" for="password">Password</label>
                                <a href="forgot-password.php" class="text-xs text-warning text-decoration-none font-serif fw-medium">Forgot Password?</a>
                            </div>
                            <div class="position-relative auth-input-group">
                                <i class="bi bi-lock-fill auth-input-icon"></i>
                                <input class="form-control" id="password" name="password" type="password" placeholder="••••••••" required autocomplete="current-password">
                                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)" title="Show/Hide Password">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                        </div>

                        <button class="btn btn-warning w-100 rounded-pill py-3 font-serif fw-bold fs-6 shadow text-uppercase tracking-wider mt-2" type="submit" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; border: none; box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                        </button>

                        <div class="text-center mt-3 font-serif small">
                            <span class="opacity-75">Don't have an account yet?</span>
                            <a href="register.php" class="text-warning font-serif fw-bold text-decoration-none ms-1">Create Account</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function fillDemoAccount(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
}

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
