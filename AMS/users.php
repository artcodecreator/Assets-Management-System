<?php
require_once __DIR__ . '/includes/auth.php';

require_role(['admin']);

$pdo = db();
$currentUser = current_user();
$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);

$activeAdminCount = static function () use ($pdo): int {
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
};

if (is_post_request()) {
    require_valid_csrf_or_fail();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = trim((string) ($_POST['role'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if (!in_array($role, USER_ROLES, true)) {
            $errors[] = 'Please select a valid role.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password_hash, role, is_active, last_password_change)
                     VALUES (:full_name, :email, :password_hash, :role, :is_active, NOW())'
                );
                $stmt->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'is_active' => $isActive,
                ]);

                set_flash('success', 'User created successfully.');
                redirect('users.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Email already exists.';
                } else {
                    $errors[] = 'Unable to create user.';
                }
            }
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = trim((string) ($_POST['role'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if (!in_array($role, USER_ROLES, true)) {
            $errors[] = 'Please select a valid role.';
        }
        
        if ($role === 'admin' && !$isActive && $activeAdminCount() <= 1) {
            $errors[] = 'Cannot deactivate the only active admin.';
        }

        if (!$errors) {
            try {
                if ($password !== '') {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET full_name = :full_name, email = :email, role = :role, is_active = :is_active, password_hash = :password_hash, last_password_change = NOW() WHERE id = :id'
                    );
                    $stmt->execute([
                        'full_name' => $fullName,
                        'email' => $email,
                        'role' => $role,
                        'is_active' => $isActive,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $id,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET full_name = :full_name, email = :email, role = :role, is_active = :is_active WHERE id = :id'
                    );
                    $stmt->execute([
                        'full_name' => $fullName,
                        'email' => $email,
                        'role' => $role,
                        'is_active' => $isActive,
                        'id' => $id,
                    ]);
                }

                set_flash('success', 'User updated successfully.');
                redirect('users.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Email already exists.';
                } else {
                    $errors[] = 'Unable to update user.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ((int) $currentUser['id'] === $id) {
            $errors[] = 'You cannot delete your own account.';
        } else {
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $userToDelete = $stmt->fetch();

            if ($userToDelete && $userToDelete['role'] === 'admin' && $activeAdminCount() <= 1) {
                $errors[] = 'Cannot delete the only active admin.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute(['id' => $id]);
                set_flash('success', 'User deleted successfully.');
                redirect('users.php');
            }
        }
    }
}

$users = $pdo->query('SELECT id, full_name, email, role, is_active, created_at FROM users ORDER BY full_name')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-people-fill me-2 text-gradient"></i>User Management</h1>
            <p>Manage user accounts and roles</p>
        </div>
        <button class="btn glass-btn glass-btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
            <i class="bi bi-plus-lg"></i> Add User
        </button>
    </div>
</div>

<?php if ($errors): ?>
    <div class="glass-alert glass-alert-danger mb-4">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= implode('<br>', array_map('h', $errors)) ?>
    </div>
<?php endif; ?>

<div class="glass-card">
    <div class="table-responsive">
        <table class="table glass-table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users): ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                </div>
                                <?= h($user['full_name']) ?>
                            </div>
                        </td>
                        <td><?= h($user['email']) ?></td>
                        <td>
                            <?php 
                            $roleClass = match($user['role']) {
                                'admin' => 'primary',
                                'manager' => 'info',
                                'viewer' => 'success',
                                default => 'secondary'
                            };
                            ?>
                            <span class="glass-badge glass-badge-<?= $roleClass ?>"><?= ucfirst($user['role']) ?></span>
                        </td>
                        <td>
                            <span class="glass-badge glass-badge-<?= (int) $user['is_active'] === 1 ? 'success' : 'danger' ?>">
                                <?= (int) $user['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><small><?= h((string) $user['created_at']) ?></small></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="<?= h(app_url('users.php?edit=' . (int) $user['id'])) ?>" class="btn glass-btn glass-btn-sm glass-btn-outline">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <?php if ((int) $user['id'] !== (int) $currentUser['id']): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= h((string) $user['id']) ?>">
                                    <button type="submit" class="btn glass-btn glass-btn-sm" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel"><i class="bi bi-person-plus-fill me-2"></i>Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control glass-form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control glass-form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select glass-form-select" required>
                            <?php foreach (USER_ROLES as $r): ?>
                                <option value="<?= h($r) ?>"><?= ucfirst($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control glass-form-control" placeholder="Min 8 characters">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" checked>
                        <label class="form-check-label" for="isActive">Active Account</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn glass-btn glass-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn glass-btn glass-btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
