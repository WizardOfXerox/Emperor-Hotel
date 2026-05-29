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
    redirect($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php');
}

renderHeader('Log In', ['../assets/css/auth/login.css']);
?>
<main class="auth-wrapper">
    <section class="auth-card">
        <p class="eyebrow">Authentication</p>
        <h1 class="display-brand mb-3">Log in to Emperor Hotel</h1>
        <p class="muted-copy mb-4">Access your dashboard, reservations, and role-based tools.</p>
        <?php renderFlashBlock(); ?>
        <form method="post" class="d-grid gap-3">
            <div>
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" required>
            </div>
            <div>
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" required>
            </div>
            <button class="btn btn-warning fw-semibold" type="submit">Log In</button>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <a class="text-warning" href="../site/home.php">Back to Home</a>
                <a class="text-warning" href="register.php">Create Account</a>
            </div>
        </form>
    </section>
</main>
</body>
</html>
