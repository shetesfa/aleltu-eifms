<?php
/**
 * GET /api/v1/employees — list active employees
 */
require_once dirname(__DIR__, 3) . '/includes/functions.php';
$user = api_require_auth();
if (!has_permission('employees', 'view')) api_error('Forbidden', 403);

$stmt = db()->query("
    SELECT e.id,
           e.employee_number,
           e.full_name,
           d.name AS department,
           e.job_title AS position,
           e.status,
           e.base_salary,
           e.hire_date
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE e.status = 'active'
    ORDER BY e.full_name
");
api_json(['employees' => $stmt->fetchAll()]);
