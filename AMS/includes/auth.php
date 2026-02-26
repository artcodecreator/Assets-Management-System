<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    static $loaded = false;
    static $cachedUser = null;

    if ($loaded) {
        return $cachedUser;
    }

    $loaded = true;
    $userId = $_SESSION['user_id'] ?? null;

    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, full_name, email, role, is_active, last_password_change, locked_until FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $userId]);
    $user = $stmt->fetch();

    if (!$user || !(bool) $user['is_active']) {
        unset($_SESSION['user_id']);
        return null;
    }

    // Check if account is locked
    if (isset($user['locked_until']) && !is_null($user['locked_until'])) {
        $lockedUntil = new DateTime($user['locked_until']);
        $now = new DateTime();
        if ($now < $lockedUntil) {
            unset($_SESSION['user_id']);
            return null;
        }
    }

    $cachedUser = $user;
    return $cachedUser;
}

function login_user(string $email, string $password): bool
{
    $email = strtolower(trim($email));

    $stmt = db()->prepare('SELECT id, password_hash, is_active, failed_login_attempts, locked_until FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !(bool) $user['is_active']) {
        return false;
    }

    // Check if account is locked
    if (isset($user['locked_until']) && !is_null($user['locked_until'])) {
        $lockedUntil = new DateTime($user['locked_until']);
        $now = new DateTime();
        if ($now < $lockedUntil) {
            return false;
        }
    }

    if (!password_verify($password, $user['password_hash'])) {
        // Increment failed login attempts
        $newAttempts = (int) $user['failed_login_attempts'] + 1;
        
        if ($newAttempts >= MAX_LOGIN_ATTEMPTS) {
            // Lock the account
            $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
            $updateStmt = db()->prepare('UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked_until WHERE id = :id');
            $updateStmt->execute(['attempts' => $newAttempts, 'locked_until' => $lockUntil, 'id' => $user['id']]);
        } else {
            $updateStmt = db()->prepare('UPDATE users SET failed_login_attempts = :attempts WHERE id = :id');
            $updateStmt->execute(['attempts' => $newAttempts, 'id' => $user['id']]);
        }
        
        return false;
    }

    // Reset failed login attempts on successful login
    $updateStmt = db()->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
    $updateStmt->execute(['id' => $user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    return true;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function require_login(): void
{
    if (current_user() === null) {
        set_flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function has_role(array $roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], $roles, true);
}

function require_role(array $roles): void
{
    require_login();

    if (!has_role($roles)) {
        set_flash('danger', 'You do not have permission to access that page.');
        redirect('dashboard.php');
    }
}

function is_admin(): bool
{
    return has_role(['admin']);
}

function can_manage_assets(): bool
{
    return has_permission('edit_assets') || has_permission('create_assets');
}

/**
 * Check if current user has a specific permission
 */
function has_permission(string $permission): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }

    // Admin always has all permissions
    if ($user['role'] === 'admin') {
        return true;
    }

    static $permissionsCache = [];

    if (!isset($permissionsCache[$user['role']])) {
        $stmt = db()->prepare('
            SELECT p.name 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role = :role
        ');
        $stmt->execute(['role' => $user['role']]);
        $permissionsCache[$user['role']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    return in_array($permission, $permissionsCache[$user['role']], true);
}

/**
 * Require a specific permission
 */
function require_permission(string $permission): void
{
    require_login();

    if (!has_permission($permission)) {
        set_flash('danger', 'You do not have permission to perform that action.');
        redirect('dashboard.php');
    }
}

/**
 * Get all permissions for current user
 */
function get_user_permissions(): array
{
    $user = current_user();
    if ($user === null) {
        return [];
    }

    // Admin gets all permissions
    if ($user['role'] === 'admin') {
        $stmt = db()->query('SELECT name FROM permissions');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $stmt = db()->prepare('
        SELECT p.name 
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role = :role
    ');
    $stmt->execute(['role' => $user['role']]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Check if password meets requirements
 */
function validate_password(string $password): array
{
    $errors = [];

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }

    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }

    if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }

    if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    return $errors;
}

/**
 * Check if password was used before
 */
function is_password_in_history(int $userId, string $newPassword): bool
{
    $stmt = db()->prepare('SELECT password_history FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['password_history'])) {
        return false;
    }

    $history = json_decode($user['password_history'], true);
    if (!is_array($history)) {
        return false;
    }

    foreach ($history as $oldHash) {
        if (password_verify($newPassword, $oldHash)) {
            return true;
        }
    }

    return false;
}

/**
 * Update password with history tracking
 */
function update_password(int $userId, string $newPassword): bool
{
    $pdo = db();
    
    // Get current password history
    $stmt = $pdo->prepare('SELECT password_history FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    $history = [];
    if (!empty($user['password_history'])) {
        $history = json_decode($user['password_history'], true);
        if (!is_array($history)) {
            $history = [];
        }
    }

    // Add current password to history before changing
    $currentStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $currentStmt->execute(['id' => $userId]);
    $currentUser = $currentStmt->fetch();
    
    if ($currentUser) {
        $history[] = $currentUser['password_hash'];
        // Keep only last N passwords
        if (count($history) > PASSWORD_HISTORY_COUNT) {
            $history = array_slice($history, -PASSWORD_HISTORY_COUNT);
        }
    }

    // Update password
    $updateStmt = $pdo->prepare('
        UPDATE users 
        SET password_hash = :password_hash, 
            last_password_change = NOW(),
            password_history = :history
        WHERE id = :id
    ');
    
    return $updateStmt->execute([
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'history' => json_encode($history),
        'id' => $userId
    ]);
}

/**
 * Change own password
 */
function change_own_password(int $userId, string $currentPassword, string $newPassword): array
{
    $errors = [];

    // Verify current password
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
        return $errors;
    }

    // Validate new password
    $validationErrors = validate_password($newPassword);
    if (!empty($validationErrors)) {
        return $validationErrors;
    }

    // Check password history
    if (is_password_in_history($userId, $newPassword)) {
        $errors[] = 'You cannot reuse any of your last ' . PASSWORD_HISTORY_COUNT . ' passwords.';
        return $errors;
    }

    // Update password
    if (!update_password($userId, $newPassword)) {
        $errors[] = 'Failed to update password. Please try again.';
        return $errors;
    }

    return $errors;
}

/**
 * Admin reset user password
 */
function admin_reset_password(int $adminId, int $userId, string $newPassword): array
{
    $errors = [];

    // Verify admin has permission
    if (!has_permission('reset_user_password')) {
        $errors[] = 'You do not have permission to reset passwords.';
        return $errors;
    }

    // Validate new password
    $validationErrors = validate_password($newPassword);
    if (!empty($validationErrors)) {
        return $validationErrors;
    }

    // Get user info
    $stmt = db()->prepare('SELECT id FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        $errors[] = 'User not found.';
        return $errors;
    }

    // Update password
    if (!update_password($userId, $newPassword)) {
        $errors[] = 'Failed to reset password. Please try again.';
        return $errors;
    }

    // Log password change request
    $logStmt = db()->prepare('
        INSERT INTO password_change_requests (user_id, changed_by, request_type, status, ip_address, notes)
        VALUES (:user_id, :changed_by, :request_type, :status, :ip_address, :notes)
    ');
    $logStmt->execute([
        'user_id' => $userId,
        'changed_by' => $adminId,
        'request_type' => 'admin_reset',
        'status' => 'completed',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'notes' => 'Password reset by administrator'
    ]);

    return $errors;
}
