CREATE TABLE IF NOT EXISTS financial_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 account_type VARCHAR(20) NOT NULL DEFAULT 'cash',
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
 bank_name VARCHAR(120) NULL,
 iban VARCHAR(34) NULL,
 notes VARCHAR(255) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 INDEX idx_financial_accounts_type(account_type),
 INDEX idx_financial_accounts_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 transaction_type VARCHAR(30) NOT NULL,
 direction VARCHAR(10) NOT NULL,
 amount DECIMAL(15,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 transaction_date DATE NOT NULL,
 party_type VARCHAR(20) NULL,
 party_id BIGINT UNSIGNED NULL,
 category VARCHAR(100) NULL,
 reference_no VARCHAR(100) NULL,
 transfer_code VARCHAR(50) NULL,
 description VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_financial_tx_account FOREIGN KEY(account_id) REFERENCES financial_accounts(id),
 CONSTRAINT fk_financial_tx_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_financial_tx_account_date(account_id,transaction_date),
 INDEX idx_financial_tx_party(party_type,party_id),
 INDEX idx_financial_tx_type(transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('finance.view','Finans kayıtlarını görüntüle','finance',NOW()),
('finance.manage','Finans kayıtlarını yönet','finance',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('finance.view','finance.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='finance.view' WHERE r.slug='staff';
