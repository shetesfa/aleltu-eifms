<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper', 'finance_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['warehouse_purchase_request'])) {
            $stockItemId = (int)$_POST['stock_item_id'];
            $quantity    = (float)$_POST['quantity'];
            $urgency     = trim($_POST['urgency'] ?? 'medium');
            $reason      = trim($_POST['reason'] ?? '');

            if ($stockItemId <= 0 || $quantity <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ ትክክለኛ ዕቃና የሚፈለገውን መጠን ያስገቡ።' : 'Valid stock item and quantity required.');
            }

            // Create a purchase request directly linked to warehouse
            $stmt = db()->prepare(
                "INSERT INTO fin_purchase_requests (stock_item_id, quantity, requested_by, status, justification, created_at)
                 VALUES (?, ?, ?, 'pending', ?, NOW())"
            );
            $notesCombined = "[መጋዘን ቀጥታ ጥያቄ - አስቸኳይነት: {$urgency}] " . $reason;
            $stmt->execute([$stockItemId, $quantity, current_user()['id'], $notesCombined]);
            $reqId = db()->lastInsertId();

            // Notify finance and GM
            $itemName = db()->query("SELECT name FROM wh_stock_items WHERE id = {$stockItemId}")->fetchColumn();
            create_notification(
                current_locale() === 'am' ? "📦 አዲስ የመጋዘን ግዢ ጥያቄ፦ {$itemName} ({$quantity}) በፋይናንስ እንዲፈቀድ ተጠይቋል።" : "New warehouse purchase request: {$itemName} ({$quantity})",
                ['finance_officer', 'general_manager', 'super_admin'],
                BASE_URL . '/modules/warehouse/purchasing.php'
            );

            log_activity('warehouse', 'create', 'fin_purchase_requests', (int)$reqId, "Warehouse direct purchase request for item #{$stockItemId} qty {$quantity}");
            flash(current_locale() === 'am' ? '✓ የግዢ ጥያቄው በቀጥታ ወደ ፋይናንስና ስራ አስኪያጅ ተልኳል!' : 'Purchase request sent directly to Finance.');
        } elseif (isset($_POST['receive_po'])) {
            $poId = (int) $_POST['receive_po'];
            $po = db()->query("SELECT stock_item_id FROM fin_purchase_orders WHERE id = " . $poId)->fetch();
            if (!$po) {
                throw new RuntimeException('Purchase order not found.');
            }
            purchase_order_receive($poId, resolve_warehouse_for_item((int) $po['stock_item_id']));
            flash(current_locale() === 'am' ? '✓ ዕቃው በትክክል ተረክቦ ወደ መጋዘን ክምችት ገብቷል!' : 'Goods physically received and stock updated.');
        }
        header('Location: ' . BASE_URL . '/modules/warehouse/purchasing.php');
        exit;
    } catch (Throwable $e) {
        flash('ስህተት፦ ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

// Fetch stock items with their current quantity & reorder level
$stockItems = db()->query(
    "SELECT i.id, i.name, i.sku, i.unit, i.product_class,
            COALESCE(MAX(r.minimum_threshold), 10.0) AS reorder_level,
            COALESCE(SUM(b.quantity_on_hand), 0) AS current_stock
     FROM wh_stock_items i
     LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.quantity_on_hand > 0
     LEFT JOIN wh_minimum_stock_rules r ON r.stock_item_id = i.id AND r.warehouse_id = 1
     GROUP BY i.id
     ORDER BY (COALESCE(SUM(b.quantity_on_hand), 0) <= COALESCE(MAX(r.minimum_threshold), 10.0)) DESC, i.name ASC"
)->fetchAll();

// Fetch direct purchase requests
$requests = db()->query(
    "SELECT pr.*, i.name AS item_name, i.unit, u.name AS requester_name,
            COALESCE((SELECT SUM(b2.quantity_on_hand) FROM wh_stock_batches b2 WHERE b2.stock_item_id = i.id), 0) AS current_stock
     FROM fin_purchase_requests pr 
     LEFT JOIN wh_stock_items i ON i.id = pr.stock_item_id
     LEFT JOIN users u ON u.id = pr.requested_by
     ORDER BY pr.id DESC LIMIT 40"
)->fetchAll();

// Count low stock items
$lowStockCount = 0;
foreach ($stockItems as $si) {
    if ((float)$si['current_stock'] <= (float)$si['reorder_level']) {
        $lowStockCount++;
    }
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🛒 <?= current_locale() === 'am' ? 'የመጋዘን ዕቃዎች ክምችት እና የቀጥታ ግዢ ጥያቄ' : 'Warehouse Stock Status & Direct Purchasing' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' 
                ? 'የቀረውን የዕቃ ክምችት በቅድሚያ በመመልከት እጥረት ያለባቸውን ዕቃዎች በቀጥታ ወደ ፋይናንስ የግዢ ጥያቄ መላኪያ ማዕከል።' 
                : 'Inspect on-hand inventory levels and raise procurement requests directly to Finance.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/warehouse/dashboard.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ መጋዘን ማዕከል' : 'Warehouse Dashboard' ?></a>
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary">📦 <?= current_locale() === 'am' ? 'ሙሉ የክምችት ዝርዝር' : 'Full Stock' ?></a>
    </div>
</div>

<!-- Stock Health Summary -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi" style="border-left:4px solid var(--primary)">
        <div class="label">📦 <?= current_locale() === 'am' ? 'ጠቅላላ የተመዘገቡ ዕቃዎች' : 'Total Registered Items' ?></div>
        <div class="value" style="color:var(--primary)"><?= count($stockItems) ?></div>
    </div>
    <div class="kpi" style="border-left:4px solid <?= $lowStockCount > 0 ? '#b3261e' : '#2e7d32' ?>">
        <div class="label">⚠️ <?= current_locale() === 'am' ? 'ያለቁ ወይም እጥረት ያለባቸው ዕቃዎች' : 'Low Stock Alert' ?></div>
        <div class="value" style="color:<?= $lowStockCount > 0 ? '#b3261e' : '#2e7d32' ?>">
            <?= $lowStockCount ?> <?= current_locale() === 'am' ? 'ዕቃዎች' : 'items' ?>
        </div>
    </div>
    <div class="kpi" style="border-left:4px solid #1565c0">
        <div class="label">⏳ <?= current_locale() === 'am' ? 'በሂደት ላይ ያሉ የግዢ ጥያቄዎች' : 'Active Purchase Requests' ?></div>
        <div class="value" style="color:#1565c0">
            <?= count(array_filter($requests, fn($r) => in_array($r['status'], ['pending', 'approved']))) ?>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(360px, 1fr));gap:20px;margin-bottom:24px;align-items:start">
    
    <!-- DIRECT PURCHASE REQUEST FORM -->
    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.06);border-top:3px solid var(--primary)">
        <h3 style="margin-top:0">➕ <?= current_locale() === 'am' ? 'አዲስ የግዢ ጥያቄ በቀጥታ ወደ ፋይናንስ ላክ' : 'Send Purchase Request to Finance' ?></h3>
        <p class="text-muted" style="font-size:13px;margin-bottom:14px">
            <?= current_locale() === 'am' ? 'የቀረውን ክምችት በማየት እጥረት ሲያጋጥም ሌሎች ክፍሎችን መጠየቅ ሳይጠበቅብዎት በቀጥታ ለፋይናንስ የግዢ ጥያቄ ማቅረብ ይችላሉ።' : 'Raise immediate procurement requests directly to Finance based on remaining stock.' ?>
        </p>

        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="warehouse_purchase_request" value="1">
            
            <div class="form-group" style="margin-bottom:12px">
                <label style="font-weight:700">📦 <?= current_locale() === 'am' ? 'የሚገዛው ዕቃ (የቀረ ክምችት ይታያል) *' : 'Stock Item (Remaining Shown) *' ?></label>
                <select name="stock_item_id" id="stockItemSelect" required style="width:100%;padding:8px;font-size:13px" onchange="updateSelectedStockInfo()">
                    <option value="">— <?= current_locale() === 'am' ? 'ዕቃ ይምረጡ' : 'Select Item' ?> —</option>
                    <?php foreach ($stockItems as $item): 
                        $isLow = (float)$item['current_stock'] <= (float)$item['reorder_level'];
                    ?>
                        <option value="<?= (int)$item['id'] ?>" data-stock="<?= (float)$item['current_stock'] ?>" data-unit="<?= h($item['unit']) ?>" data-reorder="<?= (float)$item['reorder_level'] ?>">
                            <?= $isLow ? '⚠️ ' : '✓ ' ?><?= h($item['name']) ?> (ቀሪ፡ <?= number_format((float)$item['current_stock'], 1) ?> <?= h($item['unit']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Dynamic stock info card -->
            <div id="stockInfoCard" style="display:none;background:var(--hover);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px">
                <div style="display:flex;justify-content:space-between">
                    <span><?= current_locale() === 'am' ? 'የአሁኑ ቀሪ ክምችት፦' : 'Current On Hand:' ?> <strong id="infoRemaining" style="color:var(--primary)">0</strong></span>
                    <span><?= current_locale() === 'am' ? 'የማስጠንቀቂያ መጠን፦' : 'Reorder Level:' ?> <strong id="infoReorder">0</strong></span>
                </div>
            </div>

            <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px">
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700"><?= current_locale() === 'am' ? 'የሚፈለገው ብዛት / መጠን *' : 'Quantity Needed *' ?></label>
                    <input type="number" step="0.01" min="0.01" name="quantity" required placeholder="0.00" style="padding:8px;width:100%">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700"><?= current_locale() === 'am' ? 'የአስቸኳይነት ደረጃ' : 'Urgency Level' ?></label>
                    <select name="urgency" style="padding:8px;width:100%">
                        <option value="medium"><?= current_locale() === 'am' ? 'መደበኛ (Normal)' : 'Normal' ?></option>
                        <option value="high"><?= current_locale() === 'am' ? 'ከፍተኛ (High)' : 'High' ?></option>
                        <option value="critical"><?= current_locale() === 'am' ? '🚨 እጅግ አስቸኳይ (Critical)' : 'Critical' ?></option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px">
                <label style="font-weight:700"><?= current_locale() === 'am' ? 'የግዢው ምክንያት / ማብራሪያ' : 'Reason / Justification' ?></label>
                <textarea name="reason" rows="2" style="width:100%;padding:8px;box-sizing:border-box" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ክምችቱ ከአስፈላጊው በታች በመሆኑ ለቀጣይ ሳምንት መኖ ፍጆታ የሚያስፈልግ...' : 'e.g. Stock below threshold for next week feeding...' ?>"></textarea>
            </div>

            <button class="btn" type="submit" style="width:100%;padding:10px;font-size:14px;background:#1e5631;border-color:#1e5631">
                🚀 <?= current_locale() === 'am' ? 'የግዢ ጥያቄውን ወደ ፋይናንስ ላክ (Send to Finance)' : 'Send Request to Finance' ?>
            </button>
        </form>
    </div>

    <!-- QUICK STOCK STATUS MONITOR -->
    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.06)">
        <h3 style="margin-top:0">📊 <?= current_locale() === 'am' ? 'የዕቃዎች ክምችት ቁጥጥርና ቀሪ ሁኔታ' : 'Stock Status & Reorder Thresholds' ?></h3>
        <div style="max-height:360px;overflow-y:auto">
            <table>
                <thead>
                    <tr>
                        <th><?= current_locale() === 'am' ? 'የዕቃ ስም' : 'Item' ?></th>
                        <th style="text-align:right"><?= current_locale() === 'am' ? 'ቀሪ ክምችት' : 'Remaining' ?></th>
                        <th style="text-align:right"><?= current_locale() === 'am' ? 'ዝቅተኛ ወሰን' : 'Reorder' ?></th>
                        <th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stockItems as $si): 
                        $cur = (float)$si['current_stock'];
                        $reorder = (float)$si['reorder_level'];
                        $isOut = $cur <= 0;
                        $isLow = !$isOut && ($cur <= $reorder);
                    ?>
                        <tr style="<?= $isOut ? 'background:#ffebee' : ($isLow ? 'background:#fff8e1' : '') ?>">
                            <td><strong><?= h($si['name']) ?></strong> <small class="text-muted">(<?= h($si['sku']) ?>)</small></td>
                            <td style="text-align:right;font-weight:700;color:<?= $isOut ? '#b3261e' : ($isLow ? '#e65100' : 'var(--primary)') ?>">
                                <?= number_format($cur, 1) ?> <?= h($si['unit']) ?>
                            </td>
                            <td style="text-align:right"><?= number_format($reorder, 1) ?> <?= h($si['unit']) ?></td>
                            <td>
                                <?php if ($isOut): ?>
                                    <span class="badge sold">🚨 ያለቀ</span>
                                <?php elseif ($isLow): ?>
                                    <span class="badge dry">⚠️ ዝቅተኛ</span>
                                <?php else: ?>
                                    <span class="badge milking">✓ በቂ</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TRACK RECENT PURCHASE REQUESTS -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">📋 <?= current_locale() === 'am' ? 'የቀረቡ የግዢ ጥያቄዎች እና የሂደት ሁኔታ' : 'Recent Purchase Requests & Status' ?></h3>
        <span class="text-muted" style="font-size:12px"><?= count($requests) ?> ጥያቄዎች</span>
    </div>

    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'መለያ' : 'ID' ?></th>
                    <th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th>
                    <th><?= current_locale() === 'am' ? 'የዕቃ ስም' : 'Stock Item' ?></th>
                    <th style="text-align:right"><?= current_locale() === 'am' ? 'የተጠየቀ መጠን' : 'Requested' ?></th>
                    <th style="text-align:right"><?= current_locale() === 'am' ? 'ወቅታዊ ቀሪ' : 'On Hand' ?></th>
                    <th><?= current_locale() === 'am' ? 'ጠያቂ' : 'By' ?></th>
                    <th><?= current_locale() === 'am' ? 'ማብራሪያ' : 'Notes' ?></th>
                    <th><?= current_locale() === 'am' ? 'የፋይናንስ ሁኔታ' : 'Status' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): 
                    $stBadge = match($r['status']) {
                        'approved'        => 'milking',
                        'converted_to_po' => 'active',
                        'rejected'        => 'sold',
                        default           => 'dry'
                    };
                    $stText = match($r['status']) {
                        'approved'        => (current_locale() === 'am' ? '✓ በፋይናንስ ጸድቋል' : 'Approved'),
                        'converted_to_po' => (current_locale() === 'am' ? '🛒 ታዟል / ተገዝቷል' : 'Ordered'),
                        'rejected'        => (current_locale() === 'am' ? '✕ ውድቅ ተደርጓል' : 'Rejected'),
                        default           => (current_locale() === 'am' ? '⏳ ፋይናንስን በመጠባበቅ ላይ' : 'Pending')
                    };
                ?>
                    <tr>
                        <td>#<?= (int)$r['id'] ?></td>
                        <td style="white-space:nowrap"><?= h(eth_date($r['created_at'])) ?></td>
                        <td><strong><?= h($r['item_name']) ?></strong></td>
                        <td style="text-align:right;font-weight:700;color:var(--primary)"><?= number_format((float)($r['quantity'] ?? 0), 1) ?> <?= h($r['unit']) ?></td>
                        <td style="text-align:right"><?= number_format((float)$r['current_stock'], 1) ?> <?= h($r['unit']) ?></td>
                        <td style="font-size:12px"><?= h($r['requester_name'] ?: 'መጋዘን') ?></td>
                        <td style="font-size:12px;color:var(--text-muted)"><?= h($r['justification'] ?? '—') ?></td>
                        <td><span class="badge <?= $stBadge ?>"><?= $stText ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="8" class="text-muted" style="text-align:center;padding:20px"><?= current_locale() === 'am' ? 'ምንም አይነት የግዢ ጥያቄ አልተመዘገበም።' : 'No purchase requests.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateSelectedStockInfo() {
    const sel = document.getElementById('stockItemSelect');
    const card = document.getElementById('stockInfoCard');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        card.style.display = 'block';
        document.getElementById('infoRemaining').textContent = opt.getAttribute('data-stock') + ' ' + opt.getAttribute('data-unit');
        document.getElementById('infoReorder').textContent = opt.getAttribute('data-reorder') + ' ' + opt.getAttribute('data-unit');
    } else {
        card.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
