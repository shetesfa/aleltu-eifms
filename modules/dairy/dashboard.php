<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'dairy_manager']);
require_once __DIR__ . '/../../includes/header.php';

// Dairy KPIs
$totalCows = (int) db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status NOT IN ('sold','deceased')")->fetchColumn();
$lactatingCows = (int) db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status = 'lactating'")->fetchColumn();
$pregnantCows = (int) db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status = 'pregnant'")->fetchColumn();
$sickCows = (int) db()->query("SELECT COUNT(*) FROM dairy_cows WHERE is_sick = 1")->fetchColumn();

$todayMilk = (float) db()->query(
    "SELECT COALESCE(SUM(e.yield_liters),0) FROM dairy_milk_yield_entries e
     JOIN dairy_milking_sessions s ON s.id = e.milking_session_id
     WHERE s.session_date = CURDATE()"
)->fetchColumn();

// Recent Milking Sessions
$recentSessions = db()->query(
    "SELECT s.*, u.name AS recorder_name, 
            COUNT(e.id) AS cow_count, 
            COALESCE(SUM(e.yield_liters), 0) AS total_yield
     FROM dairy_milking_sessions s
     LEFT JOIN dairy_milk_yield_entries e ON e.milking_session_id = s.id
     LEFT JOIN users u ON u.id = s.recorded_by
     GROUP BY s.id
     ORDER BY s.session_date DESC, s.id DESC
     LIMIT 7"
)->fetchAll();

// Recent Material Requests from Dairy
$dairyDeptId = (int) db()->query("SELECT id FROM departments WHERE code = 'dairy'")->fetchColumn();
$recentReqs = [];
if ($dairyDeptId) {
    $stmt = db()->prepare(
        "SELECT r.*, i.name AS item_name, i.unit 
         FROM dept_material_requests r
         JOIN wh_stock_items i ON i.id = r.stock_item_id
         WHERE r.department_id = ?
         ORDER BY r.created_at DESC LIMIT 5"
    );
    $stmt->execute([$dairyDeptId]);
    $recentReqs = $stmt->fetchAll();
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🐄 <?= h(t('nav.dairy_dashboard', 'Dairy Manager Dashboard')) ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የላሞች ጤና፣ የዕለት ወተት ምርት፣ የወተት ማለቢያ ክፍለ ጊዜዎች እና የመኖ አቅርቦት ክትትል' : 'Herd health, daily milk yields, milking sessions, and feed supplies.' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/aleltu-eifms/modules/dairy/milking.php" class="btn">🥛 <?= h(t('dairy.record_milking', 'Record Milking')) ?></a>
        <a href="/aleltu-eifms/modules/dairy/cows.php" class="btn secondary">🐮 <?= h(t('dairy.mgmt', 'Herd List')) ?></a>
        <a href="/aleltu-eifms/modules/dairy/material_request.php" class="btn secondary">📦 <?= h(t('reqs.title_dairy', 'Feed & Supplies Request')) ?></a>
        <a href="/aleltu-eifms/modules/module_reports.php?module=dairy" class="btn secondary">📋 <?= h(t('nav.module_reports', 'Dairy Reports')) ?></a>
    </div>
</div>

<!-- Dairy KPIs -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi">
        <div class="label"><?= h(t('dashboard.active_cows', 'Active Herd')) ?></div>
        <div class="value"><?= $totalCows ?> <?= current_locale() === 'am' ? 'ላሞች' : 'cows' ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= h(t('dashboard.todays_milk', "Today's Milk Yield")) ?></div>
        <div class="value" style="color:var(--primary)"><?= number_format($todayMilk, 1) ?> L</div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'አላቢ / ያረገዙ' : 'Lactating / Pregnant' ?></div>
        <div class="value"><?= $lactatingCows ?> <span style="font-size:13px;color:var(--text-muted)">/ <?= $pregnantCows ?> <?= current_locale() === 'am' ? 'ያረገዙ' : 'preg' ?></span></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'የታመሙ ላሞች' : 'Sick Cows' ?></div>
        <div class="value" style="color:<?= $sickCows > 0 ? '#b3261e' : 'inherit' ?>"><?= $sickCows ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.2fr 0.8fr;gap:20px;margin-bottom:20px">
    <!-- Recent Milking Sessions Table -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <h3 style="margin:0"><?= current_locale() === 'am' ? 'የቅርብ ጊዜ የወተት ማለቢያ ክፍለ ጊዜዎች' : 'Recent Milking Sessions' ?></h3>
            <a href="/aleltu-eifms/modules/dairy/milking.php" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= h(t('common.view_all', 'View All / Record')) ?></a>
        </div>
        <table>
            <thead>
                <tr>
                    <th><?= h(t('common.date', 'Date')) ?></th>
                    <th><?= h(t('dairy.session_time', 'Session')) ?></th>
                    <th><?= current_locale() === 'am' ? 'የታለቡ ላሞች' : 'Cows Milked' ?></th>
                    <th><?= h(t('dairy.yield_liters', 'Total Yield')) ?></th>
                    <th><?= current_locale() === 'am' ? 'መዝጋቢ' : 'Recorded By' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentSessions as $s): ?>
                    <tr>
                        <td><?= h($s['session_date']) ?></td>
                        <td><span class="badge active"><?= h(t('dairy.' . $s['session_type'], ucfirst($s['session_type']))) ?></span></td>
                        <td><?= (int)$s['cow_count'] ?> <?= current_locale() === 'am' ? 'ላሞች' : 'cows' ?></td>
                        <td><strong style="color:var(--primary)"><?= number_format($s['total_yield'], 1) ?> L</strong></td>
                        <td><?= h($s['recorder_name'] ?: (current_locale() === 'am' ? 'ሠራተኛ' : 'Worker')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentSessions)): ?>
                    <tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No milking records found for today.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Feed & Material Requests Status -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <h3 style="margin:0"><?= current_locale() === 'am' ? 'የመኖ እና እቃ ጥያቄዎች' : 'Feed & Supplies Requests' ?></h3>
            <a href="/aleltu-eifms/modules/dairy/material_request.php" class="btn secondary" style="padding:3px 8px;font-size:12px">+ <?= h(t('reqs.new_request', 'New Request')) ?></a>
        </div>
        <table>
            <thead>
                <tr>
                    <th><?= h(t('reqs.item', 'Item')) ?></th>
                    <th><?= h(t('common.quantity', 'Qty')) ?></th>
                    <th><?= h(t('common.status', 'Status')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentReqs as $r): 
                    $stBadge = match($r['status']) {
                        'fulfilled' => 'badge milking',
                        'shortage_purchase_raised' => 'badge sold',
                        'partially_issued' => 'badge dry',
                        default => 'badge'
                    };
                    $stText = match($r['status']) {
                        'fulfilled' => (current_locale() === 'am' ? 'ተጠናቋል' : 'Fulfilled'),
                        'shortage_purchase_raised' => (current_locale() === 'am' ? 'የግዢ ጥያቄ ወጥቷል' : 'Purchase Raised'),
                        'partially_issued' => (current_locale() === 'am' ? 'በከፊል ተሰጥቷል' : 'Partially Issued'),
                        'sent_to_department' => (current_locale() === 'am' ? 'ተልኳል' : 'Sent'),
                        default => (current_locale() === 'am' ? 'በመጠባበቅ ላይ' : 'Pending')
                    };
                ?>
                    <tr>
                        <td><strong><?= h($r['item_name']) ?></strong></td>
                        <td><?= number_format($r['requested_quantity'], 1) ?> <?= h($r['unit']) ?></td>
                        <td>
                            <span class="<?= $stBadge ?>"><?= h($stText) ?></span>
                            <?php if ($r['received_confirmed_at']): ?>
                                <br><small class="text-muted">✓ <?= current_locale() === 'am' ? 'ተረጋግጧል' : 'Confirmed' ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentReqs)): ?>
                    <tr><td colspan="3" class="text-muted"><?= h(t('common.no_records', 'No material requests logged yet.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
