<?php
require_once __DIR__ . '/includes/auth.php';

require_role(['admin']);

$currentPage = 'users';
$pageTitle = 'Reset User Password';
$pdo = db();
$errors = [];
$success = false;

$userId = (int) ($_GET['user_id'] ?? 0);

// Get user details
$stmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    set_flash('danger', 'User not found.');
    redirect('users.php');
}

$currentUser = current_user();

// Prevent admin from resetting their own password through this page
if ((int) $targetUser['id'] === (int) $currentUser['id']) {
    set_flash('danger', 'Use the Change Password page to reset your own password.');
    redirect('users.php');
}

if (is_post_request()) {
    require_valid_csrf_or_fail();

    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($newPassword === '') {
        $errors[] = 'New password is required.';
    }
    if (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }

    // Validate password strength
    if (!empty($newPassword)) {
        $hasUppercase = preg_match('/[A-Z]/', $newPassword);
        $hasLowercase = preg_match('/[a-z]/', $newPassword);
        $hasNumber = preg_match('/[0-9]/', $newPassword);
        $hasSpecial = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword);
        
        if (!$hasUppercase || !$hasLowercase || !$hasNumber || !$hasSpecial) {
            $errors[] = 'Password must contain at least one uppercase letter, lowercase letter, number, and special character.';
        }
    }

    if (!$errors) {
        try {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $pdo->beginTransaction();
            
            // Update password
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, last_password_change = NOW() WHERE id = :id');
            $stmt->execute(['hash' => $newHash, 'id' => (int) $targetUser['id']]);

            // Add to password history
            $stmt = $pdo->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (:user_id, :hash)');
            $stmt->execute(['user_id' => (int) $targetUser['id'], 'hash' => $newHash]);

            // Record password change request
            $stmt = $pdo->prepare('INSERT INTO password_change_requests (user_id, changed_by, request_type) VALUES (:user_id, :changed_by, :type)');
            $stmt->execute([
                'user_id' => (int) $targetUser['id'],
                'changed_by' => (int) $currentUser['id'],
                'type' => 'admin_reset'
            ]);

            $pdo->commit();
            
            set_flash('success', 'Password for ' . h($targetUser['full_name']) . ' has been reset successfully.');
            redirect('users.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Unable to reset password. Please try again.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-key-fill me-2 text-gradient"></i>Reset User Password</h1>
            <p>Reset password for <?= h($targetUser['full_name']) ?></p>
        </div>
        <a href="<?= h(app_url('users.php')) ?>" class="btn glass-btn glass-btn-outline">
            <i class="bi bi-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center p-3 rounded mb-4" style="background: rgba(139, 92, 246, 0.1);">
                <div class="user-avatar me-3" style="width: 48px; height: 48px; font-size: 1.2rem;">
                    <?= strtoupper(substr($targetUser['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <h5 class="mb-0"><?= h($targetUser['full_name']) ?></h5>
                    <small class="text-muted"><?= h($targetUser['email']) ?></small>
                </div>
                <span class="ms-auto badge glass-badge-primary"><?= ucfirst($targetUser['role']) ?></span>
            </div>

            <?php if ($errors): ?>
                <div class="glass-alert glass-alert-danger mb-4">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= implode('<br>', array_map('h', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_input() ?>
                
                <div class="mb-4">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-key"></i>
                        </span>
                        <input type="password" name="new_password" class="form-control glass-form-control" placeholder="Enter new password" required>
                    </div>
                    <small class="text-muted mt-1 d-block">
                        <i class="bi bi-info-circle me-1"></i>
                        Password must be at least 8 characters with uppercase, lowercase, number, and special character.
                    </small>
                </div>

                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-key-fill"></i>
                        </span>
                        <input type="password" name="confirm_password" class="form-control glass-form-control" placeholder="Confirm new password" required>
                    </div>
                </div>

                <div class="glass-alert glass-alert-warning mb-4">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    This action will immediately change the user's password. The user will need to use the new password to log in.
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="<?= h(app_url('users.php')) ?>" class="btn glass-btn glass-btn-outline">
                        <i class="bi bi-x-lg"></i> Cancel
                    </a>
                    <button type="submit" class="btn glass-btn glass-btn-danger">
                        <i class="bi bi-check-lg"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
