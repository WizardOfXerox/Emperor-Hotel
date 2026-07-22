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

    $userId = $userModel->create([
        'full_name' => $fullName,
        'email' => $email,
        'password' => $password,
        'role' => $role,
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
    }

    setFlash('success', 'Registration successful. You can now log in.');
    redirect('login.php');
}

renderHeader('Register', ['../assets/css/auth/register.css']);
?>
<main class="auth-wrapper">
    <section class="auth-card">
        <p class="eyebrow">Create Account</p>
        <h1 class="display-brand mb-3">Register for Emperor Hotel</h1>
        <p class="muted-copy mb-4">Use this account to access admin tools or a user booking dashboard.</p>
        <?php renderFlashBlock(); ?>
        <form method="post" class="d-grid gap-3">
            <div>
                <label class="form-label" for="full_name">Full Name</label>
                <input class="form-control" id="full_name" name="full_name" type="text" pattern="^[A-Za-z][A-Za-z .'-]*$" title="Use letters, spaces, periods, apostrophes, and hyphens only." required>
            </div>
            <div>
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" required>
            </div>
            <div>
                <label class="form-label" for="phone">Contact Phone</label>
                <input class="form-control" id="phone" name="phone" type="tel" placeholder="+63 917 123 4567" required>
            </div>
            <div>
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" minlength="6" required>
            </div>
            <div>
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="6" required>
            </div>
            <?php if ($allowAdminRole): ?>
                <div>
                    <label class="form-label" for="role">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="admin">Admin</option>
                        <option value="user">User</option>
                    </select>
                    <div class="form-text">Only the very first account can choose the admin role.</div>
                </div>
            <?php endif; ?>
            <button class="btn btn-warning fw-semibold" type="submit">Register</button>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <a class="text-warning" href="../site/home.php">Back to Home</a>
                <a class="text-warning" href="login.php">Already have an account?</a>
            </div>
        </form>
    </section>
</main>
</body>
</html>
