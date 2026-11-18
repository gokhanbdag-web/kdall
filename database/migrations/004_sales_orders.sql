CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
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
 CONSTRAINT fk_order_items_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_order_items_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 INDEX idx_order_items_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
 ADD COLUMN stock_deducted_at DATETIME NULL,
 ADD COLUMN stock_reversed_at DATETIME NULL;

ALTER TABLE product_stock_movements
 ADD COLUMN reference_type VARCHAR(40) NULL,
 ADD COLUMN reference_id BIGINT UNSIGNED NULL,
 ADD INDEX idx_stock_movement_reference(reference_type,reference_id);

CREATE TABLE IF NOT EXISTS order_status_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 from_status VARCHAR(30) NULL,
 to_status VARCHAR(30) NOT NULL,
 note VARCHAR(255) NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_order_history_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_order_history_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_order_history_order(order_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
