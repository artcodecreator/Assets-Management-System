<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$currentPage = 'reports';
$pageTitle = 'Reports';
$pdo = db();

// Assets by Location Report
$locationFilter = (int) ($_GET['location_id'] ?? 0);
$locationSql = 'SELECT id, name FROM locations ORDER BY name';
$locations = $pdo->query($locationSql)->fetchAll();

$assetsByLocation = [];
if ($locationFilter > 0) {
    $stmt = $pdo->prepare('
        SELECT a.id, a.name, a.serial_number, a.status, a.quantity, c.name AS category_name
        FROM assets a
        JOIN categories c ON c.id = a.category_id
        WHERE a.location_id = :location_id
        ORDER BY a.name
    ');
    $stmt->execute(['location_id' => $locationFilter]);
    $assetsByLocation = $stmt->fetchAll();
    
    $locationName = '';
    foreach ($locations as $loc) {
        if ((int) $loc['id'] === $locationFilter) {
            $locationName = $loc['name'];
            break;
        }
    }
}

// Low Stock Report
$lowStockSql = '
    SELECT c.id, c.name, c.low_stock_threshold,
           COALESCE(SUM(a.quantity), 0) AS total_quantity,
           COUNT(a.id) AS asset_count
    FROM categories c
    LEFT JOIN assets a ON a.category_id = c.id AND a.status IN ("In Stock", "Available")
    GROUP BY c.id
    HAVING total_quantity < c.low_stock_threshold
    ORDER BY c.name
';
$lowStockCategories = $pdo->query($lowStockSql)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-graph-up-arrow me-2 text-gradient"></i>Reports</h1>
        <p>Generate and export asset reports</p>
    </div>
</div>

<!-- Report Type Tabs -->
<ul class="nav nav-pills mb-4 glass-card p-2" id="reportTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="location-tab" data-bs-toggle="pill" data-bs-target="#locationReport" type="button" role="tab">
            <i class="bi bi-geo-alt me-1"></i> Assets by Location
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="lowstock-tab" data-bs-toggle="pill" data-bs-target="#lowStockReport" type="button" role="tab">
            <i class="bi bi-exclamation-triangle me-1"></i> Low Stock Alert
        </button>
    </li>
</ul>

<div class="tab-content" id="reportTabsContent">
    <!-- Assets by Location -->
    <div class="tab-pane fade show active" id="locationReport" role="tabpanel">
        <div class="glass-card p-4 mb-4">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Select Location</label>
                    <select name="location_id" class="form-select glass-form-select">
                        <option value="">-- Select a location --</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= (int) $loc['id'] ?>" <?= selected($locationFilter, $loc['id']) ?>><?= h($loc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn glass-btn glass-btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>

        <?php if ($locationFilter > 0): ?>
        <div class="glass-card">
            <div class="p-4 border-bottom" style="border-color: var(--glass-border) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-geo-alt-fill me-2 text-primary"></i><?= h($locationName) ?></h4>
                        <p class="text-muted mb-0"><?= count($assetsByLocation) ?> assets found</p>
                    </div>
                    <div class="d-flex gap-2 no-print">
                        <button class="btn glass-btn glass-btn-outline" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                        <button class="btn glass-btn glass-btn-outline" onclick="exportToCSV('location')">
                            <i class="bi bi-download me-1"></i> CSV
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table glass-table mb-0" id="locationTable">
                    <thead>
                        <tr>
                            <th>Asset Name</th>
                            <th>Serial Number</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assetsByLocation): ?>
                            <?php foreach ($assetsByLocation as $asset): ?>
                            <tr>
                                <td><?= h($asset['name']) ?></td>
                                <td><code><?= h($asset['serial_number']) ?></code></td>
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
                                <td><strong><?= (int)$asset['quantity'] ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">No assets found for this location.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Low Stock Report -->
    <div class="tab-pane fade" id="lowStockReport" role="tabpanel">
        <div class="glass-card">
            <div class="p-4 border-bottom" style="border-color: var(--glass-border) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Low Stock Items</h4>
                        <p class="text-muted mb-0">Categories below minimum threshold</p>
                    </div>
                    <div class="d-flex gap-2 no-print">
                        <button class="btn glass-btn glass-btn-outline" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                        <button class="btn glass-btn glass-btn-outline" onclick="exportToCSV('lowstock')">
                            <i class="bi bi-download me-1"></i> CSV
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table glass-table mb-0" id="lowStockTable">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Current Quantity</th>
                            <th>Threshold</th>
                            <th>Assets Count</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lowStockCategories): ?>
                            <?php foreach ($lowStockCategories as $cat): ?>
                            <tr>
                                <td><strong><?= h($cat['name']) ?></strong></td>
                                <td><span class="text-danger fw-bold"><?= (int)$cat['total_quantity'] ?></span></td>
                                <td><?= (int)$cat['low_stock_threshold'] ?></td>
                                <td><?= (int)$cat['asset_count'] ?></td>
                                <td>
                                    <span class="glass-badge glass-badge-danger">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Low Stock
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                                    <p class="mt-2">All stock levels are adequate!</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function exportToCSV(type) {
    let table, filename;
    if (type === 'location') {
        table = document.getElementById('locationTable');
        filename = 'assets_by_location.csv';
    } else {
        table = document.getElementById('lowStockTable');
        filename = 'low_stock_report.csv';
    }
    
    if (!table) return;
    
    let csv = [];
    let rows = table.querySelectorAll('tr');
    
    for (let row of rows) {
        let cols = row.querySelectorAll('td, th');
        let rowData = [];
        for (let col of cols) {
            rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
        }
        csv.push(rowData.join(','));
    }
    
    let csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    let downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
