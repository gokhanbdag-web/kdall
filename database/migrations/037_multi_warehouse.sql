CREATE TABLE IF NOT EXISTS warehouses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(40) NOT NULL UNIQUE,
 name VARCHAR(140) NOT NULL,
 address VARCHAR(255) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 is_default TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 INDEX idx_warehouses_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO warehouses (code,name,status,is_default,created_at)
VALUES ('MAIN','Merkez Depo','active',1,NOW());

CREATE TABLE IF NOT EXISTS product_warehouse_stocks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 warehouse_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_product_warehouse(product_id,warehouse_id),
 INDEX idx_warehouse_stock(warehouse_id,product_id),
 CONSTRAINT fk_pws_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 CONSTRAINT fk_pws_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO product_warehouse_stocks (product_id,warehouse_id,quantity,updated_at)
SELECT p.id,w.id,p.stock_quantity,NOW()
FROM products p CROSS JOIN warehouses w WHERE w.is_default=1;

ALTER TABLE product_stock_movements
 ADD COLUMN warehouse_id BIGINT UNSIGNED NULL AFTER product_id,
 ADD COLUMN transfer_id BIGINT UNSIGNED NULL AFTER warehouse_id,
 ADD INDEX idx_stock_movement_warehouse(warehouse_id,created_at),
 ADD INDEX idx_stock_movement_transfer(transfer_id);

UPDATE product_stock_movements m
JOIN warehouses w ON w.is_default=1
SET m.warehouse_id=w.id
WHERE m.warehouse_id IS NULL;

ALTER TABLE product_stock_movements
 ADD CONSTRAINT fk_stock_movement_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS warehouse_transfers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 transfer_number VARCHAR(50) NOT NULL UNIQUE,
 from_warehouse_id BIGINT UNSIGNED NOT NULL,
 to_warehouse_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'completed',
 note VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_transfer_from FOREIGN KEY(from_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_transfer_to FOREIGN KEY(to_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_transfer_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_transfer_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE product_stock_movements
 ADD CONSTRAINT fk_stock_movement_transfer FOREIGN KEY(transfer_id) REFERENCES warehouse_transfers(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS warehouse_transfer_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 transfer_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(15,3) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_transfer_item_transfer FOREIGN KEY(transfer_id) REFERENCES warehouse_transfers(id) ON DELETE CASCADE,
 CONSTRAINT fk_transfer_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
 INDEX idx_transfer_item_product(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('warehouses.view','Depoları görüntüle','inventory',NOW()),
('warehouses.manage','Depoları ve transferleri yönet','inventory',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('owner','admin','manager') AND p.permission_key IN ('warehouses.view','warehouses.manage');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='warehouses.view'
WHERE r.slug='staff';
