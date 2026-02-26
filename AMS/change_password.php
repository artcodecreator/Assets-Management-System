<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$currentPage = 'settings';
$pageTitle = 'Change Password';
$user = current_user();
$errors = [];
$success = false;

if (is_post_request()) {
    require_valid_csrf_or_fail();

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($currentPassword === '') {
        $errors[] = 'Current password is required.';
    }
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
        $pdo = db();
        
        // Verify current password
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => (int) $user['id']]);
        $userData = $stmt->fetch();

        if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            // Check password history (optional)
            if (defined('PASSWORD_HISTORY_COUNT') && PASSWORD_HISTORY_COUNT > 0) {
                $stmt = $pdo->prepare('SELECT password_hash FROM password_history WHERE user_id = :user_id ORDER BY changed_at DESC LIMIT :limit');
                $stmt->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
                $stmt->bindValue(':limit', PASSWORD_HISTORY_COUNT, PDO::PARAM_INT);
                $stmt->execute();
                $passwordHistory = $stmt->fetchAll();

                foreach ($passwordHistory as $oldHash) {
                    if (password_verify($newPassword, $oldHash['password_hash'])) {
                        $errors[] = 'You cannot reuse any of your last ' . PASSWORD_HISTORY_COUNT . ' passwords.';
                        break;
                    }
                }
            }

            if (!$errors) {
                // Update password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $pdo->beginTransaction();
                
                try {
                    // Update current password
                    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, last_password_change = NOW() WHERE id = :id');
                    $stmt->execute(['hash' => $newHash, 'id' => (int) $user['id']]);

                    // Add to password history
                    if (defined('PASSWORD_HISTORY_COUNT') && PASSWORD_HISTORY_COUNT > 0) {
                        $stmt = $pdo->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (:user_id, :hash)');
                        $stmt->execute(['user_id' => (int) $user['id'], 'hash' => $newHash]);

                        // Keep only last N passwords
                        $stmt = $pdo->prepare('DELETE FROM password_history WHERE user_id = :user_id AND id NOT IN (SELECT id FROM password_history WHERE user_id = :user_id ORDER BY changed_at DESC LIMIT :limit)');
                        $stmt->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
                        $stmt->bindValue(':limit', PASSWORD_HISTORY_COUNT, PDO::PARAM_INT);
                        $stmt->execute();
                    }

                    $pdo->commit();
                    $success = true;
                    set_flash('success', 'Password changed successfully. Please log in again with your new password.');
                    
                    // Logout user after password change
                    logout_user();
                    redirect('login.php');
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $errors[] = 'Unable to update password. Please try again.';
                }
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-key-fill me-2 text-gradient"></i>Change Password</h1>
            <p>Update your account password</p>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="glass-card p-4">
            <?php if ($errors): ?>
                <div class="glass-alert glass-alert-danger mb-4">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= implode('<br>', array_map('h', $errors)) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="glass-alert glass-alert-success mb-4">
                    <i class="bi bi-check-circle-fill"></i>
                    Password changed successfully!
                </div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_input() ?>
                
                <div class="mb-4">
                    <label class="form-label">Current Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-lock-fill"></i>
                        </span>
                        <input type="password" name="current_password" class="form-control glass-form-control" placeholder="Enter current password" required>
                    </div>
                </div>

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

                <div class="d-flex gap-2 justify-content-end">
                    <a href="<?= h(app_url('dashboard.php')) ?>" class="btn glass-btn glass-btn-outline">
                        <i class="bi bi-x-lg"></i> Cancel
                    </a>
                    <button type="submit" class="btn glass-btn glass-btn-primary">
                        <i class="bi bi-check-lg"></i> Change Password
                    </button>
                </div>
            </form>
        </div>

        <div class="glass-card p-4 mt-4">
            <h5 class="mb-3"><i class="bi bi-shield-check me-2"></i>Password Requirements</h5>
            <ul class="text-muted small ps-3 mb-0">
                <li class="mb-2">At least 8 characters long</li>
                <li class="mb-2">At least one uppercase letter (A-Z)</li>
                <li class="mb-2">At least one lowercase letter (a-z)</li>
                <li class="mb-2">At least one number (0-9)</li>
                <li>At least one special character (!@#$%^&*)</li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
