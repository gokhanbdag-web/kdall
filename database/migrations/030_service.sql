CREATE TABLE IF NOT EXISTS service_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 service_number VARCHAR(40) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NULL,
 device_name VARCHAR(190) NOT NULL,
 serial_number VARCHAR(120) NULL,
 issue_description TEXT NOT NULL,
 diagnosis TEXT NULL,
 resolution TEXT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'received',
 priority VARCHAR(20) NOT NULL DEFAULT 'normal',
 assigned_user_id BIGINT UNSIGNED NULL,
 estimated_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
 actual_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
 received_at DATETIME NOT NULL,
 due_at DATE NULL,
 delivered_at DATETIME NULL,
 stock_deducted_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_service_job_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 CONSTRAINT fk_service_job_assignee FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_service_job_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_service_jobs_status(status,priority,received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_job_parts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 service_job_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 product_name VARCHAR(190) NOT NULL,
 quantity DECIMAL(15,3) NOT NULL,
 unit_price DECIMAL(15,2) NOT NULL,
 line_total DECIMAL(15,2) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_service_part_job FOREIGN KEY(service_job_id) REFERENCES service_jobs(id) ON DELETE CASCADE,
 CONSTRAINT fk_service_part_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
 INDEX idx_service_job_parts_job(service_job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_job_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 service_job_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 from_status VARCHAR(30) NULL,
 to_status VARCHAR(30) NOT NULL,
 note VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_service_history_job FOREIGN KEY(service_job_id) REFERENCES service_jobs(id) ON DELETE CASCADE,
 CONSTRAINT fk_service_history_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_service_history_job(service_job_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('service.view','Teknik servis kayıtlarını görüntüle','service',NOW()),
('service.manage','Teknik servis iş emirlerini yönet','service',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin','manager','staff');
