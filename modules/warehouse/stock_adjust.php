<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper']);

$preselectedItemId = (int) ($_GET['item_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $warehouseId = 1; // 1 Unified Main Central Warehouse
        $itemId = (int) $_POST['stock_item_id'];
        $type = $_POST['adjustment_type'] ?? 'increase';
        $quantity = (float) $_POST['quantity'];
        $reason = trim($_POST['reason'] ?? '');

        if ($quantity <= 0) {
            throw new InvalidArgumentException('የማስተካከያው መጠን ከ 0 በላይ መሆን አለበት (Adjustment quantity must be greater than zero).');
        }
        if (empty($reason)) {
            throw new InvalidArgumentException('የማስተካከያ ምክንያት ማስገባት ግዴታ ነው (Justification reason is required).');
        }

        stock_adjust($warehouseId, $itemId, $type, $quantity, $reason, current_user()['id']);
        flash('✓ የክምችት ማስተካከያው ተመዝግቦ የዕቃው መጠን ተስተካክሏል (Stock adjustment successfully applied).');
        header('Location: /aleltu-eifms/modules/warehouse/stock_adjust.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$items = db()->query(
    "SELECT i.id, i.name, i.sku, i.unit, COALESCE(SUM(b.quantity_on_hand), 0) AS current_stock
     FROM wh_stock_items i
     LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.warehouse_id = 1 AND b.quantity_on_hand > 0
     GROUP BY i.id ORDER BY i.name"
)->fetchAll();

$adjustments = db()->query(
    "SELECT a.*, i.name AS item_name, i.unit, u.name AS adjuster_name
     FROM wh_stock_adjustments a
     JOIN wh_stock_items i ON i.id = a.stock_item_id
     JOIN users u ON u.id = a.adjusted_by
     ORDER BY a.created_at DESC LIMIT 30"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">⚖️ የዕቃ ክምችት ማስተካከያ (Controlled Stock Adjustment)</h2>
        <p class="text-muted" style="margin:0">በመጋዘን ቆጠራ ወቅት የተገኘን ትርፍ ወይም የተበላሸ/የጎደለን ዕቃ ከበቂ ማብራሪያ ጋር ማስተካከያ መመዝገቢያ።</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="/aleltu-eifms/modules/warehouse/stock.php" class="btn secondary">&larr; ወደ ክምችት ዝርዝር (Stock Inventory)</a>
    </div>
</div>

<div class="card" style="background:#f1f8e9;border-left:4px solid #2e7d32;color:#1b5e20;padding:12px 16px;margin-bottom:20px">
    <strong>💡 ይህ ገጽ ለምን ይጠቅማል? (What is this page for?)</strong>
    <p style="margin:4px 0 0 0;font-size:13px;color:#2e7d32">
        በመጋዘን ውስጥ በቆጠራ ወቅት ያልተመዘገበ ተጨማሪ ዕቃ ሲገኝ <strong>➕ ክምችት ለመጨመር</strong>፣ ወይም ዕቃ ሲበላሽ፣ ሲፈስ፣ ሲሰበር ወይም ሲጎድል <strong>➖ ክምችት ለመቀነስ</strong> ይጠቅማል። እያንዳንዱ ለውጥ ከበቂ ምክንያት ጋር በማህደር ይቀመጣል።
    </p>
</div>

<!-- Adjustment Form -->
<div class="card" style="max-width:650px;margin-bottom:24px">
    <h3>➕ አዲስ የክምችት ማስተካከያ መዝግብ (Record Stock Adjustment)</h3>
    <form method="post"><?= csrf_field() ?>
        
        <div class="form-group">
            <label>የሚስተካከለው ዕቃ (Stock Item) *</label>
            <select name="stock_item_id" id="adjStockItem" required onchange="updateLiveStockDisplay()" style="width:100%">
                <option value="">— ዕቃ ይምረጡ (Select Item) —</option>
                <?php foreach ($items as $i): ?>
                    <option value="<?= (int)$i['id'] ?>" data-stock="<?= (float)$i['current_stock'] ?>" data-unit="<?= h($i['unit']) ?>" <?= $preselectedItemId === (int)$i['id'] ? 'selected' : '' ?>>
                        <?= h($i['name']) ?> (ክምችት: <?= number_format((float)$i['current_stock'], 2) ?> <?= h($i['unit']) ?> - <?= h($i['sku']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Live Stock Display Box -->
        <div id="liveStockBox" style="display:none;background:var(--bg);border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:14px">
            በአሁኑ ሰዓት በመጋዘን ያለው መጠን (Current Stock): <strong id="liveStockVal" style="color:#1b5e20;font-size:16px">0</strong>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>የማስተካከያ ዓይነት (Direction) *</label>
                <select name="adjustment_type" id="adjType" required onchange="validateMaxDecrease()">
                    <option value="increase">➕ ክምችት ጨምር (Increase) — በቆጠራ የተገኘ ትርፍ ዕቃ</option>
                    <option value="decrease">➖ ክምችት ቀንስ (Decrease) — የተበላሸ / የጎደለ / የተጣለ ዕቃ</option>
                </select>
            </div>
            <div class="form-group">
                <label>የሚስተካከለው መጠን (Quantity) *</label>
                <input type="number" step="0.01" min="0.01" name="quantity" id="adjQty" required placeholder="ለምሳሌ፡ 10">
            </div>
        </div>

        <div class="form-group">
            <label>የማስተካከያው ምክንያት (Mandatory Reason / Justification) *</label>
            <input type="text" name="reason" required placeholder="ለምሳሌ፡ የወርሃዊ ቆጠራ ልዩነት በኃላፊ የተረጋገጠ፣ 2 ጆንያ የተበላሸ መኖ">
        </div>

        <button class="btn" type="submit">✓ ማስተካከያውን ፈጽም (Apply Adjustment)</button>
    </form>
</div>

<!-- History Log -->
<div class="card">
    <h3 style="margin-top:0">📜 የተደረጉ ማስተካከያዎች ማህደር (Adjustment Audit Log)</h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>ቀንና ሰዓት (ኢትዮጵያ)</th>
                    <th>የዕቃው ስም</th>
                    <th>የተደረገው ማስተካከያ</th>
                    <th>ምክንያት (Reason)</th>
                    <th>ያስተካከለው ሠራተኛ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adjustments as $a): ?>
                    <tr>
                        <td style="font-size:12px;white-space:nowrap">
                            📅 <?= eth_date($a['created_at']) ?><br>
                            <small class="text-muted">⏰ <?= eth_time($a['created_at']) ?></small>
                        </td>
                        <td><strong><?= h($a['item_name']) ?></strong></td>
                        <td>
                            <?php if ($a['adjustment_type'] === 'increase'): ?>
                                <span class="badge milking">+<?= number_format($a['quantity'], 2) ?> <?= h($a['unit']) ?></span>
                            <?php else: ?>
                                <span class="badge sold">-<?= number_format($a['quantity'], 2) ?> <?= h($a['unit']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($a['reason']) ?></td>
                        <td><strong><?= h($a['adjuster_name']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($adjustments)): ?>
                    <tr><td colspan="5" class="text-muted">እስካሁን የተመዘገበ ማስተካከያ የለም።</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateLiveStockDisplay() {
    const sel = document.getElementById('adjStockItem');
    const opt = sel.options[sel.selectedIndex];
    const box = document.getElementById('liveStockBox');
    const val = document.getElementById('liveStockVal');
    
    if (opt && opt.value !== '') {
        const stock = parseFloat(opt.getAttribute('data-stock') || 0);
        const unit = opt.getAttribute('data-unit') || '';
        val.innerText = stock.toFixed(2) + ' ' + unit;
        box.style.display = 'block';
        validateMaxDecrease();
    } else {
        box.style.display = 'none';
    }
}

function validateMaxDecrease() {
    const sel = document.getElementById('adjStockItem');
    const opt = sel.options[sel.selectedIndex];
    const adjType = document.getElementById('adjType').value;
    const qtyInput = document.getElementById('adjQty');

    if (opt && opt.value !== '' && adjType === 'decrease') {
        const stock = parseFloat(opt.getAttribute('data-stock') || 0);
        qtyInput.max = stock;
    } else {
        qtyInput.removeAttribute('max');
    }
}

// Initialize on page load if preselected
document.addEventListener('DOMContentLoaded', function() {
    updateLiveStockDisplay();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
