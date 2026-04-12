<?php
// ============================================================
//  Pdf_Hair — Notifications API (api/notifications.php)
// ============================================================
require_once __DIR__ . '/../config/helpers.php';

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$pdo = db();

if ($method === 'GET') {
    // List unread notifications (recent 30 days)
    $stmt = $pdo->prepare(
        'SELECT * FROM notifications 
         WHERE user_id = ? AND is_read = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY created_at DESC LIMIT 20'
    );
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();

    // Count badge
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $countStmt->execute([$user['id']]);
    $unreadCount = (int) $countStmt->fetchColumn();

    jsonResp([
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);
}

if ($method === 'POST' && $action === 'mark-read') {
    $data = body();
    $ids = $data['ids'] ?? [];

    if (empty($ids))
        jsonResp(['error' => 'No notification IDs provided'], 422);

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?");
    $stmt->execute(array_merge($ids, [$user['id']]));

    jsonResp(['success' => true, 'marked' => $stmt->rowCount()]);
}

if ($method === 'POST' && $action === 'mark-all-read') {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    jsonResp(['success' => true]);
}

