<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper']);

$preselectedItemId = (int) ($_GET['item_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $itemId = (int) $_POST['stock_item_id'];
        $qty = (float) $_POST['quantity'];
        $dest = trim($_POST['destination_type'] ?? 'internal');
        $note = trim($_POST['note'] ?? '');

        if ($qty <= 0) {
            throw new InvalidArgumentException('የወጪው መጠን ከ 0 በላይ መሆን አለበት (Quantity must be greater than zero).');
        }

        $avail = stock_current(1, $itemId);
        if ($qty > $avail) {
            throw new RuntimeException("ያለዎት ክምችት {$avail} ብቻ ስለሆነ {$qty} ወጪ ማድረግ አይቻልም (Cannot issue {$qty}; only {$avail} available).");
        }

        stock_issue(1, $itemId, $qty, $dest);
        flash("✓ {$qty} መጠን ወጪ ሆኖ ተመዝግቧል (Stock successfully issued).");
        header('Location: ' . BASE_URL . '/modules/warehouse/issue.php');
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

$recentIssues = db()->query(
    "SELECT s.*, i.name AS item_name, i.unit, u.name AS issuer_name
     FROM wh_stock_issues s
     JOIN wh_stock_items i ON i.id = s.stock_item_id
     JOIN users u ON u.id = s.issued_by
     ORDER BY s.issued_at DESC LIMIT 20"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">📤 የዕቃ ወጪ መመዝገቢያ (Direct Warehouse Stock Issue)</h2>
        <p class="text-muted" style="margin:0">ዋና ማዕከላዊ መጋዘን — ለክፍሎች ወይም ለስርጭት የሚወጡ ዕቃዎችን ወጪ መመዝገቢያ።</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary">&larr; ወደ ክምችት ዝርዝር (Stock Inventory)</a>
    </div>
</div>

<div class="card" style="max-width:620px;margin-bottom:24px">
    <h3>📤 ዕቃ ወጪ አድርግ (Issue Stock)</h3>
    <form method="post" id="issueForm"><?= csrf_field() ?>
        
        <div class="form-group">
            <label>የሚወጣው ዕቃ (Stock Item) *</label>
            <select name="stock_item_id" id="issueStockItem" required onchange="updateStockHelper()" style="width:100%">
                <option value="">— ዕቃ ይምረጡ (Select Item) —</option>
                <?php foreach ($items as $i): ?>
                    <option value="<?= (int)$i['id'] ?>" data-stock="<?= (float)$i['current_stock'] ?>" data-unit="<?= h($i['unit']) ?>" <?= $preselectedItemId === (int)$i['id'] ? 'selected' : '' ?>>
                        <?= h($i['name']) ?> (ያለው ክምችት: <?= number_format((float)$i['current_stock'], 2) ?> <?= h($i['unit']) ?> - <?= h($i['sku']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Live Stock Helper Banner -->
        <div id="stockHelperBanner" style="display:none;background:#e8f5e9;border:1px solid #c8e6c9;border-left:4px solid #2e7d32;border-radius:6px;padding:10px 14px;margin-bottom:16px">
            <div style="font-size:12px;color:#2e7d32;font-weight:600">በመጋዘን ውስጥ ያለው የቀረ ክምችት (Available Remaining Stock):</div>
            <div style="font-size:18px;font-weight:800;color:#1b5e20" id="stockHelperValue">0</div>
        </div>

        <div id="outOfStockAlert" style="display:none;background:#ffebee;border-left:4px solid #b3261e;color:#b3261e;border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600">
            ⚠️ ይህ ዕቃ በመጋዘን ውስጥ አልቋል (Out of Stock)! ወጪ ማድረግ አይቻልም።
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>የሚወጣው መጠን (Quantity to Issue) *</label>
                <input type="number" step="0.01" min="0.01" name="quantity" id="issueQty" required placeholder="ለምሳሌ፡ 20">
            </div>
            <div class="form-group">
                <label>መዳረሻ / ወጪ የተደረገበት ክፍል (Destination) *</label>
                <select name="destination_type" required>
                    <option value="dairy">🐄 የላም እርባታ ክፍል (Dairy Section)</option>
                    <option value="poultry">🐓 የዶሮ እርባታ ክፍል (Poultry Section)</option>
                    <option value="fish">🐟 የዓሣ እርባታ ክፍል (Fish Farm)</option>
                    <option value="distribution">🚚 ለሽያጭና ስርጭት (Distribution / Sales)</option>
                    <option value="veterinary">💉 የሕክምና ክፍል (Veterinary)</option>
                    <option value="general_use">🏢 አጠቃላይ አገልግሎት (General Farm Use)</option>
                    <option value="gift_protocol">🎁 የበዓል ስጦታና አስተዳደር (Holiday Gift &amp; Admin)</option>
                    <option value="food_consumption">🍲 ሰራተኞችና እንግዳ ምግብ (Staff &amp; Guest Food)</option>
                </select>
            </div>
        </div>

        <button class="btn" type="submit" id="submitIssueBtn">✓ ወጪ አድርግ (Confirm Issue)</button>
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary">ተመለስ (Cancel)</a>
    </form>
</div>

<!-- Recent Issues History Table -->
<div class="card">
    <h3 style="margin-top:0">📜 የቅርብ ጊዜ የወጪ ዕቃዎች ማህደር (Recent Stock Issues History)</h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>ቀንና ሰዓት (ኢትዮጵያ)</th>
                    <th>የዕቃው ስም</th>
                    <th>የወጣው መጠን</th>
                    <th>መዳረሻ (Destination)</th>
                    <th>ወጪ ያደረገው ሠራተኛ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentIssues as $s): ?>
                    <tr>
                        <td style="font-size:12px;white-space:nowrap">
                            📅 <?= eth_date($s['issued_at']) ?><br>
                            <small class="text-muted">⏰ <?= eth_time($s['issued_at']) ?></small>
                        </td>
                        <td><strong><?= h($s['item_name']) ?></strong></td>
                        <td><strong style="color:#b3261e">-<?= number_format($s['quantity'], 2) ?> <?= h($s['unit']) ?></strong></td>
                        <td><span class="badge active"><?= h(ucfirst(str_replace('_', ' ', $s['destination_type']))) ?></span></td>
                        <td><strong><?= h($s['issuer_name']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentIssues)): ?>
                    <tr><td colspan="5" class="text-muted">እስካሁን የተመዘገበ የወጪ ዕቃ የለም።</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateStockHelper() {
    const sel = document.getElementById('issueStockItem');
    const opt = sel.options[sel.selectedIndex];
    const banner = document.getElementById('stockHelperBanner');
    const valText = document.getElementById('stockHelperValue');
    const alertBox = document.getElementById('outOfStockAlert');
    const qtyInput = document.getElementById('issueQty');
    const submitBtn = document.getElementById('submitIssueBtn');

    if (opt && opt.value !== '') {
        const stock = parseFloat(opt.getAttribute('data-stock') || 0);
        const unit = opt.getAttribute('data-unit') || '';
        
        if (stock <= 0) {
            banner.style.display = 'none';
            alertBox.style.display = 'block';
            submitBtn.disabled = true;
            qtyInput.value = '';
            qtyInput.removeAttribute('max');
        } else {
            alertBox.style.display = 'none';
            banner.style.display = 'block';
            valText.innerText = stock.toFixed(2) + ' ' + unit;
            qtyInput.max = stock;
            submitBtn.disabled = false;
        }
    } else {
        banner.style.display = 'none';
        alertBox.style.display = 'none';
        submitBtn.disabled = false;
        qtyInput.removeAttribute('max');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateStockHelper();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
