<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'dairy_manager', 'veterinary_officer']);

$cowId = (int) ($_GET['id'] ?? 0);
$tab   = $_GET['tab'] ?? 'breeding';
$page  = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

// If no cow specified, default to first cow in database
if ($cowId <= 0) {
    $firstCow = db()->query("SELECT id FROM dairy_cows ORDER BY id ASC LIMIT 1")->fetch();
    if ($firstCow) {
        $cowId = (int) $firstCow['id'];
    }
}

$cow = null;
if ($cowId > 0) {
    $cow = db()->query("SELECT * FROM dairy_cows WHERE id = {$cowId}")->fetch();
}

$allCows = db()->query("SELECT id, tag_number, name FROM dairy_cows ORDER BY tag_number ASC")->fetchAll();

if (!$cow) {
    require_once __DIR__ . '/../../includes/header.php';
    echo '<div class="card">
        <h2>📋 Cow Full History</h2>
        <p class="text-muted">No registered cows found in the database.</p>
        <a href="<?= BASE_URL ?>/modules/dairy/cow_add.php" class="btn">+ Register New Cow</a>
    </div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Export Excel support
if (isset($_GET['export']) && in_array($_GET['export'], ['excel', 'csv', 'xls'])) {
    $filename = "cow_{$cowId}_{$tab}_history_" . date('Ymd') . ".xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo "<table border='1'>";
    switch ($tab) {
        case 'breeding':
            echo "<tr><th>Breeding Date (ኢ.ቀ)</th><th>Method</th><th>Sire Reference</th><th>Expected Calving (ኢ.ቀ)</th><th>Outcome</th><th>Recorded By</th></tr>";
            $rows = db()->query("SELECT b.*, u.name AS worker_name FROM dairy_breeding_records b JOIN users u ON u.id = b.recorded_by WHERE b.cow_id = {$cowId} ORDER BY b.breeding_date DESC")->fetchAll();
            foreach ($rows as $r) {
                echo "<tr><td>" . h(eth_date($r['breeding_date'])) . "</td><td>" . h($r['method']) . "</td><td>" . h($r['sire_reference']) . "</td><td>" . h($r['expected_calving_date'] ? eth_date($r['expected_calving_date']) : '—') . "</td><td>" . h($r['outcome'] ?? '—') . "</td><td>" . h($r['worker_name']) . "</td></tr>";
            }
            break;
        case 'calving':
            echo "<tr><th>Calving Date (ኢ.ቀ)</th><th>Outcome</th><th>Calf Tag</th><th>Notes</th></tr>";
            $rows = db()->query("SELECT c.*, calf.tag_number AS calf_tag FROM dairy_calving_records c LEFT JOIN dairy_cows calf ON calf.id = c.calf_id WHERE c.dam_id = {$cowId} ORDER BY c.calving_date DESC")->fetchAll();
            foreach ($rows as $r) {
                echo "<tr><td>" . h(eth_date($r['calving_date'])) . "</td><td>" . h($r['outcome']) . "</td><td>" . h($r['calf_tag'] ?? '—') . "</td><td>" . h($r['notes']) . "</td></tr>";
            }
            break;
        case 'health':
            echo "<tr><th>Record Date (ኢ.ቀ)</th><th>Veterinarian</th><th>Condition</th><th>Treatment</th></tr>";
            $rows = db()->query("SELECT h.*, u.name AS vet_name FROM vet_health_records h JOIN users u ON u.id = h.vet_id WHERE h.subject_type = 'dairy_cow' AND h.subject_id = {$cowId} ORDER BY h.record_date DESC")->fetchAll();
            foreach ($rows as $r) {
                echo "<tr><td>" . h(eth_date($r['record_date'])) . "</td><td>" . h($r['vet_name']) . "</td><td>" . h($r['condition_name']) . "</td><td>" . h($r['treatment']) . "</td></tr>";
            }
            break;
        case 'vaccination':
            echo "<tr><th>Vaccine</th><th>Scheduled Date (ኢ.ቀ)</th><th>Administered Date</th><th>Veterinarian</th><th>Status</th></tr>";
            $rows = db()->query("SELECT v.*, u.name AS vet_name FROM vet_vaccination_records v LEFT JOIN users u ON u.id = v.vet_id WHERE v.subject_type = 'dairy_cow' AND v.subject_id = {$cowId} ORDER BY v.scheduled_date DESC")->fetchAll();
            foreach ($rows as $r) {
                echo "<tr><td>" . h($r['vaccine_name']) . "</td><td>" . h(eth_date($r['scheduled_date'])) . "</td><td>" . h($r['administered_date'] ? eth_date($r['administered_date']) : '—') . "</td><td>" . h($r['vet_name'] ?? '—') . "</td><td>" . h($r['status']) . "</td></tr>";
            }
            break;
    }
    echo "</table>";
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

// ---- Tab data with pagination ----
$totalRows = 0;
$rows      = [];
$summaries = [];

switch ($tab) {
    case 'breeding':
        $totalRows = (int) db()->query("SELECT COUNT(*) FROM dairy_breeding_records WHERE cow_id = {$cowId}")->fetchColumn();
        $stmt = db()->prepare("SELECT b.*, u.name AS worker_name FROM dairy_breeding_records b JOIN users u ON u.id = b.recorded_by WHERE b.cow_id = ? ORDER BY b.breeding_date DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute([$cowId]); $rows = $stmt->fetchAll();
        $summaries = [
            'Total Breeding Events' => $totalRows,
            'AI Events'             => (int) db()->query("SELECT COUNT(*) FROM dairy_breeding_records WHERE cow_id = {$cowId} AND method = 'artificial_insemination'")->fetchColumn(),
            'Natural Matings'       => (int) db()->query("SELECT COUNT(*) FROM dairy_breeding_records WHERE cow_id = {$cowId} AND method = 'natural_bull'")->fetchColumn(),
            'Successful Calvings'   => (int) db()->query("SELECT COUNT(*) FROM dairy_calving_records WHERE dam_id = {$cowId} AND outcome IN ('single_live','twins_live')")->fetchColumn(),
        ];
        break;

    case 'calving':
        $totalRows = (int) db()->query("SELECT COUNT(*) FROM dairy_calving_records WHERE dam_id = {$cowId}")->fetchColumn();
        $stmt = db()->prepare("SELECT c.*, calf.tag_number AS calf_tag FROM dairy_calving_records c LEFT JOIN dairy_cows calf ON calf.id = c.calf_id WHERE c.dam_id = ? ORDER BY c.calving_date DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute([$cowId]); $rows = $stmt->fetchAll();
        $summaries = [
            'Total Calvings' => $totalRows,
            'Live Births'    => (int) db()->query("SELECT COUNT(*) FROM dairy_calving_records WHERE dam_id = {$cowId} AND outcome IN ('single_live','twins_live')")->fetchColumn(),
            'Stillbirths'    => (int) db()->query("SELECT COUNT(*) FROM dairy_calving_records WHERE dam_id = {$cowId} AND outcome = 'stillborn'")->fetchColumn(),
            'Aborted'        => (int) db()->query("SELECT COUNT(*) FROM dairy_calving_records WHERE dam_id = {$cowId} AND outcome = 'aborted'")->fetchColumn(),
        ];
        break;

    case 'health':
        $totalRows = (int) db()->query("SELECT COUNT(*) FROM vet_health_records WHERE subject_type = 'dairy_cow' AND subject_id = {$cowId}")->fetchColumn();
        $stmt = db()->prepare("SELECT h.*, u.name AS vet_name FROM vet_health_records h JOIN users u ON u.id = h.vet_id WHERE h.subject_type = 'dairy_cow' AND h.subject_id = ? ORDER BY h.record_date DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute([$cowId]); $rows = $stmt->fetchAll();
        $summaries = [
            'Total Health Exams' => $totalRows,
        ];
        break;

    case 'vaccination':
        $totalRows = (int) db()->query("SELECT COUNT(*) FROM vet_vaccination_records WHERE subject_type = 'dairy_cow' AND subject_id = {$cowId}")->fetchColumn();
        $stmt = db()->prepare("SELECT v.*, u.name AS vet_name FROM vet_vaccination_records v LEFT JOIN users u ON u.id = v.vet_id WHERE v.subject_type = 'dairy_cow' AND v.subject_id = ? ORDER BY v.scheduled_date DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute([$cowId]); $rows = $stmt->fetchAll();
        $summaries = [
            'Total Vaccinations' => $totalRows,
            'Administered'       => (int) db()->query("SELECT COUNT(*) FROM vet_vaccination_records WHERE subject_type = 'dairy_cow' AND subject_id = {$cowId} AND status = 'administered'")->fetchColumn(),
            'Scheduled/Pending'  => (int) db()->query("SELECT COUNT(*) FROM vet_vaccination_records WHERE subject_type = 'dairy_cow' AND subject_id = {$cowId} AND status = 'scheduled'")->fetchColumn(),
            'Overdue'            => (int) db()->query("SELECT COUNT(*) FROM vet_vaccination_records WHERE subject_type = 'dairy_cow' AND subject_id = {$cowId} AND status = 'scheduled' AND scheduled_date < CURDATE()")->fetchColumn(),
        ];
        break;

    case 'finances':
        $cowFin = cow_financial_summary($cowId);
        $summaries = [
            'Total Milk Revenue' => number_format($cowFin['total_milk_revenue'], 2) . ' ETB',
            'Total Expenses'     => number_format($cowFin['total_expenses'], 2) . ' ETB',
            'Net Profit'         => number_format($cowFin['net_profit'], 2) . ' ETB',
            'ROI / Margin'       => $cowFin['roi_percent'] . '%',
        ];
        break;
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));

$tabs = [
    'breeding'    => current_locale() === 'am' ? '🐄 የማዳቀል ታሪክ' : '🐄 Breeding History',
    'calving'     => current_locale() === 'am' ? '🐣 የመውለድ ታሪክ' : '🐣 Calving History',
    'health'      => current_locale() === 'am' ? '🩺 የጤና ምርመራዎች' : '🩺 Health Records',
    'vaccination' => current_locale() === 'am' ? '💉 የክትባት መዝገብ' : '💉 Vaccinations',
    'finances'    => current_locale() === 'am' ? '💰 የፋይናንስና ትርፋማነት' : '💰 Financial P&L',
];
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
    <div>
        <h2 class="mt-0"><?= current_locale() === 'am' ? 'የላም ታሪክ መዝገብ' : 'Cow History' ?>: <?= h($cow['tag_number']) ?> <?= $cow['name'] ? '— ' . h($cow['name']) : '' ?></h2>
        <p class="text-muted" style="margin:0"><?= h(t('cow.breed', 'Breed')) ?>: <?= h($cow['breed'] ?: '—') ?> | <?= h(t('cow.status', 'Status')) ?>: <span class="badge"><?= h($cow['lifecycle_status']) ?></span></p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <!-- Cow Switcher -->
        <form method="get" style="display:flex;align-items:center;gap:6px">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">
            <label style="margin:0;font-size:13px;font-weight:600"><?= current_locale() === 'am' ? 'ላም ምረጥ:' : 'Select Cow:' ?></label>
            <select name="id" onchange="this.form.submit()" style="padding:5px 8px;font-size:13px">
                <?php foreach ($allCows as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $cowId ? 'selected' : '' ?>>
                        <?= h($c['tag_number']) ?> <?= $c['name'] ? '('.h($c['name']).')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="?id=<?= $cowId ?>&tab=<?= h($tab) ?>&export=excel" class="btn secondary" style="padding:5px 12px">📊 <?= current_locale() === 'am' ? 'በዘመናዊ ኤክሴል አውርድ' : 'Export Excel' ?></a>
        <a href="<?= BASE_URL ?>/modules/dairy/cow_view.php?id=<?= $cowId ?>" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'የላሟ ሙሉ ገጽ' : 'Cow Profile' ?></a>
    </div>
</div>

<!-- Tab Navigation -->
<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="?id=<?= $cowId ?>&tab=<?= $key ?>&page=1"
           class="btn <?= $tab === $key ? '' : 'secondary' ?>"
           style="padding:7px 14px">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- KPI Summaries -->
<?php if (!empty($summaries)): ?>
<div class="kpi-row" style="margin-bottom:20px">
    <?php foreach ($summaries as $label => $val): ?>
        <div class="kpi">
            <div class="label"><?= h($label) ?></div>
            <div class="value"><?= h($val) ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <strong><?= h($tabs[$tab]) ?></strong>
        <span class="text-muted" style="font-size:13px"><?= $totalRows ?> <?= current_locale() === 'am' ? 'ጠቅላላ መዝገቦች' : 'total records' ?> | <?= current_locale() === 'am' ? 'ገጽ' : 'Page' ?> <?= $page ?> / <?= $totalPages ?></span>
    </div>

    <?php if ($tab === 'breeding'): ?>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'የተዳቀለበት ቀን' : 'Breeding Date' ?></th><th><?= current_locale() === 'am' ? 'ዘዴ' : 'Method' ?></th><th><?= current_locale() === 'am' ? 'የኮርማው መረጃ' : 'Sire Reference' ?></th><th><?= current_locale() === 'am' ? 'የመውለጃ ጊዜ' : 'Expected Calving' ?></th><th><?= current_locale() === 'am' ? 'የመዘገበው ሰራተኛ' : 'Recorded By' ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= eth_date($r['breeding_date']) ?></td>
                <td><span class="badge"><?= h(str_replace('_', ' ', $r['method'])) ?></span></td>
                <td><?= h($r['sire_reference'] ?: '—') ?></td>
                <td><?= $r['expected_calving_date'] ? eth_date($r['expected_calving_date']) : '—' ?></td>
                <td><?= h($r['worker_name'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="5" class="text-muted"><?= current_locale() === 'am' ? 'ምንም የማዳቀል መዝገብ የለም።' : 'No breeding records for this cow.' ?></td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tab === 'calving'): ?>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'የተወለደበት ቀን' : 'Calving Date' ?></th><th><?= current_locale() === 'am' ? 'ውጤት' : 'Outcome' ?></th><th><?= current_locale() === 'am' ? 'የጥጃው መለያ' : 'Calf Tag' ?></th><th><?= current_locale() === 'am' ? 'ማስታወሻ' : 'Notes' ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= eth_date($r['calving_date']) ?></td>
                <td><span class="badge <?= in_array($r['outcome'], ['single_live','twins_live','live_birth']) ? 'milking' : 'sold' ?>"><?= h(str_replace('_', ' ', $r['outcome'])) ?></span></td>
                <td><?php if ($r['calf_id'] && !empty($r['calf_tag'])): ?>
                    <a href="<?= BASE_URL ?>/modules/dairy/cow_view.php?id=<?= $r['calf_id'] ?>"><strong><?= h($r['calf_tag']) ?></strong></a>
                <?php else: ?>—<?php endif; ?></td>
                <td class="text-muted"><?= h($r['notes'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="4" class="text-muted"><?= current_locale() === 'am' ? 'ምንም የመውለድ መዝገብ የለም።' : 'No calving records for this cow.' ?></td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tab === 'health'): ?>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'የምርመራ ቀን' : 'Record Date' ?></th><th><?= current_locale() === 'am' ? 'የእንስሳት ሐኪም' : 'Veterinarian' ?></th><th><?= current_locale() === 'am' ? 'የታየበት ምልክት' : 'Condition' ?></th><th><?= current_locale() === 'am' ? 'የተሰጠ ሕክምና' : 'Treatment' ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= eth_date($r['record_date']) ?></td>
                <td><?= h($r['vet_name']) ?></td>
                <td><strong><?= h($r['condition_name'] ?: '—') ?></strong></td>
                <td class="text-muted" style="font-size:12px"><?= h($r['treatment'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="4" class="text-muted"><?= current_locale() === 'am' ? 'ምንም የጤና መዝገብ የለም።' : 'No health records for this cow.' ?></td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tab === 'vaccination'): ?>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'የክትባት ዓይነት' : 'Vaccine' ?></th><th><?= current_locale() === 'am' ? 'የተያዘበት ቀን' : 'Scheduled' ?></th><th><?= current_locale() === 'am' ? 'የተከተበበት ቀን' : 'Administered' ?></th><th><?= current_locale() === 'am' ? 'ሐኪም' : 'Veterinarian' ?></th><th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $overdue = $r['status'] === 'scheduled' && ($r['scheduled_date'] ?? '') < date('Y-m-d');
            $badgeClass = match($r['status']) {
                'administered' => 'milking',
                'scheduled'    => $overdue ? 'sold' : 'dry',
                default        => ''
            };
            ?>
            <tr>
                <td><strong><?= h($r['vaccine_name']) ?></strong></td>
                <td><?= eth_date($r['scheduled_date']) ?></td>
                <td><?= $r['administered_date'] ? eth_date($r['administered_date']) : '—' ?></td>
                <td><?= h($r['vet_name'] ?? '—') ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= $overdue ? (current_locale() === 'am' ? 'ያለፈበት' : 'OVERDUE') : h($r['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="5" class="text-muted"><?= current_locale() === 'am' ? 'ምንም የክትባት መዝገብ የለም።' : 'No vaccination records for this cow.' ?></td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tab === 'finances'): ?>
        <?php $fin = $cowFin ?? cow_financial_summary($cowId); ?>
        
        <!-- Profitability KPI Tiles -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:14px;margin-bottom:20px">
            <div class="card" style="padding:14px;border-left:4px solid #1565c0;box-shadow:none;background:var(--hover)">
                <div style="font-size:12px;color:var(--text-muted);font-weight:700">🥛 <?= current_locale() === 'am' ? 'ጠቅላላ የወተት ገቢ' : 'Total Milk Revenue' ?></div>
                <div style="font-size:20px;font-weight:800;color:#1565c0;margin-top:6px">
                    <?= number_format($fin['total_milk_revenue'], 2) ?> <small style="font-size:12px">ETB</small>
                </div>
                <small class="text-muted"><?= number_format($fin['total_milk_liters'], 1) ?> ሊትር (በ <?= $fin['milk_price'] ?> ETB/L)</small>
            </div>

            <div class="card" style="padding:14px;border-left:4px solid #c62828;box-shadow:none;background:var(--hover)">
                <div style="font-size:12px;color:var(--text-muted);font-weight:700">💸 <?= current_locale() === 'am' ? 'ጠቅላላ የወጣ ወጪ' : 'Total Expenses' ?></div>
                <div style="font-size:20px;font-weight:800;color:#c62828;margin-top:6px">
                    <?= number_format($fin['total_expenses'], 2) ?> <small style="font-size:12px">ETB</small>
                </div>
                <small class="text-muted"><?= current_locale() === 'am' ? 'መኖ፣ ህክምና፣ ክትባትና ግዢ' : 'Feed, vet, vaccines & purchase' ?></small>
            </div>

            <div class="card" style="padding:14px;border-left:4px solid <?= $fin['net_profit'] >= 0 ? '#2e7d32' : '#c62828' ?>;box-shadow:none;background:var(--hover)">
                <div style="font-size:12px;color:var(--text-muted);font-weight:700">📈 <?= current_locale() === 'am' ? 'የተጣራ ትርፍ / ኪሳራ' : 'Net Profit / Loss' ?></div>
                <div style="font-size:20px;font-weight:800;color:<?= $fin['net_profit'] >= 0 ? '#2e7d32' : '#c62828' ?>;margin-top:6px">
                    <?= number_format($fin['net_profit'], 2) ?> <small style="font-size:12px">ETB</small>
                </div>
                <small style="color:<?= $fin['net_profit'] >= 0 ? '#2e7d32' : '#c62828' ?>;font-weight:700">
                    <?= $fin['net_profit'] >= 0 ? '✓ አትራፊ ላም (ROI: '.$fin['roi_percent'].'%)' : '⚠️ ኪሳራ ላይ ያለች ላም (ROI: '.$fin['roi_percent'].'%)' ?>
                </small>
            </div>
        </div>

        <!-- Detailed Breakdown -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:16px;margin-bottom:20px">
            <!-- Expenses Breakdown Card -->
            <div style="background:var(--hover);border-radius:8px;padding:14px;border:1px solid var(--border)">
                <h4 style="margin:0 0 10px 0;color:#c62828">💸 <?= current_locale() === 'am' ? 'ለላሟ የወጣ ወጪ ዝርዝር (Cost Breakdown)' : 'Cost Breakdown' ?></h4>
                <table style="width:100%;font-size:13px;border-collapse:collapse">
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:6px 0">🌾 <?= current_locale() === 'am' ? 'የመኖ ወጪ' : 'Feed Cost' ?> (<?= $fin['days_on_farm'] ?> ቀናት × <?= $fin['daily_feed_rate'] ?> ETB)</td>
                        <td style="padding:6px 0;text-align:right;font-weight:700"><?= number_format($fin['feed_cost'], 2) ?> ETB</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:6px 0">🩺 <?= current_locale() === 'am' ? 'የህክምናና መድሃኒት ወጪ' : 'Veterinary Treatments' ?></td>
                        <td style="padding:6px 0;text-align:right;font-weight:700"><?= number_format($fin['health_cost'], 2) ?> ETB</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:6px 0">💉 <?= current_locale() === 'am' ? 'የክትባት ወጪዎች' : 'Vaccinations' ?></td>
                        <td style="padding:6px 0;text-align:right;font-weight:700"><?= number_format($fin['vaccination_cost'], 2) ?> ETB</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:6px 0">🏷️ <?= current_locale() === 'am' ? 'የመጀመሪያ የግዢ ዋጋዋ' : 'Initial Purchase Cost' ?></td>
                        <td style="padding:6px 0;text-align:right;font-weight:700"><?= number_format($fin['purchase_cost'], 2) ?> ETB</td>
                    </tr>
                    <tr style="font-weight:800;color:#c62828">
                        <td style="padding:8px 0"><?= current_locale() === 'am' ? 'ጠቅላላ የወጣ ወጪ ድምር' : 'Total Expense' ?></td>
                        <td style="padding:8px 0;text-align:right"><?= number_format($fin['total_expenses'], 2) ?> ETB</td>
                    </tr>
                </table>
            </div>

            <!-- Monthly Milk Revenue Breakdown -->
            <div style="background:var(--hover);border-radius:8px;padding:14px;border:1px solid var(--border)">
                <h4 style="margin:0 0 10px 0;color:#1565c0">🥛 <?= current_locale() === 'am' ? 'የወተት ምርት በወራት (የቅርብ ጊዜ)' : 'Recent Monthly Milk Revenue' ?></h4>
                <?php if (empty($fin['monthly_milk'])): ?>
                    <p class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'ምንም የወተት መዝገብ የለም።' : 'No milk yield recorded.' ?></p>
                <?php else: ?>
                    <table style="width:100%;font-size:13px;border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border);color:var(--text-muted)">
                                <th style="text-align:left;padding:4px 0"><?= current_locale() === 'am' ? 'ወር' : 'Month' ?></th>
                                <th style="text-align:center;padding:4px 0"><?= current_locale() === 'am' ? 'ሊትር' : 'Liters' ?></th>
                                <th style="text-align:right;padding:4px 0"><?= current_locale() === 'am' ? 'የገንዘብ ተመን (ETB)' : 'Revenue (ETB)' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fin['monthly_milk'] as $mm): 
                                $monthRev = (float)$mm['monthly_liters'] * $fin['milk_price'];
                            ?>
                                <tr style="border-bottom:1px solid rgba(0,0,0,0.05)">
                                    <td style="padding:5px 0"><strong><?= h($mm['ym']) ?></strong> (<?= (int)$mm['session_count'] ?> ክፍለጊዜ)</td>
                                    <td style="padding:5px 0;text-align:center"><?= number_format((float)$mm['monthly_liters'], 1) ?> L</td>
                                    <td style="padding:5px 0;text-align:right;font-weight:700;color:#1565c0"><?= number_format($monthRev, 2) ?> ETB</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:6px;margin-top:16px;align-items:center">
        <?php if ($page > 1): ?>
            <a href="?id=<?= $cowId ?>&tab=<?= h($tab) ?>&page=<?= $page - 1 ?>" class="btn secondary" style="padding:5px 12px">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="?id=<?= $cowId ?>&tab=<?= h($tab) ?>&page=<?= $p ?>"
               class="btn <?= $p === $page ? '' : 'secondary' ?>"
               style="padding:5px 10px"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?id=<?= $cowId ?>&tab=<?= h($tab) ?>&page=<?= $page + 1 ?>" class="btn secondary" style="padding:5px 12px">Next &raquo;</a>
        <?php endif; ?>
        <span class="text-muted" style="font-size:12px"><?= $totalRows ?> records</span>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
