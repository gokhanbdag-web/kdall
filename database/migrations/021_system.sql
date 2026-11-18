CREATE TABLE IF NOT EXISTS system_backups (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 file_name VARCHAR(190) NOT NULL,
 storage_path VARCHAR(500) NOT NULL,
 size_bytes BIGINT UNSIGNED NOT NULL,
 sha256 CHAR(64) NOT NULL,
 table_count INT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(20) NOT NULL DEFAULT 'complete',
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_system_backup_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_system_backups_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_type VARCHAR(80) NOT NULL,
 severity VARCHAR(20) NOT NULL DEFAULT 'info',
 message VARCHAR(1000) NOT NULL,
 context_json TEXT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_system_events_type_date(event_type,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('system.view','Sistem durumunu görüntüle','system',NOW()),
('system.manage','Sistem yedeklerini ve yönetimini kullan','system',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
