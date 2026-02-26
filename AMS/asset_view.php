<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$currentPage = 'assets';
$pageTitle = 'View Asset';
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid asset ID.');
    redirect('assets.php');
}

$stmt = $pdo->prepare('
    SELECT a.*, 
           c.name AS category_name,
           l.name AS location_name,
           u1.full_name AS created_by_name,
           u2.full_name AS updated_by_name
    FROM assets a
    JOIN categories c ON c.id = a.category_id
    JOIN locations l ON l.id = a.location_id
    LEFT JOIN users u1 ON u1.id = a.created_by
    LEFT JOIN users u2 ON u2.id = a.updated_by
    WHERE a.id = :id
');
$stmt->execute(['id' => $id]);
$asset = $stmt->fetch();

if (!$asset) {
    set_flash('danger', 'Asset not found.');
    redirect('assets.php');
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-eye me-2 text-gradient"></i>Asset Details</h1>
            <p>View complete asset information</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= h(app_url('assets.php')) ?>" class="btn glass-btn glass-btn-outline">
                <i class="bi bi-arrow-left"></i> Back to Assets
            </a>
            <?php if (has_permission('edit_assets') || has_permission('edit_asset_location') || has_permission('edit_asset_status')): ?>
            <a href="<?= h(app_url('asset_edit.php?id=' . $id)) ?>" class="btn glass-btn glass-btn-primary">
                <i class="bi bi-pencil"></i> Edit Asset
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h3 class="mb-1"><?= h($asset['name']) ?></h3>
                    <code class="text-muted"><?= h($asset['serial_number']) ?></code>
                </div>
                <?php 
                $statusClass = match($asset['status']) {
                    'In Use' => 'info',
                    'In Stock' => 'primary',
                    'In Repair' => 'warning',
                    'Available' => 'success',
                    default => 'secondary'
                };
                ?>
                <span class="glass-badge glass-badge-<?= $statusClass ?>" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                    <?= h($asset['status']) ?>
                </span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 rounded" style="background: rgba(139, 92, 246, 0.1);">
                        <small class="text-muted d-block mb-1">Category</small>
                        <strong><?= h($asset['category_name']) ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded" style="background: rgba(6, 182, 212, 0.1);">
                        <small class="text-muted d-block mb-1">Location</small>
                        <strong><i class="bi bi-geo-alt me-1"></i><?= h($asset['location_name']) ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded" style="background: rgba(16, 185, 129, 0.1);">
                        <small class="text-muted d-block mb-1">Purchase Date</small>
                        <strong><?= h($asset['purchase_date']) ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded" style="background: rgba(59, 130, 246, 0.1);">
                        <small class="text-muted d-block mb-1">Quantity</small>
                        <strong><?= (int)$asset['quantity'] ?> units</strong>
                    </div>
                </div>
            </div>

            <hr style="border-color: var(--glass-border);">

            <div class="row g-3 text-muted small">
                <div class="col-md-6">
                    <p class="mb-1"><i class="bi bi-person-plus me-1"></i>Created by: <span class="text-white"><?= h($asset['created_by_name'] ?? 'System') ?></span></p>
                    <p class="mb-0"><i class="bi bi-calendar-plus me-1"></i>Created at: <span class="text-white"><?= h($asset['created_at']) ?></span></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><i class="bi bi-person-dash me-1"></i>Updated by: <span class="text-white"><?= h($asset['updated_by_name'] ?? 'System') ?></span></p>
                    <p class="mb-0"><i class="bi bi-calendar-check me-1"></i>Updated at: <span class="text-white"><?= h($asset['updated_at']) ?></span></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass-card p-4 mb-4">
            <h5 class="mb-3"><i class="bi bi-graph-up me-2"></i>Quick Stats</h5>
            <div class="text-center">
                <div class="h2 mb-0 text-gradient"><?= (int)$asset['quantity'] ?></div>
                <small class="text-muted">Total Units</small>
            </div>
        </div>

        <?php if (has_permission('delete_assets')): ?>
        <div class="glass-card p-4" style="border-color: rgba(239, 68, 68, 0.3);">
            <h5 class="mb-3 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h5>
            <p class="text-muted small">Permanently delete this asset from the system.</p>
            <form method="post" action="<?= h(app_url('asset_delete.php')) ?>" onsubmit="return confirm('Are you sure you want to delete this asset? This action cannot be undone.');">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= h((string) $id) ?>">
                <button type="submit" class="btn glass-btn w-100" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
                    <i class="bi bi-trash me-1"></i> Delete Asset
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
