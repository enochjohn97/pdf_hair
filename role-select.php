<?php
// Simple role selector - goes to login page with role hint
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pdf_Hair — Select Role</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@400;500;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #080c18 0%, #1a1f2e 50%, #0f1422 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #e8edf5;
            padding: 2rem;
        }

        .logo {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2.5rem, 8vw, 4rem);
            background: linear-gradient(135deg, #f0a500, #ffc533);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            letter-spacing: 2px;
            text-align: center;
        }

        .logo span {
            color: #e8edf5;
        }

        .subtitle {
            font-size: 1.1rem;
            color: #8a96aa;
            text-align: center;
            margin-bottom: 3rem;
            max-width: 400px;
        }

        .role-grid {
            display: flex;
            justify-content: center;
            gap: 2rem;
            width: 100%;
            max-width: 1000px;
        }

        .role-card {
            flex: 0 0 300px;
            height: 280px;
            background: rgba(20, 28, 46, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            cursor: pointer;
            transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #f0a500, #3b82f6);
            transform: scaleX(0);
            transition: transform .3s ease;
        }

        .role-card:hover {
            transform: translateY(-12px);
            border-color: rgba(240, 165, 0, 0.3);
            box-shadow: 0 32px 64px rgba(240, 165, 0, 0.15);
        }

        .role-card:hover::before {
            transform: scaleX(1);
        }

        .role-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }

        .admin .role-icon {
            background: linear-gradient(135deg, rgba(240, 165, 0, 0.3), rgba(240, 165, 0, 0.1));
        }

        .manager .role-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.3), rgba(59, 130, 246, 0.1));
        }

        .staff .role-icon {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.3), rgba(34, 197, 94, 0.1));
        }

        .role-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.8rem;
            margin-bottom: .5rem;
            background: linear-gradient(135deg, #f0a500, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .role-desc {
            color: #8a96aa;
            font-size: 1rem;
            line-height: 1.6;
        }

        @media (max-width: 1024px) {
            .role-grid {
                flex-wrap: wrap;
                gap: 1.5rem;
            }

            .role-card {
                flex: 1 1 280px;
            }
        }

        @media (max-width: 640px) {
            .role-grid {
                flex-direction: column;
                align-items: center;
                gap: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="logo">PDF<span>HAIR</span></div>
    <p class="subtitle">Select your role to continue</p>

    <div class="role-grid">
        <div class="role-card admin" onclick="goToLogin('admin')">
            <div class="role-icon">👑</div>
            <div class="role-title">Admin</div>
            <div class="role-desc">Full system access - manage users, orders, customers, products</div>
        </div>

        <div class="role-card manager" onclick="goToLogin('manager')">
            <div class="role-icon">📊</div>
            <div class="role-title">Manager</div>
            <div class="role-desc">Order management, customer support, reporting</div>
        </div>

        <div class="role-card staff" onclick="goToLogin('staff')">
            <div class="role-icon">🛒</div>
            <div class="role-title">Staff</div>
            <div class="role-desc">Own orders & dashboard access</div>
        </div>
    </div>

    <script>
        function goToLogin(role) {
            window.location = 'index.php?role=' + role;
        }
    </script>
</body>

</html>