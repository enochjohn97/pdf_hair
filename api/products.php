<?php
// ============================================================
//  Pdf_Hair — Products API  (api/products.php)
// ============================================================
require_once __DIR__ . '/../config/helpers.php';

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$pdo = db();

if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $prod = $stmt->fetch();
        jsonResp($prod ?: ['error' => 'Not found']);
    }

    $search = $_GET['search'] ?? '';
    $active = $_GET['active'] ?? null;
    $managerId = isset($_GET['manager_id']) ? (int)$_GET['manager_id'] : getManagerId();

    $where = ['deleted_at IS NULL'];
    $params = [];

    if ($managerId) {
        $where[] = 'manager_id = ?';
        $params[] = $managerId;
    }

    // Dropdown mode for order forms
    $isDropdown = !empty($_GET['dropdown']);
    if ($isDropdown) {
        $where[] = 'is_active = 1';
    } else if (empty($search) && hasRole(['staff'])) {
        jsonResp(['data' => []]);
        return;
    }

    if ($search) {
        $where[] = 'name LIKE ?';
        $params[] = "%$search%";
    }
    if ($active !== null) {
        $where[] = 'is_active = ?';
        $params[] = (int) $active;
    }

    $whereSQL = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE $whereSQL ORDER BY name");
    $stmt->execute($params);
    jsonResp(['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrf($token)) {
        jsonResp(['error' => 'Invalid security token'], 403);
    }
    if (!hasPermission('product.create')) {
        jsonResp(['error' => 'Insufficient permissions to create products'], 403);
    }
    $data = body();
    $name = clean($data['name'] ?? '');
    if (!$name)
        jsonResp(['error' => 'Name is required'], 422);
    if (!isset($data['price']) || $data['price'] < 0)
        jsonResp(['error' => 'Valid price required'], 422);

    $managerId = getManagerId();
    $stmt = $pdo->prepare(
        'INSERT INTO products (name, description, price, stock_qty, unit, low_stock_alert, is_active, manager_id)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $name,
        clean($data['description'] ?? ''),
        (float) $data['price'],
        max(0, (int) ($data['stock_qty'] ?? 0)),
        clean($data['unit'] ?? 'piece'),
        max(0, (int) ($data['low_stock_alert'] ?? 5)),
        isset($data['is_active']) ? (int) $data['is_active'] : 1,
        $managerId,
    ]);
    $newId = (int) $pdo->lastInsertId();

    // Notify admin/manager/staff
    $notifyStmt = $pdo->prepare("SELECT id FROM users WHERE role != 'admin' AND is_active=1"); // Managers + Staff
    $notifyStmt->execute();
    $targets = $notifyStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($targets as $targetId) {
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, ?, ?)")
            ->execute([$targetId, "New Product: $name", "New product added by " . $user['name'] . " - ₦" . number_format($data['price'], 2), 'product_created', $newId]);
    }

    jsonResp(['success' => true, 'id' => $newId], 201);
}

if ($method === 'PUT' && $id) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrf($token)) {
        jsonResp(['error' => 'Invalid security token'], 403);
    }
    if (!hasPermission('product.update.all')) {
        jsonResp(['error' => 'Insufficient permissions to update products'], 403);
    }
    $data = body();
    $pdo->prepare(
        'UPDATE products SET name=?, description=?, price=?, stock_qty=?, unit=?,
         low_stock_alert=?, is_active=? WHERE id=?'
    )->execute([
                clean($data['name'] ?? ''),
                clean($data['description'] ?? ''),
                (float) ($data['price'] ?? 0),
                max(0, (int) ($data['stock_qty'] ?? 0)),
                clean($data['unit'] ?? 'piece'),
                max(0, (int) ($data['low_stock_alert'] ?? 5)),
                isset($data['is_active']) ? (int) $data['is_active'] : 1,
                $id,
            ]);
    jsonResp(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrf($token)) {
        jsonResp(['error' => 'Invalid security token'], 403);
    }
    if (!canDelete('product')) {
        jsonResp(['error' => 'Insufficient permissions'], 403);
    }
    // Soft delete
    $pdo->prepare('UPDATE products SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    jsonResp(['success' => true]);
}
