<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'feed_production_manager', 'field_worker', 'dairy_manager', 'poultry_manager']);

// -----------------------------------------------------------------------
// BUSINESS RULE BR-FP02:
// Feed/crop produced internally CANNOT be dispatched directly to farm units.
// All produced material must go to STORE / WAREHOUSE first.
// Only after Store confirms receipt can it be issued to departments.
// -----------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['send_to_store'])) {
            // Create a pending Store receiving record (not direct stock increase)
            $cropId      = (int)  $_POST['crop_id'];
            $sentQty     = (float)$_POST['sent_quantity_kg'];
            $notes       = trim($_POST['notes'] ?? '');

            if ($sentQty <= 0) {
                throw new RuntimeException('Sent quantity must be greater than zero.');
            }

            $harvest = db()->query("SELECT sent_to_store FROM feed_harvest_records WHERE id = {$cropId}")->fetch();
            if (!$harvest) {
                throw new RuntimeException('Harvest record not found.');
            }
            if ((int) $harvest['sent_to_store'] === 1) {
                throw new RuntimeException('This harvest has already been sent to Store.');
            }

            // Get harvest info for reference
            $crop = db()->query(
                "SELECT h.id, h.yield_kg, r.crop_type AS crop_name, p.name AS plot_name
                 FROM feed_harvest_records h
                 JOIN feed_planting_records r ON r.id = h.planting_record_id
                 JOIN feed_plots p ON p.id = r.plot_id
                 WHERE h.id = {$cropId}"
            )->fetch();
            if (!$crop) {
                throw new RuntimeException('Harvest record not found.');
            }
            $cropName = $crop['crop_name'];
            $plotName = $crop['plot_name'];

            // §27: the stock item is determined automatically from the
            // crop's own type (e.g. "Maize") instead of asking the user to
            // pick one from a dropdown of internal catalog concepts.
            $stockItemId = feed_resolve_stock_item_for_crop($cropName);

            // Warehouse is resolved automatically — single-store setup,
            // no manual warehouse picker needed.
            $warehouseId = resolve_warehouse_for_item($stockItemId);

            $sourceRef = "HARVEST-{$cropId} ({$cropName} from {$plotName})";

            $dispatchCode = store_send_to_warehouse(
                'feed_production', $sourceRef, $warehouseId, $stockItemId, $sentQty, current_user()['id']
            );
            db()->prepare("UPDATE feed_harvest_records SET sent_to_store = 1 WHERE id = ?")->execute([$cropId]);

            log_activity('feed', 'send_to_store', 'feed_harvest_records', $cropId, "Sent {$sentQty}kg {$cropName} to Warehouse #{$warehouseId}");

            flash(current_locale() === 'am' ? "✓ የመኖ ምርት ወደ መጋዘን ተልኳል፤ የመላኪያ ኮድ፡ {$dispatchCode}። የመጋዘን ኃላፊው ርክክቡን ያረጋግጣል።" : "Feed sent to Store. Dispatch code: {$dispatchCode}. Storekeeper will confirm actual receipt.");
        }
        header('Location: /aleltu-eifms/modules/feed_production/distribution.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

// Harvested crops ready to send to Store (not sent yet — prevents double counting)
$readyCrops = db()->query(
    "SELECT h.id, h.yield_kg AS actual_qty_kg, r.crop_type AS crop_name, p.name AS plot_name
     FROM feed_harvest_records h
     JOIN feed_planting_records r ON r.id = h.planting_record_id
     JOIN feed_plots p ON p.id = r.plot_id
     WHERE h.sent_to_store = 0
     ORDER BY h.id DESC LIMIT 30"
)->fetchAll();

// Already sent (recent history)
$sentHistory = db()->query(
    "SELECT r.*, i.name AS item_name, w.name AS warehouse_name
     FROM wh_store_receiving r
     JOIN wh_stock_items i ON i.id = r.stock_item_id
     JOIN wh_warehouses w  ON w.id = r.warehouse_id
     WHERE r.source_type = 'feed_production'
     ORDER BY r.sent_at DESC LIMIT 20"
)->fetchAll();
?>

<h2 class="mt-0">🌾 <?= current_locale() === 'am' ? 'የመኖ ምርት ርክክብ ወደ መጋዘን' : 'Feed Production → Store Transfer' ?></h2>

<div class="card" style="background:#e8f5e9;border-left:4px solid var(--green-primary,#2e7d32);color:#111;margin-bottom:20px;padding:16px">
    <strong style="color:#2e7d32">⚠️ <?= current_locale() === 'am' ? 'የአሰራር ደንብ BR-FP02:' : 'Business Rule BR-FP02:' ?></strong>
    <?= current_locale() === 'am' ? 'የተሰበሰበ የመኖ ሰብል (FP02) በቀጥታ ለድርጅቱ ክፍሎች አይከፋፈልም — አስቀድሞ ወደ <strong>ዋና መጋዘን</strong> ገብቶ መረጋገጥ አለበት።' : 'Harvested feed and crops (FP02) cannot be distributed directly to farm units — must first go to Store / Warehouse for confirmation.' ?>
</div>

<?php if (!empty($readyCrops)): ?>
<div class="card" style="max-width:600px">
    <h3><?= current_locale() === 'am' ? 'የተሰበሰበ መኖ ወደ መጋዘን ላክ' : 'Send Harvested Crop to Store' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="send_to_store" value="1">
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የተሰበሰበ ሰብል / ባች *' : 'Harvested Crop / Batch *' ?></label>
            <select name="crop_id" id="cropSelect" onchange="autoFillHarvestQty()" required>
                <option value="">— <?= current_locale() === 'am' ? 'ሰብል ይምረጡ' : 'Select Crop' ?> —</option>
                <?php foreach ($readyCrops as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" data-qty="<?= (float)($c['actual_qty_kg'] ?? 0) ?>">
                        <?= h("{$c['crop_name']} — {$c['plot_name']}") ?>
                        (<?= number_format((float)($c['actual_qty_kg'] ?? 0), 1) ?> kg)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የተላከ መጠን (ኪ.ግ) *' : 'Quantity Sent (kg) *' ?></label>
            <input type="number" step="0.01" name="sent_quantity_kg" id="sentQtyInput" required min="0.01">
        </div>
        <div class="form-group"><label><?= h(t('common.notes', 'Notes')) ?></label>
            <input type="text" name="notes" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የጥራት ደረጃ እና የእርጥበት መጠን' : 'e.g. Batch quality notes, moisture level' ?>">
        </div>
        <button class="btn" type="submit">📦 <?= current_locale() === 'am' ? 'ወደ መጋዘን ላክ' : 'Send to Store for Confirmation' ?></button>
    </form>
</div>

<script>
function autoFillHarvestQty() {
    const sel = document.getElementById('cropSelect');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.qty) {
        document.getElementById('sentQtyInput').value = opt.dataset.qty;
    }
}
</script>
<?php else: ?>
<div class="card">
    <p class="text-muted"><?= current_locale() === 'am' ? 'በአሁኑ ወቅት ወደ መጋዘን የሚላክ የተሰበሰበ ሰብል የለም።' : 'No harvested crops are currently recorded in the database.' ?>
        <a href="/aleltu-eifms/modules/feed_production/plots.php"><?= current_locale() === 'am' ? 'ወደ ማሳዎች ዝርዝር ሂድ →' : 'Go to Plots →' ?></a>
    </p>
</div>
<?php endif; ?>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'የቅርብ ጊዜ የመኖ ርክክብ ታሪክ' : 'Recent Feed → Store Shipments' ?></h3>
    <p class="text-muted" style="font-size:13px"><?= current_locale() === 'am' ? 'የመጋዘን ኃላፊው ርክክቡን በ' : 'Storekeeper must confirm receipt in the' ?>
        <a href="/aleltu-eifms/modules/warehouse/receiving.php"><?= current_locale() === 'am' ? 'የመጋዘን ርክክብ ገጽ' : 'Warehouse Receiving' ?></a> <?= current_locale() === 'am' ? 'ላይ ማረጋገጥ አለበት።' : 'page.' ?>
    </p>
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'የመላኪያ ኮድ' : 'Dispatch Code' ?></th>
                <th><?= current_locale() === 'am' ? 'ምንጭ ማሳ' : 'Source' ?></th>
                <th><?= current_locale() === 'am' ? 'ዕቃ' : 'Item' ?></th>
                <th><?= current_locale() === 'am' ? 'የተላከ (ኪ.ግ)' : 'Sent (kg)' ?></th>
                <th><?= current_locale() === 'am' ? 'መጋዘን' : 'Warehouse' ?></th>
                <th><?= h(t('common.status', 'Status')) ?></th>
                <th><?= current_locale() === 'am' ? 'የደረሰ (ኪ.ግ)' : 'Received (kg)' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sentHistory as $r): ?>
                <tr>
                    <td><code><?= h($r['dispatch_code']) ?></code></td>
                    <td style="font-size:12px"><?= h($r['source_reference']) ?></td>
                    <td><strong><?= h($r['item_name']) ?></strong></td>
                    <td><?= number_format($r['sent_quantity'], 2) ?></td>
                    <td><?= h($r['warehouse_name']) ?></td>
                    <td>
                        <?php
                        $badgeClass = match($r['status']) {
                            'pending_receipt'    => 'sold',
                            'received_full'      => 'milking',
                            'received_variance'  => 'dry',
                            default              => ''
                        };
                        $stText = match($r['status']) {
                            'pending_receipt' => (current_locale() === 'am' ? 'ርክክብ ይጠብቃል' : 'Pending Receipt'),
                            'received_full' => (current_locale() === 'am' ? 'ሙሉ በሙሉ ደርሷል' : 'Received Full'),
                            'received_variance' => (current_locale() === 'am' ? 'ልዩነት አለው' : 'Variance'),
                            default => $r['status']
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= h($stText) ?></span>
                    </td>
                    <td>
                        <?php if ($r['received_quantity'] !== null): ?>
                            <?= number_format($r['received_quantity'], 2) ?>
                            <?php if (abs($r['received_quantity'] - $r['sent_quantity']) > 0.01): ?>
                                <span class="badge sold" style="font-size:10px"><?= current_locale() === 'am' ? 'ልዩነት: ' : 'Variance: ' ?><?= number_format($r['sent_quantity'] - $r['received_quantity'], 2) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted"><?= current_locale() === 'am' ? 'በመጠባበቅ ላይ' : 'Pending' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sentHistory)): ?>
                <tr><td colspan="7" class="text-muted"><?= h(t('common.no_records', 'No shipments recorded yet.')) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<a href="/aleltu-eifms/modules/feed_production/plots.php">&larr; <?= current_locale() === 'am' ? 'ወደ ማሳዎች ዝርዝር ተመለስ' : 'Back to Plots' ?></a>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
