<?php
require_once __DIR__ . '/includes/functions.php';

$functions = [
    'notify_role', 'leave_decide', 'leave_request', 'attendance_mark',
    'expense_create', 'expense_approve', 'expense_reject', 'budget_variance',
    'api_json', 'api_error', 'api_require_auth', 'has_permission',
    'log_activity', 'require_permission', 'current_user', 'h', 'csrf_field',
    'cow_record_breeding', 'cow_record_calving', 'cow_record_exit',
    'dispatch_confirm_delivery', 'dispatch_mark_failed', 'dispatch_create',
    'finance_cash_position', 'cow_is_active',
];
echo "<h2>Function Check</h2><ul>";
foreach ($functions as $f) {
    $ok = function_exists($f);
    echo "<li style='color:" . ($ok ? 'green' : 'red') . "'>" . ($ok ? '✓' : '✗') . " $f</li>";
}
echo "</ul><h2>Table Check</h2><ul>";
$tables = [
    'attendance','leave_requests','tasks','fin_payslips','employees','users',
    'notifications','wh_store_receiving','dist_dispatches','fin_budgets',
    'vet_health_records','vet_vaccination_records','dairy_breeding_records',
    'dairy_calving_records','poultry_mortality_logs','fish_mortality_logs',
    'fish_feeding_logs','fish_harvest_records','fin_payroll_runs',
    'role_permissions','permissions','roles',
];
foreach ($tables as $t) {
    try {
        $c = (int)db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "<li style='color:green'>✓ $t ($c rows)</li>";
    } catch (Throwable $e) {
        echo "<li style='color:red'>✗ MISSING: $t — " . htmlspecialchars($e->getMessage()) . "</li>";
    }
}
echo "</ul><h2>Column Check — employees.user_id</h2>";
try {
    $cols = db()->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
    echo in_array('user_id', $cols) ? "<p style='color:green'>✓ employees.user_id exists</p>" : "<p style='color:red'>✗ employees.user_id MISSING</p>";
    echo "<p>Columns: " . implode(', ', $cols) . "</p>";
} catch(Throwable $e) { echo "<p style='color:red'>" . $e->getMessage() . "</p>"; }

echo "<h2>Column Check — fin_payslips</h2>";
try {
    $cols = db()->query("SHOW COLUMNS FROM fin_payslips")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Columns: " . implode(', ', $cols) . "</p>";
} catch(Throwable $e) { echo "<p style='color:red'>" . $e->getMessage() . "</p>"; }

echo "<h2>RBAC Permissions Count</h2>";
try {
    $pc = db()->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
    $rp = db()->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
    echo "<p>Permissions: $pc | Role-Permission mappings: $rp</p>";
} catch(Throwable $e) { echo "<p style='color:red'>" . $e->getMessage() . "</p>"; }

echo "<h2>API Token Column</h2>";
try {
    $cols = db()->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    echo in_array('api_token', $cols) ? "<p style='color:green'>✓ users.api_token exists</p>" : "<p style='color:red'>✗ users.api_token MISSING — migration_v5 may not have run</p>";
} catch(Throwable $e) { echo "<p style='color:red'>" . $e->getMessage() . "</p>"; }
