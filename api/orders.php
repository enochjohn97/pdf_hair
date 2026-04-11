<?php
// ============================================================
//  Pdf_Hair — Orders API  (api/orders.php)
//  GET    ?id=X           — single order with items
//  GET    (no id)         — list with filters
//  POST                   — create order
//  PUT    ?id=X           — update order
//  PATCH  ?id=X&action=status  — change status only
//  DELETE ?id=X           — delete order
// ============================================================
require_once __DIR__ . '/../config/helpers.php';

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = $_GET['action'] ?? null;
$pdo = db();

// Role-based access helpers available via helpers.php

// ── GET ───────────────────────────────────────────────────
if ($method === 'GET') {

    if ($id) {
        // Single order with items
        $stmt = $pdo->prepare(
            "SELECT o.*, u.name as creator_name
             FROM orders o
             LEFT JOIN users u ON u.id = o.created_by
             WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order)
            jsonResp(['error' => 'Order not found'], 404);

        $items = $pdo->prepare(
            'SELECT * FROM order_items WHERE order_id = ? ORDER BY id'
        );
        $items->execute([$id]);
        $order['items'] = $items->fetchAll();

        // Timeline / activity
        $log = $pdo->prepare(
            "SELECT al.*, u.name as user_name FROM activity_log al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.entity_type = 'order' AND al.entity_id = ?
             ORDER BY al.created_at DESC"
        );
        $log->execute([$id]);
        $order['timeline'] = $log->fetchAll();

        jsonResp($order);
    }

    // List orders
    $where = ['1=1'];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = 'o.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['payment_status'])) {
        $where[] = 'o.payment_status = ?';
        $params[] = $_GET['payment_status'];
    }
    if (!empty($_GET['search'])) {
        $s = '%' . $_GET['search'] . '%';
        $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.notes LIKE ?)';
        $params = array_merge($params, [$s, $s, $s]);
    }
    if (!empty($_GET['date_from'])) {
        $where[] = 'DATE(o.created_at) >= ?';
        $params[] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 'DATE(o.created_at) <= ?';
        $params[] = $_GET['date_to'];
    }

    $whereSQL = implode(' AND ', $where);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $sort = in_array($_GET['sort'] ?? '', ['created_at', 'total', 'customer_name', 'status'])
        ? $_GET['sort'] : 'created_at';
    $dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $whereSQL");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT o.id, o.order_number, o.customer_id, o.customer_name, o.status,
                o.subtotal, o.discount, o.tax_amount, o.total,
                o.payment_status, o.payment_method, o.created_at, o.updated_at,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
         FROM orders o
         WHERE $whereSQL
         ORDER BY o.$sort $dir
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    jsonResp([
        'data' => $orders,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => (int) ceil($total / $limit),
    ]);
}

// ── POST — Create ─────────────────────────────────────────
if ($method === 'POST') {
    $data = body();
    $items = $data['items'] ?? [];

    if (empty($items))
        jsonResp(['error' => 'Order must have at least one item'], 422);

    $customerName = clean($data['customer_name'] ?? '');
    if (!$customerName)
        jsonResp(['error' => 'Customer name is required'], 422);

    // Duplicate detection: same customer + same items in last 5 minutes
    if (!empty($data['customer_id'])) {
        $dupCheck = $pdo->prepare(
            "SELECT id, order_number FROM orders
             WHERE customer_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             ORDER BY created_at DESC LIMIT 1"
        );
        $dupCheck->execute([$data['customer_id']]);
        $dup = $dupCheck->fetch();
        if ($dup && empty($data['force'])) {
            jsonResp([
                'error' => 'Possible duplicate order detected',
                'duplicate' => $dup,
                'require_force' => true,
            ], 409);
        }
    }

    // Calculate totals
    $subtotal = 0;
    foreach ($items as &$item) {
        $item['line_total'] = round((float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0), 2);
        $subtotal += $item['line_total'];
    }
    unset($item);

    $discount = (float) ($data['discount'] ?? 0);
    $taxRate = (float) ($data['tax_rate'] ?? 0);
    $taxAmount = round(($subtotal - $discount) * $taxRate / 100, 2);
    $total = round($subtotal - $discount + $taxAmount, 2);
    $orderNum = generateOrderNumber();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO orders (order_number, customer_id, customer_name, status,
             subtotal, discount, tax_rate, tax_amount, total,
             payment_status, payment_method, notes, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $orderNum,
            $data['customer_id'] ?? null,
            $customerName,
            $data['status'] ?? 'pending',
            $subtotal,
            $discount,
            $taxRate,
            $taxAmount,
            $total,
            $data['payment_status'] ?? 'unpaid',
            clean($data['payment_method'] ?? ''),
            clean($data['notes'] ?? ''),
            $user['id'],
        ]);
        $orderId = (int) $pdo->lastInsertId();

        // Insert items
        $iStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, line_total, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $iStmt->execute([
                $orderId,
                $item['product_id'] ?? null,
                clean($item['product_name'] ?? ''),
                (float) ($item['quantity'] ?? 1),
                (float) ($item['unit_price'] ?? 0),
                $item['line_total'],
                clean($item['notes'] ?? ''),
            ]);
            // Reduce stock
            if (!empty($item['product_id'])) {
                $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty > 0')
                    ->execute([(float) $item['quantity'], $item['product_id']]);
            }
        }

        $pdo->commit();
        logActivity($user['id'], 'created', 'order', $orderId, "Order $orderNum created");

        jsonResp(['success' => true, 'id' => $orderId, 'order_number' => $orderNum], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResp(['error' => 'Failed to create order: ' . $e->getMessage()], 500);
    }
}

// ── PUT — Update ──────────────────────────────────────────
if ($method === 'PUT' && $id) {
    $data = body();

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order)
        jsonResp(['error' => 'Order not found'], 404);

    $items = $data['items'] ?? null;
    $subtotal = 0;

    if ($items !== null) {
        foreach ($items as &$item) {
            $item['line_total'] = round((float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0), 2);
            $subtotal += $item['line_total'];
        }
        unset($item);
    } else {
        $subtotal = (float) $order['subtotal'];
    }

    $discount = isset($data['discount']) ? (float) $data['discount'] : (float) $order['discount'];
    $taxRate = isset($data['tax_rate']) ? (float) $data['tax_rate'] : (float) $order['tax_rate'];
    $taxAmount = round(($subtotal - $discount) * $taxRate / 100, 2);
    $total = round($subtotal - $discount + $taxAmount, 2);

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            "UPDATE orders SET customer_id=?, customer_name=?, status=?,
             subtotal=?, discount=?, tax_rate=?, tax_amount=?, total=?,
             payment_status=?, payment_method=?, notes=?, updated_at=NOW()
             WHERE id=?"
        )->execute([
                    $data['customer_id'] ?? $order['customer_id'],
                    clean($data['customer_name'] ?? $order['customer_name']),
                    $data['status'] ?? $order['status'],
                    $subtotal,
                    $discount,
                    $taxRate,
                    $taxAmount,
                    $total,
                    $data['payment_status'] ?? $order['payment_status'],
                    clean($data['payment_method'] ?? $order['payment_method'] ?? ''),
                    clean($data['notes'] ?? $order['notes'] ?? ''),
                    $id,
                ]);

        if ($items !== null) {
            $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
            $iStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, line_total, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($items as $item) {
                $iStmt->execute([
                    $id,
                    $item['product_id'] ?? null,
                    clean($item['product_name'] ?? ''),
                    (float) ($item['quantity'] ?? 1),
                    (float) ($item['unit_price'] ?? 0),
                    $item['line_total'],
                    clean($item['notes'] ?? ''),
                ]);
            }
        }

        $pdo->commit();
        logActivity($user['id'], 'updated', 'order', $id, "Order {$order['order_number']} updated");
        jsonResp(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResp(['error' => $e->getMessage()], 500);
    }
}

// ── PATCH — Status Update ─────────────────────────────────
if ($method === 'PATCH' && $id && $action === 'status') {
    $data = body();
    $status = $data['status'] ?? '';
    $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

    if (!in_array($status, $validStatuses))
        jsonResp(['error' => 'Invalid status'], 422);

    $stmt = $pdo->prepare('SELECT order_number FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order)
        jsonResp(['error' => 'Not found'], 404);

    $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$status, $id]);

    logActivity(
        $user['id'],
        'status_change',
        'order',
        $id,
        "Status changed to $status on order {$order['order_number']}"
    );

    jsonResp(['success' => true, 'status' => $status]);
}

// ── DELETE ────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    if ($user['role'] !== 'admin')
        jsonResp(['error' => 'Forbidden'], 403);

    $stmt = $pdo->prepare('SELECT order_number FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order)
        jsonResp(['error' => 'Not found'], 404);

    $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
    logActivity($user['id'], 'deleted', 'order', $id, "Order {$order['order_number']} deleted");
    jsonResp(['success' => true]);
}
