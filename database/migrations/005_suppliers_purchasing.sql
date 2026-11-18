CREATE TABLE IF NOT EXISTS suppliers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 supplier_code VARCHAR(40) NULL UNIQUE,
 type VARCHAR(20) NOT NULL DEFAULT 'company',
 name VARCHAR(190) NOT NULL,
 contact_name VARCHAR(160) NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(40) NULL,
 tax_office VARCHAR(120) NULL,
 tax_number VARCHAR(40) NULL,
 address TEXT NULL,
 notes TEXT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 INDEX idx_suppliers_name(name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 purchase_number VARCHAR(50) NOT NULL UNIQUE,
 supplier_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'draft',
 subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 expected_at DATE NULL,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_purchase_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
 CONSTRAINT fk_purchase_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_purchase_status(status),
 INDEX idx_purchase_supplier(supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 purchase_order_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(190) NOT NULL,
 sku VARCHAR(80) NULL,
 quantity DECIMAL(15,3) NOT NULL,
 received_quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
 unit_cost DECIMAL(15,2) NOT NULL,
 tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
 line_subtotal DECIMAL(15,2) NOT NULL,
 line_tax DECIMAL(15,2) NOT NULL,
 line_total DECIMAL(15,2) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_purchase_item_order FOREIGN KEY(purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_purchase_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 INDEX idx_purchase_items_order(purchase_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_receipts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 purchase_order_id BIGINT UNSIGNED NOT NULL,
 receipt_number VARCHAR(50) NOT NULL UNIQUE,
 notes VARCHAR(255) NULL,
 received_by BIGINT UNSIGNED NULL,
 received_at DATETIME NOT NULL,
 CONSTRAINT fk_receipt_purchase FOREIGN KEY(purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_receipt_user FOREIGN KEY(received_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_receipts_purchase(purchase_order_id,received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_receipt_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 receipt_id BIGINT UNSIGNED NOT NULL,
 purchase_order_item_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 quantity DECIMAL(15,3) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_receipt_item_receipt FOREIGN KEY(receipt_id) REFERENCES purchase_receipts(id) ON DELETE CASCADE,
 CONSTRAINT fk_receipt_item_order_item FOREIGN KEY(purchase_order_item_id) REFERENCES purchase_order_items(id),
 CONSTRAINT fk_receipt_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('suppliers.view','Tedarikçileri görüntüle','purchasing',NOW()),
('suppliers.manage','Tedarikçileri yönet','purchasing',NOW()),
('purchases.view','Satın alma siparişlerini görüntüle','purchasing',NOW()),
('purchases.manage','Satın alma siparişlerini yönet','purchasing',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('suppliers.view','suppliers.manage','purchases.view','purchases.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('suppliers.view','purchases.view') WHERE r.slug='staff';
