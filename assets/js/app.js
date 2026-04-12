/* ============================================================
   Pdf_Hair — Main Application (app.js)
   Vanilla JS SPA — no framework required
   ============================================================ */

'use strict';

// ── State ─────────────────────────────────────────────────
const App = {
  user: null,
  csrf: null,
  products: [],
  customers: [],
  currentOrder: null,
  ordersPage: 1,
  ordersFilters: { status: '', search: '', date_from: '', date_to: '' },
  chart: null,
};

// ── Helpers ───────────────────────────────────────────────
const $ = id => document.getElementById(id);
const qs = sel => document.querySelector(sel);
const qsa = sel => [...document.querySelectorAll(sel)];

const fmtCurrency = n => '₦' + Number(n || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 });
const fmtDate = d => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const fmtDateTime = d => d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
const initials = name => name ? name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() : '?';

async function api(path, method = 'GET', body = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
  };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(path, opts);
  const data = await res.json();
  if (!res.ok) throw Object.assign(new Error(data.error || 'Request failed'), { status: res.status, data });
  return data;
}

// ── Toast Notifications ───────────────────────────────────
function toast(type, title, msg = '', duration = 4000) {
  const icons = {
    success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>`,
    error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
    warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
  };
  
  // Enhanced toast with better positioning and animation
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `
    <div class="toast-icon">${icons[type]}</div>
    <div class="toast-body">
      <div class="toast-title">${title}</div>
      ${msg ? `<div class="toast-msg">${msg}</div>` : ''}
    </div>
  `;
  $('toast-container').appendChild(el);
  
  // Auto remove with smooth fade
  setTimeout(() => {
    el.classList.add('fade-out');
    setTimeout(() => el.remove(), 300);
  }, duration);
}

// ── Modal ─────────────────────────────────────────────────
function showModal(title, body, actions = []) {
  const overlay = $('modal-overlay');
  overlay.querySelector('.modal-title').textContent = title;
  overlay.querySelector('.modal-body').innerHTML = body;
  const footer = overlay.querySelector('.modal-footer');
  footer.innerHTML = `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>`;
  actions.forEach(a => {
    const btn = document.createElement('button');
    btn.className = `btn ${a.class || 'btn-primary'}`;
    btn.textContent = a.label;
    btn.onclick = () => { a.action(); closeModal(); };
    footer.appendChild(btn);
  });
  overlay.classList.add('open');
}
function closeModal() { $('modal-overlay').classList.remove('open'); }

// ── Panel ─────────────────────────────────────────────────
function openPanel(title, bodyHTML) {
  $('panel-title').textContent = title;
  $('panel-body').innerHTML = bodyHTML;
  $('panel-overlay').classList.add('open');
  $('slide-panel').classList.add('open');
}
function closePanel() {
  $('panel-overlay').classList.remove('open');
  $('slide-panel').classList.remove('open');
  App.currentOrder = null;
}

// ── Dashboard Data Cache ──────────────────────────────────
App.dashboardData = null;

// ── Dashboard Search ─────────────────────────────────────
let dashboardSearchTimer = null;
function dashboardSearchHandler(e) {
  clearTimeout(dashboardSearchTimer);
  dashboardSearchTimer = setTimeout(() => {
    const query = e.target.value.toLowerCase().trim();
    const countEl = $('dashboard-search-count');
    let total = 0;

    // Filter Recent Orders
    const orders = App.dashboardData?.recent_orders || [];
    const filteredOrders = orders.filter(o => 
      o.order_number.toLowerCase().includes(query) ||
      o.customer_name.toLowerCase().includes(query) ||
      o.status.toLowerCase().includes(query)
    );
    renderRecentOrders(filteredOrders);
    total += filteredOrders.length;

    // Filter Top Products
    const products = App.dashboardData?.top_products || [];
    const filteredProducts = products.filter(p => 
      p.product_name.toLowerCase().includes(query)
    );
    renderTopProducts(filteredProducts);
    total += filteredProducts.length;

    // Filter Low Stock
    const lowStock = App.dashboardData?.low_stock || [];
    const filteredLowStock = lowStock.filter(item => 
      item.name.toLowerCase().includes(query) ||
      item.unit.toLowerCase().includes(query)
    );
    renderLowStockAlert(filteredLowStock);
    total += filteredLowStock.length;

    // Update count
    if (countEl) {
      countEl.textContent = query ? `${total} results` : '';
    }
  }, 300);
}

// ── Sidebar Nav ───────────────────────────────────────────
function navigate(sectionId) {
  qsa('.section').forEach(s => s.classList.remove('active'));
  qsa('.nav-item').forEach(n => n.classList.remove('active'));

  const section = $(sectionId);
  if (section) {
    section.classList.add('active');
    const navItem = qs(`[data-section="${sectionId}"]`);
    if (navItem) navItem.classList.add('active');
    qs('.page-title').textContent = navItem?.querySelector('span')?.textContent || '';
  }

  // Close sidebar on mobile
  if (window.innerWidth < 768) $('sidebar').classList.remove('open');

  // Load section data
  if (sectionId === 'section-dashboard')  loadDashboard();
  if (sectionId === 'section-orders')     loadOrders();
  if (sectionId === 'section-customers')  loadCustomers();
  if (sectionId === 'section-products')   loadProducts();
  
  updateRoleUI();
}

// ── Auth ──────────────────────────────────────────────────
async function login(e) {
  e.preventDefault();
  const btn = $('login-btn');
  btn.disabled = true;
  btn.textContent = 'Signing in…';
  try {
    const data = await api('api/auth.php?action=login', 'POST', {
      email:    $('login-email').value.trim(),
      password: $('login-pass').value,
    });
    App.user = data.user;
    App.csrf = data.csrf;
    showApp();
    toast('success', 'Welcome back!', `Signed in as ${App.user.role.toUpperCase()}`);
  } catch (err) {
    $('login-error').textContent = err.data?.error || 'Login failed. Check your credentials.';
    $('login-error').style.display = 'block';
    toast('error', 'Login Failed', err.data?.error || 'Check credentials');
    btn.disabled = false;
    btn.textContent = 'Sign In';
  }
}

async function logout() {
  try { 
    await api('api/auth.php?action=logout', 'POST'); 
    // Clear temp session data
    sessionStorage.clear();
  } catch {}
  window.location.href = 'role-select.php';  // Always go to role selector
}

// Moved to showApp() after auth
async function loadNotifications() {
  if (!App.user) return; // Only after login
  
  try {
    const data = await api('api/notifications.php');
    const badge = $('notif-badge');
    const count = data.unread_count || 0;
    if (badge) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = count > 0 ? 'block' : 'none';
    }
    App.notifications = data.notifications || [];
  } catch (err) {
    console.error('Notifications load failed:', err);
    const badge = $('notif-badge');
    if (badge) badge.style.display = 'none';
  }
}


function toggleNotifications() {
  const dropdown = $('notifications-dropdown');
  if (dropdown) {
    dropdown.remove();
    return;
  }
  
  const panel = document.createElement('div');
  panel.id = 'notifications-dropdown';
  panel.className = 'notifications-dropdown';
  panel.innerHTML = `
    <div class="notif-header">
      <span>Notifications</span>
      <button class="btn btn-icon" onclick="markAllNotificationsRead()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </button>
    </div>
    <div id="notif-list" class="notif-list"></div>
  `;
  
  const btn = $('notifications-btn');
  btn.parentNode.insertBefore(panel, btn.nextSibling);
  
  renderNotifications(App.notifications || []);
}

function renderNotifications(notifs) {
  const list = $('notif-list');
  if (!list) return;
  
  if (!notifs.length) {
    list.innerHTML = '<div class="notif-empty">No new notifications</div>';
    return;
  }
  
  list.innerHTML = notifs.map(n => `
    <div class="notif-item ${n.is_read ? 'read' : 'unread'}" onclick="markNotificationRead(${n.id})">
      <div class="notif-icon notif-${n.type}"></div>
      <div class="notif-content">
        <div class="notif-title">${n.title}</div>
        <div class="notif-message">${n.message}</div>
        <div class="notif-time">${fmtDateTime(n.created_at)}</div>
      </div>
    </div>
  `).join('');
}

async function markNotificationRead(id) {
  try {
    await api('api/notifications.php?action=mark-read', 'POST', { ids: [id] });
    loadNotifications();
    renderNotifications(App.notifications || []);
  } catch (err) {
    toast('error', 'Failed to mark as read');
  }
}

async function markAllNotificationsRead() {
  try {
    await api('api/notifications.php?action=mark-all-read', 'POST');
    loadNotifications();
  } catch (err) {
    toast('error', 'Failed to mark all as read');
  }
}

function showApp() {
  $('login-screen').style.display = 'none';
  $('app').style.display = 'flex';
  $('user-avatar-text').textContent = initials(App.user.name);
  $('user-name-text').textContent   = App.user.name;
  $('user-role-text').textContent   = App.user.role.toUpperCase();
  navigate('section-dashboard');
  loadNotifications(); // Now safe - user authenticated
  
  // Role-based UI setup
  updateRoleUI();
  
  // Polling for real-time updates
  setInterval(() => {
    if ($('section-dashboard.active')) loadDashboard();
    if ($('section-orders.active')) loadOrders(App.ordersPage);
  }, 10000); // 10s poll
}


function hasPermission(permission) {
  return App.user && App.user.permissions && App.user.permissions.includes(permission);
}

function updateRoleUI() {
  const role = App.user.role;
  const isStaffRole = role === 'staff';
  
  // Hide nav sections for staff (only dashboard + orders)
  if (isStaffRole) {
    qsa('[data-section="section-customers"], [data-section="section-products"]').forEach(nav => {
      nav.style.display = 'none';
    });
    // Staff message
    const pageTitle = qs('.page-title');
    if (pageTitle && !pageTitle.dataset.staffMsg) {
      pageTitle.dataset.staffMsg = '1';
      pageTitle.innerHTML += ' <span style="font-size:.7rem;color:var(--yellow);font-weight:400">(Staff: Pending orders only)</span>';
    }
  }
  
  // Staff: Restrict orders status filter to pending only
  const statusFilter = $('orders-status-filter');
  if (isStaffRole && statusFilter) {
    statusFilter.innerHTML = '<option value="pending" selected>Pending</option>';
    statusFilter.title = 'Staff: Pending orders only';
  } else if (statusFilter) {
    // Reset for non-staff
    const statuses = ['', 'pending','confirmed','processing','shipped','delivered','cancelled'];
    statusFilter.innerHTML = statuses.map(s => `<option value="${s}" ${App.ordersFilters.status === s ? 'selected' : ''}>${s === '' ? 'All Statuses' : s}</option>`).join('');
  }
  
  // Staff: Hide date_to input, grey date_from
  const dateTo = $('orders-date-to');
  const dateInputs = qsa('input[type="date"]');
  if (isStaffRole) {
    if (dateTo) dateTo.style.display = 'none';
    dateInputs.forEach(input => {
      input.classList.add('staff-date-grey');
      input.title = 'Staff view simplified';
    });
    App.ordersFilters.date_to = ''; // Clear filter
  } else {
    if (dateTo) dateTo.style.display = '';
    dateInputs.forEach(input => input.classList.remove('staff-date-grey'));
  }
  
  // Permissions-based UI
  const adminOnly = qsa('.admin-only');
  const managerOnly = qsa('.manager-staff-only');
  
  adminOnly.forEach(el => el.style.display = hasPermission('user.read.all') ? '' : 'none');
  managerOnly.forEach(el => el.style.display = hasRole(['admin', 'manager']) ? '' : 'none');
  
  // Hide delete buttons if no delete perms
  qsa('.delete-btn').forEach(btn => {
    btn.style.display = hasPermission('order.delete') || hasPermission('customer.delete') || hasPermission('product.delete') ? '' : 'none';
  });
  
  // New Order button - requires create perms (staff OK)
  const newOrderBtns = qsa('[onclick*="NewOrder"], [onclick*="openNewOrderForm"]');
  newOrderBtns.forEach(btn => {
    btn.style.display = hasPermission('order.create') ? '' : 'none';
  });
  
  // Staff: Hide edit for non-pending orders
  if (isStaffRole) {
    qsa('.btn[onclick*="editOrder"]').forEach(btn => {
      const row = btn.closest('tr');
      const statusCell = row.querySelector('.badge');
      if (statusCell && !statusCell.textContent.toLowerCase().includes('pending')) {
        btn.style.display = 'none';
        btn.title = 'Staff can only edit pending orders';
      }
    });
  }
  
  // Force staff orders filter to pending
  if (isStaffRole && App.ordersFilters.status !== 'pending') {
    App.ordersFilters.status = 'pending';
    if ($('orders-status-filter')) $('orders-status-filter').value = 'pending';
    loadOrders(1);
  }
}

function isStaff() {
  return App.user && App.user.role === 'staff';
}


// ════════════════════════════════════════════════════════
// DASHBOARD
// ════════════════════════════════════════════════════════
async function loadDashboard() {
  try {
    const data = await api('api/dashboard.php');
    App.dashboardData = data; // Cache for search
    const s = data.summary;

    // Stat cards
    animateValue('stat-orders',    s.total_orders);
    animateValue('stat-revenue',   s.total_revenue,   true);
    animateValue('stat-customers', s.total_customers);
    animateValue('stat-products',  s.total_products);

    $('stat-today-orders').textContent  = `${s.today_orders} today`;
    $('stat-today-revenue').textContent = `${fmtCurrency(s.today_revenue)} today`;
    $('stat-pending').textContent       = `${s.pending_orders} pending`;

    // Nav badge
    $('orders-badge').textContent = s.pending_orders || '';
    $('orders-badge').style.display = s.pending_orders ? '' : 'none';

    // Low stock alert
    renderLowStockAlert(data.low_stock);

    // Revenue chart
    renderRevenueChart(data.revenue_chart);

    // Status doughnut
    renderStatusChart(data.status_breakdown);

    // Recent orders
    renderRecentOrders(data.recent_orders);

    // Top products
    renderTopProducts(data.top_products);

  } catch (err) {
    toast('error', 'Dashboard error', err.message);
  }
}

function animateValue(elId, value, isCurrency = false) {
  const el = $(elId);
  if (!el) return;
  const start = 0;
  const end = parseFloat(value) || 0;
  const duration = 800;
  const startTime = performance.now();
  function step(now) {
    const progress = Math.min((now - startTime) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = start + (end - start) * eased;
    el.textContent = isCurrency ? fmtCurrency(current) : Math.round(current).toLocaleString();
    if (progress < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

function renderLowStockAlert(items = []) {
  const el = $('low-stock-alert');
  if (!el) return;
  if (!items.length) { el.style.display = 'none'; return; }
  el.style.display = 'flex';
  el.innerHTML = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0">
      <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
    <strong>Low Stock:</strong>&nbsp;
    ${items.map(i => `<span>${i.name} (${i.stock_qty} ${i.unit})</span>`).join(' · ')}`;
}

function renderRevenueChart(days) {
  const canvas = $('revenue-chart');
  if (!canvas || !window.Chart) return;
  if (App.chart) App.chart.destroy();

  const labels  = days.map(d => d.day);
  const revenue = days.map(d => d.revenue);
  const orders  = days.map(d => d.orders);

  App.chart = new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Revenue (₦)',
          data: revenue,
          backgroundColor: 'rgba(240,165,0,.7)',
          borderColor: '#f0a500',
          borderWidth: 1,
          borderRadius: 6,
          yAxisID: 'y',
        },
        {
          label: 'Orders',
          data: orders,
          type: 'line',
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59,130,246,.1)',
          tension: 0.4,
          pointBackgroundColor: '#3b82f6',
          pointRadius: 4,
          fill: true,
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#8a96aa', font: { family: 'Outfit' } } },
        tooltip: { backgroundColor: '#141c2e', borderColor: '#1c2a40', borderWidth: 1,
                   titleColor: '#e8edf5', bodyColor: '#8a96aa' },
      },
      scales: {
        x: { ticks: { color: '#5c6880' }, grid: { color: 'rgba(255,255,255,.04)' } },
        y: { ticks: { color: '#5c6880', callback: v => '₦' + v.toLocaleString() },
             grid: { color: 'rgba(255,255,255,.04)' } },
        y1: { position: 'right', ticks: { color: '#5c6880' }, grid: { display: false } },
      },
    },
  });
}

function renderStatusChart(breakdown) {
  const canvas = $('status-chart');
  if (!canvas || !window.Chart) return;

  // Fix canvas reuse error - destroy existing chart
  if (App.chart) {
    App.chart.destroy();
  }

  const colors = {
    pending: '#f59e0b', confirmed: '#3b82f6', processing: '#8b5cf6',
    shipped: '#f97316', delivered: '#10b981', cancelled: '#ef4444',
  };
  const labels = Object.keys(breakdown);
  const data   = Object.values(breakdown);

  App.chart = new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: labels.map(l => colors[l] || '#555'),
        borderWidth: 2,
        borderColor: '#141c2e',
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: { position: 'bottom', labels: { color: '#8a96aa', padding: 12, font: { family: 'Outfit', size: 11 } } },
        tooltip: { backgroundColor: '#141c2e', borderColor: '#1c2a40', borderWidth: 1 },
      },
    },
  });
}

function renderRecentOrders(orders = []) {
  const tbody = $('recent-orders-tbody');
  if (!tbody) return;
  tbody.innerHTML = orders.length ? orders.map(o => `
    <tr style="cursor:pointer" onclick="viewOrder(${o.id})">
      <td><span class="order-num">${o.order_number}</span></td>
      <td>${o.customer_name}</td>
      <td><span class="badge badge-dot badge-${o.status}">${o.status}</span></td>
      <td>${o.item_count} item${o.item_count != 1 ? 's' : ''}</td>
      <td class="amount">${fmtCurrency(o.total)}</td>
      <td><span class="badge badge-${o.payment_status}">${o.payment_status}</span></td>
      <td style="color:var(--text-muted);font-size:.8rem">${fmtDate(o.created_at)}</td>
    </tr>
  `).join('') : `<tr><td colspan="7"><div class="empty-state"><p>${$('dashboard-search')?.value ? 'No matching orders' : 'No orders yet'}</p></div></td></tr>`;
}

function renderTopProducts(products = []) {
  const el = $('top-products-list');
  if (!el) return;
  if (!products.length) { 
    el.innerHTML = '<div style="color:var(--text-muted);font-size:.85rem;padding:8px 0;text-align:center;">' + ($('dashboard-search')?.value ? 'No matching products' : 'No sales data yet') + '</div>'; 
    return; 
  }
  el.innerHTML = products.map((p, i) => `
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
      <div style="width:24px;height:24px;border-radius:50%;background:var(--gold-bg);color:var(--gold);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;flex-shrink:0">${i+1}</div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${p.product_name}</div>
        <div style="font-size:.75rem;color:var(--text-muted)">${Number(p.qty_sold).toLocaleString()} units sold</div>
      </div>
      <div class="amount" style="font-size:.875rem">${fmtCurrency(p.revenue)}</div>
    </div>
  `).join('');
}

// ════════════════════════════════════════════════════════
// ORDERS
// ════════════════════════════════════════════════════════
async function loadOrders(page = 1) {
  App.ordersPage = page;
  const f = App.ordersFilters;
  const params = new URLSearchParams({
    page,
    limit: 20,
    ...(f.status    && { status:    f.status }),
    ...(f.search    && { search:    f.search }),
    ...(f.date_from && { date_from: f.date_from }),
    ...(f.date_to   && { date_to:   f.date_to }),
  });

  const tbody = $('orders-tbody');
  tbody.innerHTML = skeletonRows(7, 8);

  try {
    const data = await api(`api/orders.php?${params}`);
    renderOrdersTable(data.data);
    renderPagination(data, page, loadOrders, 'orders-pagination', 'orders-count');
  } catch (err) {
    toast('error', 'Failed to load orders', err.message);
  }
}

function renderOrdersTable(orders) {
  const tbody = $('orders-tbody');
  tbody.innerHTML = orders.length ? orders.map(o => `
    <tr>
      <td><span class="order-num">${o.order_number}</span></td>
      <td>${o.customer_name}</td>
      <td><span class="badge badge-dot badge-${o.status}">${o.status}</span></td>
      <td>${o.item_count}</td>
      <td class="amount">${fmtCurrency(o.total)}</td>
      <td><span class="badge badge-${o.payment_status}">${o.payment_status}</span></td>
      <td style="color:var(--text-muted);font-size:.8rem">${fmtDate(o.created_at)}</td>
      <td>
        <div style="display:flex;gap:6px">
          <button class="btn btn-secondary btn-sm btn-icon" title="View" onclick="viewOrder(${o.id})">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
          <button class="btn btn-secondary btn-sm btn-icon ${App.user?.role === 'staff' && o.status !== 'pending' ? 'disabled-staff' : ''}" title="${App.user?.role === 'staff' && o.status !== 'pending' ? 'Staff: Pending only' : 'Edit'}" onclick="${App.user?.role === 'staff' && o.status !== 'pending' ? '' : 'editOrder(' + o.id + ')'}" ${App.user?.role === 'staff' && o.status !== 'pending' ? 'disabled' : ''}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>

          ${App.user?.role === 'admin' ? `<button class="btn btn-danger btn-sm btn-icon" title="Delete" onclick="deleteOrder(${o.id},'${o.order_number}')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
          </button>` : ''}
        </div>
      </td>
    </tr>
  `).join('') : `<tr><td colspan="8"><div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 17H5a2 2 0 00-2 2v2h18v-2a2 2 0 00-2-2h-4"/><path d="M9 17v-3a4 4 0 018 0v3"/><path d="M12 7a4 4 0 100-8 4 4 0 000 8z"/></svg>
    <h3>No orders found</h3><p>Create your first order to get started</p></div></td></tr>`;
}

async function viewOrder(id) {
  openPanel('Order Details', '<div class="skeleton" style="height:400px"></div>');
  try {
    const o = await api(`api/orders.php?id=${id}`);
    App.currentOrder = o;
    const html = `
      <div class="order-detail-header">
        <div>
          <div class="order-num-big">${o.order_number}</div>
          <div style="color:var(--text-muted);font-size:.85rem;margin-top:4px">${fmtDateTime(o.created_at)}</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
          <span class="badge badge-dot badge-${o.status}" style="font-size:.85rem;padding:5px 14px">${o.status}</span>
          <span class="badge badge-${o.payment_status}">${o.payment_status}</span>
        </div>
      </div>

      <div style="margin-bottom:20px">
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px">Update Status</div>
        <div class="status-steps">
          ${['pending','confirmed','processing','shipped','delivered','cancelled'].map(s => 
            `<button class="status-step ${o.status === s ? 'active-step' : ''}" data-s="${s}" onclick="quickStatus(${o.id},'${s}',this)">${s}</button>`
          ).join('')}
        </div>
      </div>

      <div class="detail-grid">
        <div class="detail-block">
          <div class="detail-block-label">Customer</div>
          <div class="detail-block-value">${o.customer_name}</div>
        </div>
        <div class="detail-block">
          <div class="detail-block-label">Created by</div>
          <div class="detail-block-value">${o.creator_name || '—'}</div>
        </div>
        <div class="detail-block">
          <div class="detail-block-label">Payment</div>
          <div class="detail-block-value">${o.payment_method || '—'} · <span class="badge badge-${o.payment_status}">${o.payment_status}</span></div>
        </div>
        <div class="detail-block">
          <div class="detail-block-label">Last Updated</div>
          <div class="detail-block-value">${fmtDateTime(o.updated_at)}</div>
        </div>
      </div>

      ${o.notes ? `<div class="detail-block" style="margin-bottom:20px"><div class="detail-block-label">Notes</div><div class="detail-block-value" style="white-space:pre-wrap">${o.notes}</div></div>` : ''}

      <div class="card" style="margin-bottom:20px">
        <div class="card-header"><div class="card-title">Order Items</div></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>
              ${o.items.map(i => `
                <tr>
                  <td>${i.product_name}${i.notes ? `<br><small style="color:var(--text-muted)">${i.notes}</small>` : ''}</td>
                  <td>${i.quantity}</td>
                  <td class="amount">${fmtCurrency(i.unit_price)}</td>
                  <td class="amount">${fmtCurrency(i.line_total)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
        <div style="padding:16px 24px">
          <div class="total-row"><span style="color:var(--text-muted)">Subtotal</span><span class="amount">${fmtCurrency(o.subtotal)}</span></div>
          ${+o.discount > 0 ? `<div class="total-row"><span style="color:var(--text-muted)">Discount</span><span class="amount" style="color:var(--red)">-${fmtCurrency(o.discount)}</span></div>` : ''}
          ${+o.tax_rate > 0 ? `<div class="total-row"><span style="color:var(--text-muted)">Tax (${o.tax_rate}%)</span><span class="amount">${fmtCurrency(o.tax_amount)}</span></div>` : ''}
          <div class="total-row grand"><span>Total</span><span class="amount">${fmtCurrency(o.total)}</span></div>
        </div>
      </div>

      ${o.timeline?.length ? `
        <div class="card-title" style="margin-bottom:12px">Activity Timeline</div>
        <ul class="timeline">
          ${o.timeline.map(t => `
            <li class="timeline-item">
              <div class="timeline-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
              <div class="timeline-body">
                <div class="timeline-text"><strong>${t.user_name || 'System'}</strong> — ${t.detail}</div>
                <div class="timeline-time">${fmtDateTime(t.created_at)}</div>
              </div>
            </li>
          `).join('')}
        </ul>
      ` : ''}

      <div style="margin-top:20px;display:flex;gap:10px">
        <button class="btn btn-secondary" onclick="printOrder(${o.id})">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Print Receipt
        </button>
        <button class="btn btn-secondary" onclick="editOrder(${o.id})">Edit Order</button>
      </div>`;

    $('panel-body').innerHTML = html;
  } catch (err) {
    toast('error', 'Failed to load order', err.message);
    closePanel();
  }
}

async function quickStatus(orderId, status, btn) {
  try {
    await api(`api/orders.php?id=${orderId}&action=status`, 'PATCH', { status });
    qsa('.status-step').forEach(b => {
      b.classList.remove('active-step');
      if (b.dataset.s === status) b.classList.add('active-step');
    });
    toast('success', 'Status updated', `Order moved to "${status}"`);
    loadOrders(App.ordersPage);
    loadDashboard();
  } catch (err) {
    toast('error', 'Failed to update status', err.message);
  }
}

async function deleteOrder(id, num) {
  showModal(
    'Delete Order',
    `<p>Are you sure you want to delete order <strong>${num}</strong>? This action cannot be undone.</p>`,
    [{ label: 'Delete', class: 'btn-danger', action: async () => {
      try {
        await api(`api/orders.php?id=${id}`, 'DELETE');
        toast('success', 'Order deleted');
        loadOrders(App.ordersPage);
        loadDashboard();
      } catch (err) {
        toast('error', 'Failed to delete', err.message);
      }
    }}]
  );
}

// ── Order Form ────────────────────────────────────────────
let orderItems = [];

function openNewOrderForm() {
  orderItems = [{ product_id: '', product_name: '', quantity: 1, unit_price: 0, line_total: 0, notes: '' }];
  renderOrderForm(null);
}

async function editOrder(id) {
  try {
    const o = await api(`api/orders.php?id=${id}`);
    orderItems = o.items.map(i => ({ ...i }));
    renderOrderForm(o);
  } catch (err) {
    toast('error', 'Failed to load order', err.message);
  }
}

async function renderOrderForm(order) {
  // Prefetch products and customers if not cached
  if (!App.products.length) App.products = (await api('api/products.php?active=1')).data || [];
  if (!App.customers.length) App.customers = (await api('api/customers.php?limit=200')).data || [];

  const isEdit = !!order;
  const title  = isEdit ? `Edit ${order.order_number}` : 'New Order';

  const customerOptions = App.customers.map(c =>
    `<option value="${c.id}" data-name="${c.name}" ${order?.customer_id == c.id ? 'selected' : ''}>${c.name}</option>`
  ).join('');

  const productOptions = App.products.map(p =>
    `<option value="${p.id}" data-price="${p.price}" data-name="${p.name}">${p.name} — ${fmtCurrency(p.price)}</option>`
  ).join('');

  const html = `
    <div class="form-group">
      <label>Customer *</label>
      <select id="of-customer" onchange="onCustomerSelect(this)">
        <option value="">-- Select customer or type name --</option>
        ${customerOptions}
        <option value="__new__">+ New / Walk-in Customer</option>
      </select>
    </div>
    <div class="form-group" id="of-name-wrap" style="${order?.customer_id ? 'display:none' : ''}">
      <label>Customer Name *</label>
      <input type="text" id="of-customer-name" value="${order?.customer_name || ''}" placeholder="Enter customer name">
    </div>

    <div style="margin:20px 0 12px;display:flex;align-items:center;justify-content:space-between">
      <div style="font-weight:700">Order Items</div>
      <button class="btn btn-secondary btn-sm" onclick="addOrderItem()">+ Add Item</button>
    </div>
    <div id="of-items-container"></div>
    <div class="totals-box" id="of-totals">
      <div class="total-row"><span>Subtotal</span><span class="amount" id="of-subtotal">₦0.00</span></div>
      <div class="total-row">
        <span>Discount</span>
        <input type="number" id="of-discount" value="${order?.discount || 0}" min="0" style="width:120px;text-align:right;background:var(--card);border:1px solid var(--border);border-radius:6px;padding:4px 8px;color:var(--text);font-family:'JetBrains Mono',monospace" oninput="updateOrderTotals()">
      </div>
      <div class="total-row">
        <span>Tax Rate (%)</span>
        <input type="number" id="of-taxrate" value="${order?.tax_rate || 0}" min="0" max="100" style="width:80px;text-align:right;background:var(--card);border:1px solid var(--border);border-radius:6px;padding:4px 8px;color:var(--text);font-family:'JetBrains Mono',monospace" oninput="updateOrderTotals()">
      </div>
      <div class="total-row"><span>Tax Amount</span><span class="amount" id="of-tax">₦0.00</span></div>
      <div class="total-row grand"><span>TOTAL</span><span class="amount" id="of-total">₦0.00</span></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px">
      <div class="form-group">
        <label>Order Status</label>
        <select id="of-status">
          ${isStaff() ? 
            `<option value="pending" ${(order?.status||'pending') === 'pending' ? 'selected':''}>Pending</option>` :
            ['pending','confirmed','processing','shipped','delivered','cancelled'].map(s =>
              `<option value="${s}" ${(order?.status||'pending') === s ? 'selected':''}>${s}</option>`
            ).join('')
          }
        </select>
      </div>
      <div class="form-group">
        <label>Payment Status</label>
        <select id="of-payment-status">
          ${['unpaid','partial','paid'].map(s =>
            `<option value="${s}" ${(order?.payment_status||'unpaid') === s ? 'selected':''} >${s}</option>`
          ).join('')}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Payment Method</label>
      <select id="of-payment-method">
        <option value="" ${!order?.payment_method ? 'selected':''}>—</option>
        ${['Cash','Bank Transfer','Card','POS','Mobile Money','Cheque'].map(m =>
          `<option value="${m}" ${order?.payment_method === m ? 'selected':''}>${m}</option>`
        ).join('')}
      </select>
    </div>
    <div class="form-group">
      <label>Notes</label>
      <textarea id="of-notes" placeholder="Order notes, delivery instructions…">${order?.notes || ''}</textarea>
    </div>
  `;

  // Store productOptions for item rows
  window._productOptions = productOptions;
  window._editOrderId     = order?.id;

  openPanel(title, html);
  $('panel-footer').innerHTML = `
    <button class="btn btn-secondary" onclick="closePanel()">Cancel</button>
    <button class="btn btn-primary" onclick="submitOrderForm()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      ${isEdit ? 'Update Order' : 'Create Order'}
    </button>
  `;

  // Render items
  renderItemRows();
}

function renderItemRows() {
  const container = $('of-items-container');
  if (!container) return;
  container.innerHTML = orderItems.map((item, i) => `
    <div style="display:grid;grid-template-columns:2fr 1fr 1.2fr auto;gap:6px;align-items:center;margin-bottom:8px;" id="item-row-${i}">
      <select onchange="onProductSelect(this, ${i})" style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:8px 10px;color:var(--text);font-size:.82rem;outline:none">
        <option value="">Custom item…</option>
        ${window._productOptions}
      </select>
      <input type="text" placeholder="Item name" value="${item.product_name}" oninput="orderItems[${i}].product_name=this.value" style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:8px 10px;color:var(--text);font-size:.82rem;outline:none">
      <div style="display:flex;gap:4px">
        <input type="number" placeholder="Qty" value="${item.quantity}" min="0.01" step="0.01" oninput="orderItems[${i}].quantity=+this.value;updateOrderTotals()" style="width:60px;background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:8px 6px;color:var(--text);font-size:.82rem;outline:none;text-align:center">
        <input type="number" placeholder="Price" value="${item.unit_price}" min="0" step="0.01" oninput="orderItems[${i}].unit_price=+this.value;updateOrderTotals()" style="width:100px;background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:8px 6px;color:var(--text);font-size:.82rem;font-family:'JetBrains Mono',monospace;outline:none;text-align:right">
      </div>
      <button onclick="removeOrderItem(${i})" style="background:var(--red-bg);color:var(--red);border:none;border-radius:6px;padding:8px 10px;cursor:pointer;font-size:.9rem">✕</button>
    </div>
  `).join('');
  updateOrderTotals();
}

function onProductSelect(sel, idx) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt.value) return;
  orderItems[idx].product_id   = opt.value;
  orderItems[idx].product_name = opt.dataset.name;
  orderItems[idx].unit_price   = parseFloat(opt.dataset.price) || 0;
  renderItemRows();
}

function addOrderItem() {
  orderItems.push({ product_id: '', product_name: '', quantity: 1, unit_price: 0, line_total: 0, notes: '' });
  renderItemRows();
}

function removeOrderItem(i) {
  if (orderItems.length === 1) { toast('warning', 'At least one item required'); return; }
  orderItems.splice(i, 1);
  renderItemRows();
}

function updateOrderTotals() {
  let subtotal = 0;
  orderItems.forEach(item => {
    item.line_total = Math.round(item.quantity * item.unit_price * 100) / 100;
    subtotal += item.line_total;
  });
  const discount  = parseFloat($('of-discount')?.value || 0);
  const taxRate   = parseFloat($('of-taxrate')?.value || 0);
  const taxAmount = Math.round((subtotal - discount) * taxRate / 100 * 100) / 100;
  const total     = Math.max(0, subtotal - discount + taxAmount);

  if ($('of-subtotal')) $('of-subtotal').textContent = fmtCurrency(subtotal);
  if ($('of-tax'))      $('of-tax').textContent      = fmtCurrency(taxAmount);
  if ($('of-total'))    $('of-total').textContent    = fmtCurrency(total);
}

function onCustomerSelect(sel) {
  const val = sel.value;
  const nameWrap = $('of-name-wrap');
  const nameInput = $('of-customer-name');
  if (val === '__new__') {
    nameWrap.style.display = '';
    nameInput.value = '';
    nameInput.focus();
  } else if (val) {
    nameWrap.style.display = 'none';
    const opt = sel.options[sel.selectedIndex];
    nameInput.value = opt.dataset.name;
  } else {
    nameWrap.style.display = '';
  }
}

async function submitOrderForm() {
  const customerId = $('of-customer')?.value;
  const customerName = ($('of-customer-name')?.value || '').trim();

  if (!customerName && (!customerId || customerId === '__new__')) {
    toast('warning', 'Customer name required'); return;
  }
  if (!orderItems.length) { toast('warning', 'Add at least one item'); return; }
  if (orderItems.some(i => !i.product_name)) { toast('warning', 'All items need a name'); return; }

  const payload = {
    customer_id:     (customerId && customerId !== '__new__') ? customerId : null,
    customer_name:   customerName || $('of-customer')?.options[$('of-customer').selectedIndex]?.dataset?.name || '',
    status:          $('of-status')?.value || 'pending',
    payment_status:  $('of-payment-status')?.value || 'unpaid',
    payment_method:  $('of-payment-method')?.value || '',
    notes:           $('of-notes')?.value || '',
    discount:        parseFloat($('of-discount')?.value || 0),
    tax_rate:        parseFloat($('of-taxrate')?.value || 0),
    items:           orderItems,
  };

  const isEdit   = !!window._editOrderId;
  const endpoint = isEdit ? `api/orders.php?id=${window._editOrderId}` : 'api/orders.php';
  const method   = isEdit ? 'PUT' : 'POST';

  const btn = qs('#slide-panel .btn-primary');
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

  try {
    await api(endpoint, method, payload);
    toast('success', isEdit ? 'Order updated!' : 'Order created!');
    closePanel();
    loadOrders(App.ordersPage);
    loadDashboard();
  } catch (err) {
    if (err.data?.require_force) {
      showModal('Duplicate Detected', `
        <p>A recent order for this customer exists: <strong>${err.data.duplicate.order_number}</strong>.</p>
        <p style="margin-top:8px;color:var(--text-muted)">Do you want to create another order anyway?</p>
      `, [{ label: 'Create Anyway', action: async () => {
        await api(endpoint, method, { ...payload, force: true });
        toast('success', 'Order created!');
        closePanel(); loadOrders(); loadDashboard();
      }}]);
    } else {
      toast('error', 'Failed to save order', err.message);
    }
    if (btn) { btn.disabled = false; btn.textContent = isEdit ? 'Update Order' : 'Create Order'; }
  }
}

function printOrder(id) {
  if (!App.currentOrder) return;
  const o = App.currentOrder;
  const win = window.open('', '_blank');
  win.document.write(`
    <!DOCTYPE html><html><head><title>Receipt ${o.order_number}</title>
    <style>body{font-family:Arial,sans-serif;padding:40px;color:#111;max-width:500px;margin:auto}
    h1{color:#f0a500;font-size:2rem;margin-bottom:4px}.meta{color:#666;font-size:.85rem;margin-bottom:24px}
    table{width:100%;border-collapse:collapse;margin-bottom:16px}th,td{border-bottom:1px solid #eee;padding:8px 0;font-size:.9rem}
    th{text-align:left;color:#888;font-size:.75rem;text-transform:uppercase}td:last-child,th:last-child{text-align:right}
    .totals{margin-left:auto;width:240px}.row{display:flex;justify-content:space-between;padding:4px 0;font-size:.9rem}
    .grand{font-weight:700;font-size:1.1rem;border-top:2px solid #111;margin-top:8px;padding-top:8px}
    .footer{margin-top:32px;text-align:center;color:#aaa;font-size:.75rem}</style>
    </head><body>
    <h1>Pdf_Hair</h1>
    <div class="meta">
      <div><strong>Order:</strong> ${o.order_number}</div>
      <div><strong>Customer:</strong> ${o.customer_name}</div>
      <div><strong>Date:</strong> ${fmtDateTime(o.created_at)}</div>
      <div><strong>Status:</strong> ${o.status} | Payment: ${o.payment_status}</div>
    </div>
    <table>
      <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
      <tbody>${o.items.map(i=>`<tr><td>${i.product_name}</td><td>${i.quantity}</td><td>${fmtCurrency(i.unit_price)}</td><td>${fmtCurrency(i.line_total)}</td></tr>`).join('')}</tbody>
    </table>
    <div class="totals">
      <div class="row"><span>Subtotal</span><span>${fmtCurrency(o.subtotal)}</span></div>
      ${+o.discount>0?`<div class="row"><span>Discount</span><span>-${fmtCurrency(o.discount)}</span></div>`:''}
      ${+o.tax_rate>0?`<div class="row"><span>Tax (${o.tax_rate}%)</span><span>${fmtCurrency(o.tax_amount)}</span></div>`:''}
      <div class="row grand"><span>TOTAL</span><span>${fmtCurrency(o.total)}</span></div>
    </div>
    <div class="footer">Thank you for your business!</div>
    </body></html>`);
  win.document.close();
  win.print();
}

// ════════════════════════════════════════════════════════
// CUSTOMERS
// ════════════════════════════════════════════════════════
async function loadCustomers(search = '') {
  const grid = $('customers-grid');
  grid.innerHTML = '<div class="skeleton" style="height:200px;border-radius:14px"></div>';
  try {
    const data = await api(`api/customers.php?limit=100&search=${encodeURIComponent(search)}`);
    App.customers = data.data;
    $('customers-count').textContent = `${data.total} customers`;
    renderCustomersGrid(data.data);
  } catch (err) {
    toast('error', 'Failed to load customers', err.message);
  }
}

function renderCustomersGrid(customers) {
  const grid = $('customers-grid');
  grid.innerHTML = customers.length ? customers.map(c => `
    <div class="entity-card" onclick="viewCustomer(${c.id})">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="width:40px;height:40px;border-radius:50%;background:var(--gold-bg);color:var(--gold);display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0">${initials(c.name)}</div>
        <div>
          <div class="entity-card-name">${c.name}</div>
          <div style="font-size:.75rem;color:var(--text-muted)">${c.order_count} order${c.order_count != 1 ? 's' : ''}</div>
        </div>
      </div>
      <div class="entity-card-meta">
        ${c.email ? `<span>✉ ${c.email}</span>` : ''}
        ${c.phone ? `<span>📞 ${c.phone}</span>` : ''}
        ${c.address ? `<span>📍 ${c.address.substring(0,40)}${c.address.length>40?'…':''}</span>` : ''}
      </div>
      <div class="entity-card-footer">
        <span style="font-size:.75rem;color:var(--text-muted)">Since ${fmtDate(c.created_at)}</span>
        <div style="display:flex;gap:6px">
          <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation();editCustomer(${c.id})">Edit</button>
          ${App.user?.role === 'admin' ? `<button class="btn btn-danger btn-sm" onclick="event.stopPropagation();deleteCustomer(${c.id},'${c.name}')">Delete</button>` : ''}
        </div>
      </div>
    </div>
  `).join('') : `<div class="empty-state" style="grid-column:1/-1">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
    <h3>No customers yet</h3><p>Add your first customer</p></div>`;
}

async function viewCustomer(id) {
  openPanel('Customer Details', '<div class="skeleton" style="height:400px"></div>');
  const c = await api(`api/customers.php?id=${id}`);
  $('panel-body').innerHTML = `
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px">
      <div style="width:60px;height:60px;border-radius:50%;background:var(--gold-bg);color:var(--gold);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.3rem">${initials(c.name)}</div>
      <div>
        <div style="font-size:1.3rem;font-weight:700">${c.name}</div>
        <div style="color:var(--text-muted);font-size:.85rem">Customer since ${fmtDate(c.created_at)}</div>
      </div>
    </div>
    <div class="detail-grid" style="margin-bottom:24px">
      ${c.email   ? `<div class="detail-block"><div class="detail-block-label">Email</div><div class="detail-block-value">${c.email}</div></div>` : ''}
      ${c.phone   ? `<div class="detail-block"><div class="detail-block-label">Phone</div><div class="detail-block-value">${c.phone}</div></div>` : ''}
      ${c.address ? `<div class="detail-block" style="grid-column:1/-1"><div class="detail-block-label">Address</div><div class="detail-block-value">${c.address}</div></div>` : ''}
    </div>
    <div class="card-title" style="margin-bottom:12px">Recent Orders (${c.orders.length})</div>
    ${c.orders.length ? `<div class="table-wrap"><table>
      <thead><tr><th>Order #</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
      <tbody>${c.orders.map(o => `<tr style="cursor:pointer" onclick="viewOrder(${o.id})">
        <td><span class="order-num">${o.order_number}</span></td>
        <td><span class="badge badge-${o.status}">${o.status}</span></td>
        <td class="amount">${fmtCurrency(o.total)}</td>
        <td style="color:var(--text-muted);font-size:.8rem">${fmtDate(o.created_at)}</td>
      </tr>`).join('')}</tbody>
    </table></div>` : '<p style="color:var(--text-muted)">No orders yet</p>'}
  `;
  $('panel-footer').innerHTML = `
    <button class="btn btn-secondary" onclick="closePanel()">Close</button>
    <button class="btn btn-secondary" onclick="editCustomer(${id})">Edit</button>
  `;
}

function openCustomerForm(customer = null) {
  const html = `
    <div class="form-group"><label>Full Name *</label><input type="text" id="cf-name" value="${customer?.name || ''}" placeholder="Customer name"></div>
    <div class="form-group"><label>Email</label><input type="email" id="cf-email" value="${customer?.email || ''}" placeholder="email@example.com"></div>
    <div class="form-group"><label>Phone</label><input type="tel" id="cf-phone" value="${customer?.phone || ''}" placeholder="+234 800 000 0000"></div>
    <div class="form-group"><label>Address</label><textarea id="cf-address" placeholder="Delivery / billing address">${customer?.address || ''}</textarea></div>
    <div class="form-group"><label>Notes</label><textarea id="cf-notes" placeholder="Internal notes about this customer">${customer?.notes || ''}</textarea></div>
  `;
  openPanel(customer ? 'Edit Customer' : 'New Customer', html);
  $('panel-footer').innerHTML = `
    <button class="btn btn-secondary" onclick="closePanel()">Cancel</button>
    <button class="btn btn-primary" onclick="submitCustomerForm(${customer?.id || null})">Save Customer</button>
  `;
}

async function editCustomer(id) {
  const c = await api(`api/customers.php?id=${id}`);
  openCustomerForm(c);
}

async function submitCustomerForm(id) {
  const name = $('cf-name').value.trim();
  if (!name) { toast('warning', 'Name is required'); return; }
  const payload = {
    name, email: $('cf-email').value, phone: $('cf-phone').value,
    address: $('cf-address').value, notes: $('cf-notes').value,
  };
  try {
    if (id) { await api(`api/customers.php?id=${id}`, 'PUT', payload); toast('success', 'Customer updated'); }
    else     { await api('api/customers.php', 'POST', payload); toast('success', 'Customer created'); }
    closePanel();
    loadCustomers();
    App.customers = [];
  } catch (err) { toast('error', 'Failed', err.message); }
}

async function deleteCustomer(id, name) {
  showModal('Delete Customer', `<p>Delete <strong>${name}</strong>? Their order history will be preserved.</p>`,
    [{ label: 'Delete', class: 'btn-danger', action: async () => {
      await api(`api/customers.php?id=${id}`, 'DELETE');
      toast('success', 'Customer deleted'); loadCustomers(); App.customers = [];
    }}]);
}

// ════════════════════════════════════════════════════════
// PRODUCTS
// ════════════════════════════════════════════════════════
async function loadProducts(search = '') {
  const grid = $('products-grid');
  grid.innerHTML = '<div class="skeleton" style="height:200px;border-radius:14px"></div>';
  try {
    const data = await api(`api/products.php?active=1&search=${encodeURIComponent(search)}`);
    App.products = data.data;
    $('products-count').textContent = `${data.data.length} products`;
    renderProductsGrid(data.data);
  } catch (err) {
    toast('error', 'Failed to load products', err.message);
  }
}

function renderProductsGrid(products) {
  const grid = $('products-grid');
  grid.innerHTML = products.length ? products.map(p => `
    <div class="entity-card">
      <div class="entity-card-name">${p.name}</div>
      ${p.description ? `<div style="font-size:.8rem;color:var(--text-muted);margin-top:4px;margin-bottom:8px">${p.description}</div>` : ''}
      <div style="font-family:'JetBrains Mono',monospace;font-size:1.2rem;font-weight:700;color:var(--gold);margin:12px 0">${fmtCurrency(p.price)}</div>
      <div style="display:flex;gap:8px;align-items:center">
        <span style="font-size:.8rem;background:${p.stock_qty <= p.low_stock_alert ? 'var(--red-bg)' : 'var(--green-bg)'};color:${p.stock_qty <= p.low_stock_alert ? 'var(--red)' : 'var(--green)'};padding:3px 10px;border-radius:99px">
Stock: ${p.stock_qty}, Unit: ${p.unit}${p.stock_qty <= p.low_stock_alert ? ' ⚠' : ''}
        </span>
      </div>
      <div class="entity-card-footer">
        <span></span>
        <div style="display:flex;gap:6px">
          <button class="btn btn-secondary btn-sm" onclick="editProduct(${p.id})">Edit</button>
          ${App.user?.role === 'admin' ? `<button class="btn btn-danger btn-sm" onclick="deleteProduct(${p.id},'${p.name}')">Remove</button>` : ''}
        </div>
      </div>
    </div>
  `).join('') : `<div class="empty-state" style="grid-column:1/-1">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
    <h3>No products yet</h3><p>Add your products or services</p></div>`;
}

function openProductForm(product = null) {
  const html = `
    <div class="form-group"><label>Product / Service Name *</label><input type="text" id="pf-name" value="${product?.name || ''}" placeholder="e.g. Custom T-Shirt"></div>
    <div class="form-group"><label>Description</label><textarea id="pf-desc" placeholder="Short description…">${product?.description || ''}</textarea></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group"><label>Unit Price (₦) *</label><input type="number" id="pf-price" value="${product?.price || 0}" min="0" step="0.01"></div>
      <div class="form-group"><label>Unit</label><input type="text" id="pf-unit" value="${product?.unit || 'piece'}" placeholder="piece / kg / hr…"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group"><label>Stock Quantity</label><input type="number" id="pf-stock" value="${product?.stock_qty || 0}" min="0"></div>
      <div class="form-group"><label>Low Stock Alert</label><input type="number" id="pf-lowstock" value="${product?.low_stock_alert || 5}" min="0"></div>
    </div>
  `;
  openPanel(product ? 'Edit Product' : 'New Product', html);
  $('panel-footer').innerHTML = `
    <button class="btn btn-secondary" onclick="closePanel()">Cancel</button>
    <button class="btn btn-primary" onclick="submitProductForm(${product?.id || null})">Save Product</button>
  `;
}

async function editProduct(id) {
  const p = await api(`api/products.php?id=${id}`);
  openProductForm(p);
}

async function submitProductForm(id) {
  const name = $('pf-name').value.trim();
  if (!name) { toast('warning', 'Product name required'); return; }
  const payload = {
    name, description: $('pf-desc').value,
    price: parseFloat($('pf-price').value || 0),
    unit: $('pf-unit').value || 'piece',
    stock_qty: parseInt($('pf-stock').value || 0),
    low_stock_alert: parseInt($('pf-lowstock').value || 5),
  };
  try {
    if (id) { await api(`api/products.php?id=${id}`, 'PUT', payload); toast('success', 'Product updated'); }
    else     { await api('api/products.php', 'POST', payload); toast('success', 'Product added'); }
    closePanel(); loadProducts(); App.products = [];
  } catch (err) { toast('error', 'Failed', err.message); }
}

async function deleteProduct(id, name) {
  showModal('Remove Product', `<p>Remove <strong>${name}</strong> from your catalog?</p>`,
    [{ label: 'Remove', class: 'btn-danger', action: async () => {
      await api(`api/products.php?id=${id}`, 'DELETE');
      toast('success', 'Product removed'); loadProducts(); App.products = [];
    }}]);
}

// ════════════════════════════════════════════════════════
// UTILITIES
// ════════════════════════════════════════════════════════
function renderPagination(data, page, loadFn, paginationId, countId) {
  if (countId) $(countId).textContent = `${data.total} total`;
  const el = $(paginationId);
  if (!el) return;
  const total = data.total_pages || 1;
  const start = (page - 1) * data.limit + 1;
  const end   = Math.min(page * data.limit, data.total);
  el.innerHTML = `
    <div class="pagination-info">${data.total > 0 ? `Showing ${start}–${end} of ${data.total}` : 'No results'}</div>
    <div class="pagination-btns">
      <button class="page-btn" ${page <= 1 ? 'disabled' : ''} onclick="${loadFn.name}(${page-1})">‹ Prev</button>
      ${Array.from({length:Math.min(total,5)}, (_,i) => {
        const p = Math.max(1, Math.min(page-2, total-4)) + i;
        return p <= total ? `<button class="page-btn ${p===page?'active':''}" onclick="${loadFn.name}(${p})">${p}</button>` : '';
      }).join('')}
      <button class="page-btn" ${page >= total ? 'disabled' : ''} onclick="${loadFn.name}(${page+1})">Next ›</button>
    </div>
  `;
}

function skeletonRows(cols, rows) {
  return Array.from({length:rows}, () =>
    `<tr>${Array.from({length:cols}, () =>
      `<td><div class="skeleton" style="height:16px;border-radius:4px"></div></td>`
    ).join('')}</tr>`
  ).join('');
}

function exportOrders() {
  const f = App.ordersFilters;
  const params = new URLSearchParams({ type:'orders', ...(f.status && {status:f.status}), ...(f.date_from && {date_from:f.date_from}), ...(f.date_to && {date_to:f.date_to}) });
  window.open(`api/export.php?${params}`, '_blank');
}

// ── User Dropdown & Role Switch ──────────────────────────
function toggleUserDropdown() {
  const dropdown = $('user-dropdown');
  dropdown.classList.toggle('open');
}

function switchRole(role) {
  const btn = event.target;
  btn.disabled = true;
  btn.textContent = 'Switching…';
  
  api('api/auth.php?action=switch-role', 'POST', { role })
    .then(data => {
      App.user.role = data.user.role;
      updateRoleUI();
      toast('success', 'Role switched', data.message);
      $('user-role-text').textContent = data.user.role.toUpperCase();
    })
    .catch(err => {
      toast('error', 'Switch failed', err.data?.error || 'Try again');
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = btn.textContent.replace('Switching…', role === 'manager' ? 'Switch to Manager View' : 'Switch to Staff View');
      toggleUserDropdown();  // Close dropdown
    });
}

// ── Theme & UI Utils ─────────────────────────────────────
function toggleTheme() {
  document.documentElement.classList.toggle('light');
  const isLight = document.documentElement.classList.contains('light');
  localStorage.setItem('theme', isLight ? 'light' : 'dark');
  updateThemeIcon();
}

function togglePassword() {
  const passInput = $('login-pass');
  const isVisible = passInput.type === 'text';
  passInput.type = isVisible ? 'password' : 'text';
  qsa('.eye-toggle .eye-open, .eye-toggle .eye-closed').forEach(el => el.style.display = 'none');
  qsa(isVisible ? '.eye-closed' : '.eye-open').forEach(el => el.style.display = '');
}

function openAbout() {
  window.open('https://pdfhairs.com/', '_self');
}

function updateThemeIcon() {
  const icon = $('theme-icon');
  if (!icon) return;
  const isLight = document.documentElement.classList.contains('light');
  if (isLight) {
    icon.innerHTML = '<path stroke-width="2" stroke="currentColor" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
  } else {
    icon.innerHTML = `
      <circle cx="12" cy="12" r="5"/>
      <line x1="12" y1="1" x2="12" y2="3"/>
      <line x1="12" y1="21" x2="12" y2="23"/>
      <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
      <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
      <line x1="1" y1="12" x2="3" y2="12"/>
      <line x1="21" y1="12" x2="23" y2="12"/>
      <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
      <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>`;
  }
}

// ════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', async () => {
  // Load theme
  const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
  if (savedTheme === 'light') document.documentElement.classList.add('light');
  updateThemeIcon();

// Check existing session first (silent)
  try {
    const data = await api('api/auth.php?action=me');
    App.user = data.user;
    App.csrf = data.csrf;
    showApp(); // This calls loadNotifications()
  } catch (err) {
    if (err.status !== 401) {
      console.error('Session check failed:', err);
    }
    // 401 = no session, show login (silent)
    $('login-screen').style.display = 'flex';
  }


  // Login form
  $('login-form').addEventListener('submit', login);

  // Nav items
  qsa('.nav-item[data-section]').forEach(item => {
    item.addEventListener('click', () => navigate(item.dataset.section));
  });

  // Mobile menu
  $('menu-toggle').addEventListener('click', () => {
    $('sidebar').classList.toggle('open');
  });

  // Overlay
  $('panel-overlay').addEventListener('click', closePanel);
  $('modal-overlay').addEventListener('click', e => {
    if (e.target === $('modal-overlay')) closeModal();
  });

  // Order filters
  $('orders-search').addEventListener('input', debounce(e => {
    App.ordersFilters.search = e.target.value;
    loadOrders(1);
  }, 400));

  $('orders-status-filter').addEventListener('change', e => {
    App.ordersFilters.status = e.target.value;
    loadOrders(1);
  });

  $('orders-date-from').addEventListener('change', e => {
    App.ordersFilters.date_from = e.target.value;
    loadOrders(1);
  });

  $('orders-date-to').addEventListener('change', e => {
    App.ordersFilters.date_to = e.target.value;
    loadOrders(1);
  });

  // Customer search
  $('customers-search').addEventListener('input', debounce(e => loadCustomers(e.target.value), 400));

  // Product search
  $('products-search').addEventListener('input', debounce(e => loadProducts(e.target.value), 400));

  // Dashboard search
  $('dashboard-search')?.addEventListener('input', dashboardSearchHandler);

  // Keyboard shortcuts
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closePanel(); closeModal(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') { e.preventDefault(); openNewOrderForm(); }
  });
});

function debounce(fn, ms) {
  let timer;
  return function(...args) { clearTimeout(timer); timer = setTimeout(() => fn.apply(this, args), ms); };
}
