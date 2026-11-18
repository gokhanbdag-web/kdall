CREATE TABLE IF NOT EXISTS quality_checks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 check_number VARCHAR(40) NOT NULL UNIQUE,
 check_type VARCHAR(40) NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 reference_type VARCHAR(40) NULL,
 reference_id BIGINT UNSIGNED NULL,
 quantity DECIMAL(15,3) NOT NULL DEFAULT 1,
 result VARCHAR(20) NOT NULL DEFAULT 'pending',
 inspector_user_id BIGINT UNSIGNED NULL,
 checked_at DATETIME NULL,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_quality_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 CONSTRAINT fk_quality_inspector FOREIGN KEY(inspector_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_quality_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_quality_checks_result(result,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quality_check_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quality_check_id BIGINT UNSIGNED NOT NULL,
 parameter_name VARCHAR(190) NOT NULL,
 expected_value VARCHAR(190) NULL,
 actual_value VARCHAR(190) NULL,
 passed TINYINT(1) NOT NULL DEFAULT 0,
 notes VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_quality_item_check FOREIGN KEY(quality_check_id) REFERENCES quality_checks(id) ON DELETE CASCADE,
 INDEX idx_quality_items_check(quality_check_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('quality.view','Kalite kontrol kayıtlarını görüntüle','quality',NOW()),
('quality.manage','Kalite kontrol süreçlerini yönet','quality',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin','manager','staff');
