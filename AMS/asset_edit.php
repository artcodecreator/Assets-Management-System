<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

// Admin can edit all fields, Manager can only edit location and status
$canEditAll = has_permission('edit_assets');
$canEditPartial = has_permission('edit_asset_location') || has_permission('edit_asset_status');

if (!$canEditAll && !$canEditPartial) {
    set_flash('danger', 'You do not have permission to edit assets.');
    redirect('assets.php');
}

$currentPage = 'assets';
$pageTitle = 'Edit Asset';
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid asset ID.');
    redirect('assets.php');
}

$stmt = $pdo->prepare('SELECT * FROM assets WHERE id = :id');
$stmt->execute(['id' => $id]);
$asset = $stmt->fetch();

if (!$asset) {
    set_flash('danger', 'Asset not found.');
    redirect('assets.php');
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$locations = $pdo->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();

$errors = [];
$name = $asset['name'];
$serialNumber = $asset['serial_number'];
$categoryId = $asset['category_id'];
$locationId = $asset['location_id'];
$purchaseDate = $asset['purchase_date'];
$status = $asset['status'];
$quantity = $asset['quantity'];

if (is_post_request()) {
    require_valid_csrf_or_fail();

    if ($canEditAll) {
        $name = trim((string) ($_POST['name'] ?? ''));
        $serialNumber = trim((string) ($_POST['serial_number'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 1);
    }
    
    if ($canEditPartial || $canEditAll) {
        $locationId = (int) ($_POST['location_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
    }
    
    $purchaseDate = trim((string) ($_POST['purchase_date'] ?? ''));

    if ($canEditAll) {
        if ($name === '') {
            $errors[] = 'Asset name is required.';
        }
        if ($serialNumber === '') {
            $errors[] = 'Serial number is required.';
        }
        if ($categoryId <= 0) {
            $errors[] = 'Please select a category.';
        }
        if ($quantity < 1) {
            $errors[] = 'Quantity must be at least 1.';
        }
    }
    
    if ($canEditPartial || $canEditAll) {
        if ($locationId <= 0) {
            $errors[] = 'Please select a location.';
        }
        if (!valid_status($status)) {
            $errors[] = 'Invalid status selected.';
        }
    }
    
    if ($purchaseDate === '') {
        $errors[] = 'Purchase date is required.';
    }

    if (!$errors) {
        try {
            $user = current_user();
            
            if ($canEditAll) {
                $stmt = $pdo->prepare('
                    UPDATE assets 
                    SET name = :name, serial_number = :serial_number, category_id = :category_id, 
                        location_id = :location_id, purchase_date = :purchase_date, 
                        status = :status, quantity = :quantity, updated_by = :updated_by
                    WHERE id = :id
                ');
                $stmt->execute([
                    'name' => $name,
                    'serial_number' => $serialNumber,
                    'category_id' => $categoryId,
                    'location_id' => $locationId,
                    'purchase_date' => $purchaseDate,
                    'status' => $status,
                    'quantity' => $quantity,
                    'updated_by' => (int) $user['id'],
                    'id' => $id,
                ]);
            } else {
                // Manager can only update location and status
                $stmt = $pdo->prepare('
                    UPDATE assets 
                    SET location_id = :location_id, status = :status, updated_by = :updated_by
                    WHERE id = :id
                ');
                $stmt->execute([
                    'location_id' => $locationId,
                    'status' => $status,
                    'updated_by' => (int) $user['id'],
                    'id' => $id,
                ]);
            }

            set_flash('success', 'Asset updated successfully.');
            redirect('assets.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Serial number already exists.';
            } else {
                $errors[] = 'Unable to update asset. Please try again.';
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-pencil-square me-2 text-gradient"></i>Edit Asset</h1>
            <p>Update asset information</p>
        </div>
        <a href="<?= h(app_url('assets.php')) ?>" class="btn glass-btn glass-btn-outline">
            <i class="bi bi-arrow-left"></i> Back to Assets
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="glass-card p-4">
            <?php if ($errors): ?>
                <div class="glass-alert glass-alert-danger mb-4">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= implode('<br>', array_map('h', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_input() ?>
                
                <?php if ($canEditAll): ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Asset Name *</label>
                        <input type="text" name="name" class="form-control glass-form-control" value="<?= h($name) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Serial Number *</label>
                        <input type="text" name="serial_number" class="form-control glass-form-control" value="<?= h($serialNumber) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category *</label>
                        <select name="category_id" class="form-select glass-form-select" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>" <?= selected($categoryId, $cat['id']) ?>><?= h($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="quantity" class="form-control glass-form-control" value="<?= h((string) $quantity) ?>" min="1" required>
                    </div>
                </div>
                <?php else: ?>
                <div class="glass-alert glass-alert-info mb-4">
                    <i class="bi bi-info-circle-fill"></i>
                    As a Manager, you can only update the Location and Status fields.
                </div>
                <input type="hidden" name="name" value="<?= h($name) ?>">
                <input type="hidden" name="serial_number" value="<?= h($serialNumber) ?>">
                <input type="hidden" name="category_id" value="<?= h((string) $categoryId) ?>">
                <input type="hidden" name="quantity" value="<?= h((string) $quantity) ?>">
                <?php endif; ?>
                
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <label class="form-label">Location *</label>
                        <select name="location_id" class="form-select glass-form-select" required>
                            <option value="">-- Select Location --</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= (int) $loc['id'] ?>" <?= selected($locationId, $loc['id']) ?>><?= h($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select glass-form-select" required>
                            <?php foreach (ASSET_STATUSES as $s): ?>
                                <option value="<?= h($s) ?>" <?= selected($status, $s) ?>><?= h($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Purchase Date *</label>
                        <input type="date" name="purchase_date" class="form-control glass-form-control" value="<?= h($purchaseDate) ?>" <?= $canEditAll ? 'required' : 'readonly' ?>>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2 justify-content-end">
                    <a href="<?= h(app_url('assets.php')) ?>" class="btn glass-btn glass-btn-outline">Cancel</a>
                    <button type="submit" class="btn glass-btn glass-btn-primary">
                        <i class="bi bi-check-lg"></i> Update Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="glass-card p-4">
            <h5 class="mb-3"><i class="bi bi-shield-check me-2"></i>Role Permissions</h5>
            <ul class="text-muted small ps-3">
                <li class="mb-2"><strong>Admin:</strong> Can edit all fields</li>
                <li class="mb-2"><strong>Manager:</strong> Can edit Location & Status only</li>
                <li><strong>Viewer:</strong> Cannot edit assets</li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
