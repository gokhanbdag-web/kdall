CREATE TABLE IF NOT EXISTS accounting_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) NOT NULL UNIQUE,
 name VARCHAR(190) NOT NULL,
 account_type VARCHAR(20) NOT NULL,
 parent_id BIGINT UNSIGNED NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_accounting_parent FOREIGN KEY(parent_id) REFERENCES accounting_accounts(id) ON DELETE SET NULL,
 INDEX idx_accounting_type(account_type,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_journals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 journal_no VARCHAR(40) NOT NULL UNIQUE,
 journal_date DATE NOT NULL,
 description VARCHAR(255) NOT NULL,
 source_type VARCHAR(40) NULL,
 source_id BIGINT UNSIGNED NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'posted',
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_accounting_journal_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_accounting_journal_date(journal_date,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_journal_lines (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 journal_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 debit DECIMAL(15,2) NOT NULL DEFAULT 0,
 credit DECIMAL(15,2) NOT NULL DEFAULT 0,
 line_description VARCHAR(255) NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_accounting_line_journal FOREIGN KEY(journal_id) REFERENCES accounting_journals(id) ON DELETE CASCADE,
 CONSTRAINT fk_accounting_line_account FOREIGN KEY(account_id) REFERENCES accounting_accounts(id),
 INDEX idx_accounting_line_account(account_id,journal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO accounting_accounts(code,name,account_type,is_active,created_at) VALUES
('100','Kasa','asset',1,NOW()),
('102','Bankalar','asset',1,NOW()),
('120','Alıcılar','asset',1,NOW()),
('153','Ticari Mallar','asset',1,NOW()),
('191','İndirilecek KDV','asset',1,NOW()),
('320','Satıcılar','liability',1,NOW()),
('360','Ödenecek Vergi ve Fonlar','liability',1,NOW()),
('500','Sermaye','equity',1,NOW()),
('600','Yurtiçi Satışlar','income',1,NOW()),
('610','Satıştan İadeler','income',1,NOW()),
('621','Satılan Ticari Mallar Maliyeti','expense',1,NOW()),
('760','Pazarlama Satış Dağıtım Giderleri','expense',1,NOW()),
('770','Genel Yönetim Giderleri','expense',1,NOW()),
('780','Finansman Giderleri','expense',1,NOW());

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('accounting.view','Muhasebe kayıtlarını görüntüle','accounting',NOW()),
('accounting.manage','Muhasebe kayıtlarını yönet','accounting',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='accounting.view' WHERE r.slug IN ('manager','staff');
