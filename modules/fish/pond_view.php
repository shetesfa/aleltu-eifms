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
        header('Location: /aleltu-eifms/modules/fish/ponds.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['stock_pond'])) {
            fish_stock_pond($pondId, $_POST['species'], $_POST['stocking_date'], (int) $_POST['stocking_quantity'], $_POST['stocking_density_per_sq_m'] !== '' ? (float) $_POST['stocking_density_per_sq_m'] : null);
            flash(current_locale() === 'am' ? '✓ ዓሣ ወደ ኩሬው በተሳካ ሁኔታ ተለቋል።' : 'Pond stocked.');
        } elseif (isset($_POST['feed_stock_id'])) {
            fish_log_feeding((int) $_POST['feed_stock_id'], (float) $_POST['quantity_kg'], $_POST['feed_type'] ?? '');
            flash(current_locale() === 'am' ? '✓ የመኖ አቅርቦት ተመዝግቧል።' : 'Feeding logged.');
        } elseif (isset($_POST['mortality_stock_id'])) {
            fish_record_mortality((int) $_POST['mortality_stock_id'], $_POST['log_date'], (int) $_POST['count'], $_POST['suspected_cause'] ?? '');
            flash(current_locale() === 'am' ? '✓ የሞቱ ዓሦች ቁጥር ተመዝግቧል።' : 'Mortality logged.');
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
        header("Location: /aleltu-eifms/modules/fish/pond_view.php?id={$pondId}");
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$activeStocks = db()->prepare("SELECT * FROM fish_stocks WHERE pond_id = ? AND status = 'active'");
$activeStocks->execute([$pondId]);
$activeStocks = $activeStocks->fetchAll();

// Only fresh fish items — sends directly into the warehouse inventory as Fresh Fish
// Exclude feed items (አልፋልፋ, ድርቆሽ, መኖ)
$items = db()->query("SELECT id, name, sku FROM wh_stock_items WHERE (sku LIKE 'FISH%' OR name LIKE '%ዓሳ%' OR name LIKE '%Fish%') AND name NOT LIKE '%አልፋልፋ%' AND name NOT LIKE '%ድርቆሽ%' AND name NOT LIKE '%መኖ%' ORDER BY id ASC")->fetchAll();
if (empty($items)) {
    $items = db()->query("SELECT id, name, sku FROM wh_stock_items LIMIT 5")->fetchAll();
}
?>
<h2 class="mt-0">🐟 <?= current_locale() === 'am' ? 'ኩሬ' : 'Pond' ?>: <?= h($pond['name']) ?> <span class="badge <?= $pond['status']==='stocked'?'active':'dry' ?>"><?= $pond['status']==='stocked'?(current_locale()==='am'?'ዓሣ ያለበት':'Stocked'):(current_locale()==='am'?'ባዶ':'Empty') ?></span></h2>

<?php if ($pond['status'] === 'empty'): ?>
<div class="card" style="max-width:500px">
    <h3><?= current_locale() === 'am' ? 'ዓሣ ወደ ኩሬው አስገባ' : 'Stock This Pond' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="stock_pond" value="1">
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የዓሣ ዝርያ *' : 'Species *' ?></label><input type="text" name="species" required value="Nile Tilapia (የአባይ ቴላፒያ)"></div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የተለቀቀበት ቀን *' : 'Stocking Date *' ?></label><input type="date" name="stocking_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-row">
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የዓሣ ብዛት (ፍሬ) *' : 'Quantity *' ?></label><input type="number" name="stocking_quantity" required placeholder="5000"></div>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'ጥግግት (በካሬ ሜትር)' : 'Density (per m²)' ?></label><input type="number" step="0.01" name="stocking_density_per_sq_m" placeholder="10"></div>
        </div>
        <button class="btn" type="submit"><?= current_locale() === 'am' ? 'ዓሣውን ወደ ኩሬው ልቀቅ' : 'Stock Pond' ?></button>
    </form>
</div>
<?php endif; ?>

<?php foreach ($activeStocks as $stock): ?>
    <?php $liveCount = (int) $stock['stocking_quantity'] - (int) $stock['cumulative_mortality']; ?>
    <div class="card">
        <h3><?= h($stock['species']) ?> — <?= current_locale() === 'am' ? 'የተለቀቀው በ' : 'stocked on' ?> <?= h(eth_date($stock['stocking_date'])) ?></h3>
        <div class="kpi-row">
            <div class="kpi"><div class="label"><?= h(t('fish.live_count', 'Live Count')) ?></div><div class="value"><?= $liveCount ?></div></div>
            <div class="kpi"><div class="label"><?= h(t('fish.harvested_to_date', 'Harvested to Date')) ?></div><div class="value"><?= number_format($stock['cumulative_harvested_kg'], 1) ?> kg</div></div>
        </div>

        <div class="form-row">
            <form method="post" style="flex:1;border:1px solid #dfe5dd;border-radius:6px;padding:12px"><?= csrf_field() ?>
                <h4 style="margin-top:0">🌿 <?= current_locale() === 'am' ? 'የመኖ አቅርቦት መዝግብ' : 'Log Feeding' ?></h4>
                <input type="hidden" name="feed_stock_id" value="<?= (int) $stock['id'] ?>">
                <div class="form-group"><label><?= current_locale() === 'am' ? 'የመኖ መጠን (ኪ.ግ) *' : 'Quantity (kg) *' ?></label><input type="number" step="0.01" name="quantity_kg" required placeholder="5.0"></div>
                <div class="form-group"><label><?= current_locale() === 'am' ? 'የመኖ ዓይነት' : 'Feed Type' ?></label>
                    <input type="text" name="feed_type" value="የዓሣ መኖ" list="feedTypeSuggestions">
                    <datalist id="feedTypeSuggestions">
                        <option value="የዓሣ መኖ (Fish Feed Pellets)">
                        <option value="ተንሳፋፊ መኖ (Floating Feed)">
                    </datalist>
                </div>
                <button class="btn secondary" type="submit"><?= current_locale() === 'am' ? 'መኖ መዝግብ' : 'Log Feeding' ?></button>
            </form>

            <form method="post" style="flex:1;border:1px solid #dfe5dd;border-radius:6px;padding:12px"><?= csrf_field() ?>
                <h4 style="margin-top:0">⚠️ <?= current_locale() === 'am' ? 'የሞቱ ዓሦች መዝገብ' : 'Log Mortality' ?></h4>
                <input type="hidden" name="mortality_stock_id" value="<?= (int) $stock['id'] ?>">
                <div class="form-group"><label><?= current_locale() === 'am' ? 'ቀን *' : 'Date *' ?></label><input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label><?= current_locale() === 'am' ? 'የሞቱት ብዛት (ፍሬ) *' : 'Count *' ?></label><input type="number" name="count" required></div>
                <div class="form-group"><label><?= current_locale() === 'am' ? 'የተጠረጠረ ምክንያት' : 'Suspected Cause' ?></label><input type="text" name="suspected_cause" placeholder="ለምሳሌ፡ የውሃ ሙቀት፣ የኦክስጅን እጥረት"></div>
                <button class="btn secondary" type="submit"><?= current_locale() === 'am' ? 'የሞት መዝገብ አስገባ' : 'Log Mortality' ?></button>
            </form>
        </div>

        <div style="margin-top:14px">
            <form method="post" style="border:1px solid #dfe5dd;border-radius:6px;padding:12px;max-width:480px"><?= csrf_field() ?>
                <h4 style="margin-top:0">🎣 <?= current_locale() === 'am' ? 'የዓሣ ምርት ሰብስብና ወደ መጋዘን ላክ' : 'Harvest & Send to Store' ?></h4>
                <input type="hidden" name="harvest_stock_id" value="<?= (int) $stock['id'] ?>">
                <div class="form-row">
                    <div class="form-group"><label><?= current_locale() === 'am' ? 'የተሰበሰበበት ቀን *' : 'Date *' ?></label><input type="date" name="harvest_date" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label><?= current_locale() === 'am' ? 'የምርት መጠን (ኪ.ግ) *' : 'Qty (kg) *' ?></label><input type="number" step="0.01" name="quantity_kg" required placeholder="50.0"></div>
                </div>
                <div class="form-group"><label><?= current_locale() === 'am' ? 'ወደ መጋዘን የሚላክበት የዕቃ ዓይነት' : 'Send to Store as' ?></label>
                    <select name="stock_item_id" required>
                        <?php foreach ($items as $i): ?>
                            <option value="<?= (int) $i['id'] ?>"><?= h($i['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="background:rgba(218,18,26,0.06);padding:8px 10px;border-radius:6px;border:1px solid rgba(218,18,26,0.2)">
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

        <!-- Feeding History -->
        <?php
        $feedLogs = db()->query("SELECT * FROM fish_feeding_logs WHERE fish_stock_id = {$stock['id']} ORDER BY scheduled_at DESC LIMIT 10")->fetchAll();
        $harvests = db()->query("SELECT * FROM fish_harvest_records WHERE fish_stock_id = {$stock['id']} ORDER BY harvest_date DESC LIMIT 10")->fetchAll();
        ?>
        <div style="margin-top:16px">
            <h4 style="margin:0 0 8px 0">🌿 <?= current_locale() === 'am' ? 'የቅርብ ጊዜ የመኖ አቅርቦት ታሪክ' : 'Recent Feeding History' ?></h4>
            <table>
                <tr><th><?= current_locale() === 'am' ? 'ቀንና ሰዓት' : 'Date/Time' ?></th><th><?= current_locale() === 'am' ? 'መጠን (ኪ.ግ)' : 'Qty (kg)' ?></th><th><?= current_locale() === 'am' ? 'የመኖ ዓይነት' : 'Feed Type' ?></th></tr>
                <?php foreach ($feedLogs as $fl): ?>
                    <tr><td><?= h(eth_date(substr($fl['scheduled_at'], 0, 10))) ?> <?= h(eth_time($fl['scheduled_at'])) ?></td><td><?= h($fl['quantity_kg']) ?> kg</td><td><?= h($fl['feed_type'] ?: '—') ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($feedLogs)): ?><tr><td colspan="3" class="text-muted"><?= h(t('common.no_records', 'No feeding logs recorded.')) ?></td></tr><?php endif; ?>
            </table>
        </div>

        <?php if (!empty($harvests)): ?>
            <div style="margin-top:16px">
                <h4 style="margin:0 0 8px 0">🎣 <?= current_locale() === 'am' ? 'የምርት ስብሰባ ታሪክ' : 'Harvest History' ?></h4>
                <table>
                    <tr><th><?= current_locale() === 'am' ? 'የተሰበሰበበት ቀን' : 'Harvest Date' ?></th><th><?= current_locale() === 'am' ? 'የምርት መጠን (ኪ.ግ)' : 'Qty (kg)' ?></th><th><?= current_locale() === 'am' ? 'ዓይነት' : 'Type' ?></th></tr>
                    <?php foreach ($harvests as $hr): ?>
                        <tr><td><?= h(eth_date($hr['harvest_date'])) ?></td><td><strong><?= h($hr['quantity_kg']) ?> kg</strong></td><td><span class="badge"><?= h(ucfirst($hr['type'])) ?></span></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<a href="/aleltu-eifms/modules/fish/ponds.php">&larr; <?= current_locale() === 'am' ? 'ወደ ኩሬዎች ዝርዝር ተመለስ' : 'Back to Ponds' ?></a>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
