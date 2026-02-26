<?php
require_once __DIR__ . '/includes/auth.php';

require_role(['admin']);

if (!is_post_request()) {
    redirect('assets.php');
}

require_valid_csrf_or_fail();

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid asset ID.');
    redirect('assets.php');
}

$pdo = db();

try {
    $stmt = $pdo->prepare('DELETE FROM assets WHERE id = :id');
    $stmt->execute(['id' => $id]);
    
    set_flash('success', 'Asset deleted successfully.');
} catch (PDOException $e) {
    set_flash('danger', 'Unable to delete asset. It may have associated records.');
}

redirect('assets.php');
