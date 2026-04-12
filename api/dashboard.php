<?php
// ============================================================
//  Pdf_Hair — Dashboard API  (api/dashboard.php)
//  GET /api/dashboard.php — RBAC aware stats
// ============================================================
require_once __DIR__ . '/../config/helpers.php';

$user = requireAuth();
$userId = $user['id'];
$global = hasPermission('dashboard.view.global');

try {
    $pdo = db();

    // ── Summary Cards ─────────────────────────────────────
    $totalOrdersStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL' . ($global ? '' : ' AND created_by = ?'));
    $totalOrdersStmt->execute($global ? [] : [$userId]);
    $totalOrders = $totalOrdersStmt->fetchColumn();

    $totalRevenueStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'" . ($global ? '' : ' AND created_by = ?'));
    $totalRevenueStmt->execute($global ? [] : [$userId]);
    $totalRevenue = $totalRevenueStmt->fetchColumn();

    $totalCustomers = $pdo->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL')->fetchColumn();
    $totalProducts = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1 AND deleted_at IS NULL')->fetchColumn();

    // Today's stats - own if staff
    $todayOrdersStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()" . ($global ? '' : ' AND created_by = ?'));
    $todayOrdersStmt->execute($global ? [] : [$userId]);
    $todayOrders = $todayOrdersStmt->fetchColumn();

    $todayRevenueStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status!='cancelled'" . ($global ? '' : ' AND created_by = ?'));
    $todayRevenueStmt->execute($global ? [] : [$userId]);
    $todayRevenue = $todayRevenueStmt->fetchColumn();

    // Pending orders count - own if staff
    $pendingOrdersStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='pending'" . ($global ? '' : ' AND created_by = ?'));
    $pendingOrdersStmt->execute($global ? [] : [$userId]);
    $pendingOrders = $pendingOrdersStmt->fetchColumn();

    // ── Order Status Breakdown ────────────────────────────
    $statusRows = $pdo->prepare(
        "SELECT status, COUNT(*) as count FROM orders WHERE deleted_at IS NULL" . ($global ? '' : ' AND created_by = ?') . " GROUP BY status"
    );
    $statusRows->execute($global ? [] : [$userId]);
    $statusBreakdown = [];
    foreach ($statusRows->fetchAll() as $r)
        $statusBreakdown[$r['status']] = (int) $r['count'];

    // ── Revenue Last 7 Days ───────────────────────────────
    $revenueChart = $pdo->prepare(
        "SELECT DATE(created_at) as day, COALESCE(SUM(total),0) as revenue, COUNT(*) as orders
         FROM orders
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
           AND status != 'cancelled'" . ($global ? '' : ' AND created_by = ?') . "
         GROUP BY DATE(created_at)
         ORDER BY day ASC"
    );
    $revenueChart->execute($global ? [] : [$userId]);
    $revenueChart = $revenueChart->fetchAll();

    // Fill missing days
    $revenueMap = [];
    foreach ($revenueChart as $r)
        $revenueMap[$r['day']] = $r;
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $days[] = [
            'day' => date('D', strtotime($d)),
            'date' => $d,
            'revenue' => isset($revenueMap[$d]) ? (float) $revenueMap[$d]['revenue'] : 0,
            'orders' => isset($revenueMap[$d]) ? (int) $revenueMap[$d]['orders'] : 0,
        ];
    }

    // ── Recent Orders ─────────────────────────────────────
    $recentOrders = $pdo->prepare(
        "SELECT o.id, o.order_number, o.customer_name, o.status, o.total,
                o.payment_status, o.created_at,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
         FROM orders o
         WHERE o.deleted_at IS NULL" . ($global ? '' : ' AND o.created_by = ?') . "
         ORDER BY o.created_at DESC
         LIMIT 10"
    );
    $recentOrders->execute($global ? [] : [$userId]);
    $recentOrders = $recentOrders->fetchAll();

    // ── Low Stock Alerts ──────────────────────────────────
    $lowStock = $pdo->query(
        "SELECT id, name, stock_qty, low_stock_alert, unit
         FROM products
         WHERE stock_qty <= low_stock_alert AND is_active = 1 AND deleted_at IS NULL
         ORDER BY stock_qty ASC
         LIMIT 5"
    )->fetchAll();

    // ── Top Products by Sales ─────────────────────────────
    $topProducts = $pdo->prepare(
        "SELECT oi.product_name, SUM(oi.quantity) as qty_sold, SUM(oi.line_total) as revenue
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.status != 'cancelled' AND o.deleted_at IS NULL" . ($global ? '' : ' AND o.created_by = ?') . "
         GROUP BY oi.product_name
         ORDER BY revenue DESC
         LIMIT 5"
    );
    $topProducts->execute($global ? [] : [$userId]);
    $topProducts = $topProducts->fetchAll();

    jsonResp([
        'summary' => [
            'total_orders' => (int) $totalOrders,
            'total_revenue' => (float) $totalRevenue,
            'total_customers' => (int) $totalCustomers,
            'total_products' => (int) $totalProducts,
            'today_orders' => (int) $todayOrders,
            'today_revenue' => (float) $todayRevenue,
            'pending_orders' => (int) $pendingOrders,
        ],
        'status_breakdown' => $statusBreakdown,
        'revenue_chart' => $days,
        'recent_orders' => $recentOrders,
        'low_stock' => $lowStock,
        'top_products' => $topProducts,
    ]);

} catch (PDOException $e) {
    jsonResp(['error' => 'Database error: ' . $e->getMessage()], 500);
}
?>