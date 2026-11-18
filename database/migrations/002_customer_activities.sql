CREATE TABLE IF NOT EXISTS customer_activities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 activity_type VARCHAR(30) NOT NULL DEFAULT 'note',
 subject VARCHAR(190) NOT NULL,
 body TEXT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_customer_activities_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 CONSTRAINT fk_customer_activities_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_customer_activities_customer(customer_id,created_at),
 INDEX idx_customer_activities_type(activity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
