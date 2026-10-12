<?php
/**
 * Final Complete Migration — run once as super_admin
 * Fixes: audit_log table, api_token column, payslip columns,
 *        notifications link_url column, RBAC seed, and any missing indexes.
 * URL: http://localhost/aleltu-eifms/run_final_migration.php
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_role(['super_admin']);

$results = [];
$errors  = [];

function try_exec(PDO $db, string $label, string $sql): void {
    global $results, $errors;
    try {
        $db->exec($sql);
        $results[] = "✓ $label";
    } catch (Throwable $e) {
        // Ignore "already exists" type errors
        if (stripos($e->getMessage(), 'Duplicate') !== false ||
            stripos($e->getMessage(), 'already exists') !== false ||
            stripos($e->getMessage(), 'Multiple primary key') !== false) {
            $results[] = "⚠ $label (already exists — skipped)";
        } else {
            $errors[] = "✗ $label: " . $e->getMessage();
        }
    }
}

$db = db();

// ─── 1. audit_log table ─────────────────────────────────────────────────────
try_exec($db, 'Create audit_log table', "
CREATE TABLE IF NOT EXISTS audit_log (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NULL,
    module       VARCHAR(50)  NOT NULL DEFAULT '',
    action       VARCHAR(50)  NOT NULL DEFAULT '',
    table_name   VARCHAR(80)  NOT NULL DEFAULT '',
    record_id    INT UNSIGNED NOT NULL DEFAULT 0,
    details      TEXT,
    ip_address   VARCHAR(45)  NOT NULL DEFAULT '',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_module (module),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ─── 2. users.api_token column ──────────────────────────────────────────────
try_exec($db, 'Add users.api_token', "ALTER TABLE users ADD COLUMN api_token VARCHAR(64) NULL UNIQUE AFTER password");
try_exec($db, 'Index users.api_token', "CREATE INDEX idx_users_api_token ON users(api_token)");

// ─── 3. notifications.link_url column ──────────────────────────────────────
try_exec($db, 'Add notifications.link_url', "ALTER TABLE notifications ADD COLUMN link_url VARCHAR(255) NULL AFTER message");

// ─── 4. fin_payslips — optional allowances/deductions columns ──────────────
try_exec($db, 'Add fin_payslips.allowances', "ALTER TABLE fin_payslips ADD COLUMN allowances DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER net_pay");
try_exec($db, 'Add fin_payslips.deductions', "ALTER TABLE fin_payslips ADD COLUMN deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER allowances");

// ─── 5. employees — add position alias if not exists ───────────────────────
try_exec($db, 'Add employees.phone_alt', "ALTER TABLE employees ADD COLUMN phone_alt VARCHAR(20) NULL AFTER phone");

// ─── 6. RBAC permissions seed ───────────────────────────────────────────────
// 6a. Seed permissions table
$perms = [
    ['dairy',       'view'],   ['dairy',       'create'], ['dairy',       'edit'],  ['dairy',       'delete'],  ['dairy',       'export'],
    ['poultry',     'view'],   ['poultry',     'create'], ['poultry',     'edit'],  ['poultry',     'delete'],  ['poultry',     'export'],
    ['fish',        'view'],   ['fish',        'create'], ['fish',        'edit'],  ['fish',        'delete'],  ['fish',        'export'],
    ['warehouse',   'view'],   ['warehouse',   'create'], ['warehouse',   'edit'],  ['warehouse',   'delete'],  ['warehouse',   'approve'],
    ['finance',     'view'],   ['finance',     'create'], ['finance',     'edit'],  ['finance',     'delete'],  ['finance',     'approve'],
    ['employees',   'view'],   ['employees',   'create'], ['employees',   'edit'],  ['employees',   'delete'],  ['employees',   'approve'],
    ['attendance',  'view'],   ['attendance',  'create'], ['attendance',  'edit'],
    ['veterinary',  'view'],   ['veterinary',  'create'], ['veterinary',  'edit'],
    ['distribution','view'],   ['distribution','create'], ['distribution','edit'],  ['distribution','approve'],
    ['feed',        'view'],   ['feed',        'create'], ['feed',        'edit'],
    ['reports',     'view'],   ['reports',     'export'],
    ['system',      'admin'],
];

$permIds = []; // module.action => id
foreach ($perms as [$mod, $act]) {
    try {
        $s = $db->prepare("INSERT IGNORE INTO permissions (module, action_code, description) VALUES (?, ?, ?)");
        $s->execute([$mod, $act, ucfirst($act).' '.ucfirst($mod)]);
    } catch (Throwable) {}
    $s2 = $db->prepare("SELECT id FROM permissions WHERE module=? AND action_code=?");
    $s2->execute([$mod, $act]);
    $row = $s2->fetch();
    if ($row) $permIds["$mod.$act"] = (int)$row['id'];
}
$results[] = '✓ Permissions seeded (' . count($permIds) . ' entries)';

// 6b. Role → permissions mapping
$roles = [];
$rs = $db->query("SELECT id, name FROM roles");
foreach ($rs->fetchAll() as $r) $roles[$r['name']] = (int)$r['id'];

$rolePerms = [
    'super_admin'            => array_keys($permIds), // all
    'general_manager'        => array_keys($permIds), // all
    'dairy_manager'          => ['dairy.view','dairy.create','dairy.edit','dairy.export','veterinary.view','employees.view','reports.view'],
    'poultry_manager'        => ['poultry.view','poultry.create','poultry.edit','poultry.export','veterinary.view','employees.view','reports.view'],
    'fish_manager'           => ['fish.view','fish.create','fish.edit','fish.export','veterinary.view','employees.view','reports.view'],
    'warehouse_keeper'       => ['warehouse.view','warehouse.create','warehouse.edit','warehouse.approve','reports.view','reports.export'],
    'finance_officer'        => ['finance.view','finance.create','finance.edit','finance.approve','employees.view','reports.view','reports.export'],
    'hr_officer'             => ['employees.view','employees.create','employees.edit','employees.approve','attendance.view','attendance.create','attendance.edit','reports.view'],
    'veterinary_officer'     => ['veterinary.view','veterinary.create','veterinary.edit','dairy.view','poultry.view','fish.view','employees.view'],
    'feed_production_manager'=> ['feed.view','feed.create','feed.edit','warehouse.view','reports.view'],
    'distribution_officer'   => ['distribution.view','distribution.create','distribution.edit','distribution.approve','warehouse.view'],
    'field_worker'           => ['dairy.view','dairy.create','poultry.view','poultry.create','fish.view','attendance.view'],
];

$rpInserted = 0;
$stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
foreach ($rolePerms as $roleName => $permKeys) {
    $roleId = $roles[$roleName] ?? null;
    if (!$roleId) continue;
    foreach ($permKeys as $key) {
        $permId = $permIds[$key] ?? null;
        if (!$permId) continue;
        try { $stmt->execute([$roleId, $permId]); $rpInserted++; } catch (Throwable) {}
    }
}
$results[] = "✓ Role-permissions seeded ($rpInserted mappings)";

// ─── 7. Verify api_token column exists ─────────────────────────────────────
$cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$results[] = in_array('api_token', $cols) ? '✓ users.api_token confirmed' : '✗ users.api_token still missing — check privileges';

// ─── 8. Verify audit_log exists ─────────────────────────────────────────────
try {
    $db->query("SELECT COUNT(*) FROM audit_log");
    $results[] = '✓ audit_log table confirmed';
} catch (Throwable $e) {
    $errors[] = '✗ audit_log still missing: ' . $e->getMessage();
}

// ─── 9. Verify permissions seeded ───────────────────────────────────────────
$pc = (int)$db->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
$rp = (int)$db->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
$results[] = "✓ RBAC: $pc permissions, $rp role-permission mappings";
?>
<!DOCTYPE html>
<html><head><title>Final Migration</title>
<style>
body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:20px;background:#f5f5f5}
.card{background:#fff;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
h2{color:#2e7d32;margin-top:0}
.ok{color:#2e7d32;padding:4px 0;border-bottom:1px solid #eee}
.warn{color:#e65100;padding:4px 0;border-bottom:1px solid #eee}
.err{color:#b3261e;padding:4px 0;border-bottom:1px solid #eee}
.btn{display:inline-block;padding:10px 20px;background:#2e7d32;color:#fff;text-decoration:none;border-radius:4px;margin-top:16px}
</style></head><body>
<div class="card">
<h2>🌿 Aleltu EIFMS — Final Migration Complete</h2>
<?php foreach ($results as $r): ?>
    <div class="<?= strpos($r,'⚠') !== false ? 'warn' : 'ok' ?>"><?= htmlspecialchars($r) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $e): ?>
    <div class="err"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<p style="margin-top:16px"><strong>Total:</strong> <?= count($results) ?> steps completed, <?= count($errors) ?> errors.</p>
<?php if (empty($errors)): ?>
<p style="color:#2e7d32;font-weight:bold">✅ All migrations applied successfully! The system is 100% ready.</p>
<?php else: ?>
<p style="color:#b3261e">⚠️ Some steps failed. Review the errors above.</p>
<?php endif; ?>
<a href="/aleltu-eifms/index.php" class="btn">← Go to Dashboard</a>
<p style="color:#999;font-size:12px;margin-top:16px">⚠️ <strong>Delete this file immediately after running.</strong></p>
</div>
</body></html>
