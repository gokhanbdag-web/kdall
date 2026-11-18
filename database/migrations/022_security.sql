CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email_hash CHAR(64) NOT NULL,
 ip_address VARCHAR(45) NOT NULL,
 success TINYINT(1) NOT NULL DEFAULT 0,
 attempted_at DATETIME NOT NULL,
 INDEX idx_login_ip_time(ip_address,success,attempted_at),
 INDEX idx_login_email_time(email_hash,success,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 session_hash CHAR(64) NOT NULL UNIQUE,
 ip_address VARCHAR(45) NULL,
 user_agent VARCHAR(255) NULL,
 last_activity DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_user_session_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_user_sessions_user(user_id,revoked_at,last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 event_type VARCHAR(80) NOT NULL,
 severity VARCHAR(20) NOT NULL DEFAULT 'info',
 ip_address VARCHAR(45) NULL,
 message VARCHAR(500) NOT NULL,
 context_json TEXT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_security_event_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_security_events_date(event_type,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('security.view','Güvenlik kayıtlarını görüntüle','security',NOW()),
('security.manage','Oturum ve güvenlik ayarlarını yönet','security',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
