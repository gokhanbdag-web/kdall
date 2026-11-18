CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL,
 message VARCHAR(500) NOT NULL,
 type VARCHAR(20) NOT NULL DEFAULT 'info',
 url VARCHAR(500) NULL,
 read_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_notification_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_notifications_user_read(user_id,read_at,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 rule_key VARCHAR(80) NOT NULL,
 reference_id VARCHAR(100) NOT NULL,
 run_date DATE NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'done',
 message VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_automation_daily(rule_key,reference_id,run_date),
 INDEX idx_automation_logs_date(run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('notifications.view','Bildirimleri görüntüle','notifications',NOW()),
('notifications.manage','Bildirim ve otomasyonları yönet','notifications',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin','manager','staff');
