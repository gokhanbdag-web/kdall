CREATE TABLE IF NOT EXISTS shipping_carriers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 tracking_url_template VARCHAR(500) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 INDEX idx_shipping_carriers_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 shipment_number VARCHAR(50) NOT NULL UNIQUE,
 order_id BIGINT UNSIGNED NOT NULL,
 carrier_id BIGINT UNSIGNED NULL,
 tracking_number VARCHAR(120) NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'pending',
 shipping_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
 weight DECIMAL(10,3) NOT NULL DEFAULT 0,
 package_count INT UNSIGNED NOT NULL DEFAULT 1,
 shipped_at DATETIME NULL,
 delivered_at DATETIME NULL,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_shipment_order FOREIGN KEY(order_id) REFERENCES orders(id),
 CONSTRAINT fk_shipment_carrier FOREIGN KEY(carrier_id) REFERENCES shipping_carriers(id) ON DELETE SET NULL,
 CONSTRAINT fk_shipment_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_shipments_order(order_id),
 INDEX idx_shipments_status(status),
 INDEX idx_shipments_tracking(tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipment_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 shipment_id BIGINT UNSIGNED NOT NULL,
 from_status VARCHAR(30) NULL,
 to_status VARCHAR(30) NOT NULL,
 note VARCHAR(255) NULL,
 user_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_shipment_event_shipment FOREIGN KEY(shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
 CONSTRAINT fk_shipment_event_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_shipment_events(shipment_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('shipments.view','Kargo ve gönderileri görüntüle','shipping',NOW()),
('shipments.manage','Kargo ve gönderileri yönet','shipping',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('shipments.view','shipments.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='shipments.view' WHERE r.slug='staff';
