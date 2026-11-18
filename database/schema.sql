CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role VARCHAR(40) NOT NULL DEFAULT 'staff',
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 setting_key VARCHAR(190) NOT NULL UNIQUE,
 setting_value LONGTEXT NULL,
 updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_code VARCHAR(40) NULL UNIQUE,
 type VARCHAR(20) NOT NULL DEFAULT 'individual',
 name VARCHAR(190) NOT NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(40) NULL,
 tax_number VARCHAR(40) NULL,
 notes TEXT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 INDEX idx_customers_name(name),
 INDEX idx_customers_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sku VARCHAR(80) NULL UNIQUE,
 name VARCHAR(190) NOT NULL,
 description TEXT NULL,
 purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0,
 sale_price DECIMAL(15,2) NOT NULL DEFAULT 0,
 stock_quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 INDEX idx_products_name(name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_number VARCHAR(50) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'draft',
 subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 notes TEXT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_orders_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 INDEX idx_orders_status(status),
 INDEX idx_orders_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 description TEXT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'open',
 priority VARCHAR(20) NOT NULL DEFAULT 'normal',
 due_at DATETIME NULL,
 assigned_user_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 INDEX idx_tasks_status(status),
 CONSTRAINT fk_tasks_assignee FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 entity_type VARCHAR(80) NULL,
 entity_id BIGINT UNSIGNED NULL,
 ip_address VARCHAR(45) NULL,
 user_agent VARCHAR(255) NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_audit_created(created_at),
 INDEX idx_audit_entity(entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
