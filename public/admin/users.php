<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../app/helpers/mailer.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

$db = Database::connect();
$currentAdmin = currentUser();
$userModel = new User($db);
$guestModel = new Guest($db);
$editUser = null;
$editGuest = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? 'user');

            if ($userModel->findByEmail($email)) {
                throw new RuntimeException('That email is already in use.');
            }

            $userModel->create([
                'full_name' => $fullName,
                'email' => $email,
                'password' => $password,
                'role' => $role,
            ]);

            $createdUser = $userModel->findByEmail($email);
            if ($createdUser) {
                $guestModel->ensureForUser($createdUser, $phone);
            }

            // Dispatch SMTP Welcome Email Notice
            sendSmtpEmail(
                $email,
                'Welcome to Emperor Hotel',
                "<p>Hello <strong>" . e($fullName) . "</strong>,</p><p>Your account has been created by front desk administration with role: <strong>" . e(ucfirst($role)) . "</strong>.</p>",
                'NEW_USER'
            );

            setFlash('success', 'User account created and welcome email dispatched via SMTP.');
            redirect('users.php');
        }

        if ($action === 'update') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $role = (string) ($_POST['role'] ?? 'user');
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));

            if ($userId === (int) $currentAdmin['user_id'] && $role !== 'admin') {
                throw new RuntimeException('You cannot remove your own admin access here.');
            }

            $existingUser = $userModel->findByEmail($email);

            if ($existingUser && (int) $existingUser['user_id'] !== $userId) {
                throw new RuntimeException('That email is already in use by another account.');
            }

            $userModel->update($userId, [
                'full_name' => $fullName,
                'email' => $email,
                'password' => (string) ($_POST['password'] ?? ''),
                'role' => $role,
            ]);

            $updatedUser = $userModel->find($userId);
            if ($updatedUser) {
                $guestModel->ensureForUser($updatedUser, $phone);
            }

            if ($userId === (int) $currentAdmin['user_id']) {
                $updatedSessionUser = $userModel->find($userId);
                if ($updatedSessionUser) {
                    loginUser($updatedSessionUser);
                }
            }

            setFlash('success', 'User account and guest contact details updated.');
            redirect('users.php');
        }

        if ($action === 'delete') {
            $userId = (int) ($_POST['user_id'] ?? 0);

            if ($userId === (int) $currentAdmin['user_id']) {
                throw new RuntimeException('You cannot delete your own account while logged in.');
            }

            $userModel->delete($userId);
            setFlash('success', 'User account deleted.');
            redirect('users.php');
        }
    } catch (Throwable $exception) {
        setFlash('error', $exception->getMessage());
        redirect('users.php');
    }
}

if (isset($_GET['edit'])) {
    $editUser = $userModel->find((int) $_GET['edit']);
    if ($editUser) {
        $editGuest = $guestModel->findByUserId((int) $editUser['user_id']);
    }
}

$users = $userModel->all();

renderAdminLayoutStart('Users', 'users', $currentAdmin, ['../assets/css/admin/users.css']);
?>
<section class="row g-4">
    <div class="col-xl-4">
        <div class="panel-card p-4 h-100">
            <p class="eyebrow mb-1"><?php echo $editUser ? 'Update account' : 'Create account'; ?></p>
            <h3 class="mb-3"><?php echo $editUser ? 'Edit User' : 'New User'; ?></h3>
            <form method="post" class="d-grid gap-3">
                <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'create'; ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="user_id" value="<?php echo e($editUser['user_id']); ?>">
                <?php endif; ?>
                <div>
                    <label class="form-label" for="full_name">Full Name</label>
                    <input class="form-control" id="full_name" name="full_name" type="text" value="<?php echo e($editUser['full_name'] ?? ''); ?>" pattern="^[A-Za-z][A-Za-z .'-]*$" title="Use letters, spaces, periods, apostrophes, and hyphens only." required>
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="<?php echo e($editUser['email'] ?? ''); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="phone">Phone Number</label>
                    <input class="form-control" id="phone" name="phone" type="tel" value="<?php echo e($editGuest['phone'] ?? ''); ?>" placeholder="+63 912 345 6789" required>
                </div>
                <div>
                    <label class="form-label" for="password">Password <?php if ($editUser): ?><span class="text-light-emphasis small">(leave blank to keep current)</span><?php endif; ?></label>
                    <input class="form-control" id="password" name="password" type="password" minlength="6" <?php echo $editUser ? '' : 'required'; ?>>
                </div>
                <div>
                    <label class="form-label" for="role">Role</label>
                    <select class="form-select" id="role" name="role">
                        <?php foreach (['admin', 'user'] as $role): ?>
                            <option value="<?php echo e($role); ?>" <?php echo (($editUser['role'] ?? 'user') === $role) ? 'selected' : ''; ?>>
                                <?php echo e(ucfirst($role)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-warning fw-semibold" type="submit"><?php echo $editUser ? 'Save Changes' : 'Create User'; ?></button>
                <?php if ($editUser): ?>
                    <a class="btn btn-outline-light" href="users.php">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">User Management</p>
                    <h3 class="mb-0">Registered Accounts</h3>
                </div>
                <span class="badge-soft"><?php echo e(count($users)); ?> total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-soft align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($user['full_name']); ?></td>
                                <td><?php echo e($user['email']); ?></td>
                                <td><?php echo e(!empty($user['phone']) ? $user['phone'] : 'N/A'); ?></td>
                                <td><span class="badge-soft"><?php echo e(ucfirst($user['role'])); ?></span></td>
                                <td class="small text-muted"><?php echo e(date('Y-m-d', strtotime($user['created_at']))); ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-light" href="users.php?edit=<?php echo e($user['user_id']); ?>">Edit</a>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo e($user['user_id']); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php renderAdminLayoutEnd(); ?>
