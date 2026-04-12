2<?php
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PdfHair — Select Role</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
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
            <div class="role-desc">Order management, reporting<br><small>Click to use</small></div>
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
    <div style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%);text-align:center;color:var(--text-muted);font-size:0.75rem;">
        <div>v1.0 — Secure Login</div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function selectRole(role, el) {
                const input = document.getElementById('role-input');
                const cards = document.querySelectorAll('.role-card');

                if (!input) {
                    alert('Setup error');
                    return;
                }

                // Visual feedback
                cards.forEach(card => card.classList.remove('selected'));
                el.classList.add('selected');
                
                // Add loading indicator
                el.style.opacity = '0.6';
                const originalHTML = el.innerHTML;
                el.innerHTML = '<div style="padding: 20px; text-align: center;">Loading...</div>';

                // Set role and redirect
                input.value = role;
                setTimeout(() => {
                    window.location.href = `index.php?role=${role}`;
                }, 800);
            }

            // Expose to onclick
            window.selectRole = selectRole;
            
            // Add keyboard navigation
            document.querySelectorAll('.role-card').forEach((card, index) => {
                card.tabIndex = 0;
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const role = card.classList[1]; // admin, manager, staff
                        selectRole(role, card);
                    }
                });
            });
        });
    </script>

    <style>
        .role-card.selected {
            transform: scale(1.02) !important;
            border-color: var(--gold) !important;
            box-shadow: 0 20px 40px rgba(240, 165, 0, .25) !important;
        }

        .login-fields {
            background: rgba(20, 28, 46, .6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .role-desc small {
            font-size: .75rem;
            opacity: .8;
            display: block;
            margin-top: .5rem;
        }

        /* Hide form */
        #role-login-form {
            display: none;
        }
    </style>
</body>

</html>