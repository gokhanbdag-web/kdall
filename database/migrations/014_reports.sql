INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('reports.view','Raporları ve analizleri görüntüle','reports',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin','manager');
