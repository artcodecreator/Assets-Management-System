<?php
$pageTitle = $pageTitle ?? APP_NAME;
$currentPage = $currentPage ?? '';
$user = current_user();
$flashes = get_flashes();

function nav_active(string $page, string $currentPage): string
{
    return $page === $currentPage ? 'active' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | <?= h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= h(app_url('css/glassy.css')) ?>" rel="stylesheet">
    <style>
        body { overflow-x: hidden; }
    </style>
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg glass-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= h(app_url('dashboard.php')) ?>">
            <i class="bi bi-box-seam-fill me-2"></i><?= h(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation" style="background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border);">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('dashboard', $currentPage) ?>" href="<?= h(app_url('dashboard.php')) ?>">
                        <i class="bi bi-grid-1x2-fill me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('assets', $currentPage) ?>" href="<?= h(app_url('assets.php')) ?>">
                        <i class="bi bi-box-fill me-1"></i> Assets
                    </a>
                </li>
                <?php if (has_role(['admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('categories', $currentPage) ?>" href="<?= h(app_url('categories.php')) ?>">
                        <i class="bi bi-tags-fill me-1"></i> Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('locations', $currentPage) ?>" href="<?= h(app_url('locations.php')) ?>">
                        <i class="bi bi-geo-alt-fill me-1"></i> Locations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('users', $currentPage) ?>" href="<?= h(app_url('users.php')) ?>">
                        <i class="bi bi-people-fill me-1"></i> Users
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('reports', $currentPage) ?>" href="<?= h(app_url('reports.php')) ?>">
                        <i class="bi bi-graph-up-arrow me-1"></i> Reports
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown user-dropdown">
                    <button class="btn glass-btn d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(139, 92, 246, 0.2); border-color: rgba(139, 92, 246, 0.3);">
                        <div class="user-avatar">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                        <span class="d-none d-md-inline"><?= h($user['full_name']) ?></span>
                        <span class="badge glass-badge-<?= $user['role'] === 'admin' ? 'primary' : ($user['role'] === 'manager' ? 'info' : 'success') ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?= h(app_url('change_password.php')) ?>">
                                <i class="bi bi-key-fill me-2"></i> Change Password
                            </a>
                        </li>
                        <li><hr class="dropdown-divider" style="border-color: var(--glass-border);"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= h(app_url('logout.php')) ?>">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container py-4">
<?php foreach ($flashes as $flash): ?>
    <div class="glass-alert glass-alert-<?= h($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
        <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill') ?>"></i>
        <?= h($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1);"></button>
    </div>
<?php endforeach; ?>
