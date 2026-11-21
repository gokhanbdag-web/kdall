CREATE TABLE IF NOT EXISTS crm_activity_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NULL,
 action_key VARCHAR(80) NOT NULL,
 entity_type VARCHAR(30) NOT NULL,
 entity_id BIGINT UNSIGNED NULL,
 before_json JSON NULL,
 after_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_crm_history_customer_created(customer_id,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
