CREATE TABLE IF NOT EXISTS sales_returns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 return_number VARCHAR(50) NOT NULL UNIQUE,
 order_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'requested',
 reason VARCHAR(160) NOT NULL,
 notes TEXT NULL,
 refund_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 refunded_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 received_at DATETIME NULL,
 refunded_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_return_order FOREIGN KEY(order_id) REFERENCES orders(id),
 CONSTRAINT fk_return_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 CONSTRAINT fk_return_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_return_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_returns_order(order_id),
 INDEX idx_returns_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_return_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 return_id BIGINT UNSIGNED NOT NULL,
 order_item_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(190) NOT NULL,
 quantity DECIMAL(15,3) NOT NULL,
 restock_quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
 refund_amount DECIMAL(15,2) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_return_item_return FOREIGN KEY(return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
 CONSTRAINT fk_return_item_order_item FOREIGN KEY(order_item_id) REFERENCES order_items(id),
 CONSTRAINT fk_return_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 INDEX idx_return_items_return(return_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_return_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 return_id BIGINT UNSIGNED NOT NULL,
 from_status VARCHAR(20) NULL,
 to_status VARCHAR(20) NOT NULL,
 note VARCHAR(255) NULL,
 user_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_return_event_return FOREIGN KEY(return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
 CONSTRAINT fk_return_event_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_return_events(return_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('returns.view','İade ve değişimleri görüntüle','returns',NOW()),
('returns.manage','İade ve değişimleri yönet','returns',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('returns.view','returns.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='returns.view' WHERE r.slug='staff';
