CREATE TABLE IF NOT EXISTS api_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 token_prefix VARCHAR(20) NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 scopes_json TEXT NOT NULL,
 last_used_at DATETIME NULL,
 expires_at DATETIME NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_api_token_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_api_tokens_user(user_id,revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_request_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 token_id BIGINT UNSIGNED NULL,
 method VARCHAR(10) NOT NULL,
 request_path VARCHAR(500) NOT NULL,
 ip_address VARCHAR(45) NULL,
 status_code SMALLINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_api_log_token FOREIGN KEY(token_id) REFERENCES api_tokens(id) ON DELETE SET NULL,
 INDEX idx_api_logs_token_date(token_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_endpoints (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 url VARCHAR(500) NOT NULL,
 secret_cipher TEXT NOT NULL,
 events_json TEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_webhook_endpoint_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_webhook_endpoint_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 endpoint_id BIGINT UNSIGNED NOT NULL,
 event_name VARCHAR(100) NOT NULL,
 payload_json LONGTEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME NOT NULL,
 response_code SMALLINT UNSIGNED NULL,
 response_body VARCHAR(1000) NULL,
 delivered_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_webhook_delivery_endpoint FOREIGN KEY(endpoint_id) REFERENCES webhook_endpoints(id) ON DELETE CASCADE,
 INDEX idx_webhook_delivery_queue(status,next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('api.view','API anahtarlarını ve webhookları görüntüle','integrations',NOW()),
('api.manage','API anahtarlarını ve webhookları yönet','integrations',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
