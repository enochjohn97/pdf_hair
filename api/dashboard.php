<?php
// ============================================================
//  Pdf_Hair — Dashboard API  (api/dashboard.php)
//  GET /api/dashboard.php
// ============================================================
require_once __DIR__ . '/../config/helpers.php';

$user = requireAuth();

try {
    $pdo = db();

    // ── Summary Cards ─────────────────────────────────────
    $totalOrders    = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $totalRevenue   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
    $totalCustomers = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $totalProducts  = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();

    // Today's stats
    $todayOrders  = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $todayRevenue = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status!='cancelled'")->fetchColumn();

    // Pending orders count
    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();

    // ── Order Status Breakdown ────────────────────────────
    $statusRows = $pdo->query(
        "SELECT status, COUNT(*) as count FROM orders GROUP BY status"
    )->fetchAll();
    $statusBreakdown = [];
    foreach ($statusRows as $r) $statusBreakdown[$r['status']] = (int)$r['count'];

    // ── Revenue Last 7 Days ───────────────────────────────
    $revenueChart = $pdo->query(
        "SELECT DATE(created_at) as day, COALESCE(SUM(total),0) as revenue, COUNT(*) as orders
         FROM orders
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
           AND status != 'cancelled'
         GROUP BY DATE(created_at)
         ORDER BY day ASC"
    )->fetchAll();

    // Fill missing days
    $revenueMap = [];
    foreach ($revenueChart as $r) $revenueMap[$r['day']] = $r;
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $days[] = [
            'day'     => date('D', strtotime($d)),
            'date'    => $d,
            'revenue' => isset($revenueMap[$d]) ? (float)$revenueMap[$d]['revenue'] : 0,
            'orders'  => isset($revenueMap[$d]) ? (int)$revenueMap[$d]['orders']    : 0,
        ];
    }

    // ── Recent Orders ─────────────────────────────────────
    $recentOrders = $pdo->query(
        "SELECT o.id, o.order_number, o.customer_name, o.status, o.total,
                o.payment_status, o.created_at,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
         FROM orders o
         ORDER BY o.created_at DESC
         LIMIT 10"
    )->fetchAll();

    // ── Low Stock Alerts ──────────────────────────────────
    $lowStock = $pdo->query(
        "SELECT id, name, stock_qty, low_stock_alert, unit
         FROM products
         WHERE stock_qty <= low_stock_alert AND is_active = 1
         ORDER BY stock_qty ASC
         LIMIT 5"
    )->fetchAll();

    // ── Top Products by Sales ─────────────────────────────
    $topProducts = $pdo->query(
        "SELECT oi.product_name, SUM(oi.quantity) as qty_sold, SUM(oi.line_total) as revenue
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.status != 'cancelled'
         GROUP BY oi.product_name
         ORDER BY revenue DESC
         LIMIT 5"
    )->fetchAll();

    jsonResp([
        'summary' => [
            'total_orders'    => (int)$totalOrders,
            'total_revenue'   => (float)$totalRevenue,
            'total_customers' => (int)$totalCustomers,
            'total_products'  => (int)$totalProducts,
            'today_orders'    => (int)$todayOrders,
            'today_revenue'   => (float)$todayRevenue,
            'pending_orders'  => (int)$pendingOrders,
        ],
        'status_breakdown' => $statusBreakdown,
        'revenue_chart'    => $days,
        'recent_orders'    => $recentOrders,
        'low_stock'        => $lowStock,
        'top_products'     => $topProducts,
    ]);

} catch (PDOException $e) {
    jsonResp(['error' => 'Database error: ' . $e->getMessage()], 500);
}
