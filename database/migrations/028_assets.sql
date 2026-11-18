CREATE TABLE IF NOT EXISTS asset_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fixed_assets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_code VARCHAR(40) NOT NULL UNIQUE,
 name VARCHAR(190) NOT NULL,
 category_id BIGINT UNSIGNED NULL,
 serial_number VARCHAR(120) NULL,
 purchase_date DATE NOT NULL,
 purchase_cost DECIMAL(15,2) NOT NULL,
 residual_value DECIMAL(15,2) NOT NULL DEFAULT 0,
 useful_life_months SMALLINT UNSIGNED NOT NULL DEFAULT 60,
 status VARCHAR(30) NOT NULL DEFAULT 'in_use',
 assigned_employee_id BIGINT UNSIGNED NULL,
 location VARCHAR(190) NULL,
 warranty_end DATE NULL,
 notes TEXT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_fixed_asset_category FOREIGN KEY(category_id) REFERENCES asset_categories(id) ON DELETE SET NULL,
 INDEX idx_fixed_assets_status(status,purchase_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_maintenance (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_id BIGINT UNSIGNED NOT NULL,
 maintenance_type VARCHAR(60) NOT NULL,
 description VARCHAR(500) NOT NULL,
 cost DECIMAL(15,2) NOT NULL DEFAULT 0,
 performed_at DATE NOT NULL,
 next_due_at DATE NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_asset_maintenance_asset FOREIGN KEY(asset_id) REFERENCES fixed_assets(id) ON DELETE CASCADE,
 CONSTRAINT fk_asset_maintenance_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_asset_maintenance_due(next_due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('assets.view','Demirbaşları görüntüle','assets',NOW()),
('assets.manage','Demirbaşları ve bakımları yönet','assets',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin','manager');
