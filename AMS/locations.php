<?php
require_once __DIR__ . '/includes/auth.php';

require_role(['admin']);

$pdo = db();
$errors = [];

if (is_post_request()) {
    require_valid_csrf_or_fail();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '') {
            $errors[] = 'Location name is required.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare('INSERT INTO locations (name) VALUES (:name)');
                $stmt->execute(['name' => $name]);
                set_flash('success', 'Location created successfully.');
                redirect('locations.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Location already exists.';
                } else {
                    $errors[] = 'Unable to create location.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM assets WHERE location_id = :id');
        $stmt->execute(['id' => $id]);
        $assetCount = (int) $stmt->fetchColumn();

        if ($assetCount > 0) {
            $errors[] = 'Cannot delete location with associated assets.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM locations WHERE id = :id');
            $stmt->execute(['id' => $id]);
            set_flash('success', 'Location deleted successfully.');
            redirect('locations.php');
        }
    }
}

$locations = $pdo->query('
    SELECT l.id, l.name, l.created_at,
           COUNT(a.id) AS asset_count, COALESCE(SUM(a.quantity), 0) AS total_quantity
    FROM locations l
    LEFT JOIN assets a ON a.location_id = l.id
    GROUP BY l.id
    ORDER BY l.name
')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-geo-alt-fill me-2 text-gradient"></i>Locations</h1>
            <p>Manage asset locations</p>
        </div>
        <button class="btn glass-btn glass-btn-primary" data-bs-toggle="modal" data-bs-target="#locationModal">
            <i class="bi bi-plus-lg"></i> Add Location
        </button>
    </div>
</div>

<?php if ($errors): ?>
    <div class="glass-alert glass-alert-danger mb-4">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= implode('<br>', array_map('h', $errors)) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php if ($locations): ?>
        <?php foreach ($locations as $location): ?>
        <div class="col-md-6 col-lg-4 stagger-item">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1"><i class="bi bi-geo-alt me-2 text-primary"></i><?= h($location['name']) ?></h5>
                        <small class="text-muted">Created: <?= h($location['created_at']) ?></small>
                    </div>
                    <?php if ((int)$location['asset_count'] === 0): ?>
                    <form method="post" onsubmit="return confirm('Delete this location?');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= h((string) $location['id']) ?>">
                        <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: rgba(6, 182, 212, 0.2);">
                            <div class="h4 mb-0" style="color: #06b6d4;"><?= (int)$location['asset_count'] ?></div>
                            <small class="text-muted">Assets</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: rgba(16, 185, 129, 0.2);">
                            <div class="h4 mb-0" style="color: #10b981;"><?= (int)$location['total_quantity'] ?></div>
                            <small class="text-muted">Total Qty</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="glass-card p-5 text-center">
                <i class="bi bi-geo-alt" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-3 text-muted">No locations found. Create your first location.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="locationModalLabel"><i class="bi bi-geo-alt me-2"></i>Add Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Location Name</label>
                        <input type="text" name="name" class="form-control glass-form-control" placeholder="e.g., Office A, Warehouse 1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn glass-btn glass-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn glass-btn glass-btn-primary">Create Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
