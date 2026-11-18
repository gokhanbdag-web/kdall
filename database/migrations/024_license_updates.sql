CREATE TABLE IF NOT EXISTS license_activations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 license_id VARCHAR(120) NOT NULL,
 customer_name VARCHAR(190) NULL,
 edition VARCHAR(80) NULL,
 licensed_domain VARCHAR(190) NOT NULL,
 machine_fingerprint CHAR(64) NOT NULL,
 issued_at DATETIME NULL,
 expires_at DATETIME NULL,
 features_json TEXT NULL,
 signature_hash CHAR(64) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 activated_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_license_status(status,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS update_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 version VARCHAR(40) NOT NULL,
 commit_hash VARCHAR(64) NULL,
 status VARCHAR(20) NOT NULL,
 message VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_update_history_date(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('license.view','Lisans ve güncelleme durumunu görüntüle','license',NOW()),
('license.manage','Lisansı etkinleştir ve yönet','license',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
