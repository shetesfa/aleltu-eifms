<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['super_admin', 'general_manager']);
require_once __DIR__ . '/../includes/header.php';

$module = $_GET['module'] ?? '';
$user_q = trim($_GET['user'] ?? '');
$curEthMonth = eth_month_range();
$from   = $_GET['from'] ?? $curEthMonth['from'];
$to     = $_GET['to']   ?? date('Y-m-d');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

// Export CSV
if (($_GET['export'] ?? '') === 'csv') {
    require_once __DIR__ . '/../includes/export.php';
    $rows = db()->query("SELECT a.created_at, u.name AS user_name, a.module, a.action, a.table_name, a.record_id, a.details, a.ip_address FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 5000")->fetchAll();
    export_csv($rows, 'audit_log_' . date('Y-m-d') . '.csv');
}

// Build query
$where = ["a.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)"];
$params = [$from, $to];
if ($module) { $where[] = "a.module = ?"; $params[] = $module; }
if ($user_q) { $where[] = "(u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$user_q%"; $params[] = "%$user_q%"; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id=a.user_id $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = (int)ceil($total / $limit);

$stmt = db()->prepare("SELECT a.*, u.name AS user_name, u.email AS user_email FROM audit_log a LEFT JOIN users u ON u.id=a.user_id $whereSQL ORDER BY a.id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = db()->query("SELECT DISTINCT module FROM audit_log ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 class="mt-0">🔍 <?= h(t('nav.audit_log', 'Audit Log')) ?></h2>
    <div style="display:flex;gap:8px">
        <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn secondary">⬇ <?= h(t('common.export_csv', 'Export CSV')) ?></a>
    </div>
</div>

<div class="card" style="margin-bottom:16px">
    <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <div class="form-group" style="margin:0">
            <label><?= current_locale() === 'am' ? 'የስራ ክፍል' : 'Module' ?></label>
            <select name="module" style="min-width:140px">
                <option value=""><?= current_locale() === 'am' ? 'ሁሉም ክፍሎች' : 'All Modules' ?></option>
                <?php foreach ($modules as $m): ?>
                    <option value="<?= h($m) ?>" <?= $module === $m ? 'selected' : '' ?>><?= h(ucfirst($m)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0"><label><?= h(t('common.from', 'From')) ?></label><input type="date" name="from" value="<?= h($from) ?>"></div>
        <div class="form-group" style="margin:0"><label><?= h(t('common.to', 'To')) ?></label><input type="date" name="to" value="<?= h($to) ?>"></div>
        <div class="form-group" style="margin:0"><label><?= current_locale() === 'am' ? 'ተጠቃሚ' : 'User' ?></label><input type="text" name="user" value="<?= h($user_q) ?>" placeholder="<?= current_locale() === 'am' ? 'ስም ወይም ኢሜይል' : 'name or email' ?>"></div>
        <button class="btn secondary" type="submit"><?= h(t('common.filter', 'Filter')) ?></button>
        <a href="<?= BASE_URL ?>/modules/audit_log.php" class="btn secondary"><?= current_locale() === 'am' ? 'አጽዳ' : 'Reset' ?></a>
    </form>
</div>

<div class="card">
    <div style="margin-bottom:10px;display:flex;justify-content:space-between">
        <span class="text-muted"><?= current_locale() === 'am' ? 'የተገኙ መዝገቦች ብዛት: ' . number_format($total) : 'Showing ' . number_format($total) . ' records' ?></span>
        <?php if ($pages > 1): ?>
        <span><?= current_locale() === 'am' ? 'ገጽ ' . $page . ' ከ ' . $pages : 'Page ' . $page . ' of ' . $pages ?></span>
        <?php endif; ?>
    </div>
    <table>
        <thead>
            <tr>
                <th><?= h(t('common.created_at', 'Date/Time')) ?></th>
                <th><?= current_locale() === 'am' ? 'ተጠቃሚ' : 'User' ?></th>
                <th><?= current_locale() === 'am' ? 'የስራ ክፍል' : 'Module' ?></th>
                <th><?= h(t('common.actions', 'Action')) ?></th>
                <th><?= current_locale() === 'am' ? 'ሰንጠረዥ' : 'Table' ?></th>
                <th><?= current_locale() === 'am' ? 'የመዝገብ መለያ' : 'Record ID' ?></th>
                <th><?= h(t('common.notes', 'Details')) ?></th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $l):
            $actionClass = match($l['action']) {
                'create'  => 'milking',
                'delete'  => 'sold',
                'approve' => 'active',
                'reject'  => 'sold',
                'edit','update' => 'dry',
                default   => ''
            };
        ?>
            <tr>
                <td style="white-space:nowrap;font-size:12px">
                    <div><?= eth_datetime($l['created_at']) ?></div>
                    <small class="text-muted" style="font-size:10px"><?= h($l['created_at']) ?></small>
                </td>
                <td><?= h($l['user_name'] ?: (current_locale() === 'am' ? 'ሲስተም' : 'System')) ?></td>
                <td><span class="badge"><?= h($l['module']) ?></span></td>
                <td><span class="badge <?= $actionClass ?>"><?= h($l['action']) ?></span></td>
                <td class="text-muted" style="font-size:12px"><?= h($l['table_name']) ?></td>
                <td class="text-muted" style="font-size:12px"><?= $l['record_id'] ?: '—' ?></td>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($l['details']) ?>"><?= h($l['details'] ?: '—') ?></td>
                <td class="text-muted" style="font-size:11px"><?= h($l['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
            <tr><td colspan="8" class="text-muted"><?= h(t('common.no_records', 'No audit records found for this filter.')) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
    <div style="display:flex;gap:6px;margin-top:16px;flex-wrap:wrap">
        <?php for ($p = 1; $p <= min($pages, 20); $p++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"
               class="btn secondary" style="padding:4px 10px;font-size:12px;<?= $p === $page ? 'background:var(--green-primary);color:#fff' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
