<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$error = '';
$email = '';

if (is_post_request()) {
    require_valid_csrf_or_fail();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } elseif (login_user($email, $password)) {
        set_flash('success', 'Welcome back to ' . APP_NAME . '!');
        redirect('dashboard.php');
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(APP_NAME) ?> | Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= h(app_url('css/glassy.css')) ?>" rel="stylesheet">
    <style>
        body { overflow-x: hidden; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card fade-in-up">
        <div class="login-brand">
            <h1><i class="bi bi-box-seam-fill me-2"></i><?= h(APP_NAME) ?></h1>
            <p>Sign in to access your dashboard</p>
        </div>

        <?php foreach (get_flashes() as $flash): ?>
            <div class="glass-alert glass-alert-<?= h($flash['type']) ?> mb-4">
                <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
                <?= h($flash['message']) ?>
            </div>
        <?php endforeach; ?>

        <?php if ($error !== ''): ?>
            <div class="glass-alert glass-alert-danger mb-4">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_input() ?>
            <div class="mb-4">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-envelope-fill"></i>
                    </span>
                    <input type="email" class="form-control glass-form-control" id="email" name="email" value="<?= h($email) ?>" placeholder="Enter your email" required>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input type="password" class="form-control glass-form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            <button type="submit" class="btn glass-btn glass-btn-primary w-100 py-3">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <hr class="my-4" style="border-color: var(--glass-border);">
        
        <div class="text-center">
            <p class="text-muted small mb-3">Demo Accounts</p>
            <div class="d-flex flex-column gap-2">
                <div class="glass-badge d-flex justify-content-between align-items-center" style="background: rgba(139, 92, 246, 0.2); border-color: rgba(139, 92, 246, 0.3);">
                    <span><i class="bi bi-shield-fill me-2"></i>Admin</span>
                    <code class="text-white-50">admin@example.com</code>
                </div>
                <div class="glass-badge d-flex justify-content-between align-items-center" style="background: rgba(6, 182, 212, 0.2); border-color: rgba(6, 182, 212, 0.3);">
                    <span><i class="bi bi-person-fill-gear me-2"></i>Manager</span>
                    <code class="text-white-50">manager@example.com</code>
                </div>
                <div class="glass-badge d-flex justify-content-between align-items-center" style="background: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.3);">
                    <span><i class="bi bi-person-fill me-2"></i>Viewer</span>
                    <code class="text-white-50">viewer@example.com</code>
                </div>
            </div>
            <p class="text-muted small mt-3">
                <i class="bi bi-key me-1"></i>Password: <code class="text-gradient">Glassy@2024</code>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
