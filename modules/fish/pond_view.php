<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'fish_manager', 'field_worker']);

$pondId = (int) ($_GET['id'] ?? 0);
$pond = $pondId > 0 ? db()->query("SELECT * FROM fish_ponds WHERE id = {$pondId}")->fetch() : null;
if (!$pond) {
    $firstId = db()->query("SELECT id FROM fish_ponds ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstId) {
        $pondId = (int)$firstId;
        $pond = db()->query("SELECT * FROM fish_ponds WHERE id = {$pondId}")->fetch();
    } else {
        flash('No pond records found.', 'error');
        header('Location: ' . BASE_URL . '/modules/fish/ponds.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['stock_pond'])) {
            fish_stock_pond($pondId, $_POST['species'], $_POST['stocking_date'], (int) $_POST['stocking_quantity'], $_POST['stocking_density_per_sq_m'] !== '' ? (float) $_POST['stocking_density_per_sq_m'] : null);
            flash(current_locale() === 'am' ? '✓ ዓሣ ወደ ኩሬው በተሳካ ሁኔታ ተለቋል።' : 'Pond stocked successfully.');
        } elseif (isset($_POST['feed_stock_id'])) {
            fish_log_feeding((int) $_POST['feed_stock_id'], (float) $_POST['quantity_kg'], $_POST['feed_type'] ?? '');
            flash(current_locale() === 'am' ? '✓ የመኖ አቅርቦት ተመዝግቧል።' : 'Feeding logged successfully.');
        } elseif (isset($_POST['mortality_stock_id'])) {
            fish_record_mortality((int) $_POST['mortality_stock_id'], $_POST['log_date'], (int) $_POST['count'], $_POST['suspected_cause'] ?? '');
            flash(current_locale() === 'am' ? '✓ የሞቱ ዓሦች ቁጥር ተመዝግቧል።' : 'Mortality recorded.');
        } elseif (isset($_POST['sample_growth'])) {
            $stockId = (int) $_POST['growth_stock_id'];
            $sDate = $_POST['sample_date'] ?? date('Y-m-d');
            $sSize = (int) $_POST['sample_size'];
            $avgWeight = (float) $_POST['average_weight_g'];
            if ($stockId > 0 && $sSize > 0 && $avgWeight > 0) {
                $stmt = db()->prepare("INSERT INTO fish_growth_samples (fish_stock_id, sample_date, sample_size, average_weight_g) VALUES (?, ?, ?, ?)");
                $stmt->execute([$stockId, $sDate, $sSize, $avgWeight]);
                flash(current_locale() === 'am' ? '✓ የዓሣ እድገት ናሙና መረጃ ተመዝግቧል።' : 'Growth sample recorded.');
            }
        } elseif (isset($_POST['harvest_stock_id'])) {
            fish_harvest(
                (int) $_POST['harvest_stock_id'],
                $_POST['harvest_date'],
                (float) $_POST['quantity_kg'],
                isset($_POST['final_harvest']),
                (int) $_POST['stock_item_id']
            );
            flash(current_locale() === 'am' ? '✓ የዓሣ ምርት ተሰብስቦ ወደ ማዕከላዊ መጋዘን ተልኳል።' : 'Harvest recorded and moved to Warehouse.');
        }
        $tabRedirect = $_POST['active_tab'] ?? 'summary';
        header("Location: " . BASE_URL . "/modules/fish/pond_view.php?id={$pondId}&tab=" . urlencode($tabRedirect));
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$activeStocks = db()->prepare("SELECT * FROM fish_stocks WHERE pond_id = ? AND status = 'active'");
$activeStocks->execute([$pondId]);
$activeStocks = $activeStocks->fetchAll();
$currentStock = $activeStocks[0] ?? null;

// Only fresh fish items — sends directly into the warehouse inventory as Fresh Fish
$items = db()->query("SELECT id, name, sku FROM wh_stock_items WHERE (sku LIKE 'FISH%' OR name LIKE '%ዓሳ%' OR name LIKE '%Fish%') AND name NOT LIKE '%አልፋልፋ%' AND name NOT LIKE '%ድርቆሽ%' AND name NOT LIKE '%መኖ%' ORDER BY id ASC")->fetchAll();
if (empty($items)) {
    $items = db()->query("SELECT id, name, sku FROM wh_stock_items LIMIT 5")->fetchAll();
}

$feedLogs = [];
$mortalityLogs = [];
$growthSamples = [];
$harvests = [];
$allStocks = db()->query("SELECT * FROM fish_stocks WHERE pond_id = {$pondId} ORDER BY id DESC")->fetchAll();

if ($currentStock) {
    $stId = (int)$currentStock['id'];
    $feedLogs = db()->query("SELECT * FROM fish_feeding_logs WHERE fish_stock_id = {$stId} ORDER BY scheduled_at DESC LIMIT 30")->fetchAll();
    $mortalityLogs = db()->query("SELECT * FROM fish_mortality_logs WHERE fish_stock_id = {$stId} ORDER BY log_date DESC LIMIT 30")->fetchAll();
    $growthSamples = db()->query("SELECT * FROM fish_growth_samples WHERE fish_stock_id = {$stId} ORDER BY sample_date DESC LIMIT 30")->fetchAll();
    $harvests = db()->query("SELECT * FROM fish_harvest_records WHERE fish_stock_id = {$stId} ORDER BY harvest_date DESC LIMIT 30")->fetchAll();
}

$activeTab = $_GET['tab'] ?? 'summary';
$validTabs = ['summary', 'stock', 'feeding', 'mortality', 'growth', 'harvest', 'history'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'summary';
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            🐟 <?= current_locale() === 'am' ? 'የኩሬ እይታ' : 'Pond View' ?>: <?= h($pond['name']) ?>
            <span class="badge <?= $pond['status']==='stocked'?'active':'dry' ?>" style="margin-left:8px">
                <?= $pond['status']==='stocked'?(current_locale()==='am'?'ዓሣ ያለበት':'Stocked'):(current_locale()==='am'?'ባዶ ኩሬ':'Empty') ?>
            </span>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'የኩሬው ሙሉ መረጃ፣ የዓሣ ክምችት፣ መኖ፣ ሞት፣ እድገት እና ምርት ስብሰባ።' : 'Comprehensive pond profile, stocking, feeding, mortality, growth samples and harvesting.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/fish/ponds.php" class="btn secondary">← <?= current_locale() === 'am' ? 'ወደ ኩሬዎች ዝርዝር' : 'Back to Ponds' ?></a>
    </div>
</div>

<!-- 7 Modular Navigation Tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap;border-bottom:2px solid var(--border);padding-bottom:8px">
    <a href="?id=<?= $pondId ?>&tab=summary" class="btn <?= $activeTab === 'summary' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:13px">
        📊 <?= current_locale() === 'am' ? '1. ማጠቃለያ' : '1. Summary' ?>
    </a>
    <a href="?id=<?= $pondId ?>&tab=stock" class="btn <?= $activeTab === 'stock' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:13px">
        🐟 <?= current_locale() === 'am' ? '2. ክምችት (Stock)' : '2. Stock' ?>
    </a>
    <a href="?id=<?= $pondId ?>&tab=feeding" class="btn <?= $activeTab === 'feeding' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:13px">
        🌿 <?= current_locale() === 'am' ? '3. መኖ አቅርቦት' : '3. Feeding' ?>
    </a>
    <a href="?id=<?= $pondId ?>&tab=mortality" class="btn <?= $activeTab === 'mortality' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:13px">
        ⚠️ <?= current_locale() === 'am' ? '4. የሞት መዝገብ' : '4. Mortality' ?>
    </a>
    <a href="?id=<?= $pondId ?>&tab=growth" class="btn <?= $activeTab === 'growth' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:13px">
        📈 <?= current_locale() === 'am' ? '5. እድገትና ናሙና' : '5. Growth' ?>
    </a>
    <a href="?id=<?= $pondId ?>&tab=harvest" class="btn <?= $activeTab === 'harvest' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:13px">
        🎣 <?= current_locale() === 'am' ? '6. ምርት ስብሰባ' : '6. Harvest' ?>
    </a>
    <a href="?id=<?= $pondId ?>&tab=history" class="btn <?= $activeTab === 'history' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:13px">
        📜 <?= current_locale() === 'am' ? '7. ሙሉ ታሪክ' : '7. History' ?>
    </a>
</div>

<!-- TAB 1: SUMMARY -->
<?php if ($activeTab === 'summary'): ?>
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">📊 <?= current_locale() === 'am' ? 'የኩሬው አጠቃላይ ማጠቃለያ' : 'Pond Overview' ?></h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;margin-bottom:18px">
        <div style="background:var(--hover);padding:12px 14px;border-radius:8px">
            <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የኩሬ ስም' : 'Pond Name' ?></div>
            <div style="font-size:16px;font-weight:700"><?= h($pond['name']) ?></div>
        </div>
        <div style="background:var(--hover);padding:12px 14px;border-radius:8px">
            <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የኩሬ ስፋት' : 'Surface Area' ?></div>
            <div style="font-size:16px;font-weight:700"><?= number_format($pond['surface_area_sq_m'] ?? 0, 1) ?> m²</div>
        </div>
        <div style="background:var(--hover);padding:12px 14px;border-radius:8px">
            <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የውሃ ጥልቀት' : 'Water Depth' ?></div>
            <div style="font-size:16px;font-weight:700"><?= number_format($pond['depth_m'] ?? 0, 1) ?> m</div>
        </div>
        <div style="background:var(--hover);padding:12px 14px;border-radius:8px">
            <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የኩሬው ሁኔታ' : 'Status' ?></div>
            <div style="font-size:16px;font-weight:700"><?= $pond['status']==='stocked'?(current_locale()==='am'?'ዓሣ ያለበት':'Stocked'):(current_locale()==='am'?'ባዶ':'Empty') ?></div>
        </div>
    </div>

    <?php if ($currentStock): ?>
        <?php
        $liveCount = (int)$currentStock['stocking_quantity'] - (int)$currentStock['cumulative_mortality'];
        $survivalRate = (int)$currentStock['stocking_quantity'] > 0 ? round(($liveCount / (int)$currentStock['stocking_quantity']) * 100, 1) : 0;
        ?>
        <h4 style="margin:16px 0 8px">🐟 <?= current_locale() === 'am' ? 'የአሁኑ የዓሣ ዝርያ መረጃ' : 'Active Stock Details' ?></h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px">
            <div style="background:rgba(46,125,50,0.08);border-left:4px solid #2e7d32;padding:12px 14px;border-radius:6px">
                <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የዓሣ ዝርያ' : 'Species' ?></div>
                <div style="font-size:16px;font-weight:700"><?= h($currentStock['species']) ?></div>
            </div>
            <div style="background:rgba(46,125,50,0.08);border-left:4px solid #2e7d32;padding:12px 14px;border-radius:6px">
                <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'ህያው ዓሦች (Live Count)' : 'Live Count' ?></div>
                <div style="font-size:18px;font-weight:700;color:#2e7d32"><?= number_format($liveCount) ?> / <?= number_format($currentStock['stocking_quantity']) ?></div>
            </div>
            <div style="background:rgba(46,125,50,0.08);border-left:4px solid #2e7d32;padding:12px 14px;border-radius:6px">
                <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የመዳን ምጣኔ (Survival)' : 'Survival Rate' ?></div>
                <div style="font-size:18px;font-weight:700;color:#2e7d32"><?= $survivalRate ?>%</div>
            </div>
            <div style="background:rgba(46,125,50,0.08);border-left:4px solid #2e7d32;padding:12px 14px;border-radius:6px">
                <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የተሰበሰበ ምርት' : 'Total Harvested' ?></div>
                <div style="font-size:18px;font-weight:700;color:var(--primary)"><?= number_format($currentStock['cumulative_harvested_kg'], 1) ?> kg</div>
            </div>
        </div>
    <?php else: ?>
        <p class="text-muted"><?= current_locale() === 'am' ? 'በአሁኑ ሰዓት በኩሬው ውስጥ ምንም ንቁ የዓሣ ክምችት የለም። ዓሣ ለመልቀቅ የ "ክምችት (Stock)" ታብ ይጠቀሙ።' : 'Pond currently has no active stock. Go to Stock tab to stock it.' ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- TAB 2: STOCK -->
<?php if ($activeTab === 'stock'): ?>
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">🐟 <?= current_locale() === 'am' ? 'የዓሣ ክምችት እና መልቀቂያ' : 'Fish Stocking' ?></h3>
    <?php if ($pond['status'] === 'empty' || empty($currentStock)): ?>
        <div style="max-width:550px">
            <h4>➕ <?= current_locale() === 'am' ? 'አዲስ ዓሣ ወደ ኩሬው ልቀቅ' : 'Stock This Pond' ?></h4>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="stock_pond" value="1">
                <input type="hidden" name="active_tab" value="stock">
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የዓሣ ዝርያ *' : 'Species *' ?></label>
                    <input type="text" name="species" required value="Nile Tilapia (የአባይ ቴላፒያ)">
                </div>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የተለቀቀበት ቀን *' : 'Stocking Date *' ?></label>
                    <input type="date" name="stocking_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የዓሣ ብዛት (ፍሬ) *' : 'Quantity *' ?></label>
                        <input type="number" name="stocking_quantity" required placeholder="5000">
                    </div>
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'ጥግግት (በካሬ ሜትር)' : 'Density (per m²)' ?></label>
                        <input type="number" step="0.01" name="stocking_density_per_sq_m" placeholder="10">
                    </div>
                </div>
                <button class="btn" type="submit"><?= current_locale() === 'am' ? 'ዓሣውን ወደ ኩሬው ልቀቅ' : 'Stock Pond' ?></button>
            </form>
        </div>
    <?php else: ?>
        <div class="card" style="background:var(--hover);border-left:4px solid #2e7d32;margin-bottom:16px">
            <h4 style="margin:0 0 6px"><?= h($currentStock['species']) ?></h4>
            <p style="margin:0;font-size:13px">
                <?= current_locale() === 'am' ? 'የተለቀቀበት ቀን፦' : 'Stocked Date:' ?> <strong><?= eth_date($currentStock['stocking_date']) ?></strong> | 
                <?= current_locale() === 'am' ? 'የመጀመሪያ ብዛት፦' : 'Initial Qty:' ?> <strong><?= number_format($currentStock['stocking_quantity']) ?></strong> | 
                <?= current_locale() === 'am' ? 'ጥግግት፦' : 'Density:' ?> <strong><?= $currentStock['stocking_density_per_sq_m'] ?> /m²</strong>
            </p>
        </div>
    <?php endif; ?>

    <h4 style="margin-top:20px">📋 <?= current_locale() === 'am' ? 'የኩሬው የዓሣ ክምችት ታሪክ' : 'Pond Stocking History' ?></h4>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'ዝርያ' : 'Species' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተለቀቀበት ቀን' : 'Stocked Date' ?></th>
                    <th><?= current_locale() === 'am' ? 'ብዛት' : 'Qty' ?></th>
                    <th><?= current_locale() === 'am' ? 'የሞቱ' : 'Mortality' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተሰበሰበ (ኪ.ግ)' : 'Harvested (kg)' ?></th>
                    <th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allStocks as $st): ?>
                    <tr>
                        <td><strong><?= h($st['species']) ?></strong></td>
                        <td><?= eth_date($st['stocking_date']) ?></td>
                        <td><?= number_format($st['stocking_quantity']) ?></td>
                        <td style="color:#c62828"><?= number_format($st['cumulative_mortality']) ?></td>
                        <td style="color:var(--primary);font-weight:700"><?= number_format($st['cumulative_harvested_kg'], 1) ?> kg</td>
                        <td><span class="badge <?= $st['status']==='active'?'milking':'sold' ?>"><?= h($st['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- TAB 3: FEEDING -->
<?php if ($activeTab === 'feeding'): ?>
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">🌿 <?= current_locale() === 'am' ? 'የመኖ አቅርቦት አስተዳደር' : 'Feeding Management' ?></h3>
    <?php if ($currentStock): ?>
        <div style="max-width:500px;margin-bottom:20px">
            <h4>➕ <?= current_locale() === 'am' ? 'አዲስ የመኖ አቅርቦት መዝግብ' : 'Log Feeding' ?></h4>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="feed_stock_id" value="<?= (int) $currentStock['id'] ?>">
                <input type="hidden" name="active_tab" value="feeding">
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የመኖ መጠን (ኪ.ግ) *' : 'Quantity (kg) *' ?></label>
                    <input type="number" step="0.01" name="quantity_kg" required placeholder="5.0">
                </div>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የመኖ ዓይነት' : 'Feed Type' ?></label>
                    <input type="text" name="feed_type" value="የዓሣ መኖ" list="feedTypeSuggestions">
                    <datalist id="feedTypeSuggestions">
                        <option value="የዓሣ መኖ (Fish Feed Pellets)">
                        <option value="ተንሳፋፊ መኖ (Floating Feed)">
                    </datalist>
                </div>
                <button class="btn" type="submit">🌿 <?= current_locale() === 'am' ? 'መኖ መዝግብ' : 'Log Feeding' ?></button>
            </form>
        </div>

        <h4>📋 <?= current_locale() === 'am' ? 'የመኖ አቅርቦት ታሪክ' : 'Feeding Log History' ?></h4>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th><?= current_locale() === 'am' ? 'ቀንና ሰዓት' : 'Date/Time' ?></th>
                        <th><?= current_locale() === 'am' ? 'የመኖ መጠን (ኪ.ግ)' : 'Quantity (kg)' ?></th>
                        <th><?= current_locale() === 'am' ? 'የመኖ ዓይነት' : 'Feed Type' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedLogs as $fl): ?>
                        <tr>
                            <td><?= h(eth_date(substr($fl['scheduled_at'], 0, 10))) ?> <?= h(eth_time($fl['scheduled_at'])) ?></td>
                            <td><strong><?= h($fl['quantity_kg']) ?> kg</strong></td>
                            <td><?= h($fl['feed_type'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($feedLogs)): ?>
                        <tr><td colspan="3" class="text-muted"><?= current_locale() === 'am' ? 'ምንም የመኖ መዝገብ የለም።' : 'No feeding logs recorded.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted"><?= current_locale() === 'am' ? 'መኖ ለመመዝገብ መጀመሪያ ዓሣ ወደ ኩሬው መለቀቅ አለበት።' : 'Pond must be stocked to log feeding.' ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- TAB 4: MORTALITY -->
<?php if ($activeTab === 'mortality'): ?>
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">⚠️ <?= current_locale() === 'am' ? 'የዓሣ ሞት መዝገብ' : 'Mortality Tracking' ?></h3>
    <?php if ($currentStock): ?>
        <div style="max-width:500px;margin-bottom:20px">
            <h4>➕ <?= current_locale() === 'am' ? 'የሞቱ ዓሦች መዝግብ' : 'Log Mortality' ?></h4>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="mortality_stock_id" value="<?= (int) $currentStock['id'] ?>">
                <input type="hidden" name="active_tab" value="mortality">
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'ቀን *' : 'Date *' ?></label>
                    <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የሞቱት ብዛት (ፍሬ) *' : 'Count *' ?></label>
                    <input type="number" name="count" required placeholder="0">
                </div>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የተጠረጠረ ምክንያት' : 'Suspected Cause' ?></label>
                    <input type="text" name="suspected_cause" placeholder="ለምሳሌ፡ የውሃ ሙቀት፣ የኦክስጅን እጥረት">
                </div>
                <button class="btn secondary" type="submit">⚠️ <?= current_locale() === 'am' ? 'የሞት መዝገብ አስገባ' : 'Log Mortality' ?></button>
            </form>
        </div>

        <h4>📋 <?= current_locale() === 'am' ? 'የሞት መዝገቦች ታሪክ' : 'Mortality Log History' ?></h4>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th>
                        <th><?= current_locale() === 'am' ? 'የሞቱት ብዛት' : 'Count' ?></th>
                        <th><?= current_locale() === 'am' ? 'ምክንያት' : 'Cause' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mortalityLogs as $ml): ?>
                        <tr>
                            <td><?= eth_date($ml['log_date']) ?></td>
                            <td><strong style="color:#c62828"><?= number_format($ml['count']) ?></strong></td>
                            <td><?= h($ml['suspected_cause'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($mortalityLogs)): ?>
                        <tr><td colspan="3" class="text-muted"><?= current_locale() === 'am' ? 'ምንም የሞት መዝገብ የለም።' : 'No mortality logged.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted"><?= current_locale() === 'am' ? 'የሞት መዝገብ ለማስገባት መጀመሪያ ዓሣ በኩሬው ውስጥ ሊኖር ይገባል።' : 'Pond must be stocked to log mortality.' ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- TAB 5: GROWTH & SAMPLING -->
<?php if ($activeTab === 'growth'): ?>
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">📈 <?= current_locale() === 'am' ? 'የዓሣ እድገትና ክብደት ናሙና' : 'Growth & Weight Sampling' ?></h3>
    <?php if ($currentStock): ?>
        <div style="max-width:500px;margin-bottom:20px">
            <h4>➕ <?= current_locale() === 'am' ? 'የእድገት ናሙና መዝግብ' : 'Log Growth Sample' ?></h4>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="sample_growth" value="1">
                <input type="hidden" name="growth_stock_id" value="<?= (int) $currentStock['id'] ?>">
                <input type="hidden" name="active_tab" value="growth">
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የናሙና የተወሰደበት ቀን *' : 'Sample Date *' ?></label>
                    <input type="date" name="sample_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የናሙና ብዛት (ፍሬ) *' : 'Sample Size *' ?></label>
                        <input type="number" name="sample_size" required placeholder="30">
                    </div>
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'አማካይ ክብደት (በግራም) *' : 'Average Weight (g) *' ?></label>
                        <input type="number" step="0.1" name="average_weight_g" required placeholder="250.0">
                    </div>
                </div>
                <button class="btn" type="submit">📈 <?= current_locale() === 'am' ? 'የእድገት ናሙና አስገባ' : 'Save Sample' ?></button>
            </form>
        </div>

        <h4>📋 <?= current_locale() === 'am' ? 'የእድገት ናሙናዎች ታሪክ' : 'Growth Sampling History' ?></h4>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th><?= current_locale() === 'am' ? 'የናሙና ቀን' : 'Sample Date' ?></th>
                        <th><?= current_locale() === 'am' ? 'የናሙና ብዛት' : 'Sample Size' ?></th>
                        <th><?= current_locale() === 'am' ? 'አማካይ ክብደት (ግራም)' : 'Avg Weight (g)' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($growthSamples as $gs): ?>
                        <tr>
                            <td><?= eth_date($gs['sample_date']) ?></td>
                            <td><?= number_format($gs['sample_size']) ?> <?= current_locale() === 'am' ? 'ፍሬ' : 'pcs' ?></td>
                            <td><strong style="color:var(--primary)"><?= number_format($gs['average_weight_g'], 1) ?> g</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($growthSamples)): ?>
                        <tr><td colspan="3" class="text-muted"><?= current_locale() === 'am' ? 'ምንም የእድገት ናሙና መረጃ የለም።' : 'No growth samples recorded yet.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted"><?= current_locale() === 'am' ? 'የእድገት ናሙና ለመመዝገብ መጀመሪያ ዓሣ በኩሬው ውስጥ ሊኖር ይገባል።' : 'Pond must be stocked to log growth.' ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- TAB 6: HARVEST -->
<?php if ($activeTab === 'harvest'): ?>
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">🎣 <?= current_locale() === 'am' ? 'የዓሣ ምርት ስብሰባ እና ማስተላለፍ' : 'Harvesting & Transfer to Store' ?></h3>
    <?php if ($currentStock): ?>
        <div style="max-width:520px;margin-bottom:20px">
            <h4>➕ <?= current_locale() === 'am' ? 'ምርት ሰብስብና ወደ መጋዘን ላክ' : 'Harvest Fish' ?></h4>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="harvest_stock_id" value="<?= (int) $currentStock['id'] ?>">
                <input type="hidden" name="active_tab" value="harvest">
                <div class="form-row">
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የተሰበሰበበት ቀን *' : 'Harvest Date *' ?></label>
                        <input type="date" name="harvest_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የምርት መጠን (ኪ.ግ) *' : 'Qty (kg) *' ?></label>
                        <input type="number" step="0.01" name="quantity_kg" required placeholder="50.0">
                    </div>
                </div>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'ወደ መጋዘን የሚላክበት የዕቃ ዓይነት' : 'Send to Store as' ?></label>
                    <select name="stock_item_id" required>
                        <?php foreach ($items as $i): ?>
                            <option value="<?= (int) $i['id'] ?>"><?= h($i['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="background:rgba(218,18,26,0.06);padding:10px 12px;border-radius:6px;border:1px solid rgba(218,18,26,0.2)">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:700;color:#b3261e;cursor:pointer">
                        <input type="checkbox" name="final_harvest" value="1" style="width:auto">
                        <?= current_locale() === 'am' ? 'ይህ የመጨረሻው ምርት መሰብሰቢያ ነው (ኩሬውን ባዶ ያደርገዋል)' : 'This is the final harvest (empties the pond)' ?>
                    </label>
                </div>
                <button class="btn danger" type="submit" onclick="return confirm('<?= current_locale() === 'am' ? 'እርግጠኛ ነዎት? የዓሣ ምርቱ ተመዝግቦ ወደ መጋዘን ይላካል።' : 'Confirm harvest record?' ?>')">
                    🎣 <?= current_locale() === 'am' ? 'ምርቱን መዝግብና ወደ መጋዘን ላክ' : 'Record Harvest' ?>
                </button>
            </form>
        </div>

        <h4>📋 <?= current_locale() === 'am' ? 'የተሰበሰቡ ምርቶች ታሪክ' : 'Harvest History' ?></h4>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th><?= current_locale() === 'am' ? 'የተሰበሰበበት ቀን' : 'Harvest Date' ?></th>
                        <th><?= current_locale() === 'am' ? 'የምርት መጠን (ኪ.ግ)' : 'Qty (kg)' ?></th>
                        <th><?= current_locale() === 'am' ? 'የስብሰባ ዓይነት' : 'Type' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($harvests as $hr): ?>
                        <tr>
                            <td><?= eth_date($hr['harvest_date']) ?></td>
                            <td><strong style="color:var(--primary)"><?= number_format($hr['quantity_kg'], 1) ?> kg</strong></td>
                            <td><span class="badge"><?= h(ucfirst($hr['type'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($harvests)): ?>
                        <tr><td colspan="3" class="text-muted"><?= current_locale() === 'am' ? 'ምንም የተሰበሰበ ምርት የለም።' : 'No harvests recorded yet.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted"><?= current_locale() === 'am' ? 'ምርት ለመሰብሰብ መጀመሪያ በኩሬው ውስጥ ዓሣ ሊኖር ይገባል።' : 'Pond must be stocked to harvest.' ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- TAB 7: HISTORY -->
<?php if ($activeTab === 'history'): ?>
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">📜 <?= current_locale() === 'am' ? 'የኩሬው ሙሉ እንቅስቃሴዎች ታሪክ' : 'Pond History Timeline' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'የኢትዮጵያ ቀን' : 'Date' ?></th>
                    <th><?= current_locale() === 'am' ? 'የክንውን ዓይነት' : 'Event Type' ?></th>
                    <th><?= current_locale() === 'am' ? 'ዝርዝር መግለጫ' : 'Description' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $timeline = [];
                foreach ($allStocks as $s) {
                    $timeline[] = ['date' => $s['stocking_date'], 'type' => 'Stocking', 'desc' => "Stocked {$s['stocking_quantity']} pcs of {$s['species']}"];
                }
                foreach ($feedLogs as $f) {
                    $timeline[] = ['date' => substr($f['scheduled_at'], 0, 10), 'type' => 'Feeding', 'desc' => "Fed {$f['quantity_kg']} kg ({$f['feed_type']})"];
                }
                foreach ($mortalityLogs as $m) {
                    $timeline[] = ['date' => $m['log_date'], 'type' => 'Mortality', 'desc' => "Mortality: {$m['count']} pcs - Cause: " . ($m['suspected_cause'] ?: 'Unspecified')];
                }
                foreach ($harvests as $h) {
                    $timeline[] = ['date' => $h['harvest_date'], 'type' => 'Harvest', 'desc' => "Harvested {$h['quantity_kg']} kg ({$h['type']})"];
                }
                usort($timeline, fn($a, $b) => strcmp($b['date'], $a['date']));
                ?>
                <?php foreach (array_slice($timeline, 0, 50) as $t): ?>
                    <tr>
                        <td><strong><?= eth_date($t['date']) ?></strong></td>
                        <td><span class="badge active"><?= h($t['type']) ?></span></td>
                        <td><?= h($t['desc']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($timeline)): ?>
                    <tr><td colspan="3" class="text-muted"><?= current_locale() === 'am' ? 'ምንም እንቅስቃሴ አልተመዘገበም።' : 'No timeline activity recorded.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div style="margin-top:16px">
    <a href="<?= BASE_URL ?>/modules/fish/ponds.php" class="btn secondary">← <?= current_locale() === 'am' ? 'ወደ ኩሬዎች ዝርዝር' : 'Back to Ponds' ?></a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
