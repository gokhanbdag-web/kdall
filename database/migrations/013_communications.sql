CREATE TABLE IF NOT EXISTS communication_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 smtp_host VARCHAR(190) NOT NULL,
 smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
 smtp_encryption VARCHAR(10) NOT NULL DEFAULT 'tls',
 smtp_username VARCHAR(190) NULL,
 smtp_password_cipher TEXT NULL,
 from_email VARCHAR(190) NOT NULL,
 from_name VARCHAR(190) NOT NULL,
 updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 template_key VARCHAR(100) NOT NULL UNIQUE,
 channel VARCHAR(20) NOT NULL DEFAULT 'email',
 name VARCHAR(150) NOT NULL,
 subject VARCHAR(190) NULL,
 body LONGTEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 INDEX idx_message_templates_channel(channel,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 channel VARCHAR(20) NOT NULL,
 recipient VARCHAR(190) NOT NULL,
 subject VARCHAR(190) NULL,
 body LONGTEXT NOT NULL,
 status VARCHAR(20) NOT NULL,
 provider_response VARCHAR(1000) NULL,
 sent_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_message_log_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_message_logs_channel_status(channel,status),
 INDEX idx_message_logs_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('communications.view','İletişim kayıtlarını görüntüle','communications',NOW()),
('communications.manage','İletişim ayarlarını ve mesajları yönet','communications',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('communications.view','communications.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='communications.view' WHERE r.slug='staff';
