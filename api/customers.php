<?php
// ============================================================
//  Pdf_Hair — Customers API  (api/customers.php)
// ============================================================
require_once __DIR__ . '/../config/helpers.php';

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$pdo = db();

if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $cust = $stmt->fetch();
        if (!$cust)
            jsonResp(['error' => 'Not found'], 404);

        $orders = $pdo->prepare(
            'SELECT id, order_number, status, total, created_at FROM orders
             WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20'
        );
        $orders->execute([$id]);
        $cust['orders'] = $orders->fetchAll();
        jsonResp($cust);
    }

    $search = $_GET['search'] ?? '';
    $limit = min(200, max(10, (int) ($_GET['limit'] ?? 50)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    // Staff search only
    if (empty($search) && hasRole(['staff'])) {
        jsonResp(['data' => [], 'total' => 0, 'message' => 'Search required for staff']);
        return;
    }

    // Dropdown mode for order forms (staff/manager can see more)
    $isDropdown = !empty($_GET['dropdown']);
    if ($isDropdown || $search) {
        $s = $isDropdown ? '' : "%$search%";
        $stmt = $pdo->prepare(
            'SELECT *, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) as order_count
             FROM customers c WHERE ' . ($isDropdown ? '1=1' : '(name LIKE ? OR email LIKE ? OR phone LIKE ?)') . '
             AND c.deleted_at IS NULL
             ORDER BY name LIMIT ? OFFSET ?'
        );
        if ($isDropdown) {
            $stmt->execute([$limit, $offset]);
        } else {
            $stmt->execute([$s, $s, $s, $limit, $offset]);
        }
    } else {
        // Staff: require search unless dropdown
        if (hasRole(['staff']) && !$isDropdown) {
            jsonResp(['data' => [], 'total' => 0, 'message' => 'Search required for staff']);
            return;
        }
        $stmt = $pdo->prepare(
            'SELECT *, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) as order_count
             FROM customers c WHERE deleted_at IS NULL ORDER BY name LIMIT ? OFFSET ?'
        );
        $stmt->execute([$limit, $offset]);
    }

    $total = $pdo->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL')->fetchColumn();
    jsonResp(['data' => $stmt->fetchAll(), 'total' => (int) $total]);
}

if ($method === 'POST') {
    if (!hasPermission('customer.create')) {
        jsonResp(['error' => 'Insufficient permissions to create customers'], 403);
    }
    $data = body();
    $name = clean($data['name'] ?? '');
    if (!$name)
        jsonResp(['error' => 'Name is required'], 422);

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
    $newId = (int) $pdo->lastInsertId();

    // Notify admin/manager
    $notifyStmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin','manager') AND is_active=1");
    $notifyStmt->execute();
    $targets = $notifyStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($targets as $targetId) {
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, ?, ?)")
            ->execute([$targetId, "New Customer: $name", "New customer created by " . $user['name'], 'customer_created', $newId]);
    }

    jsonResp(['success' => true, 'id' => $newId], 201);
}

if ($method === 'PUT' && $id) {
    if (!hasPermission('customer.update.all')) {
        jsonResp(['error' => 'Insufficient permissions to update customers'], 403);
    }
    $data = body();
    $pdo->prepare(
        'UPDATE customers SET name=?, email=?, phone=?, address=?, notes=? WHERE id=?'
    )->execute([
                clean($data['name'] ?? ''),
                clean($data['email'] ?? ''),
                clean($data['phone'] ?? ''),
                clean($data['address'] ?? ''),
                clean($data['notes'] ?? ''),
                $id,
            ]);
    jsonResp(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    if ($user['role'] !== 'admin')
        jsonResp(['error' => 'Forbidden'], 403);
    $pdo->prepare('UPDATE customers SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    jsonResp(['success' => true]);
}
