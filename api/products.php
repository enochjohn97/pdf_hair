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
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $prod = $stmt->fetch();
        jsonResp($prod ?: ['error' => 'Not found']);
    }

    $search = $_GET['search'] ?? '';
    $active = $_GET['active'] ?? null;
    $where = ['1=1'];
    $params = [];

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
    if (!canManageOrders()) {
        jsonResp(['error' => 'Only admin/manager can create products'], 403);
    }
    $data = body();
    $name = clean($data['name'] ?? '');
    if (!$name)
        jsonResp(['error' => 'Name is required'], 422);
    if (!isset($data['price']) || $data['price'] < 0)
        jsonResp(['error' => 'Valid price required'], 422);

    $stmt = $pdo->prepare(
        'INSERT INTO products (name, description, price, stock_qty, unit, low_stock_alert, is_active)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $name,
        clean($data['description'] ?? ''),
        (float) $data['price'],
        max(0, (int) ($data['stock_qty'] ?? 0)),
        clean($data['unit'] ?? 'piece'),
        max(0, (int) ($data['low_stock_alert'] ?? 5)),
        isset($data['is_active']) ? (int) $data['is_active'] : 1,
    ]);
    jsonResp(['success' => true, 'id' => (int) $pdo->lastInsertId()], 201);
}

if ($method === 'PUT' && $id) {
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
    if ($user['role'] !== 'admin')
        jsonResp(['error' => 'Forbidden'], 403);
    // Soft delete
    $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$id]);
    jsonResp(['success' => true]);
}
