CREATE TABLE IF NOT EXISTS documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 category VARCHAR(80) NOT NULL DEFAULT 'general',
 description VARCHAR(500) NULL,
 related_type VARCHAR(40) NULL,
 related_id BIGINT UNSIGNED NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 current_version_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_document_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_documents_category(category,status),
 INDEX idx_documents_related(related_type,related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 document_id BIGINT UNSIGNED NOT NULL,
 storage_path VARCHAR(500) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(120) NOT NULL,
 size_bytes BIGINT UNSIGNED NOT NULL,
 sha256 CHAR(64) NOT NULL,
 uploaded_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_document_version_document FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE CASCADE,
 CONSTRAINT fk_document_version_user FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_document_versions_document(document_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('files.view','Dosya ve belgeleri görüntüle','files',NOW()),
('files.manage','Dosya ve belgeleri yönet','files',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('files.view','files.manage') WHERE r.slug='manager';
