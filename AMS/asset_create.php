<?php
require_once __DIR__ . '/includes/auth.php';

require_role(['admin']);

$currentPage = 'assets';
$pageTitle = 'Add Asset';
$pdo = db();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$locations = $pdo->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();

$errors = [];
$name = '';
$serialNumber = '';
$categoryId = '';
$locationId = '';
$purchaseDate = date('Y-m-d');
$status = 'In Stock';
$quantity = 1;

if (is_post_request()) {
    require_valid_csrf_or_fail();

    $name = trim((string) ($_POST['name'] ?? ''));
    $serialNumber = trim((string) ($_POST['serial_number'] ?? ''));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $locationId = (int) ($_POST['location_id'] ?? 0);
    $purchaseDate = trim((string) ($_POST['purchase_date'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));
    $quantity = (int) ($_POST['quantity'] ?? 1);

    if ($name === '') {
        $errors[] = 'Asset name is required.';
    }
    if ($serialNumber === '') {
        $errors[] = 'Serial number is required.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Please select a category.';
    }
    if ($locationId <= 0) {
        $errors[] = 'Please select a location.';
    }
    if ($purchaseDate === '') {
        $errors[] = 'Purchase date is required.';
    }
    if (!valid_status($status)) {
        $errors[] = 'Invalid status selected.';
    }
    if ($quantity < 1) {
        $errors[] = 'Quantity must be at least 1.';
    }

    if (!$errors) {
        try {
            $user = current_user();
            $stmt = $pdo->prepare('
                INSERT INTO assets (name, serial_number, category_id, location_id, purchase_date, status, quantity, created_by, updated_by)
                VALUES (:name, :serial_number, :category_id, :location_id, :purchase_date, :status, :quantity, :created_by, :updated_by)
            ');
            $stmt->execute([
                'name' => $name,
                'serial_number' => $serialNumber,
                'category_id' => $categoryId,
                'location_id' => $locationId,
                'purchase_date' => $purchaseDate,
                'status' => $status,
                'quantity' => $quantity,
                'created_by' => (int) $user['id'],
                'updated_by' => (int) $user['id'],
            ]);

            set_flash('success', 'Asset created successfully.');
            redirect('assets.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Serial number already exists.';
            } else {
                $errors[] = 'Unable to create asset. Please try again.';
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-plus-circle me-2 text-gradient"></i>Add New Asset</h1>
            <p>Create a new inventory asset record</p>
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
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Asset Name *</label>
                        <input type="text" name="name" class="form-control glass-form-control" value="<?= h($name) ?>" placeholder="e.g., Dell Latitude 5540" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Serial Number *</label>
                        <input type="text" name="serial_number" class="form-control glass-form-control" value="<?= h($serialNumber) ?>" placeholder="e.g., LT-DEL-5540-001" required>
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
                        <label class="form-label">Location *</label>
                        <select name="location_id" class="form-select glass-form-select" required>
                            <option value="">-- Select Location --</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= (int) $loc['id'] ?>" <?= selected($locationId, $loc['id']) ?>><?= h($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Purchase Date *</label>
                        <input type="date" name="purchase_date" class="form-control glass-form-control" value="<?= h($purchaseDate) ?>" required>
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
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="quantity" class="form-control glass-form-control" value="<?= h((string) $quantity) ?>" min="1" required>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2 justify-content-end">
                    <a href="<?= h(app_url('assets.php')) ?>" class="btn glass-btn glass-btn-outline">Cancel</a>
                    <button type="submit" class="btn glass-btn glass-btn-primary">
                        <i class="bi bi-check-lg"></i> Create Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="glass-card p-4">
            <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Quick Tips</h5>
            <ul class="text-muted small ps-3">
                <li class="mb-2">Asset name should be descriptive and unique.</li>
                <li class="mb-2">Serial number must be unique across all assets.</li>
                <li class="mb-2">Purchase date helps track asset age and warranty.</li>
                <li>Status determines asset availability for assignment.</li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
