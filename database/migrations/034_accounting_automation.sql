ALTER TABLE accounting_journal_lines
 ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'TRY' AFTER account_id,
 ADD INDEX idx_accounting_line_currency(account_id,currency,journal_id);

ALTER TABLE accounting_journals
 ADD UNIQUE KEY uq_accounting_source(source_type,source_id);

INSERT IGNORE INTO accounting_accounts(code,name,account_type,is_active,created_at) VALUES
('391','Hesaplanan KDV','liability',1,NOW()),
('100','Kasa','asset',1,NOW()),
('102','Bankalar','asset',1,NOW()),
('120','Alıcılar','asset',1,NOW()),
('153','Ticari Mallar','asset',1,NOW()),
('191','İndirilecek KDV','asset',1,NOW()),
('320','Satıcılar','liability',1,NOW()),
('360','Ödenecek Vergi ve Fonlar','liability',1,NOW()),
('500','Sermaye','equity',1,NOW()),
('600','Yurtiçi Satışlar','income',1,NOW()),
('610','Satıştan İadeler / İskontolar','income',1,NOW()),
('621','Satılan Ticari Mallar Maliyeti','expense',1,NOW()),
('760','Pazarlama Satış Dağıtım Giderleri','expense',1,NOW()),
('770','Genel Yönetim Giderleri','expense',1,NOW()),
('780','Finansman Giderleri','expense',1,NOW());
