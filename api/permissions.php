<?php
// ============================================================
//  Pdf_Hair — Permissions API  (api/permissions.php)
// Admin only: View / assign permissions to users
// ============================================================
require_once __DIR__ . '/../config/helpers.php';

$user = requireAuth();
if (!hasPermission('user.update') && !hasPermission('dashboard.system.logs')) {
    jsonResp(['error' => 'Admin access required'], 403);
}

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;

if ($method === 'GET') {
    if ($userId) {
        // User's permissions
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.description FROM permissions p 
                               INNER JOIN user_permissions up ON p.id = up.permission_id 
                               WHERE up.user_id = ? ORDER BY p.name");
        $stmt->execute([$userId]);
        jsonResp(['data' => $stmt->fetchAll()]);
    } else {
        // All permissions list
        $stmt = $pdo->query("SELECT * FROM permissions ORDER BY name");
        jsonResp(['data' => $stmt->fetchAll()]);
    }
}

if ($method === 'POST' && $userId) {
    // Assign perms to user (admin only)
    if (!hasPermission('user.update')) {
        jsonResp(['error' => 'Insufficient permissions'], 403);
    }
    $data = body();
    $perms = $data['permissions'] ?? []; // array of perm names

    $pdo->beginTransaction();

    // Clear existing
    $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);

    // Add new
    $permStmt = $pdo->prepare('INSERT IGNORE INTO user_permissions (user_id, permission_id) VALUES (?, (SELECT id FROM permissions WHERE name = ?))');
    foreach ($perms as $perm) {
        $permStmt->execute([$userId, $perm]);
    }

    $pdo->commit();

    // Invalidate session cache
    unset($_SESSION['permissions']);

    jsonResp(['success' => true]);
}
?>