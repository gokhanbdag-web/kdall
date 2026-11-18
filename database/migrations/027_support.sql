CREATE TABLE IF NOT EXISTS support_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_number VARCHAR(40) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NULL,
 subject VARCHAR(190) NOT NULL,
 description TEXT NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'open',
 priority VARCHAR(20) NOT NULL DEFAULT 'normal',
 assigned_user_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL,
 due_at DATETIME NULL,
 closed_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_support_ticket_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 CONSTRAINT fk_support_ticket_assignee FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_support_ticket_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_support_tickets_status_priority(status,priority,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 message TEXT NOT NULL,
 is_internal TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_support_message_ticket FOREIGN KEY(ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
 CONSTRAINT fk_support_message_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_support_messages_ticket(ticket_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('support.view','Destek taleplerini görüntüle','support',NOW()),
('support.create','Destek talebi oluştur ve yanıtla','support',NOW()),
('support.manage','Destek taleplerini yönet ve ata','support',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin','manager','staff');
