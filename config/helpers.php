<?php
// ============================================================
//  OrderPro — Session / Auth / Security Helpers
// ============================================================
require_once __DIR__ . '/database.php';

function bootSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400 * 7,
            'path'     => '/',
            'secure'   => false,       // set TRUE on HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function requireAuth(): array {
    bootSession();
    if (empty($_SESSION['user_id'])) {
        jsonResp(['error' => 'Unauthorized'], 401);
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

function currentUser(): ?array {
    bootSession();
    if (empty($_SESSION['user_id'])) return null;
    return ['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'], 'role' => $_SESSION['user_role']];
}

function jsonResp(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string {
    bootSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validateCsrf(string $token): bool {
    bootSession();
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function generateOrderNumber(): string {
    $prefix = 'ORD';
    $date   = date('Ymd');
    $rand   = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    return "$prefix-$date-$rand";
}

function logActivity(int $userId, string $action, string $entity, int $entityId, string $detail = ''): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $action, $entity, $entityId, $detail]);
    } catch (Exception) { /* non-fatal */ }
}
