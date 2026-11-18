-- Bring existing finance account opening balances into the ledger.
INSERT IGNORE INTO accounting_journals(journal_no,journal_date,description,source_type,source_id,created_by,created_at)
SELECT CONCAT('OTO-OPEN-',fa.id),DATE(fa.created_at),CONCAT('Açılış bakiyesi · ',fa.name),'finance_account_opening',fa.id,NULL,NOW()
FROM financial_accounts fa WHERE fa.opening_balance>0 AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=cash.id AND x.currency=fa.currency AND x.debit=fa.opening_balance AND x.credit=0 AND x.line_description='Kasa/banka açılış bakiyesi');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,cash.id,fa.currency,fa.opening_balance,0,'Kasa/banka açılış bakiyesi',NOW()
FROM financial_accounts fa
JOIN accounting_journals j ON j.source_type='finance_account_opening' AND j.source_id=fa.id
JOIN accounting_accounts cash ON cash.code=IF(fa.account_type='bank','102','100')
WHERE fa.opening_balance>0 AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=capital.id AND x.currency=fa.currency AND x.debit=0 AND x.credit=fa.opening_balance AND x.line_description='Açılış sermayesi');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,capital.id,fa.currency,0,fa.opening_balance,'Açılış sermayesi',NOW()
FROM financial_accounts fa
JOIN accounting_journals j ON j.source_type='finance_account_opening' AND j.source_id=fa.id
JOIN accounting_accounts capital ON capital.code='500'
WHERE fa.opening_balance>0;

-- Bring already issued invoices into the ledger before syncing their payments.
INSERT IGNORE INTO accounting_journals(journal_no,journal_date,description,source_type,source_id,created_by,created_at)
SELECT CONCAT('OTO-INV-',i.id),i.issue_date,CONCAT('Fatura ',i.invoice_number),'invoice',i.id,i.created_by,NOW()
FROM invoices i WHERE i.status IN ('issued','paid') AND i.invoice_type IN ('sales','purchase');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,a.id,i.currency,i.grand_total,0,'Satış faturası alacağı',NOW()
FROM invoices i JOIN accounting_journals j ON j.source_type='invoice' AND j.source_id=i.id
JOIN accounting_accounts a ON a.code='120'
WHERE i.status IN ('issued','paid') AND i.invoice_type='sales' AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=a.id AND x.currency=i.currency AND x.debit=i.grand_total AND x.credit=0 AND x.line_description='Satış faturası alacağı');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,a.id,i.currency,0,i.subtotal,'Satış geliri',NOW()
FROM invoices i JOIN accounting_journals j ON j.source_type='invoice' AND j.source_id=i.id
JOIN accounting_accounts a ON a.code='600'
WHERE i.status IN ('issued','paid') AND i.invoice_type='sales' AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=a.id AND x.currency=i.currency AND x.debit=0 AND x.credit=i.subtotal AND x.line_description='Satış geliri');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,a.id,i.currency,0,i.tax_total,'Hesaplanan KDV',NOW()
FROM invoices i JOIN accounting_journals j ON j.source_type='invoice' AND j.source_id=i.id
JOIN accounting_accounts a ON a.code='391'
WHERE i.status IN ('issued','paid') AND i.invoice_type='sales' AND i.tax_total>0 AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=a.id AND x.currency=i.currency AND x.debit=0 AND x.credit=i.tax_total AND x.line_description='Hesaplanan KDV');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,a.id,i.currency,i.discount_total,0,'Satış iskontosu',NOW()
FROM invoices i JOIN accounting_journals j ON j.source_type='invoice' AND j.source_id=i.id
JOIN accounting_accounts a ON a.code='610'
WHERE i.status IN ('issued','paid') AND i.invoice_type='sales' AND i.discount_total>0 AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=a.id AND x.currency=i.currency AND x.debit=i.discount_total AND x.credit=0 AND x.line_description='Satış iskontosu');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,a.id,i.currency,i.subtotal,0,'Alış / stok maliyeti',NOW()
FROM invoices i JOIN accounting_journals j ON j.source_type='invoice' AND j.source_id=i.id
JOIN accounting_accounts a ON a.code='153'
WHERE i.status IN ('issued','paid') AND i.invoice_type='purchase' AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=a.id AND x.currency=i.currency AND x.debit=i.subtotal AND x.credit=0 AND x.line_description='Alış / stok maliyeti');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,a.id,i.currency,i.tax_total,0,'İndirilecek KDV',NOW()
FROM invoices i JOIN accounting_journals j ON j.source_type='invoice' AND j.source_id=i.id
JOIN accounting_accounts a ON a.code='191'
WHERE i.status IN ('issued','paid') AND i.invoice_type='purchase' AND i.tax_total>0 AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=a.id AND x.currency=i.currency AND x.debit=i.tax_total AND x.credit=0 AND x.line_description='İndirilecek KDV');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,a.id,i.currency,0,i.grand_total,'Satıcı borcu',NOW()
FROM invoices i JOIN accounting_journals j ON j.source_type='invoice' AND j.source_id=i.id
JOIN accounting_accounts a ON a.code='320'
WHERE i.status IN ('issued','paid') AND i.invoice_type='purchase' AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=a.id AND x.currency=i.currency AND x.debit=0 AND x.credit=i.grand_total AND x.line_description='Satıcı borcu');

-- Sync historical non-transfer financial transactions.
INSERT IGNORE INTO accounting_journals(journal_no,journal_date,description,source_type,source_id,created_by,created_at)
SELECT CONCAT('OTO-FIN-',t.id),t.transaction_date,CONCAT('Finans hareketi #',t.id),'finance_transaction',t.id,t.created_by,NOW()
FROM financial_transactions t
WHERE t.transaction_type IN ('income','expense','collection','supplier_payment') AND t.amount>0 AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=a.id AND x.currency=t.currency AND x.debit=t.amount AND x.credit=0 AND x.line_description='Finans hareketi borç kaydı');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,a.id,t.currency,t.amount,0,'Finans hareketi borç kaydı',NOW()
FROM financial_transactions t JOIN accounting_journals j ON j.source_type='finance_transaction' AND j.source_id=t.id
JOIN financial_accounts fa ON fa.id=t.account_id
JOIN accounting_accounts a ON a.code=CASE WHEN t.transaction_type='expense' THEN '770' WHEN t.transaction_type='supplier_payment' THEN '320' ELSE IF(fa.account_type='bank','102','100') END
WHERE t.transaction_type IN ('income','expense','collection','supplier_payment') AND t.amount>0 AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=a.id AND x.currency=t.currency AND x.debit=0 AND x.credit=t.amount AND x.line_description='Finans hareketi alacak kaydı');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,a.id,t.currency,0,t.amount,'Finans hareketi alacak kaydı',NOW()
FROM financial_transactions t JOIN accounting_journals j ON j.source_type='finance_transaction' AND j.source_id=t.id
JOIN financial_accounts fa ON fa.id=t.account_id
JOIN accounting_accounts a ON a.code=CASE WHEN t.transaction_type='income' THEN '600' WHEN t.transaction_type='collection' THEN '120' ELSE IF(fa.account_type='bank','102','100') END
WHERE t.transaction_type IN ('income','expense','collection','supplier_payment') AND t.amount>0;

-- Sync historical paired cash/bank transfers as one balanced journal.
INSERT IGNORE INTO accounting_journals(journal_no,journal_date,description,source_type,source_id,created_by,created_at)
SELECT CONCAT('OTO-TRF-',t.id),t.transaction_date,CONCAT('Kasa/banka transferi ',t.transfer_code),'finance_transfer',t.id,t.created_by,NOW()
FROM financial_transactions t
WHERE t.transaction_type='transfer_out' AND t.transfer_code IS NOT NULL;

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,dest.id,t.currency,t.amount,0,'Transfer girişi',NOW()
FROM financial_transactions t
JOIN financial_transactions ti ON ti.transfer_code=t.transfer_code AND ti.transaction_type='transfer_in'
JOIN financial_accounts fa ON fa.id=ti.account_id
JOIN accounting_accounts dest ON dest.code=IF(fa.account_type='bank','102','100')
JOIN accounting_journals j ON j.source_type='finance_transfer' AND j.source_id=t.id
WHERE t.transaction_type='transfer_out' AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=dest.id AND x.currency=t.currency AND x.debit=t.amount AND x.credit=0 AND x.line_description='Transfer girişi');

INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at)
SELECT j.id,src.id,t.currency,0,t.amount,'Transfer çıkışı',NOW()
FROM financial_transactions t
JOIN financial_accounts fa ON fa.id=t.account_id
JOIN accounting_accounts src ON src.code=IF(fa.account_type='bank','102','100')
JOIN accounting_journals j ON j.source_type='finance_transfer' AND j.source_id=t.id
WHERE t.transaction_type='transfer_out' AND NOT EXISTS (SELECT 1 FROM accounting_journal_lines x WHERE x.journal_id=j.id AND x.account_id=src.id AND x.currency=t.currency AND x.debit=0 AND x.credit=t.amount AND x.line_description='Transfer çıkışı');
