<?php
/**
 * Feed Production Dashboard
 * Aleltu Enterprise Integrated Farm Management System
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'feed_production_manager', 'field_worker', 'dairy_manager', 'poultry_manager']);

$curEthMonth = eth_month_range();

// Fetch summary metrics
$totalPlots = (int) db()->query("SELECT COUNT(*) FROM feed_plots")->fetchColumn();
$totalHectares = (float) db()->query("SELECT COALESCE(SUM(area_hectares), 0) FROM feed_plots")->fetchColumn();
$growingPlots = (int) db()->query("SELECT COUNT(DISTINCT plot_id) FROM feed_planting_records WHERE status = 'growing'")->fetchColumn();
$recentDistributions = (int) db()->query("SELECT COUNT(*) FROM feed_distribution_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Fetch active plots
$plots = db()->query(
    "SELECT p.*,
        (SELECT COUNT(*) FROM feed_planting_records r WHERE r.plot_id = p.id AND r.status = 'growing') AS growing_count
     FROM feed_plots p ORDER BY p.name"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🌾 <?= current_locale() === 'am' ? 'የመኖ እርሻ ማዕከል እና ሰብል ልማት ዳሽቦርድ' : 'Feed Production Dashboard' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የመኖ እርሻ ማሳዎች፣ ዘር መዝራት፣ ሰብል መሰብሰብ እና ምርት ወደ መጋዘን ማስተላለፊያ ማዕከል' : 'Planting, crop management, harvest, and warehouse distribution.' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/modules/feed_production/formulas.php" class="btn">🧪 <?= current_locale() === 'am' ? 'የመኖ ቀመሮች' : 'Feed Formulas' ?></a>
        <a href="<?= BASE_URL ?>/modules/feed_production/batches.php" class="btn">⚙️ <?= current_locale() === 'am' ? 'የመኖ ማምረቻ ባች' : 'Production Batches' ?></a>
        <a href="<?= BASE_URL ?>/modules/feed_production/plots.php" class="btn secondary">🌱 <?= current_locale() === 'am' ? 'የማሳዎች ዝርዝር' : 'Manage Plots' ?></a>
        <a href="<?= BASE_URL ?>/modules/feed_production/distribution.php" class="btn secondary">🚚 <?= current_locale() === 'am' ? 'የመኖ ርክክብ' : 'Send to Warehouse' ?></a>
    </div>
</div>

<div class="metrics-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:20px">
    <div class="card" style="margin:0;border-left:4px solid #2e7d32">
        <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'ጠቅላላ የመኖ ማሳዎች' : 'Total Plots' ?></div>
        <div style="font-size:24px;font-weight:700;color:#2e7d32"><?= $totalPlots ?></div>
        <div style="font-size:11px;color:#666"><?= number_format($totalHectares, 2) ?> <?= current_locale() === 'am' ? 'ሄክታር መሬት' : 'hectares' ?></div>
    </div>
    <div class="card" style="margin:0;border-left:4px solid #1976d2">
        <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'በማደግ ላይ ያሉ ሰብሎች' : 'Growing Crops' ?></div>
        <div style="font-size:24px;font-weight:700;color:#1976d2"><?= $growingPlots ?></div>
        <div style="font-size:11px;color:#666"><?= current_locale() === 'am' ? 'በንቁ ልማት ላይ ያሉ ማሳዎች' : 'Active plots' ?></div>
    </div>
    <div class="card" style="margin:0;border-left:4px solid #f57c00">
        <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'የቅርብ 30 ቀናት ርክክብ' : 'Recent 30-Day Dispatches' ?></div>
        <div style="font-size:24px;font-weight:700;color:#f57c00"><?= $recentDistributions ?></div>
        <div style="font-size:11px;color:#666"><?= current_locale() === 'am' ? 'ወደ መጋዘን የተላኩ ምርቶች' : 'Batches sent to warehouse' ?></div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">🌾 <?= current_locale() === 'am' ? 'የመኖ እርሻ ማሳዎች ዝርዝር' : 'Farm Crop Plots' ?></h3>
        <a href="<?= BASE_URL ?>/modules/feed_production/plots.php" class="btn secondary" style="padding:4px 10px;font-size:12px">➕ <?= current_locale() === 'am' ? 'አዲስ ማሳ መዝግብ' : 'Add Plot' ?></a>
    </div>

    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'የማሳ ስም' : 'Plot Name' ?></th>
                    <th><?= current_locale() === 'am' ? 'ስፋት (በሄክታር)' : 'Area (ha)' ?></th>
                    <th><?= current_locale() === 'am' ? 'በማደግ ላይ ያለ ሰብል' : 'Currently Growing' ?></th>
                    <th><?= h(t('common.actions', 'Action')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plots as $p): ?>
                    <tr>
                        <td><strong><?= h($p['name']) ?></strong></td>
                        <td><?= $p['area_hectares'] !== null ? number_format((float) $p['area_hectares'], 2) : '—' ?></td>
                        <td>
                            <?php if ($p['growing_count'] > 0): ?>
                                <span class="badge" style="background:#e8f5e9;color:#2e7d32">🌱 <?= $p['growing_count'] ?> <?= current_locale() === 'am' ? 'በማደግ ላይ' : 'growing' ?></span>
                            <?php else: ?>
                                <span class="badge" style="background:#f5f5f5;color:#777"><?= current_locale() === 'am' ? 'ባዶ / ዝግጁ' : 'Fallow' ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/modules/feed_production/plot_view.php?id=<?= (int)$p['id'] ?>" class="btn secondary" style="padding:3px 8px;font-size:12px">
                                👁 <?= current_locale() === 'am' ? 'ዝርዝር እይታ' : 'View' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($plots)): ?>
                    <tr><td colspan="4" class="text-muted" style="text-align:center"><?= current_locale() === 'am' ? 'ምንም የተመዘገበ የመኖ ማሳ የለም።' : 'No feed plots registered yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
