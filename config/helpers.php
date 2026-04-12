<?php
// ============================================================
//  Pdf_Hair — Session / Auth / Security Helpers
// ============================================================
require_once __DIR__ . '/database.php';

function bootSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400 * 7,
            'path' => '/',
            'secure' => filter_var($_ENV['SESSION_SECURE'] ?? getenv('SESSION_SECURE') ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Debug logging for 401 troubleshooting
        if (isset($_GET['debug_session'])) {
            error_log("SESSION DEBUG: ID=" . session_id() . ", UserID=" . ($_SESSION['user_id'] ?? 'null'));
        }
    }
}

function requireAuth(): array
{
    bootSession();
    if (empty($_SESSION['user_id'])) {
        jsonResp(['error' => 'Unauthorized'], 401);
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
    ];
}

function currentUser(): ?array
{
    bootSession();
    if (empty($_SESSION['user_id']))
        return null;

    $role = $_SESSION['temp_role'] ?? $_SESSION['user_role'];

    if (!isset($_SESSION['permissions'])) {
        $_SESSION['permissions'] = getUserPermissions($_SESSION['user_id']);
    }

    // If temp_role is active, use temp_permissions instead
    $permissions = $_SESSION['permissions'];
    if (!empty($_SESSION['temp_role']) && isset($_SESSION['temp_permissions'])) {
        $permissions = $_SESSION['temp_permissions'];
    }

    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $role,
        'permissions' => $permissions
    ];
}

function jsonResp(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function body(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function clean(string $v): string
{
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    bootSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validateCsrf(string $token): bool
{
    bootSession();
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function generateOrderNumber(): string
{
    $prefix = 'ORD';
    $date = date('Ymd');
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    return "$prefix-$date-$rand";
}

function logActivity(int $userId, string $action, string $entity, int $entityId, string $detail = ''): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $action, $entity, $entityId, $detail]);

        // Auto-generate notification for relevant users
        if (in_array($entity, ['order'])) {
            createNotification($userId, $action, $entity, $entityId, $detail);
        }
    } catch (Exception) { /* non-fatal */
    }
}

function createNotification(int $userId, string $action, string $entity, int $entityId, string $detail = ''): void
{
    try {
        $pdo = db();
        $title = match ($action) {
            'login' => 'User logged in',
            'logout' => 'User logged out',
            'created' => 'New order created',
            'status_change' => 'Order status updated',
            default => ucfirst($action) . ' activity'
        };

        $message = $detail ?: "Activity on {$entity} #{$entityId}";
        $type = match ($entity) {
            'order' => 'order_status',
            default => 'order_created'
        };

        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type, related_id, is_read)
             VALUES (?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([$userId, $title, $message, $type, $entityId]);
    } catch (Exception $e) {
        // Table may not exist yet
    }
}

function getUserPermissions(int $userId): array
{
    static $cache = [];
    if (!isset($cache[$userId])) {
        $stmt = db()->prepare("SELECT p.name FROM permissions p INNER JOIN user_permissions up ON p.id = up.permission_id WHERE up.user_id = ?");
        $stmt->execute([$userId]);
        $cache[$userId] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $cache[$userId];
}

function hasPermission(string $permission): bool
{
    $user = currentUser();
    return $user && in_array($permission, $user['permissions'] ?? []);
}

function hasRole(array $allowedRoles): bool
{
    $user = currentUser();
    if (!$user)
        return false;
    return in_array($user['role'], $allowedRoles);
}

function canManageOrders(): bool
{
    return hasPermission('customer.create') || hasPermission('product.create') || hasPermission('order.create') || hasRole(['admin', 'manager']);
}

function canApproveOrders(): bool
{
    return hasRole(['admin']);
}

function canEditEntity(string $entity): bool
{
    $user = currentUser();
    if (!$user || $user['role'] === 'admin')
        return true;
    if ($user['role'] === 'manager' && $entity === 'orders')
        return true;
    return false;
}

function canListAll(string $entity): bool
{
    return hasRole(['admin', 'manager']);
}

function canDelete(string $entity): bool
{
    return hasPermission($entity . '.delete') || hasRole(['admin']);
}

function canEditOrder(array $order): bool
{
    $user = currentUser();
    if (!$user || (!hasPermission('order.update.own') && !hasPermission('order.update.all')))
        return false;

    if ($user['role'] === 'admin' || hasPermission('order.override.lock'))
        return true;
    if ($user['role'] === 'manager') {
        return $order['locked_by_manager'] == 0;
    }
    if ($user['role'] === 'staff') {
        return $order['created_by'] == $user['id']
            && $order['locked_by_manager'] == 0
            && $order['status'] == 'pending';
    }
    return false;
}

function canChangeStatus(array $order, string $newStatus): bool
{
    $user = currentUser();
    if (!$user)
        return false;

    if ($user['role'] === 'admin')
        return true;
    if ($user['role'] === 'manager')
        return true;
    if ($user['role'] === 'staff') {
        return $order['status'] == 'pending';
    }
    return false;
}


