CREATE TABLE IF NOT EXISTS warehouse_stocktakes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 stocktake_number VARCHAR(50) NOT NULL UNIQUE,
 warehouse_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 notes VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 completed_by BIGINT UNSIGNED NULL,
 completed_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_stocktake_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_stocktake_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_stocktake_completer FOREIGN KEY(completed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_stocktake_status_created(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouse_stocktake_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 stocktake_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 expected_quantity DECIMAL(15,3) NOT NULL,
 counted_quantity DECIMAL(15,3) NULL,
 variance DECIMAL(15,3) NULL,
 counted_at DATETIME NULL,
 UNIQUE KEY uq_stocktake_product(stocktake_id,product_id),
 CONSTRAINT fk_stocktake_item_session FOREIGN KEY(stocktake_id) REFERENCES warehouse_stocktakes(id) ON DELETE CASCADE,
 CONSTRAINT fk_stocktake_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
 INDEX idx_stocktake_item_product(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
