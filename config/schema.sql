CREATE DATABASE IF NOT EXISTS pdfhair_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pdfhair_db;
SET FOREIGN_KEY_CHECKS = 0;

-- create a new database user
CREATE USER 'pdfhair_user'@'127.0.0.1' IDENTIFIED BY 'Pdfhair@12345';

-- grant privileges to the database
GRANT ALL PRIVILEGES ON pdfhair_db.* TO 'pdfhair_user'@'127.0.0.1';

-- apply changes
FLUSH PRIVILEGES;

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
    type       ENUM('login','logout','order_created','order_status','low_stock') NOT NULL,
    related_id INT UNSIGNED NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_id, type),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created  (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for better activity_log performance
ALTER TABLE activity_log ADD INDEX idx_created_user (created_at, user_id);

-- Sample data
INSERT INTO notifications (user_id, title, message, type, related_id) VALUES
(1, 'Welcome!', 'Welcome to PdfHair dashboard', 'login', NULL),
(2, 'New login', 'Manager logged in', 'login', NULL);

SELECT 'Schema updated successfully!' AS status;
        
select * from users;
        
INSERT IGNORE INTO users (
    name, email, password_hash, role
) VALUES (
    'admin@pdfhair.com', 'System Admin', '$2a$12$ZQF5lmaJ/UZ1/Fkft28U5eDArhzva.UZNtA0y/8elypk9rXr58zuS', 'admin', 1, 1, NOW()
);

INSERT IGNORE INTO users (
    name, email, password_hash, role
) VALUES (
    'manager@pdfhair.com', 'Manager', '$2a$12$D5UefhGnpDnO3y.i6D239uZpAn.dRpJ7xhakBrcHmJxGcX2zfFWD6', 'manager', 1, 1, NOW()
);