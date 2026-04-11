<?php
// ============================================================
//  Pdf_Hair — Customers API  (api/customers.php)
// ============================================================
require_once __DIR__ . '/../config/helpers.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$pdo    = db();

if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $cust = $stmt->fetch();
        if (!$cust) jsonResp(['error' => 'Not found'], 404);

        $orders = $pdo->prepare(
            'SELECT id, order_number, status, total, created_at FROM orders
             WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20'
        );
        $orders->execute([$id]);
        $cust['orders'] = $orders->fetchAll();
        jsonResp($cust);
    }

    $search = $_GET['search'] ?? '';
    $limit  = min(200, max(10, (int)($_GET['limit'] ?? 50)));
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    if ($search) {
        $s    = "%$search%";
        $stmt = $pdo->prepare(
            'SELECT *, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) as order_count
             FROM customers c WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?
             ORDER BY name LIMIT ? OFFSET ?'
        );
        $stmt->execute([$s, $s, $s, $limit, $offset]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT *, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) as order_count
             FROM customers c ORDER BY name LIMIT ? OFFSET ?'
        );
        $stmt->execute([$limit, $offset]);
    }

    $total = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    jsonResp(['data' => $stmt->fetchAll(), 'total' => (int)$total]);
}

if ($method === 'POST') {
    $data = body();
    $name = clean($data['name'] ?? '');
    if (!$name) jsonResp(['error' => 'Name is required'], 422);

    $stmt = $pdo->prepare(
        'INSERT INTO customers (name, email, phone, address, notes) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([
        $name,
        clean($data['email'] ?? ''),
        clean($data['phone'] ?? ''),
        clean($data['address'] ?? ''),
        clean($data['notes'] ?? ''),
    ]);
    jsonResp(['success' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}

if ($method === 'PUT' && $id) {
    $data = body();
    $pdo->prepare(
        'UPDATE customers SET name=?, email=?, phone=?, address=?, notes=? WHERE id=?'
    )->execute([
        clean($data['name'] ?? ''), clean($data['email'] ?? ''),
        clean($data['phone'] ?? ''), clean($data['address'] ?? ''),
        clean($data['notes'] ?? ''), $id,
    ]);
    jsonResp(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    if ($user['role'] !== 'admin') jsonResp(['error' => 'Forbidden'], 403);
    $pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
    jsonResp(['success' => true]);
}
