CREATE TABLE IF NOT EXISTS marketplace_connections (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 marketplace VARCHAR(40) NOT NULL,
 store_name VARCHAR(150) NOT NULL,
 seller_id VARCHAR(80) NOT NULL,
 api_key_cipher TEXT NULL,
 api_secret_cipher TEXT NULL,
 environment VARCHAR(20) NOT NULL DEFAULT 'production',
 storefront VARCHAR(8) NOT NULL DEFAULT 'TR',
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 last_test_at DATETIME NULL,
 last_error VARCHAR(255) NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 INDEX idx_marketplace_connections_provider(marketplace,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_product_map (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 connection_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 external_barcode VARCHAR(80) NOT NULL,
 external_product_id VARCHAR(100) NULL,
 sync_enabled TINYINT(1) NOT NULL DEFAULT 1,
 last_synced_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_marketplace_map_connection FOREIGN KEY(connection_id) REFERENCES marketplace_connections(id) ON DELETE CASCADE,
 CONSTRAINT fk_marketplace_map_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 UNIQUE KEY uq_marketplace_map_product(connection_id,product_id),
 UNIQUE KEY uq_marketplace_map_barcode(connection_id,external_barcode),
 INDEX idx_marketplace_map_sync(connection_id,sync_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_sync_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 connection_id BIGINT UNSIGNED NOT NULL,
 job_type VARCHAR(40) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'queued',
 batch_request_id VARCHAR(120) NULL,
 item_count INT UNSIGNED NOT NULL DEFAULT 0,
 request_summary TEXT NULL,
 response_body MEDIUMTEXT NULL,
 error_message VARCHAR(1000) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_marketplace_job_connection FOREIGN KEY(connection_id) REFERENCES marketplace_connections(id) ON DELETE CASCADE,
 CONSTRAINT fk_marketplace_job_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_marketplace_jobs_connection(connection_id,created_at),
 INDEX idx_marketplace_jobs_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('marketplaces.view','Pazaryeri bağlantılarını görüntüle','marketplaces',NOW()),
('marketplaces.manage','Pazaryeri bağlantılarını yönet','marketplaces',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('marketplaces.view','marketplaces.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='marketplaces.view' WHERE r.slug='staff';
