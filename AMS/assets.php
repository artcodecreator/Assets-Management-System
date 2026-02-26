<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$currentPage = 'assets';
$pageTitle = 'Assets';
$pdo = db();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$locations = $pdo->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();

$search = trim((string) ($_GET['search'] ?? ''));
$categoryId = (int) ($_GET['category_id'] ?? 0);
$status = trim((string) ($_GET['status'] ?? ''));
$locationId = (int) ($_GET['location_id'] ?? 0);

if ($status !== '' && !valid_status($status)) {
    $status = '';
}

$sql = 'SELECT a.id, a.name, a.serial_number, a.purchase_date, a.status, a.quantity,
               c.name AS category_name,
               l.name AS location_name
        FROM assets a
        JOIN categories c ON c.id = a.category_id
        JOIN locations l ON l.id = a.location_id
        WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (a.name LIKE :search OR a.serial_number LIKE :search OR c.name LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($categoryId > 0) {
    $sql .= ' AND a.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

if ($status !== '') {
    $sql .= ' AND a.status = :status';
    $params['status'] = $status;
}

if ($locationId > 0) {
    $sql .= ' AND a.location_id = :location_id';
    $params['location_id'] = $locationId;
}

$sql .= ' ORDER BY a.name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-box-seam me-2 text-gradient"></i>Assets</h1>
            <p>Track and search all inventory records</p>
        </div>
        <?php if (has_permission('create_assets')): ?>
            <a href="<?= h(app_url('asset_create.php')) ?>" class="btn glass-btn glass-btn-primary">
                <i class="bi bi-plus-lg"></i> Add Asset
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search & Filter -->
<div class="glass-card mb-4 p-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="search">
                <i class="bi bi-search me-1"></i> Search
            </label>
            <input type="text" class="form-control glass-form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Name, serial number, or category">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="category_id">Category</label>
            <select class="form-select glass-form-select" id="category_id" name="category_id">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= selected($categoryId, $cat['id']) ?>><?= h($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select glass-form-select" id="status" name="status">
                <option value="">All Statuses</option>
                <?php foreach (ASSET_STATUSES as $s): ?>
                    <option value="<?= h($s) ?>" <?= selected($status, $s) ?>><?= h($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="location_id">Location</label>
            <select class="form-select glass-form-select" id="location_id" name="location_id">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int) $loc['id'] ?>" <?= selected($locationId, $loc['id']) ?>><?= h($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <div class="d-flex gap-2">
                <button type="submit" class="btn glass-btn glass-btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="<?= h(app_url('assets.php')) ?>" class="btn glass-btn glass-btn-outline">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Assets Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="table glass-table mb-0">
            <thead>
                <tr>
                    <th>Asset Name</th>
                    <th>Serial Number</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Purchase Date</th>
                    <th>Qty</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assets): ?>
                    <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= h($asset['name']) ?></div>
                        </td>
                        <td><code class="small"><?= h($asset['serial_number']) ?></code></td>
                        <td><?= h($asset['category_name']) ?></td>
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
                        <td><?= h($asset['purchase_date']) ?></td>
                        <td><span class="fw-bold"><?= (int)$asset['quantity'] ?></span></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="<?= h(app_url('asset_view.php?id=' . (int) $asset['id'])) ?>" class="action-btn action-btn-view" title="View">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                <?php if (has_permission('edit_assets') || has_permission('edit_asset_location') || has_permission('edit_asset_status')): ?>
                                    <a href="<?= h(app_url('asset_edit.php?id=' . (int) $asset['id'])) ?>" class="action-btn action-btn-edit" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (has_permission('delete_assets')): ?>
                                    <form method="post" action="<?= h(app_url('asset_delete.php')) ?>" class="d-inline" onsubmit="return confirm('Delete this asset permanently?');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="id" value="<?= h((string) $asset['id']) ?>">
                                        <button type="submit" class="action-btn action-btn-delete" title="Delete">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.5;"></i>
                            <p class="mt-2">No assets found for the selected filters.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
