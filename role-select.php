<?php
// Simple role selector - goes to login page with role hint
require_once __DIR__ . '/config/helpers.php';
bootSession();

// Redirect if already authenticated
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'staff';
    header('Location: index.php?role=' . urlencode($role));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PdfHair — Select Role</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="role-selector-page">
        <div class="logo">PDF<span>HAIR</span></div>
        <p class="subtitle">Select your role to continue</p>

        <div class="role-grid">
            <div class="role-card admin" onclick="selectRole('admin', this)">
                <div class="role-icon">👑</div>
                <div class="role-title">Admin</div>
                <div class="role-desc">Full system access - manage users, orders, customers,
                    products<br><small>Click to use</small></div>
            </div>
            <div class="role-card manager" onclick="selectRole('manager', this)">
                <div class="role-icon">📊</div>
                <div class="role-title">Manager</div>
                <div class="role-desc">Order management, reporting, and customer insights<br><small>Click to use</small></div>
            </div>
            <div class="role-card staff" onclick="selectRole('staff', this)">
                <div class="role-icon">🛒</div>
                <div class="role-title">Staff</div>
                <div class="role-desc">Dashboard + own pending orders only<br><small>Click to use</small></div>
            </div>
        </div>

        <!-- Hidden form for role param -->
        <form id="role-login-form" method="GET" action="index.php">
            <input type="hidden" id="role-input" name="role" value="">
        </form>

        <!-- Footer -->
        <div style="margin-top: 4rem; text-align:center; color:var(--text-muted); font-size:0.75rem; opacity: 0.6;">
            <div>v1.0 — Secure Login System</div>
        </div>
    </div>

    <script>
        function selectRole(role, el) {
            const input = document.getElementById('role-input');
            const cards = document.querySelectorAll('.role-card');

            if (!input) return;

            // Visual feedback
            cards.forEach(card => card.classList.remove('selected'));
            el.classList.add('selected');
            
            // Add loading indicator overlay instead of replacing content
            const loader = document.createElement('div');
            loader.style.position = 'absolute';
            loader.style.inset = '0';
            loader.style.background = 'rgba(8, 12, 24, 0.8)';
            loader.style.display = 'flex';
            loader.style.alignItems = 'center';
            loader.style.justifyContent = 'center';
            loader.style.borderRadius = '20px';
            loader.style.backdropFilter = 'blur(4px)';
            loader.style.zIndex = '10';
            loader.innerHTML = '<div class="loader-spinner"></div><div style="margin-left:12px; font-weight:600; color:var(--gold);">Loading...</div>';
            
            el.style.position = 'relative';
            el.appendChild(loader);

            // Set role and redirect
            input.value = role;
            setTimeout(() => {
                window.location.href = `index.php?role=${role}`;
            }, 600);
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Add keyboard navigation
            document.querySelectorAll('.role-card').forEach((card, index) => {
                card.tabIndex = 0;
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const role = card.classList.contains('admin') ? 'admin' : (card.classList.contains('manager') ? 'manager' : 'staff');
                        selectRole(role, card);
                    }
                });
            });
        });
    </script>

    <style>
        .loader-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(240, 165, 0, 0.2);
            border-top-color: var(--gold);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .role-card.selected {
            transform: scale(1.02) !important;
            border-color: var(--gold) !important;
            box-shadow: 0 20px 40px rgba(240, 165, 0, .25) !important;
        }

        #role-login-form {
            display: none;
        }
    </style>
</body>

</html>