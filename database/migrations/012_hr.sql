CREATE TABLE IF NOT EXISTS departments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL UNIQUE,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_code VARCHAR(40) NOT NULL UNIQUE,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(40) NULL,
 department_id BIGINT UNSIGNED NULL,
 position VARCHAR(120) NULL,
 hire_date DATE NULL,
 termination_date DATE NULL,
 employment_status VARCHAR(20) NOT NULL DEFAULT 'active',
 base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 notes TEXT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_employee_department FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE SET NULL,
 INDEX idx_employees_name(first_name,last_name),
 INDEX idx_employees_status(employment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_leaves (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id BIGINT UNSIGNED NOT NULL,
 leave_type VARCHAR(40) NOT NULL,
 start_date DATE NOT NULL,
 end_date DATE NOT NULL,
 day_count DECIMAL(6,2) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 reason VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_leave_employee FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE,
 CONSTRAINT fk_leave_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_leave_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_employee_leave_dates(employee_id,start_date,end_date),
 INDEX idx_employee_leave_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_attendance (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id BIGINT UNSIGNED NOT NULL,
 work_date DATE NOT NULL,
 check_in DATETIME NULL,
 check_out DATETIME NULL,
 notes VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_attendance_employee FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE,
 CONSTRAINT fk_attendance_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_employee_attendance_day(employee_id,work_date),
 INDEX idx_attendance_date(work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 payment_type VARCHAR(30) NOT NULL,
 pay_period CHAR(7) NULL,
 amount DECIMAL(15,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 payment_date DATE NOT NULL,
 reference_no VARCHAR(100) NULL,
 notes VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_employee_payment_employee FOREIGN KEY(employee_id) REFERENCES employees(id),
 CONSTRAINT fk_employee_payment_account FOREIGN KEY(account_id) REFERENCES financial_accounts(id),
 CONSTRAINT fk_employee_payment_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_employee_payments_period(employee_id,pay_period),
 INDEX idx_employee_payments_date(payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('employees.view','Personel kayıtlarını görüntüle','hr',NOW()),
('employees.manage','Personel kayıtlarını yönet','hr',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('employees.view','employees.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='employees.view' WHERE r.slug='staff';
