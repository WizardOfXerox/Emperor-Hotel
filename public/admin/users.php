<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');

$db = Database::connect();
$currentAdmin = currentUser();
$userModel = new User($db);
$editUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $email = trim((string) ($_POST['email'] ?? ''));

            if ($userModel->findByEmail($email)) {
                throw new RuntimeException('That email is already in use.');
            }

            $userModel->create([
                'full_name' => (string) ($_POST['full_name'] ?? ''),
                'email' => $email,
                'password' => (string) ($_POST['password'] ?? ''),
                'role' => (string) ($_POST['role'] ?? 'user'),
            ]);

            setFlash('success', 'User account created.');
            redirect('users.php');
        }

        if ($action === 'update') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $role = (string) ($_POST['role'] ?? 'user');

            if ($userId === (int) $currentAdmin['user_id'] && $role !== 'admin') {
                throw new RuntimeException('You cannot remove your own admin access here.');
            }

            $existingUser = $userModel->findByEmail((string) ($_POST['email'] ?? ''));

            if ($existingUser && (int) $existingUser['user_id'] !== $userId) {
                throw new RuntimeException('That email is already in use by another account.');
            }

            $userModel->update($userId, [
                'full_name' => (string) ($_POST['full_name'] ?? ''),
                'email' => (string) ($_POST['email'] ?? ''),
                'password' => (string) ($_POST['password'] ?? ''),
                'role' => $role,
            ]);

            if ($userId === (int) $currentAdmin['user_id']) {
                $updatedSessionUser = $userModel->find($userId);
                if ($updatedSessionUser) {
                    loginUser($updatedSessionUser);
                }
            }

            setFlash('success', 'User account updated.');
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
                    <input class="form-control" id="full_name" name="full_name" type="text" value="<?php echo e($editUser['full_name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="<?php echo e($editUser['email'] ?? ''); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="password">Password <?php if ($editUser): ?><span class="text-light-emphasis small">(leave blank to keep current)</span><?php endif; ?></label>
                    <input class="form-control" id="password" name="password" type="password" <?php echo $editUser ? '' : 'required'; ?>>
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
                            <th>Role</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo e($user['full_name']); ?></td>
                                <td><?php echo e($user['email']); ?></td>
                                <td><span class="badge-soft"><?php echo e(ucfirst($user['role'])); ?></span></td>
                                <td><?php echo e(date('Y-m-d', strtotime($user['created_at']))); ?></td>
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
