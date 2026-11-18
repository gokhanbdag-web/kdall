CREATE TABLE IF NOT EXISTS data_transfer_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 transfer_type VARCHAR(20) NOT NULL,
 entity VARCHAR(40) NOT NULL,
 file_name VARCHAR(255) NULL,
 rows_total INT UNSIGNED NOT NULL DEFAULT 0,
 rows_success INT UNSIGNED NOT NULL DEFAULT 0,
 rows_failed INT UNSIGNED NOT NULL DEFAULT 0,
 details_json MEDIUMTEXT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_data_transfer_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_data_transfer_date(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('data.view','Veri içe ve dışa aktarma ekranını görüntüle','data',NOW()),
('data.manage','Veri içe ve dışa aktarma işlemlerini yönet','data',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
