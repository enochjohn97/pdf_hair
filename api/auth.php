<?php
// ============================================================
//  Pdf_Hair — Auth API  (api/auth.php)
//  POST /api/auth.php?action=login   { email, password }
//  POST /api/auth.php?action=logout
//  GET  /api/auth.php?action=me
// ============================================================
require_once __DIR__ . '/../config/helpers.php';
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ("$method:$action") {

    // ── LOGIN ──────────────────────────────────────────────
    case 'POST:login':
        $data = body();
        $email = trim($data['email'] ?? '');
        $pass = $data['password'] ?? '';

        if (!$email || !$pass)
            jsonResp(['error' => 'Email and password are required'], 422);

        try {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($pass, $user['password_hash'])) {
                jsonResp(['error' => 'Invalid credentials'], 401);
            }

            // Update last_login
            db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
                ->execute([$user['id']]);

            bootSession();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            jsonResp([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ],
                'csrf' => csrfToken(),
            ]);
        } catch (PDOException $e) {
            jsonResp(['error' => 'Database error'], 500);
        }
        break;

    // ── LOGOUT ─────────────────────────────────────────────
    case 'POST:logout':
        bootSession();
        session_destroy();
        jsonResp(['success' => true]);
        break;

    // ── CURRENT USER ───────────────────────────────────────
    case 'GET:me':
        bootSession();
        $user = currentUser();
        if (!$user)
            jsonResp(['error' => 'Not authenticated'], 401);
        jsonResp(['user' => $user, 'csrf' => csrfToken()]);
        break;

    // ── CHANGE PASSWORD ────────────────────────────────────
    case 'POST:change-password':
        $user = requireAuth();
        $data = body();
        $old = $data['old_password'] ?? '';
        $new = $data['new_password'] ?? '';

        if (strlen($new) < 8)
            jsonResp(['error' => 'New password must be at least 8 characters'], 422);

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($old, $row['password_hash'])) {
            jsonResp(['error' => 'Current password is incorrect'], 401);
        }

        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
        jsonResp(['success' => true, 'message' => 'Password changed successfully']);
        break;

    // ── ROLE SWITCH (Admin Only) ───────────────────────────────
    case 'POST:switch-role':
        $user = requireAuth();
        if ($user['role'] !== 'admin') {
            jsonResp(['error' => 'Admin access only'], 403);
        }
        $data = body();
        $role = $data['role'] ?? '';

        if (!in_array($role, ['manager', 'staff'])) {
            jsonResp(['error' => 'Invalid role. Use: manager or staff'], 422);
        }

        // Set temporary role override
        $_SESSION['temp_role'] = $role;

        jsonResp([
            'success' => true,
            'message' => "Switched to {$role} view",
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $role  // Return temp role
            ],
            'csrf' => csrfToken()
        ]);
        break;

    default:
        jsonResp(['error' => 'Not found'], 404);
}
