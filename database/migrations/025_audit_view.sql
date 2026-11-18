INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('audit.view','Denetim kayıtlarını görüntüle','audit',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='audit.view' WHERE r.slug IN ('owner','admin');
