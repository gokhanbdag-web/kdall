CREATE TABLE IF NOT EXISTS financial_advance_allocations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 financial_transaction_id BIGINT UNSIGNED NOT NULL,
 invoice_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(15,2) NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_advance_transaction FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id),
 CONSTRAINT fk_advance_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
 CONSTRAINT fk_advance_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_advance_transaction (financial_transaction_id),
 INDEX idx_advance_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
