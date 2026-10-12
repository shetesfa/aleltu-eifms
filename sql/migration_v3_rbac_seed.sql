-- RBAC Permissions Seed
-- Run this once to populate the permissions and role_permissions tables.
-- roles: super_admin=1, general_manager=2, dairy_manager=3, poultry_manager=4,
--        fish_manager=5, feed_production_manager=6, veterinary_officer=7,
--        warehouse_keeper=8, distribution_officer=9, finance_officer=10,
--        hr_officer=11, field_worker=12

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE role_permissions;
TRUNCATE TABLE permissions;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------
-- PERMISSIONS
-- -------------------------------------------------------
INSERT INTO permissions (module, action_code, description) VALUES
-- Dairy
('dairy','view',   'View dairy cows and milking records'),
('dairy','create', 'Record milking, register cows'),
('dairy','edit',   'Edit cow records'),
('dairy','delete', 'Remove cow records'),
('dairy','approve','Approve cow operations'),
('dairy','export', 'Export dairy reports'),
-- Poultry
('poultry','view',   'View poultry flocks and operations'),
('poultry','create', 'Record poultry operations (feeding, egg collection, mortality)'),
('poultry','edit',   'Edit poultry records'),
('poultry','delete', 'Delete poultry records'),
('poultry','approve','Approve poultry operations'),
('poultry','export', 'Export poultry reports'),
-- Fish
('fish','view',   'View fish ponds and operations'),
('fish','create', 'Record fish operations'),
('fish','edit',   'Edit fish records'),
('fish','delete', 'Delete fish records'),
('fish','export', 'Export fish reports'),
-- Feed Production
('feed_production','view',   'View feed production plots and crops'),
('feed_production','create', 'Record planting, harvest, send to store'),
('feed_production','edit',   'Edit feed production records'),
('feed_production','delete', 'Delete feed production records'),
('feed_production','export', 'Export feed production reports'),
-- Warehouse
('warehouse','view',   'View stock levels and warehouse records'),
('warehouse','create', 'Receive, issue, and adjust stock'),
('warehouse','edit',   'Edit warehouse records'),
('warehouse','delete', 'Delete warehouse records'),
('warehouse','approve','Approve receiving and issuing'),
('warehouse','export', 'Export warehouse reports'),
-- Distribution
('distribution','view',   'View vehicles and dispatches'),
('distribution','create', 'Create dispatch records'),
('distribution','edit',   'Edit dispatch records'),
('distribution','export', 'Export distribution reports'),
-- Finance
('finance','view',   'View cash flow, expenses, purchasing'),
('finance','create', 'Create expenses, purchase requests, and payments'),
('finance','edit',   'Edit financial records'),
('finance','delete', 'Delete financial records'),
('finance','approve','Approve purchase orders and expenses'),
('finance','export', 'Export financial reports'),
-- Payroll
('payroll','view',   'View payroll runs and payslips'),
('payroll','create', 'Create payroll runs and record payments'),
('payroll','approve','Finalize payroll'),
('payroll','export', 'Export payroll CSV'),
-- Employees / HR
('employees','view',   'View employee list and profiles'),
('employees','create', 'Register new employees'),
('employees','edit',   'Edit employee records'),
('employees','delete', 'Deactivate/remove employees'),
('employees','export', 'Export employee data'),
-- Attendance
('attendance','view',   'View attendance records'),
('attendance','create', 'Record daily attendance'),
('attendance','edit',   'Edit attendance records'),
-- Veterinary
('veterinary','view',   'View health records, vaccinations, disease incidents'),
('veterinary','create', 'Record health exams, vaccinations, treatments'),
('veterinary','edit',   'Edit veterinary records'),
('veterinary','delete', 'Delete veterinary records'),
('veterinary','export', 'Export veterinary reports'),
-- Notifications
('notifications','view',   'View own notifications'),
('notifications','create', 'Create system notifications'),
-- Audit Log
('audit_log','view', 'View system audit log'),
-- Reports
('reports','view',   'View company-wide reports'),
('reports','export', 'Export reports'),
-- Admin
('admin','view',   'View user management'),
('admin','create', 'Create users and assign roles'),
('admin','edit',   'Edit users and roles'),
('admin','delete', 'Deactivate users');

-- -------------------------------------------------------
-- ROLE PERMISSIONS
-- Helper: assign all actions for a module to a role
-- -------------------------------------------------------

-- SUPER ADMIN (1) — all permissions (inserted dynamically below)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- GENERAL MANAGER (2) — view+create+approve+export everything; no delete/admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE action_code IN ('view','create','edit','approve','export')
  AND module != 'admin';

-- DAIRY MANAGER (3)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE module = 'dairy' AND action_code IN ('view','create','edit','approve','export');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE module = 'veterinary' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE module = 'warehouse' AND action_code IN ('view','create');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE module = 'reports' AND action_code = 'view';

-- POULTRY MANAGER (4)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions
WHERE module = 'poultry' AND action_code IN ('view','create','edit','approve','export');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE module = 'veterinary' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE module = 'warehouse' AND action_code IN ('view','create');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE module = 'reports' AND action_code = 'view';

-- FISH MANAGER (5)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions
WHERE module = 'fish' AND action_code IN ('view','create','edit','export');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE module = 'warehouse' AND action_code IN ('view','create');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE module = 'reports' AND action_code = 'view';

-- FEED PRODUCTION MANAGER (6)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions
WHERE module = 'feed_production' AND action_code IN ('view','create','edit','export');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE module = 'warehouse' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';

-- VETERINARY OFFICER (7)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions
WHERE module = 'veterinary' AND action_code IN ('view','create','edit','export');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE module = 'dairy' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE module = 'poultry' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';

-- WAREHOUSE KEEPER / STORE KEEPER (8)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions
WHERE module = 'warehouse' AND action_code IN ('view','create','edit','approve','export');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions WHERE module = 'distribution' AND action_code IN ('view','create','edit');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions WHERE module = 'reports' AND action_code = 'view';

-- DISTRIBUTION OFFICER (9)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 9, id FROM permissions
WHERE module = 'distribution' AND action_code IN ('view','create','edit','export');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 9, id FROM permissions WHERE module = 'warehouse' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 9, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';

-- FINANCE OFFICER (10)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 10, id FROM permissions
WHERE module IN ('finance','payroll') AND action_code IN ('view','create','edit','approve','export');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 10, id FROM permissions WHERE module = 'employees' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 10, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 10, id FROM permissions WHERE module = 'reports' AND action_code = 'view';

-- HR OFFICER (11)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 11, id FROM permissions
WHERE module IN ('employees','attendance') AND action_code IN ('view','create','edit','export');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 11, id FROM permissions WHERE module = 'payroll' AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 11, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';

-- FIELD WORKER (12)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 12, id FROM permissions
WHERE module IN ('dairy','poultry','fish') AND action_code = 'create';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 12, id FROM permissions
WHERE module IN ('dairy','poultry','fish') AND action_code = 'view';
INSERT INTO role_permissions (role_id, permission_id)
SELECT 12, id FROM permissions WHERE module = 'attendance' AND action_code IN ('view','create');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 12, id FROM permissions WHERE module = 'notifications' AND action_code = 'view';

SELECT CONCAT('Permissions seeded: ', COUNT(*), ' rows') AS result FROM permissions;
SELECT CONCAT('Role permissions seeded: ', COUNT(*), ' rows') AS result FROM role_permissions;
