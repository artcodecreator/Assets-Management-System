<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$currentPage = 'dashboard';
$pageTitle = 'Dashboard';
$pdo = db();

$totalAssets = (int) $pdo->query('SELECT COUNT(*) FROM assets')->fetchColumn();
$totalCategories = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$totalLocations = (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn();
$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();

$lowStockCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM (
        SELECT c.id
        FROM categories c
        LEFT JOIN assets a ON a.category_id = c.id AND a.status IN ('In Stock', 'Available')
        GROUP BY c.id, c.low_stock_threshold
        HAVING COALESCE(SUM(a.quantity), 0) < c.low_stock_threshold
    ) AS low_stock"
)->fetchColumn();

$statusRows = $pdo->query(
    'SELECT status, COUNT(*) AS asset_count, COALESCE(SUM(quantity), 0) AS total_quantity
     FROM assets GROUP BY status ORDER BY status'
)->fetchAll();

$recentAssets = $pdo->query(
    'SELECT a.id, a.name, a.serial_number, a.status, a.quantity, l.name AS location_name, a.updated_at
     FROM assets a
     JOIN locations l ON l.id = a.location_id
     ORDER BY a.updated_at DESC LIMIT 8'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-speedometer2 me-2 text-gradient"></i>Dashboard</h1>
            <p>Inventory and asset tracking overview</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (has_permission('create_assets')): ?>
            <a href="<?= h(app_url('asset_create.php')) ?>" class="btn glass-btn glass-btn-primary">
                <i class="bi bi-plus-lg"></i> Add Asset
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3 stagger-item">
        <div class="stats-card">
            <div class="stats-icon" style="background: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <div class="stats-value"><?= h((string) $totalAssets) ?></div>
            <div class="stats-label">Total Assets</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 stagger-item">
        <div class="stats-card">
            <div class="stats-icon" style="background: rgba(139, 92, 246, 0.2); color: #8b5cf6;">
                <i class="bi bi-tags-fill"></i>
            </div>
            <div class="stats-value"><?= h((string) $totalCategories) ?></div>
            <div class="stats-label">Categories</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 stagger-item">
        <div class="stats-card">
            <div class="stats-icon" style="background: rgba(6, 182, 212, 0.2); color: #06b6d4;">
                <i class="bi bi-geo-alt-fill"></i>
            </div>
            <div class="stats-value"><?= h((string) $totalLocations) ?></div>
            <div class="stats-label">Locations</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 stagger-item">
        <div class="stats-card">
            <div class="stats-icon" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="stats-value"><?= h((string) $lowStockCount) ?></div>
            <div class="stats-label">Low Stock Alerts</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Asset Status Distribution -->
    <div class="col-lg-6">
        <div class="glass-card p-4">
            <h5 class="mb-4"><i class="bi bi-pie-chart-fill me-2 text-gradient"></i>Asset Status Distribution</h5>
            <div class="row g-3">
                <?php 
                $statusColors = [
                    'In Use' => ['bg' => 'rgba(59, 130, 246, 0.2)', 'color' => '#3b82f6', 'icon' => 'bi-laptop'],
                    'In Stock' => ['bg' => 'rgba(139, 92, 246, 0.2)', 'color' => '#8b5cf6', 'icon' => 'bi-box-fill'],
                    'In Repair' => ['bg' => 'rgba(245, 158, 11, 0.2)', 'color' => '#f59e0b', 'icon' => 'bi-tools'],
                    'Available' => ['bg' => 'rgba(16, 185, 129, 0.2)', 'color' => '#10b981', 'icon' => 'bi-check-circle-fill']
                ];
                foreach ($statusRows as $row): 
                    $colors = $statusColors[$row['status']] ?? ['bg' => 'rgba(100, 100, 100, 0.2)', 'color' => '#64748b', 'icon' => 'bi-question-circle'];
                ?>
                <div class="col-6">
                    <div class="d-flex align-items-center p-3 rounded" style="background: <?= $colors['bg'] ?>;">
                        <div class="me-3">
                            <i class="bi <?= $colors['icon'] ?>" style="font-size: 1.5rem; color: <?= $colors['color'] ?>;"></i>
                        </div>
                        <div>
                            <div class="fw-bold" style="color: <?= $colors['color'] ?>;"><?= h($row['status']) ?></div>
                            <div class="small text-muted"><?= (int)$row['asset_count'] ?> assets (<?= (int)$row['total_quantity'] ?> qty)</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Assets -->
    <div class="col-lg-6">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2 text-gradient"></i>Recent Assets</h5>
                <a href="<?= h(app_url('assets.php')) ?>" class="btn glass-btn glass-btn-sm glass-btn-outline">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table glass-table mb-0">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Status</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentAssets): ?>
                            <?php foreach ($recentAssets as $asset): ?>
                            <tr>
                                <td>
                                    <a href="<?= h(app_url('asset_view.php?id=' . (int) $asset['id'])) ?>" class="text-decoration-none">
                                        <div class="fw-semibold"><?= h($asset['name']) ?></div>
                                        <small class="text-muted"><?= h($asset['serial_number']) ?></small>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    $statusClass = match($asset['status']) {
                                        'In Use' => 'info',
                                        'In Stock' => 'primary',
                                        'In Repair' => 'warning',
                                        'Available' => 'success',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="glass-badge glass-badge-<?= $statusClass ?>"><?= h($asset['status']) ?></span>
                                </td>
                                <td><small><i class="bi bi-geo-alt me-1"></i><?= h($asset['location_name']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No assets found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
