<?php
require_once __DIR__ . '/config/helpers.php';
bootSession();

// If already logged in, allow through
if (!empty($_SESSION['user_id'])) {
  $roleHint = $_SESSION['user_role'] ?? 'staff';
  $isLoggedIn = true;
} elseif (empty($_GET['role'])) {
  // Not logged in and no role selected
  header('Location: role-select.php');
  exit;
} else {
  // Not logged in but role selected
  $roleHint = $_GET['role'] ?? 'staff';
  $isLoggedIn = false;
}

// Handle direct login from role-select.php
if (($_POST['action'] ?? '') === 'login') {
  // Forward to auth API - role hint will be handled by auth.php
  $_POST['role_hint'] = $roleHint;
  $_POST['action'] = 'login';
  include 'api/auth.php';
  exit;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="PdfHair — Professional Order Management System">
  <title>PdfHair — Order Management</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
  <script>
    window.roleHint = <?php echo json_encode($roleHint); ?>;
  </script>
  <script src="assets/js/app.js" defer></script>

</head>

<body>

  <!-- ══════════════════════════════════════════════════════
     TOAST CONTAINER
     ══════════════════════════════════════════════════════ -->
  <div id="toast-container"></div>

  <!-- ══════════════════════════════════════════════════════
     MODAL
     ══════════════════════════════════════════════════════ -->
  <div class="modal-overlay" id="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title" id="modal-title">Confirm</div>
        <button class="btn btn-icon" onclick="closeModal()">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
      <div class="modal-body" id="modal-body"></div>
      <div class="modal-footer" id="modal-footer"></div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
     SLIDE PANEL
     ══════════════════════════════════════════════════════ -->
  <div class="panel-overlay" id="panel-overlay"></div>
  <div class="panel" id="slide-panel">
    <div class="panel-header">
      <div class="panel-title" id="panel-title">Panel</div>
      <button class="btn btn-icon" onclick="closePanel()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </div>
    <div class="panel-body" id="panel-body"></div>
    <div class="panel-footer" id="panel-footer"></div>
  </div>

  <!-- ══════════════════════════════════════════════════════
     LOGIN SCREEN
     ══════════════════════════════════════════════════════ -->
  <div id="login-screen" style="display:<?= $isLoggedIn ? 'none' : 'flex' ?>">
    <div class="login-card">
      <div class="login-logo">PDF<span>HAIR</span></div>
      <p class="login-sub">Sign in to your workspace</p>

      <?php
      $roleEmoji = ['admin' => '👑', 'manager' => '📊', 'staff' => '🛒'];
      $roleEmoji = $roleEmoji[$roleHint] ?? '👤';
      $roleLabel = ucfirst($roleHint);
      ?>
      <div class="role-pill role-<?= $roleHint ?>">
        <?= $roleEmoji ?> Signing in as <?= $roleLabel ?>
      </div>

      <form id="login-form">
        <div class="form-group">
          <label>Email Address</label>
          <div class="input-group">
            <input type="email" id="login-email" placeholder="me@company.com" autocomplete="username" required>
            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
              <polyline points="22,6 12,13 2,6" />
            </svg>
          </div>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-group">
            <input type="password" id="login-pass" placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="eye-toggle" onclick="togglePassword()" tabindex="-1">
              <svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              <svg class="eye-open" style="display:none" width="18" height="18"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
                <line x1="1" y1="1" x2="23" y2="23" stroke-linecap="round" />
              </svg>
            </button>
          </div>
        </div>
        <div id="login-error" class="error-msg" style="display:none; margin-bottom:15px; color:var(--red); font-size:0.85rem; text-align:center; background:rgba(239, 68, 68, 0.1); padding: 8px; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2);"></div>
        <button type="submit" class="btn btn-primary btn-lg btn-full" id="login-btn">Sign In</button>
      </form>

      <div style="text-align:center;margin-top:20px;">
        <a href="role-select.php"
          style="font-size:0.85rem;color:var(--text-muted);text-decoration:none;border-bottom:1px solid var(--text-muted);opacity:0.7;hover:opacity:1;">←
          Back to role select</a>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
     MAIN APP
     ══════════════════════════════════════════════════════ -->
  <div id="app" style="display:<?= $isLoggedIn ? 'block' : 'none' ?>">

    <!-- SIDEBAR ──────────────────────────────────────────── -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-logo">
        <div class="sidebar-logo-text">PDF<span>HAIR</span></div>
        <div class="sidebar-logo-sub">Management System</div>
      </div>

      <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>

        <div class="nav-item active" data-section="section-dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" />
            <rect x="14" y="3" width="7" height="7" />
            <rect x="14" y="14" width="7" height="7" />
            <rect x="3" y="14" width="7" height="7" />
          </svg>
          <span>Dashboard</span>
        </div>

        <div class="nav-item" data-section="section-orders">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
            <polyline points="10 9 9 9 8 9" />
          </svg>
          <span>Orders</span>
          <span class="nav-badge" id="orders-badge" style="display:<?= $isLoggedIn ? 'none' : 'flex' ?>">0</span>
        </div>

        <div class="nav-item" data-section="section-customers">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
          </svg>
          <span>Customers</span>
        </div>

        <div class="nav-item" data-section="section-products">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
            <line x1="3" y1="6" x2="21" y2="6" />
            <path d="M16 10a4 4 0 01-8 0" />
          </svg>
          <span>Products</span>
        </div>

        <div class="nav-section-label" style="margin-top:8px">Account</div>

        <div class="nav-item" onclick="openAbout()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <line x1="15" y1="9" x2="9" y2="15" />
            <line x1="9" y1="9" x2="15" y2="15" />
          </svg>
          <span>About</span>
        </div>
        <div class="nav-item" onclick="logout()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
          <span>Logout</span>
        </div>
      </nav>

      <div class="sidebar-footer">
        <div class="sidebar-user">
          <div class="user-avatar" id="user-avatar-text">?</div>
          <div class="user-info">
            <div class="user-name" id="user-name-text">Loading…</div>
            <div class="user-role" id="user-role-text"></div>
          </div>
        </div>
      </div>
    </aside>

    <!-- MAIN ─────────────────────────────────────────────── -->
    <div class="main">

      <!-- TOPBAR ─────────────────────────────────────────── -->
      <header class="topbar">
        <div class="topbar-left">
          <button class="menu-toggle" id="menu-toggle">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="6" x2="21" y2="6" />
              <line x1="3" y1="12" x2="21" y2="12" />
              <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          </button>
          <div class="page-title">Dashboard</div>
        </div>
        <div class="topbar-right">
          <button id="notifications-btn" class="btn btn-icon relative" title="Notifications"
            onclick="toggleNotifications()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <span id="notif-badge" class="notif-badge" style="display:<?= $isLoggedIn ? 'none' : 'flex' ?>">0</span>
          </button>
          <button id="theme-toggle" class="btn btn-icon" title="Toggle theme" onclick="toggleTheme()">
            <svg id="theme-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="2">
              <circle cx="12" cy="12" r="5" />
              <line x1="12" y1="1" x2="12" y2="3" />
              <line x1="12" y1="21" x2="12" y2="23" />
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
              <line x1="1" y1="12" x2="3" y2="12" />
              <line x1="21" y1="12" x2="23" y2="12" />
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
            </svg>
          </button>
          <button class="btn btn-primary btn-sm" onclick="openNewOrderForm()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            New Order
          </button>
        </div>

      </header>

      <!-- CONTENT ────────────────────────────────────────── -->
      <main class="content">

        <!-- ══════════════════════════════════════════════
           DASHBOARD SECTION
           ══════════════════════════════════════════════ -->
        <div class="section active" id="section-dashboard">

          <!-- Dashboard Search Toolbar -->
          <div class="toolbar" style="margin-bottom:24px;">
            <div class="search-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
              <input type="text" id="dashboard-search" placeholder="Search recent orders, products, low stock…">
            </div>
            <div id="dashboard-search-count" style="font-size:.875rem;color:var(--text-muted);white-space:nowrap;">
            </div>
          </div>

          <!-- Low Stock Alert -->
          <div class="alert-bar" id="low-stock-alert" style="display:<?= $isLoggedIn ? 'none' : 'flex' ?>"></div>

          <!-- Stat Cards -->
          <div class="stats-grid">
            <div class="stat-card gold">
              <div class="stat-icon gold">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                </svg>
              </div>
              <div class="stat-value" id="stat-orders">—</div>
              <div class="stat-label">Total Orders</div>
              <div class="stat-change neutral" id="stat-today-orders"></div>
            </div>

            <div class="stat-card green">
              <div class="stat-icon green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="12" y1="1" x2="12" y2="23" />
                  <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" />
                </svg>
              </div>
              <div class="stat-value" id="stat-revenue">—</div>
              <div class="stat-label">Total Revenue</div>
              <div class="stat-change up" id="stat-today-revenue"></div>
            </div>

            <div class="stat-card blue">
              <div class="stat-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
                </svg>
              </div>
              <div class="stat-value" id="stat-customers">—</div>
              <div class="stat-label">Customers</div>
            </div>

            <div class="stat-card purple">
              <div class="stat-icon purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                  <line x1="3" y1="6" x2="21" y2="6" />
                  <path d="M16 10a4 4 0 01-8 0" />
                </svg>
              </div>
              <div class="stat-value" id="stat-products">—</div>
              <div class="stat-label">Products</div>
              <div class="stat-change neutral" id="stat-pending"></div>
            </div>
          </div>

          <!-- Charts -->
          <div class="charts-grid">
            <div class="card">
              <div class="card-header">
                <div class="card-title">Revenue & Orders — Last 7 Days</div>
              </div>
              <div class="card-body">
                <canvas id="revenue-chart" loading="lazy"></canvas>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <div class="card-title">Order Status</div>
              </div>
              <div class="card-body">
                <canvas id="status-chart" loading="lazy"></canvas>
              </div>
            </div>
          </div>

          <!-- Bottom Row - Full height -->
          <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;height:calc(100% - 48px);">
            <div class="card" style="display:flex;flex-direction:column;height:100%;">
              <div class="card-header">
                <div class="card-title">Recent Orders</div>
                <button class="btn btn-secondary btn-sm" onclick="navigate('section-orders')">View All</button>
              </div>
              <div class="table-wrap flex-grow" style="display:flex;flex-direction:column;height:0;">
                <div style="flex:1;overflow:auto;">
                  <table>
                    <thead>
                      <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Date</th>
                      </tr>
                    </thead>
                    <tbody id="recent-orders-tbody">
                      <tr>
                        <?php for ($i = 0; $i < 7; $i++)
                          echo '<td><div class="skeleton" style="height:14px;border-radius:4px"></div></td>'; ?>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="card" style="height:100%;">
              <div class="card-header">
                <div class="card-title">Top Products</div>
              </div>
              <div class="card-body flex-grow">
                <div id="top-products-list" style="height:100%;overflow:auto;">
                  <div class="skeleton" style="height:180px;border-radius:8px"></div>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /dashboard -->

        <!-- ══════════════════════════════════════════════
           ORDERS SECTION
           ══════════════════════════════════════════════ -->
        <div class="section" id="section-orders">
          <div class="section-header">
            <div>
              <div class="section-title">Orders</div>
              <div class="section-subtitle" id="orders-count">Loading…</div>
            </div>
            <div style="display:flex;gap:10px">
              <button class="btn btn-secondary" onclick="exportOrders()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Export CSV
              </button>
              <button class="btn btn-primary" onclick="openNewOrderForm()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                New Order
              </button>
            </div>
          </div>

          <!-- Toolbar -->
          <div class="toolbar">
            <div class="search-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
              <input type="text" id="orders-search" placeholder="Search orders, customers…">
            </div>
            <select class="filter-select" id="orders-status-filter">
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="processing">Processing</option>
              <option value="shipped">Shipped</option>
              <option value="delivered">Delivered</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <input type="date" class="filter-select" id="orders-date-from" placeholder="Date">
          </div>

          <div class="card">
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="orders-tbody">
                  <tr>
                    <?php for ($i = 0; $i < 8; $i++)
                      echo '<td><div class="skeleton" style="height:14px;border-radius:4px"></div></td>'; ?>
                  </tr>
                </tbody>
              </table>
            </div>
            <div id="orders-pagination"></div>
          </div>
        </div><!-- /orders -->

        <!-- ══════════════════════════════════════════════
           CUSTOMERS SECTION
           ══════════════════════════════════════════════ -->
        <div class="section" id="section-customers">
          <div class="section-header">
            <div>
              <div class="section-title">Customers</div>
              <div class="section-subtitle" id="customers-count"></div>
            </div>
            <div style="display:flex;gap:10px">
              <button class="btn btn-secondary" onclick="window.open('api/export.php?type=customers','_blank')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Export
              </button>
              <button class="btn btn-primary" onclick="openCustomerForm()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                New Customer
              </button>
            </div>
          </div>

          <div class="toolbar">
            <div class="search-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
              <input type="text" id="customers-search" placeholder="Search customers…">
            </div>
          </div>

          <div class="entity-grid" id="customers-grid">
            <div class="skeleton" style="height:180px;border-radius:14px"></div>
            <div class="skeleton" style="height:180px;border-radius:14px"></div>
            <div class="skeleton" style="height:180px;border-radius:14px"></div>
          </div>
        </div><!-- /customers -->

        <!-- ══════════════════════════════════════════════
           PRODUCTS SECTION
           ══════════════════════════════════════════════ -->
        <div class="section" id="section-products">
          <div class="section-header">
            <div>
              <div class="section-title">Products &amp; Services</div>
              <div class="section-subtitle" id="products-count"></div>
            </div>
            <div style="display:flex;gap:10px">
              <button class="btn btn-secondary" onclick="window.open('api/export.php?type=products','_blank')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Export
              </button>
              <button class="btn btn-primary" onclick="openProductForm()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                New Product
              </button>
            </div>
          </div>

          <div class="toolbar">
            <div class="search-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
              <input type="text" id="products-search" placeholder="Search products…">
            </div>
          </div>

          <div class="entity-grid" id="products-grid">
            <div class="skeleton" style="height:180px;border-radius:14px"></div>
            <div class="skeleton" style="height:180px;border-radius:14px"></div>
            <div class="skeleton" style="height:180px;border-radius:14px"></div>
          </div>
        </div><!-- /products -->

      </main><!-- /content -->

      <!-- Footer -->
      <footer
        style="padding:16px 32px;border-top:1px solid var(--border);font-size:.75rem;color:var(--text-muted);display:flex;justify-content:space-between">
        <span>PdfHair v1.0 — Built for your business</span>
        <span>Keyboard: <kbd
            style="background:var(--surface);border:1px solid var(--border);border-radius:4px;padding:1px 6px">Ctrl+N</kbd>
          New Order · <kbd
            style="background:var(--surface);border:1px solid var(--border);border-radius:4px;padding:1px 6px">Esc</kbd>
          Close</span>
      </footer>

    </div><!-- /main -->
  </div><!-- /app -->

</body>

</html>