<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'feed_production_manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['produce_batch'])) {
            $formulaId = (int)($_POST['formula_id'] ?? 0);
            $targetItemId = (int)($_POST['target_stock_item_id'] ?? 0);
            $producedKg = (float)($_POST['produced_quantity_kg'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            if ($formulaId <= 0 || $targetItemId <= 0 || $producedKg <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ ትክክለኛ ፎርሙላ፣ የተጠናቀቀ የመኖ ዕቃ እና የማምረቻ መጠን ያስገቡ።' : 'Please select a valid formula, target feed item, and production quantity.');
            }

            $batchId = feed_batch_produce($formulaId, $producedKg, $targetItemId, $notes ?: null);
            flash(current_locale() === 'am' ? "✓ የመኖ ማምረቻ ባች #{$batchId} በተሳካ ሁኔታ ተከናውኗል። ጥሬ ዕቃዎች ተቀንሰው የተጠናቀቀው መኖ ወደ መጋዘን ገብቷል።" : "Feed production batch #{$batchId} completed successfully. Raw materials issued and finished feed stocked.");
            header('Location: ' . BASE_URL . '/modules/feed_production/batches.php');
            exit;
        }
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

// Fetch active formulas
$formulas = db()->query("
    SELECT f.*, 
           (SELECT COUNT(*) FROM feed_formula_ingredients WHERE formula_id = f.id) AS ingredient_count
    FROM feed_formulas f 
    WHERE f.is_active = 1 
    ORDER BY f.name ASC
")->fetchAll();

// Fetch target feed stock items (finished feeds)
$targetItems = db()->query("
    SELECT id, sku, name, unit 
    FROM wh_stock_items 
    WHERE partition_code = 'feed_nutrition' OR product_class = 'feed' OR sku LIKE 'FEED%'
    ORDER BY name ASC
")->fetchAll();

// Fetch production batches
$batches = db()->query("
    SELECT b.*, f.name AS formula_name, f.code AS formula_code,
           si.name AS target_item_name, si.sku AS target_item_sku, si.unit AS target_item_unit,
           u.name AS producer_name
    FROM feed_production_batches b
    JOIN feed_formulas f ON f.id = b.formula_id
    JOIN wh_stock_items si ON si.id = b.target_stock_item_id
    LEFT JOIN users u ON u.id = b.produced_by
    ORDER BY b.id DESC
")->fetchAll();

$totalBatches = count($batches);
$totalKgProduced = array_sum(array_column($batches, 'produced_quantity_kg'));
$totalRawCost = array_sum(array_column($batches, 'total_raw_cost'));

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">⚙️ <?= current_locale() === 'am' ? 'የመኖ ማምረቻ ባቾች (Feed Production Batches)' : 'Feed Production Batches' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'በቀመር መሰረት ጥሬ ዕቃዎችን ከጎተራ በመቀነስ የተጠናቀቀ መኖ ማምረት እና ወደ መጋዘን ማስገባት' : 'Execute feed production runs, consume FIFO raw ingredients, and deposit finished feed into inventory.' ?></p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/feed_production/formulas.php" class="btn secondary">🧪 <?= current_locale() === 'am' ? 'የመኖ ቀመሮች' : 'Formulas' ?></a>
        <a href="<?= BASE_URL ?>/modules/feed_production/dashboard.php" class="btn secondary">🌾 <?= current_locale() === 'am' ? 'የመኖ ዳሽቦርድ' : 'Feed Dashboard' ?></a>
    </div>
</div>

<div class="metrics-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:20px">
    <div class="card" style="margin:0;border-left:4px solid #1976d2">
        <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'ጠቅላላ የተከናወኑ ባቾች' : 'Total Batches Run' ?></div>
        <div style="font-size:24px;font-weight:700;color:#1976d2"><?= $totalBatches ?></div>
    </div>
    <div class="card" style="margin:0;border-left:4px solid #2e7d32">
        <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'ጠቅላላ የተመረተ መኖ (ኪ.ግ)' : 'Total Produced Feed (kg)' ?></div>
        <div style="font-size:24px;font-weight:700;color:#2e7d32"><?= number_format($totalKgProduced, 1) ?> kg</div>
    </div>
    <div class="card" style="margin:0;border-left:4px solid #f57c00">
        <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'ጠቅላላ የወጣ የጥሬ ዕቃ ወጪ' : 'Total Raw Material Cost' ?></div>
        <div style="font-size:24px;font-weight:700;color:#f57c00"><?= number_format($totalRawCost, 2) ?> ETB</div>
    </div>
</div>

<!-- BATCH EXECUTION CARD -->
<div class="card" style="margin-bottom:24px">
    <h3 style="margin-top:0">🏭 <?= current_locale() === 'am' ? 'አዲስ የመኖ ማምረቻ ባች ማከናወኛ' : 'Run New Feed Production Batch' ?></h3>
    <?php if (empty($formulas)): ?>
        <p class="text-muted"><?= current_locale() === 'am' ? 'ምንም ንቁ የመኖ ቀመር አልተገኘም። እባክዎ መጀመሪያ በ "የመኖ ቀመሮች" ገጽ ላይ ቀመር ይመዝግቡ።' : 'No active formulas found. Please register a formula first.' ?></p>
        <a href="<?= BASE_URL ?>/modules/feed_production/formulas.php" class="btn">🧪 <?= current_locale() === 'am' ? 'ቀመር ለመመዝገብ እዚህ ይጫኑ' : 'Go to Formulas' ?></a>
    <?php else: ?>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="produce_batch" value="1">
            <div class="form-row" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:14px">
                <div class="form-group" style="margin:0">
                    <label><?= current_locale() === 'am' ? 'የመኖ ቀመር ምረጥ *' : 'Select Formula *' ?></label>
                    <select name="formula_id" required>
                        <option value=""><?= current_locale() === 'am' ? '-- ቀመር ምረጥ --' : '-- Select Formula --' ?></option>
                        <?php foreach ($formulas as $f): ?>
                            <option value="<?= $f['id'] ?>">
                                <?= h($f['name']) ?> (<?= h($f['code']) ?> - <?= ucfirst(h($f['target_animal'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin:0">
                    <label><?= current_locale() === 'am' ? 'የሚመረተው የመኖ ዕቃ (ጎተራ) *' : 'Target Stock Item *' ?></label>
                    <select name="target_stock_item_id" required>
                        <option value=""><?= current_locale() === 'am' ? '-- የመኖ ዕቃ ምረጥ --' : '-- Select Target Feed Item --' ?></option>
                        <?php foreach ($targetItems as $ti): ?>
                            <option value="<?= $ti['id'] ?>">
                                <?= h($ti['name']) ?> (<?= h($ti['sku']) ?> - <?= h($ti['unit']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin:0">
                    <label><?= current_locale() === 'am' ? 'የሚመረተው መጠን (በኪሎግራም) *' : 'Production Quantity (kg) *' ?></label>
                    <input type="number" step="0.01" min="0.1" name="produced_quantity_kg" required placeholder="e.g. 500.00">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px">
                <label><?= current_locale() === 'am' ? 'የማምረቻ ማስታወሻ (አማራጭ)' : 'Production Notes (optional)' ?></label>
                <input type="text" name="notes" placeholder="<?= current_locale() === 'am' ? 'የፈረቃ መረጃ፣ የማሽን ሁኔታ፣ ወዘተ...' : 'Operator info, machine/mill notes...' ?>">
            </div>

            <div style="background:#e8f5e9;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:13px;color:#1b5e20">
                ℹ️ <?= current_locale() === 'am' ? 'ይህ ትዕዛዝ በቀመሩ ምጣኔ መሰረት ጥሬ ዕቃዎችን ከማዕከላዊ መጋዘን በ FIFO ቅደም ተከተል ይቀንሳል፣ የወጣውን ትክክለኛ ወጪ ያሰላል፣ እና አዲሱን መኖ ወደ መጋዘን ክምችት ያስገባል።' : 'This will automatically issue raw materials from FIFO batches, calculate true unit cost per kg, and receive the finished feed batch into warehouse inventory.' ?>
            </div>

            <button class="btn" type="submit" style="padding:8px 18px">⚙️ <?= current_locale() === 'am' ? 'ባቹን አምርትና ወደ መጋዘን አስገባ' : 'Produce & Deposit to Warehouse' ?></button>
        </form>
    <?php endif; ?>
</div>

<!-- BATCHES HISTORY -->
<div class="card">
    <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'የተከናወኑ የማምረቻ ባቾች ታሪክ' : 'Production Batch History' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'የባች ኮድ' : 'Batch Code' ?></th>
                    <th><?= current_locale() === 'am' ? 'ቀመር' : 'Formula' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተመረተ የመኖ ዓይነት' : 'Target Item' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተመረተ መጠን' : 'Produced Qty' ?></th>
                    <th><?= current_locale() === 'am' ? 'ጠቅላላ የጥሬ ዕቃ ወጪ' : 'Total Raw Cost' ?></th>
                    <th><?= current_locale() === 'am' ? 'የ1 ኪ.ግ እውነተኛ ወጪ' : 'Unit Cost/kg' ?></th>
                    <th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th>
                    <th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $b): ?>
                    <?php
                    $ingLogs = db()->prepare("
                        SELECT bi.*, si.name AS item_name, si.unit
                        FROM feed_batch_ingredients bi
                        JOIN wh_stock_items si ON si.id = bi.stock_item_id
                        WHERE bi.production_batch_id = ?
                    ");
                    $ingLogs->execute([$b['id']]);
                    $ings = $ingLogs->fetchAll();
                    ?>
                    <tr>
                        <td><strong><code><?= h($b['batch_code']) ?></code></strong></td>
                        <td><?= h($b['formula_name']) ?> <span class="badge" style="background:#f1f8e9;color:#33691e;font-size:10px"><?= h($b['formula_code']) ?></span></td>
                        <td><?= h($b['target_item_name']) ?> (<code><?= h($b['target_item_sku']) ?></code>)</td>
                        <td style="font-weight:700;color:var(--primary)"><?= number_format((float)$b['produced_quantity_kg'], 1) ?> <?= h($b['target_item_unit']) ?></td>
                        <td><?= number_format((float)$b['total_raw_cost'], 2) ?> ETB</td>
                        <td style="font-weight:700;color:#2e7d32"><?= number_format((float)$b['unit_cost_per_kg'], 4) ?> ETB/<?= h($b['target_item_unit']) ?></td>
                        <td><?= eth_date($b['production_date']) ?></td>
                        <td>
                            <span class="badge <?= $b['status'] === 'completed' ? 'active' : 'dry' ?>">
                                <?= $b['status'] === 'completed' ? (current_locale() === 'am' ? 'የተጠናቀቀ' : 'Completed') : h($b['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php if (!empty($ings)): ?>
                        <tr style="background:var(--hover)">
                            <td colspan="8" style="padding:6px 14px;font-size:12px">
                                <strong><?= current_locale() === 'am' ? 'የተፈጁ ጥሬ ዕቃዎች፦' : 'Consumed Ingredients:' ?></strong>
                                <?php foreach ($ings as $bi): ?>
                                    <span style="display:inline-block;margin-right:12px;background:#fff;padding:2px 8px;border-radius:4px;border:1px solid #ddd">
                                        <?= h($bi['item_name']) ?>: <strong><?= number_format((float)$bi['actual_quantity_kg'], 2) ?> <?= h($bi['unit']) ?></strong> 
                                        (<?= number_format((float)$bi['unit_cost'], 2) ?> ETB/<?= h($bi['unit']) ?> = <?= number_format((float)$bi['total_cost'], 2) ?> ETB)
                                    </span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (empty($batches)): ?>
                    <tr>
                        <td colspan="8" class="text-muted" style="text-align:center">
                            <?= current_locale() === 'am' ? 'ምንም የተከናወነ የማምረቻ ባች የለም። ከላይ ባለው ቅጽ የመጀመሪያውን ባች ያከናውኑ።' : 'No production batches run yet.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>