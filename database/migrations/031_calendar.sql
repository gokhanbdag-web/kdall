CREATE TABLE IF NOT EXISTS calendar_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 description TEXT NULL,
 event_type VARCHAR(40) NOT NULL DEFAULT 'meeting',
 status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
 start_at DATETIME NOT NULL,
 end_at DATETIME NULL,
 location VARCHAR(190) NULL,
 customer_id BIGINT UNSIGNED NULL,
 assigned_user_id BIGINT UNSIGNED NULL,
 reminder_minutes SMALLINT UNSIGNED NULL DEFAULT 15,
 reminder_sent_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_calendar_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 CONSTRAINT fk_calendar_assignee FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_calendar_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_calendar_start_status(start_at,status),
 INDEX idx_calendar_reminder(status,reminder_sent_at,start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('calendar.view','Takvimi görüntüle','calendar',NOW()),
('calendar.manage','Takvim etkinliklerini yönet','calendar',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin','manager','staff');
