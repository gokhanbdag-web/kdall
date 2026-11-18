ALTER TABLE purchase_orders ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'TRY';

CREATE TABLE IF NOT EXISTS invoices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_number VARCHAR(50) NOT NULL UNIQUE,
 invoice_type VARCHAR(20) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 customer_id BIGINT UNSIGNED NULL,
 supplier_id BIGINT UNSIGNED NULL,
 source_order_id BIGINT UNSIGNED NULL,
 source_purchase_id BIGINT UNSIGNED NULL,
 issue_date DATE NOT NULL,
 due_date DATE NULL,
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 notes TEXT NULL,
 external_reference VARCHAR(120) NULL,
 created_by BIGINT UNSIGNED NULL,
 issued_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_invoice_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 CONSTRAINT fk_invoice_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
 CONSTRAINT fk_invoice_order FOREIGN KEY(source_order_id) REFERENCES orders(id) ON DELETE SET NULL,
 CONSTRAINT fk_invoice_purchase FOREIGN KEY(source_purchase_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
 CONSTRAINT fk_invoice_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_invoice_type_status(invoice_type,status),
 INDEX idx_invoice_issue_date(issue_date),
 INDEX idx_invoice_customer(customer_id),
 INDEX idx_invoice_supplier(supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 description VARCHAR(190) NOT NULL,
 sku VARCHAR(80) NULL,
 quantity DECIMAL(15,3) NOT NULL,
 unit_price DECIMAL(15,2) NOT NULL,
 tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
 line_subtotal DECIMAL(15,2) NOT NULL,
 line_tax DECIMAL(15,2) NOT NULL,
 line_total DECIMAL(15,2) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_invoice_item_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
 CONSTRAINT fk_invoice_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 INDEX idx_invoice_items_invoice(invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE financial_transactions ADD COLUMN invoice_id BIGINT UNSIGNED NULL;
ALTER TABLE financial_transactions ADD INDEX idx_financial_tx_invoice(invoice_id);
ALTER TABLE financial_transactions ADD CONSTRAINT fk_financial_tx_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE SET NULL;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('invoices.view','Faturaları görüntüle','invoicing',NOW()),
('invoices.manage','Fatura oluştur ve yönet','invoicing',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('invoices.view','invoices.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='invoices.view' WHERE r.slug='staff';
