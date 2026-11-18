ALTER TABLE orders ADD COLUMN payment_method VARCHAR(30) NOT NULL DEFAULT 'manual';
ALTER TABLE orders ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS payment_gateway_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 provider VARCHAR(30) NOT NULL DEFAULT 'paytr',
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 test_mode TINYINT(1) NOT NULL DEFAULT 1,
 config_cipher MEDIUMTEXT NULL,
 updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(30) NOT NULL,
 provider_reference VARCHAR(100) NOT NULL UNIQUE,
 amount DECIMAL(15,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 provider_response MEDIUMTEXT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_payment_transaction_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 INDEX idx_payment_transactions_provider_status(provider,status),
 INDEX idx_payment_transactions_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('payments.view','Ödeme kayıtlarını görüntüle','payments',NOW()),
('payments.manage','Ödeme sağlayıcılarını yönet','payments',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('payments.view','payments.manage') WHERE r.slug='manager';
