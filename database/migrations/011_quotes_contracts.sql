CREATE TABLE IF NOT EXISTS quotes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quote_number VARCHAR(50) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 valid_until DATE NULL,
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 converted_order_id BIGINT UNSIGNED NULL,
 accepted_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_quote_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
 CONSTRAINT fk_quote_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_quote_order FOREIGN KEY(converted_order_id) REFERENCES orders(id) ON DELETE SET NULL,
 INDEX idx_quotes_customer(customer_id),
 INDEX idx_quotes_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quote_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(190) NOT NULL,
 sku VARCHAR(80) NULL,
 quantity DECIMAL(15,3) NOT NULL,
 unit_price DECIMAL(15,2) NOT NULL,
 tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
 line_subtotal DECIMAL(15,2) NOT NULL,
 line_tax DECIMAL(15,2) NOT NULL,
 line_total DECIMAL(15,2) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_quote_item_quote FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
 CONSTRAINT fk_quote_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 INDEX idx_quote_items_quote(quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 contract_number VARCHAR(50) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 quote_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 start_date DATE NULL,
 end_date DATE NULL,
 contract_value DECIMAL(15,2) NOT NULL DEFAULT 0,
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 terms LONGTEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_contract_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
 CONSTRAINT fk_contract_quote FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
 CONSTRAINT fk_contract_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_contract_customer(customer_id),
 INDEX idx_contract_status(status),
 INDEX idx_contract_end(end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('quotes.view','Teklifleri görüntüle','sales_documents',NOW()),
('quotes.manage','Teklifleri yönet','sales_documents',NOW()),
('contracts.view','Sözleşmeleri görüntüle','sales_documents',NOW()),
('contracts.manage','Sözleşmeleri yönet','sales_documents',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('quotes.view','quotes.manage','contracts.view','contracts.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('quotes.view','contracts.view') WHERE r.slug='staff';
