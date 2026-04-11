<?php
// ============================================================
//  Pdf_Hair — Export API  (api/export.php)
//  GET ?type=orders|customers|products[&status=X&date_from=Y&date_to=Z]
// ============================================================
require_once __DIR__ . '/../config/helpers.php';

$user = requireAuth();
$type = $_GET['type'] ?? 'orders';
$pdo  = db();

header('Content-Type: text/csv; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function outputCsv(array $rows, array $headers): void {
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, $headers);
    foreach ($rows as $row) fputcsv($out, array_values($row));
    fclose($out);
}

switch ($type) {

    case 'orders':
        header('Content-Disposition: attachment; filename="orders_' . date('Ymd_His') . '.csv"');

        $where  = ['1=1'];
        $params = [];
        if (!empty($_GET['status']))    { $where[] = 'status = ?';            $params[] = $_GET['status']; }
        if (!empty($_GET['date_from'])) { $where[] = 'DATE(created_at) >= ?'; $params[] = $_GET['date_from']; }
        if (!empty($_GET['date_to']))   { $where[] = 'DATE(created_at) <= ?'; $params[] = $_GET['date_to']; }

        $whereSQL = implode(' AND ', $where);
        $stmt = $pdo->prepare(
            "SELECT order_number as 'Order #', customer_name as 'Customer',
             status as 'Status', subtotal as 'Subtotal', discount as 'Discount',
             tax_amount as 'Tax', total as 'Total',
             payment_status as 'Payment', payment_method as 'Pay Method',
             notes as 'Notes', created_at as 'Date'
             FROM orders WHERE $whereSQL ORDER BY created_at DESC"
        );
        $stmt->execute($params);
        outputCsv($stmt->fetchAll(), [
            'Order #','Customer','Status','Subtotal','Discount','Tax','Total',
            'Payment','Pay Method','Notes','Date'
        ]);
        break;

    case 'customers':
        header('Content-Disposition: attachment; filename="customers_' . date('Ymd_His') . '.csv"');
        $rows = $pdo->query(
            "SELECT c.name, c.email, c.phone, c.address,
             COUNT(o.id) as orders,
             COALESCE(SUM(o.total),0) as total_spent,
             c.created_at
             FROM customers c LEFT JOIN orders o ON o.customer_id = c.id
             GROUP BY c.id ORDER BY c.name"
        )->fetchAll();
        outputCsv($rows, ['Name','Email','Phone','Address','Orders','Total Spent','Joined']);
        break;

    case 'products':
        header('Content-Disposition: attachment; filename="products_' . date('Ymd_His') . '.csv"');
        $rows = $pdo->query(
            "SELECT name, description, price, stock_qty, unit, low_stock_alert,
             IF(is_active,'Yes','No') as active, created_at
             FROM products ORDER BY name"
        )->fetchAll();
        outputCsv($rows, ['Name','Description','Price','Stock','Unit','Low Stock Alert','Active','Created']);
        break;

    default:
        jsonResp(['error' => 'Invalid export type'], 400);
}
