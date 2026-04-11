# OrderPro — Order Management System
**Version 1.0 | PHP + MySQL + Vanilla JS**

---

## 🚀 Quick Setup (5 minutes)

### Requirements
- PHP 8.0+ (with PDO & PDO_MySQL)
- MySQL 5.7+ or MariaDB 10.3+
- Web server: Apache / Nginx / XAMPP / Laragon

---

### Step 1 — Upload Files
Upload the entire `orderpro/` folder to your web server root (e.g., `/var/www/html/orderpro/` or XAMPP's `htdocs/orderpro/`).

### Step 2 — Configure Database
Open `config/database.php` and update:
```php
define('DB_HOST',    'localhost');   // Your MySQL host
define('DB_PORT',    '3306');        // MySQL port
define('DB_NAME',    'orderpro_db'); // Database name (auto-created)
define('DB_USER',    'root');        // Your MySQL username
define('DB_PASS',    '');            // Your MySQL password
```

### Step 3 — Install Database
1. Visit: `http://yourdomain.com/orderpro/install.php`
2. Click **"Install Now"**
3. Installation creates all tables + default admin account

### Step 4 — Login
- Visit: `http://yourdomain.com/orderpro/`
- **Email:** `admin@orderpro.com`
- **Password:** `Admin@1234`

### Step 5 — Secure Your Installation
⚠️ **Delete `install.php` immediately after first login!**
```bash
rm install.php
```

---

## 📁 File Structure
```
orderpro/
├── index.php              # Main SPA entry point
├── install.php            # One-click installer (DELETE AFTER USE)
├── config/
│   ├── database.php       # DB configuration & connection
│   └── helpers.php        # Session, auth, security helpers
├── api/
│   ├── auth.php           # Login / logout / session
│   ├── dashboard.php      # Dashboard stats & charts
│   ├── orders.php         # Orders CRUD
│   ├── customers.php      # Customers CRUD
│   ├── products.php       # Products CRUD
│   └── export.php         # CSV export
└── assets/
    ├── css/style.css      # Premium dark theme
    └── js/app.js          # SPA application logic
```

---

## ✨ Features

### Core
- ✅ Create, read, update, delete orders
- ✅ Automatic order number generation (ORD-YYYYMMDD-XXXXX)
- ✅ **Duplicate order detection** (warns if same customer orders within 5 min)
- ✅ Auto-calculated subtotal, discount, tax, and grand total
- ✅ Order status workflow: Pending → Confirmed → Processing → Shipped → Delivered
- ✅ Payment status tracking: Unpaid / Partial / Paid
- ✅ Activity timeline per order (who did what, when)

### Dashboard
- ✅ Live stats: Total orders, revenue, customers, products
- ✅ Today's orders and revenue at a glance
- ✅ Revenue + Orders chart (last 7 days)
- ✅ Order status doughnut chart
- ✅ Low stock alerts
- ✅ Top products by revenue
- ✅ Recent orders table

### Customers
- ✅ Full customer CRM (name, email, phone, address, notes)
- ✅ Customer order history
- ✅ Search and filter

### Products
- ✅ Product/service catalog with pricing
- ✅ Stock quantity tracking with low-stock alerts
- ✅ Customizable units (piece, kg, hour, etc.)

### UX & Design
- ✅ Premium dark theme with gold accents
- ✅ Fully responsive (mobile, tablet, desktop)
- ✅ Smooth slide-in panel for order entry
- ✅ Animated stat counters
- ✅ Toast notifications
- ✅ Keyboard shortcuts (Ctrl+N = New Order, Esc = Close)
- ✅ Print receipt functionality
- ✅ CSV export (orders, customers, products)

### Security
- ✅ PDO prepared statements (SQL injection prevention)
- ✅ Password hashing with bcrypt (cost 12)
- ✅ Session-based authentication
- ✅ XSS prevention (htmlspecialchars on all output)
- ✅ Role-based access (admin / manager / staff)
- ✅ HttpOnly + SameSite=Strict session cookies
- ✅ CSRF token generation

---

## 🔒 Production Checklist
- [ ] Delete `install.php`
- [ ] Change default admin password immediately
- [ ] Set `'secure' => true` in `config/helpers.php` (requires HTTPS)
- [ ] Use a strong DB password
- [ ] Place DB credentials in environment variables (not in code)
- [ ] Enable HTTPS / SSL certificate

---

## 🛠️ Customization

### Change Currency Symbol
In `assets/js/app.js`, find:
```js
const fmtCurrency = n => '₦' + ...
```
Change `₦` to your currency symbol (e.g., `$`, `€`, `£`).

### Change Tax Rate Default
In the order form JS, change `tax_rate` default value.

### Add New Users
Via MySQL:
```sql
INSERT INTO users (name, email, password_hash, role)
VALUES ('Jane Doe', 'jane@example.com', '$2y$12$...', 'staff');
```
(Generate password hash using PHP's `password_hash()`)

---

## 📞 Support
Built for small-to-medium businesses that need structured, professional order management without complexity.
