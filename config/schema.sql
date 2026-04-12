CREATE DATABASE IF NOT EXISTS pdfhair_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pdfhair_db;
SET FOREIGN_KEY_CHECKS = 0;

-- ⚠️  PRODUCTION: Create DB user manually with secure password
-- CREATE USER 'pdfhair_user'@'127.0.0.1' IDENTIFIED BY 'your_secure_password';
-- GRANT ALL PRIVILEGES ON pdfhair_db.* TO 'pdfhair_user'@'127.0.0.1';
-- FLUSH PRIVILEGES;
--
-- Use .env DB_PASS instead

-- users
CREATE TABLE IF NOT EXISTS users (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(120) NOT NULL,
            email         VARCHAR(191) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role          ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff',
            is_active     TINYINT(1) NOT NULL DEFAULT 1,
            last_login    DATETIME NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- customers
CREATE TABLE IF NOT EXISTS customers (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(191) NOT NULL,
            email      VARCHAR(191) NULL,
            phone      VARCHAR(30)  NULL,
            address    TEXT         NULL,
            notes      TEXT         NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name  (name),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- products
        CREATE TABLE IF NOT EXISTS products (
            id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name           VARCHAR(191) NOT NULL,
            description    TEXT         NULL,
            price          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            stock_qty      INT NOT NULL DEFAULT 0,
            unit           VARCHAR(30) NOT NULL DEFAULT 'piece',
            low_stock_alert INT NOT NULL DEFAULT 5,
            is_active      TINYINT(1) NOT NULL DEFAULT 1,
            created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- orders
        CREATE TABLE IF NOT EXISTS orders (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(30) NOT NULL UNIQUE,
            customer_id  INT UNSIGNED NULL,
            customer_name VARCHAR(191) NOT NULL DEFAULT '',
            status       ENUM('pending','confirmed','processing','shipped','delivered','cancelled')
                         NOT NULL DEFAULT 'pending',
            subtotal     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            discount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            tax_rate     DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
            tax_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
            payment_method VARCHAR(60) NULL,
            notes        TEXT NULL,
            created_by   INT UNSIGNED NULL,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL,
            INDEX idx_status     (status),
            INDEX idx_created_at (created_at),
            INDEX idx_customer   (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- order_items
        CREATE TABLE IF NOT EXISTS order_items (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id     INT UNSIGNED NOT NULL,
            product_id   INT UNSIGNED NULL,
            product_name VARCHAR(191) NOT NULL,
            quantity     DECIMAL(10,2) NOT NULL DEFAULT 1,
            unit_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            line_total   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            notes        VARCHAR(255) NULL,
            FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            INDEX idx_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- activity log   
        CREATE TABLE IF NOT EXISTS activity_log (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NULL,
            action      VARCHAR(60) NOT NULL,
            entity_type VARCHAR(60) NOT NULL,
            entity_id   INT UNSIGNED NOT NULL,
            detail      TEXT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_user   (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(120) NOT NULL,
    message    TEXT NOT NULL,
    type       ENUM('login','logout','order_created','order_status','low_stock','customer_created','product_created') NOT NULL,
    related_id INT UNSIGNED NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_id, type),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created  (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions tables for granular RBAC
CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for better activity_log performance
-- Note: ADD INDEX IF NOT EXISTS requires MySQL 8.0.14+. For older versions, these are safe to re-run or ignore errors.
-- ALTER TABLE activity_log ADD INDEX IF NOT EXISTS idx_created_user (created_at, user_id);
-- Alternatively, use this for all MySQL versions:
ALTER TABLE activity_log ADD INDEX idx_created_user (created_at, user_id);

-- Add missing columns for RBAC/soft deletes
-- Note: ADD COLUMN IF NOT EXISTS requires MySQL 8.0.3+. These statements are safe on first run.
-- On subsequent runs on MySQL < 8.0.3, they will fail - this is expected.

ALTER TABLE orders ADD COLUMN IF NOT EXISTS locked_by_manager TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE orders ADD INDEX idx_locked (locked_by_manager);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE orders ADD INDEX idx_deleted (deleted_at);

ALTER TABLE customers ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE customers ADD INDEX idx_deleted (deleted_at);

ALTER TABLE products ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE products ADD INDEX idx_deleted (deleted_at);

-- ⚠️  SAMPLE DATA (DEV ONLY) - Remove in production or create users manually via UI
-- Default: admin@pdfhair.com / admin123 (CHANGE IMMEDIATELY after first login!)
--
-- To generate new hash: php -r "echo password_hash('newpass', PASSWORD_BCRYPT);"

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('System Admin', 'admin@pdfhair.com', '$2a$12$OLxQ0JTUkMcpwXy44JDexuW9GZID1sLEKeFnRSAaI3DxLv2Gp/mPa', 'admin', 1)
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('Manager', 'manager@pdfhair.com', '$2a$12$7K7q/kwUbtc93X3C.FSa4umrddyT1XbvLaSbPDJCj69k6w3G8rDlG', 'manager', 1)
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('Staff', 'staff@pdfhair.com', '$2a$12$5kB4RHVdBQ1ZM.0mc7lQQuoO8fS5RkgVm3V9rSkMDkNlk5OoYH9cS', 'staff', 1)
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- Notifications (now after users exist)
INSERT INTO notifications (user_id, title, message, type, related_id) VALUES
(1, 'Welcome!', 'Welcome to PdfHair dashboard', 'login', NULL),
(2, 'New login', 'Manager logged in', 'login', NULL) ON DUPLICATE KEY UPDATE message=message;

-- Permissions (safe idempotent inserts)
INSERT INTO permissions (name, description) VALUES
('order.create', 'Create new orders'),
('order.read.own', 'Read own created orders'),
('order.read.all', 'Read all orders'),
('order.update.own', 'Update own orders before lock'),
('order.update.all', 'Update any order'),
('order.approve', 'Approve pending orders'),
('order.lock', 'Lock order after approve'),
('order.delete', 'Soft delete orders'),
('order.void', 'Void/cancel orders'),
('order.restore', 'Restore deleted orders'),
('order.override.lock', 'Override manager lock'),
('customer.create', 'Create new customers'),
('customer.read.search', 'Search/read customers for order creation'),
('customer.read.all', 'List all customers'),
('customer.update.all', 'Update any customer'),
('customer.delete', 'Soft delete customers'),
('customer.restore', 'Restore deleted customers'),
('product.create', 'Create new products'),
('product.read.search', 'Search/read products for order creation'),
('product.read.all', 'List all products'),
('product.update.all', 'Update any product'),
('product.delete', 'Soft delete products'),
('product.restore', 'Restore deleted products'),
('dashboard.view.global', 'Full dashboard overview (read-only for staff)'),
('dashboard.view.sales', 'Sales and revenue stats'),
('dashboard.view.inventory', 'Inventory and stock stats'),
('dashboard.system.logs', 'System logs and audit trail'),
('user.read.all', 'Read all users'),
('user.create', 'Create users'),
('user.update', 'Update users'),
('user.delete', 'Delete users')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign role bundles (idempotent)
INSERT IGNORE INTO user_permissions (user_id, permission_id) SELECT 1, id FROM permissions;
INSERT IGNORE INTO user_permissions (user_id, permission_id)
SELECT 2, p.id FROM permissions p WHERE p.name NOT REGEXP '^(order\\.(delete|restore|void|override)|customer\\.(delete|restore)|product\\.(delete|restore)|user\\.|dashboard\\.system\\.logs)$';
INSERT IGNORE INTO user_permissions (user_id, permission_id)
SELECT 3, p.id FROM permissions p WHERE p.name IN (
  'order.create', 'order.read.own', 'order.update.own',
  'customer.create', 'customer.read.search',
  'product.create', 'product.read.search'
);

SELECT '✅ Schema ready! DEV LOGIN: admin@pdfhair.com / admin123 (CHANGE ASAP!) | Copy .env.example → .env & update DB_PASS' AS status;
SELECT id, name, email, role FROM users WHERE is_active=1 ORDER BY role;
