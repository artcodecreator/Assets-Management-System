<?php
require_once __DIR__ . '/includes/auth.php';

require_role(['admin']);

$pdo = db();
$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);

if (is_post_request()) {
    require_valid_csrf_or_fail();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $threshold = (int) ($_POST['low_stock_threshold'] ?? 5);

        if ($name === '') {
            $errors[] = 'Category name is required.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare('INSERT INTO categories (name, low_stock_threshold) VALUES (:name, :threshold)');
                $stmt->execute(['name' => $name, 'threshold' => $threshold]);
                set_flash('success', 'Category created successfully.');
                redirect('categories.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Category already exists.';
                } else {
                    $errors[] = 'Unable to create category.';
                }
            }
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $threshold = (int) ($_POST['low_stock_threshold'] ?? 5);

        if ($name === '') {
            $errors[] = 'Category name is required.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare('UPDATE categories SET name = :name, low_stock_threshold = :threshold WHERE id = :id');
                $stmt->execute(['name' => $name, 'threshold' => $threshold, 'id' => $id]);
                set_flash('success', 'Category updated successfully.');
                redirect('categories.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Category already exists.';
                } else {
                    $errors[] = 'Unable to update category.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM assets WHERE category_id = :id');
        $stmt->execute(['id' => $id]);
        $assetCount = (int) $stmt->fetchColumn();

        if ($assetCount > 0) {
            $errors[] = 'Cannot delete category with associated assets.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
            $stmt->execute(['id' => $id]);
            set_flash('success', 'Category deleted successfully.');
            redirect('categories.php');
        }
    }
}

$categories = $pdo->query('
    SELECT c.id, c.name, c.low_stock_threshold, c.created_at,
           COUNT(a.id) AS asset_count, COALESCE(SUM(a.quantity), 0) AS total_quantity
    FROM categories c
    LEFT JOIN assets a ON a.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name
')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-tags-fill me-2 text-gradient"></i>Categories</h1>
            <p>Manage asset categories and stock thresholds</p>
        </div>
        <button class="btn glass-btn glass-btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
            <i class="bi bi-plus-lg"></i> Add Category
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
    <?php if ($categories): ?>
        <?php foreach ($categories as $category): ?>
        <div class="col-md-6 col-lg-4 stagger-item">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1"><?= h($category['name']) ?></h5>
                        <small class="text-muted">Created: <?= h($category['created_at']) ?></small>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" style="background: rgba(255,255,255,0.1);">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?= h(app_url('categories.php?edit=' . (int) $category['id'])) ?>">
                                    <i class="bi bi-pencil me-2"></i>Edit
                                </a>
                            </li>
                            <li>
                                <form method="post" onsubmit="return confirm('Delete this category?');">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= h((string) $category['id']) ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-trash me-2"></i>Delete
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: rgba(59, 130, 246, 0.2);">
                            <div class="h4 mb-0" style="color: #3b82f6;"><?= (int)$category['asset_count'] ?></div>
                            <small class="text-muted">Assets</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: rgba(139, 92, 246, 0.2);">
                            <div class="h4 mb-0" style="color: #8b5cf6;"><?= (int)$category['total_quantity'] ?></div>
                            <small class="text-muted">Total Qty</small>
                        </div>
                    </div>
                </div>

                <div class="mt-3 pt-3" style="border-top: 1px solid var(--glass-border);">
                    <small class="text-muted">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Low stock threshold: <span class="fw-bold text-warning"><?= (int)$category['low_stock_threshold'] ?></span>
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="glass-card p-5 text-center">
                <i class="bi bi-tags" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-3 text-muted">No categories found. Create your first category.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalLabel"><i class="bi bi-tag me-2"></i>Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control glass-form-control" placeholder="e.g., IT Equipment" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Low Stock Threshold</label>
                        <input type="number" name="low_stock_threshold" class="form-control glass-form-control" value="5" min="1">
                        <small class="text-muted">Alert when quantity falls below this number</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn glass-btn glass-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn glass-btn glass-btn-primary">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
