CREATE TABLE IF NOT EXISTS roles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 slug VARCHAR(60) NOT NULL UNIQUE,
 description VARCHAR(255) NULL,
 is_system TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 permission_key VARCHAR(120) NOT NULL UNIQUE,
 name VARCHAR(160) NOT NULL,
 module VARCHAR(80) NOT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
 role_id BIGINT UNSIGNED NOT NULL,
 permission_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY(role_id, permission_id),
 CONSTRAINT fk_role_permissions_role FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE,
 CONSTRAINT fk_role_permissions_permission FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
 user_id BIGINT UNSIGNED NOT NULL,
 role_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY(user_id, role_id),
 CONSTRAINT fk_user_roles_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_user_roles_role FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (name,slug,description,is_system,created_at) VALUES
('Sahip','owner','Tüm sistem yetkileri',1,NOW()),
('Yönetici','admin','Sistem yönetimi',1,NOW()),
('Yönetici Yardımcısı','manager','Operasyonel yönetim',1,NOW()),
('Personel','staff','Sınırlı operasyon erişimi',1,NOW());

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('dashboard.view','Gösterge panelini görüntüle','dashboard',NOW()),
('customers.view','Müşterileri görüntüle','crm',NOW()),
('customers.manage','Müşterileri yönet','crm',NOW()),
('products.view','Ürünleri görüntüle','catalog',NOW()),
('products.manage','Ürünleri yönet','catalog',NOW()),
('orders.view','Siparişleri görüntüle','sales',NOW()),
('orders.manage','Siparişleri yönet','sales',NOW()),
('tasks.view','Görevleri görüntüle','tasks',NOW()),
('tasks.manage','Görevleri yönet','tasks',NOW()),
('users.view','Kullanıcıları görüntüle','users',NOW()),
('users.manage','Kullanıcıları yönet','users',NOW()),
('roles.manage','Rol ve izinleri yönet','users',NOW()),
('audit.view','İşlem kayıtlarını görüntüle','system',NOW()),
('settings.manage','Sistem ayarlarını yönet','system',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN
('dashboard.view','customers.view','customers.manage','products.view','products.manage','orders.view','orders.manage','tasks.view','tasks.manage')
WHERE r.slug='manager';

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN
('dashboard.view','customers.view','products.view','orders.view','tasks.view')
WHERE r.slug='staff';

INSERT IGNORE INTO user_roles (user_id,role_id,created_at)
SELECT u.id,r.id,NOW() FROM users u JOIN roles r ON r.slug=u.role;
