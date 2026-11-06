<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper']);

$currentUser = current_user();
$preselectedItemId = (int) ($_GET['item_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $issueMethod = $_POST['issue_method'] ?? 'direct_dept';

        if ($issueMethod === 'direct_dept') {
            // Method 1: Direct Warehouse Issue to Department without prior request
            $itemId = (int) $_POST['stock_item_id'];
            $qty = (float) $_POST['quantity'];
            $deptId = (int) $_POST['department_id'];
            $note = trim($_POST['note'] ?? '');

            if ($qty <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የወጪው መጠን ከ 0 በላይ መሆን አለበት።' : 'Quantity must be greater than zero.');
            }
            if ($deptId <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ ተቀባይ የስራ ክፍል ይምረጡ።' : 'Target department is required.');
            }

            warehouse_direct_issue_to_dept(1, $itemId, $qty, $deptId, $note, $currentUser['id']);
            flash(current_locale() === 'am' ? "✓ {$qty} መጠን በቀጥታ ወደ ክፍሉ ተልኳል! የክፍሉ ኃላፊ ሲመጣ በሲስተሙ አጽድቆ ይረከባል።" : "Stock issued directly to department. Pending department manager confirmation.");
        } elseif ($issueMethod === 'fulfill_req') {
            // Method 2: Fulfill existing department requisition request
            $reqId = (int) $_POST['request_id'];
            dept_material_request_fulfill($reqId, 1, $currentUser['id']);
            flash(current_locale() === 'am' ? '✓ የክፍሉ የዕቃ ጥያቄ ተሞልቶ እቃው ወጪ ሆኗል!' : 'Material request fulfilled and issued.');
        } else {
            // General internal issue
            $itemId = (int) $_POST['stock_item_id'];
            $qty = (float) $_POST['quantity'];
            $dest = trim($_POST['destination_type'] ?? 'general_overhead');
            $note = trim($_POST['note'] ?? '');

            if ($qty <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የወጪው መጠን ከ 0 በላይ መሆን አለበት።' : 'Quantity must be greater than zero.');
            }
            $avail = stock_current(1, $itemId);
            if ($qty > $avail) {
                throw new RuntimeException(current_locale() === 'am' ? "ያለዎት ክምችት {$avail} ብቻ ስለሆነ {$qty} ወጪ ማድረግ አይቻልም።" : "Cannot issue {$qty}; only {$avail} available.");
            }

            stock_issue(1, $itemId, $qty, $dest);
            flash(current_locale() === 'am' ? "✓ {$qty} መጠን ወጪ ሆኖ ተመዝግቧል።" : 'Stock successfully issued.');
        }
        header('Location: ' . BASE_URL . '/modules/warehouse/issue.php');
        exit;
    } catch (Throwable $e) {
        flash(current_locale() === 'am' ? 'ስህተት፦ ' . $e->getMessage() : 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$items = db()->query(
    "SELECT i.id, i.name, i.sku, i.unit, COALESCE(SUM(b.quantity_on_hand), 0) AS current_stock
     FROM wh_stock_items i
     LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.warehouse_id = 1 AND b.quantity_on_hand > 0
     GROUP BY i.id ORDER BY i.name"
)->fetchAll();

// Departments for direct issue
$departments = db()->query(
    "SELECT id, name, code, business_unit FROM departments WHERE code IN ('DAIRY', 'POULTRY', 'FISH', 'FEED', 'VET') ORDER BY id"
)->fetchAll();

// Pending Requisitions waiting for store fulfillment
$pendingReqs = db()->query(
    "SELECT r.*, i.name AS item_name, i.sku, i.unit, d.name AS dept_name, u.name AS requester_name
     FROM dept_material_requests r
     JOIN wh_stock_items i ON i.id = r.stock_item_id
     JOIN departments d ON d.id = r.department_id
     JOIN users u ON u.id = r.requested_by
     WHERE r.status = 'pending'
     ORDER BY r.created_at ASC"
)->fetchAll();

// Dispatches / issues tracking: both direct and requisition-based
$recentDispatches = db()->query(
    "SELECT r.*, i.name AS item_name, i.sku, i.unit, d.name AS dept_name,
            u.name AS issuer_name, ru.name AS issuer_role,
            rec.name AS receiver_name, rrec.name AS receiver_role
     FROM dept_material_requests r
     JOIN wh_stock_items i ON i.id = r.stock_item_id
     JOIN departments d ON d.id = r.department_id
     LEFT JOIN users u ON u.id = r.fulfilled_by
     LEFT JOIN roles ru ON ru.id = u.role_id
     LEFT JOIN users rec ON rec.id = r.received_confirmed_by
     LEFT JOIN roles rrec ON rrec.id = rec.role_id
     ORDER BY r.created_at DESC LIMIT 30"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">📤 <?= current_locale() === 'am' ? 'የዕቃ ወጪ እና ለክፍሎች ርክክብ ማዕከል' : 'Warehouse Stock Issue & Department Dispatches' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'ለስራ ክፍሎች (Dairy, Poultry ወዘተ) እቃዎችን በቀጥታ መላኪያ ወይም በጥያቄ መሰረት ወጪ ማድረጊያ።' : 'Issue materials to farm departments directly or fulfill pending requisitions.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ክምችት ዝርዝር' : 'Back to Inventory' ?></a>
    </div>
</div>

<!-- Pending Department Requests (Method 2) -->
<?php if (!empty($pendingReqs)): ?>
    <div class="card" style="border-left:5px solid #0288d1;background:#e1f5fe;margin-bottom:24px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="font-size:24px">📝</span>
            <div>
                <h3 style="margin:0;color:#01579b">
                    <?= current_locale() === 'am' ? 'ከስራ ክፍሎች የቀረቡ የዕቃ ፍላጎት ጥያቄዎች (' . count($pendingReqs) . ')' : 'Pending Requisitions from Departments (' . count($pendingReqs) . ')' ?>
                </h3>
                <small class="text-muted"><?= current_locale() === 'am' ? 'ክፍሎች አስቀድመው በሲስተሙ የጠየቋቸው እቃዎች ናቸው፤ እቃውን አውጥተው ለመላክ "እቃውን ላክ" ይጫኑ።' : 'Requisitions submitted by department managers.' ?></small>
            </div>
        </div>

        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr style="background:rgba(0,0,0,0.03)">
                        <th>የቀረበበት ቀን</th>
                        <th>ጠያቂ ክፍል</th>
                        <th>የዕቃው ስም</th>
                        <th>የተጠየቀው መጠን</th>
                        <th>ጠያቂ ሠራተኛ</th>
                        <th>እርምጃ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingReqs as $pr): ?>
                        <tr>
                            <td style="font-size:12px;white-space:nowrap">
                                📅 <?= eth_date($pr['created_at']) ?> <small class="text-muted">⏰ <?= eth_time($pr['created_at']) ?></small>
                            </td>
                            <td><strong><?= h($pr['dept_name']) ?></strong></td>
                            <td><?= h($pr['item_name']) ?> <span class="text-muted" style="font-size:11px">(<?= h($pr['sku']) ?>)</span></td>
                            <td><strong style="color:#01579b;font-size:15px"><?= number_format($pr['requested_quantity'], 2) ?> <?= h($pr['unit']) ?></strong></td>
                            <td>👤 <?= h($pr['requester_name']) ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('<?= current_locale() === 'am' ? 'ይህንን እቃ ከመጋዘን ወጪ አድርገው ለክፍሉ መላክ ይፈልጋሉ?' : 'Issue and send this item to department?' ?>');" style="margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="issue_method" value="fulfill_req">
                                    <input type="hidden" name="request_id" value="<?= (int)$pr['id'] ?>">
                                    <button type="submit" class="btn success" style="padding:6px 12px;font-size:12px">
                                        🚀 <?= current_locale() === 'am' ? 'እቃውን ወጪ አድርግና ላክ' : 'Issue & Send' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Issue Form: Supports Direct Department Issue without Request -->
<div class="card" style="max-width:680px;margin-bottom:24px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
        <span style="font-size:24px">🚚</span>
        <div>
            <h3 style="margin:0"><?= current_locale() === 'am' ? 'በቀጥታ ለክፍሎች እቃ መላኪያ (Direct Warehouse Issue)' : 'Direct Issue to Department (No Request Needed)' ?></h3>
            <small class="text-muted"><?= current_locale() === 'am' ? 'የክፍሉ ኃላፊ ሳይኖር በአካል መኖ/እቃ ሲጠየቅ፣ መጋዘኑ በቀጥታ ይልካል፤ የክፍሉ ኃላፊ ሲመጣ ያጸድቃል/ይረከባል።' : 'Send feed or supplies immediately. Department manager confirms upon receipt.' ?></small>
        </div>
    </div>

    <form method="post" id="directIssueForm"><?= csrf_field() ?>
        <input type="hidden" name="issue_method" value="direct_dept">

        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'ተቀባይ የስራ ክፍል (Destination Department) *' : 'Destination Department *' ?></label>
            <select name="department_id" required style="width:100%;font-size:15px;padding:8px">
                <option value="">— ተቀባይ ክፍል ይምረጡ (Select Department) —</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>">
                        🏢 <?= h($d['name']) ?> (<?= h($d['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'የሚላከው ዕቃ (Stock Item) *' : 'Stock Item *' ?></label>
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
            <div style="font-size:12px;color:#2e7d32;font-weight:600">በመጋዘን ውስጥ ያለው የቀረ ክምችት (Available In Warehouse):</div>
            <div style="font-size:18px;font-weight:800;color:#1b5e20" id="stockHelperValue">0</div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የሚላከው መጠን (Quantity to Send) *' : 'Quantity *' ?></label>
                <input type="number" step="0.01" min="0.01" name="quantity" id="issueQty" required placeholder="ለምሳሌ፡ 20">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'ማስታወሻ / የተረካቢ ሰራተኛ ስም' : 'Note / Physical Requester' ?></label>
                <input type="text" name="note" placeholder="ለምሳሌ፡ ሰራተኛው አበበ መጥቶ በቃል ስለጠየቀ የተሰጠ...">
            </div>
        </div>

        <button class="btn" type="submit" id="btnSubmitIssue">
            🚀 <?= current_locale() === 'am' ? 'እቃውን ወጪ አድርግና ለክፍሉ ላክ (Dispatch to Department)' : 'Dispatch Stock to Department' ?>
        </button>
    </form>
</div>

<!-- History and Dispatches Tracking -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0">📜 <?= current_locale() === 'am' ? 'የወጡ እቃዎችና የርክክብ ሁኔታ ማህደር (Dispatches & Receipt Log)' : 'Dispatches & Receipt Log' ?></h3>
        <span class="text-muted" style="font-size:12px">ሁለቱም መንገዶች (በቀጥታ የተላኩ እና በጥያቄ የወጡ) እዚህ ተመዝግበዋል</span>
    </div>

    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>ቀንና ሰዓት (ኢትዮጵያ)</th>
                    <th>የተላከለት ክፍል</th>
                    <th>የዕቃው ስም</th>
                    <th>መጠን</th>
                    <th>የወጣበት መንገድ</th>
                    <th>ከመጋዘን ያወጣው</th>
                    <th>የርክክብ ሁኔታ (Status)</th>
                    <th>በክፍሉ ያጸደቀው/የተረከበው</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentDispatches as $d): ?>
                    <tr>
                        <td style="font-size:12px;white-space:nowrap">
                            📅 <?= eth_date($d['created_at']) ?><br>
                            <small class="text-muted">⏰ <?= eth_time($d['created_at']) ?></small>
                        </td>
                        <td><strong>🏢 <?= h($d['dept_name']) ?></strong></td>
                        <td><?= h($d['item_name']) ?> <span class="text-muted" style="font-size:11px">(<?= h($d['sku']) ?>)</span></td>
                        <td><strong><?= number_format($d['issued_quantity'] > 0 ? $d['issued_quantity'] : $d['requested_quantity'], 2) ?> <?= h($d['unit']) ?></strong></td>
                        <td style="font-size:12px">
                            <?php if (str_contains($d['note'] ?? '', 'በቀጥታ ከመጋዘን')): ?>
                                <span class="badge" style="background:#e3f2fd;color:#0d47a1">🚚 ቀጥታ ከመጋዘን</span>
                            <?php else: ?>
                                <span class="badge" style="background:#f3e5f5;color:#4a148c">📝 በክፍሉ ጥያቄ</span>
                            <?php endif; ?>
                            <?php if (!empty($d['note'])): ?>
                                <div class="text-muted" style="font-size:11px;margin-top:2px"><?= h($d['note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($d['issuer_name'])): ?>
                                👤 <strong><?= h($d['issuer_name']) ?></strong><br>
                                <small class="text-muted"><?= h($d['issuer_role']) ?></small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($d['status'] === 'completed'): ?>
                                <span class="badge" style="background:#e8f5e9;color:#2e7d32">✓ ተረክቧል (Completed)</span>
                            <?php elseif ($d['status'] === 'sent_to_department'): ?>
                                <span class="badge" style="background:#fff8e1;color:#f57c00">⏳ ርክክብ ይጠብቃል (Pending)</span>
                            <?php else: ?>
                                <span class="badge" style="background:#f5f5f5;color:#616161"><?= h($d['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($d['receiver_name'])): ?>
                                🤝 <strong><?= h($d['receiver_name']) ?></strong><br>
                                <small class="text-muted"><?= eth_date($d['received_confirmed_at']) ?> <?= eth_time($d['received_confirmed_at']) ?></small>
                            <?php else: ?>
                                <span class="text-muted" style="font-style:italic">ገና አልተረከበም</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentDispatches)): ?>
                    <tr><td colspan="8" class="text-muted" style="text-align:center">ምንም የተመዘገበ ወጪ የለም።</td></tr>
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
    const val = document.getElementById('stockHelperValue');
    const qtyInput = document.getElementById('issueQty');
    
    if (opt && opt.value !== '') {
        const stock = parseFloat(opt.getAttribute('data-stock') || 0);
        const unit = opt.getAttribute('data-unit') || '';
        val.innerText = stock.toFixed(2) + ' ' + unit;
        banner.style.display = 'block';
        qtyInput.max = stock;
    } else {
        banner.style.display = 'none';
        qtyInput.removeAttribute('max');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateStockHelper();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
