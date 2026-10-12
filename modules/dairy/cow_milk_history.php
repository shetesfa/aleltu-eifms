<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'dairy_manager']);

$cowId = (int)($_GET['id'] ?? 0);
$cow = $cowId > 0 ? db()->query("SELECT * FROM dairy_cows WHERE id = {$cowId}")->fetch() : null;
if (!$cow) {
    $firstId = db()->query("SELECT id FROM dairy_cows ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstId) {
        $cowId = (int)$firstId;
        $cow = db()->query("SELECT * FROM dairy_cows WHERE id = {$cowId}")->fetch();
    } else {
        flash('No cow records found.', 'error');
        header('Location: /aleltu-eifms/modules/dairy/cows.php');
        exit;
    }
}

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$totalCount = (int)db()->query(
    "SELECT COUNT(*) FROM dairy_milk_yield_entries e 
     JOIN dairy_milking_sessions s ON s.id = e.milking_session_id 
     WHERE e.cow_id = {$cowId}"
)->fetchColumn();

$totalPages = max(1, ceil($totalCount / $limit));

$entries = db()->prepare(
    "SELECT e.yield_liters, e.observation, e.flagged_abnormal, e.created_at, s.session_date, s.session_type
     FROM dairy_milk_yield_entries e
     JOIN dairy_milking_sessions s ON s.id = e.milking_session_id
     WHERE e.cow_id = ?
     ORDER BY s.session_date DESC, s.session_type ASC
     LIMIT ? OFFSET ?"
);
$entries->bindValue(1, $cowId, PDO::PARAM_INT);
$entries->bindValue(2, $limit, PDO::PARAM_INT);
$entries->bindValue(3, $offset, PDO::PARAM_INT);
$entries->execute();
$entries = $entries->fetchAll();

$stats = db()->query(
    "SELECT 
        COALESCE(SUM(yield_liters), 0) AS total_production,
        COALESCE(AVG(yield_liters), 0) AS avg_production,
        COALESCE(MAX(yield_liters), 0) AS peak_production,
        COUNT(DISTINCT s.session_date) AS total_milking_days
     FROM dairy_milk_yield_entries e
     JOIN dairy_milking_sessions s ON s.id = e.milking_session_id
     WHERE e.cow_id = {$cowId}"
)->fetch();

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 class="mt-0"><?= h(t('dairy.milking_entry', 'Milk Yield History')) ?> — Cow <?= h($cow['tag_number']) ?> (<?= h($cow['name'] ?: 'Unnamed') ?>)</h2>
    <a href="/aleltu-eifms/modules/dairy/cow_view.php?id=<?= $cowId ?>" class="btn secondary">&larr; <?= h(t('common.back', 'Back to Cow Profile')) ?></a>
</div>

<div class="kpi-row">
    <div class="kpi">
        <div class="label"><?= h(t('common.total', 'Total Lifetime Yield')) ?></div>
        <div class="value"><?= number_format((float)$stats['total_production'], 2) ?> L</div>
    </div>
    <div class="kpi">
        <div class="label">Average Session Yield</div>
        <div class="value"><?= number_format((float)$stats['avg_production'], 2) ?> L</div>
    </div>
    <div class="kpi">
        <div class="label">Peak Session Yield</div>
        <div class="value"><?= number_format((float)$stats['peak_production'], 2) ?> L</div>
    </div>
    <div class="kpi">
        <div class="label">Milking Days</div>
        <div class="value"><?= number_format((int)$stats['total_milking_days']) ?> Days</div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <h3 style="margin:0">📋 <?= h(t('dairy.milking_entry', 'Complete Production Records')) ?> (<?= $totalCount ?>)</h3>
        <button type="button" onclick="exportMilkTableToExcel()" class="btn secondary" style="padding:6px 14px;font-size:13px">
            📊 <?= current_locale() === 'am' ? 'በዘመናዊ ኤክሴል አውርድ (Download Excel)' : 'Download Excel' ?>
        </button>
    </div>
    
    <table id="milkHistoryTable">
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'ቀን (በኢትዮጵያ አቆጣጠር)' : 'Date (ET)' ?></th>
                <th><?= current_locale() === 'am' ? 'ክፍለ ጊዜ' : 'Session' ?></th>
                <th><?= h(t('dairy.yield_liters', 'Yield (Liters)')) ?></th>
                <th><?= current_locale() === 'am' ? 'ምልከታ / ማስታወሻ' : 'Observation' ?></th>
                <th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Flag' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $e): 
                $sessName = match($e['session_type']) {
                    'morning' => (current_locale() === 'am' ? 'የጠዋት' : 'Morning'),
                    'afternoon' => (current_locale() === 'am' ? 'የከሰዓት' : 'Afternoon'),
                    'evening' => (current_locale() === 'am' ? 'የምሽት' : 'Evening'),
                    default => ucfirst($e['session_type'])
                };
            ?>
                <tr>
                    <td><strong><?= h(eth_date($e['session_date'])) ?></strong></td>
                    <td><span class="badge"><?= h($sessName) ?></span></td>
                    <td><strong style="color:var(--primary)"><?= number_format((float)$e['yield_liters'], 2) ?> L</strong></td>
                    <td class="text-muted"><?= h($e['observation'] ?: '—') ?></td>
                    <td>
                        <?php if ($e['flagged_abnormal']): ?>
                            <span class="badge sold">⚠️ <?= current_locale() === 'am' ? 'ያልተለመደ (Abnormal)' : 'Abnormal' ?></span>
                        <?php else: ?>
                            <span class="badge milking">✓ <?= current_locale() === 'am' ? 'መደበኛ (Normal)' : 'Normal' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?>
                <tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No milking entries registered.')) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <div style="display:flex;justify-content:center;gap:8px;margin-top:20px">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?id=<?= $cowId ?>&page=<?= $p ?>" class="btn <?= $p === $page ? '' : 'secondary' ?>" style="padding:6px 12px;font-size:13px">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function exportMilkTableToExcel() {
    const table = document.getElementById('milkHistoryTable');
    let html = table.outerHTML.replace(/ /g, '%20');
    const a = document.createElement('a');
    a.href = 'data:application/vnd.ms-excel,' + html;
    a.download = 'የላም_<?= h($cow['tag_number']) ?>_የወተት_ታሪክ.xls';
    a.click();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
