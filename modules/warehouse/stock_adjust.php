<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper']);

$currentUser = current_user();
$canApprove = has_role(['super_admin', 'general_manager']);
$preselectedItemId = (int) ($_GET['item_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $warehouseId = 1; // 1 Unified Main Central Warehouse

        if (isset($_POST['request_adjustment'])) {
            $itemId = (int) $_POST['stock_item_id'];
            $type = $_POST['adjustment_type'] ?? 'increase';
            $quantity = (float) $_POST['quantity'];
            $reason = trim($_POST['reason'] ?? '');

            if ($quantity <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የማስተካከያው መጠን ከ 0 በላይ መሆን አለበት።' : 'Adjustment quantity must be greater than zero.');
            }
            if (empty($reason)) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የማስተካከያ ምክንያት ማስገባት ግዴታ ነው!' : 'Justification reason is required.');
            }

            stock_adjust_request($warehouseId, $itemId, $type, $quantity, $reason, $currentUser['id']);
            flash(current_locale() === 'am' ? '✓ የክምችት ማስተካከያ ጥያቄው ተመዝግቧል። ስራ አስኪያጁ (Manager) ሲያጸድቀው በራስ-ሰር ክምችቱ ይስተካከላል።' : 'Stock adjustment request submitted. Pending manager approval.');
        } elseif (isset($_POST['approve_adjustment'])) {
            if (!$canApprove) {
                throw new RuntimeException(current_locale() === 'am' ? 'ማስተካከያውን የማጽደቅ ስልጣን የለዎትም።' : 'Unauthorized to approve stock adjustment.');
            }
            $adjId = (int) $_POST['adjustment_id'];
            stock_adjust_approve($adjId, $currentUser['id']);
            flash(current_locale() === 'am' ? '✓ የክምችት ማስተካከያው ጸድቆ የዕቃው መጠን በራስ-ሰር ተስተካክሏል!' : 'Stock adjustment approved and inventory balance updated.');
        } elseif (isset($_POST['reject_adjustment'])) {
            if (!$canApprove) {
                throw new RuntimeException(current_locale() === 'am' ? 'ማስተካከያውን ውድቅ የማድረግ ስልጣን የለዎትም።' : 'Unauthorized to reject stock adjustment.');
            }
            $adjId = (int) $_POST['adjustment_id'];
            $rejReason = trim($_POST['rejection_reason'] ?? 'በስራ አስኪያጁ ውድቅ ተደርጓል');
            stock_adjust_reject($adjId, $currentUser['id'], $rejReason);
            flash(current_locale() === 'am' ? '✓ የክምችት ማስተካከያው ውድቅ ተደርጓል።' : 'Stock adjustment rejected.');
        }
        header('Location: ' . BASE_URL . '/modules/warehouse/stock_adjust.php');
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

// Pending adjustments for manager review
$pendingAdjustments = db()->query(
    "SELECT a.*, i.name AS item_name, i.sku, i.unit, u.name AS requester_name, r.name AS requester_role
     FROM wh_stock_adjustments a
     JOIN wh_stock_items i ON i.id = a.stock_item_id
     JOIN users u ON u.id = a.adjusted_by
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE a.status = 'pending'
     ORDER BY a.created_at DESC"
)->fetchAll();

// Adjustment History
$adjustments = db()->query(
    "SELECT a.*, i.name AS item_name, i.sku, i.unit, 
            u.name AS adjuster_name, r.name AS adjuster_role,
            app.name AS approver_name, rapp.name AS approver_role
     FROM wh_stock_adjustments a
     JOIN wh_stock_items i ON i.id = a.stock_item_id
     JOIN users u ON u.id = a.adjusted_by
     LEFT JOIN roles r ON r.id = u.role_id
     LEFT JOIN users app ON app.id = a.approved_by
     LEFT JOIN roles rapp ON rapp.id = app.role_id
     ORDER BY a.created_at DESC LIMIT 50"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">⚖️ <?= current_locale() === 'am' ? 'የዕቃ ክምችት ማስተካከያና ማጽደቂያ' : 'Controlled Stock Adjustment & Approval' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'የመጋዘን ቆጠራ ልዩነት፣ የተበላሸ ወይም ትርፍ ዕቃ ማስተካከያ ጥያቄ ማቅረቢያ እና በስራ አስኪያጅ ማጽደቂያ ማዕከል።' : 'Submit and approve stock adjustments with full audit controls.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ክምችት ዝርዝር' : 'Back to Inventory' ?></a>
    </div>
</div>

<!-- Pending Approvals Box for Managers -->
<?php if (!empty($pendingAdjustments)): ?>
    <div class="card" style="border-left:5px solid #f57c00;background:#fff8e1;margin-bottom:24px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <span style="font-size:24px">⏳</span>
            <div>
                <h3 style="margin:0;color:#e65100">
                    <?= current_locale() === 'am' ? 'የስራ አስኪያጅ ውሳኔ የሚጠብቁ ማስተካከያዎች (' . count($pendingAdjustments) . ')' : 'Stock Adjustments Pending Manager Approval (' . count($pendingAdjustments) . ')' ?>
                </h3>
                <small class="text-muted"><?= current_locale() === 'am' ? 'ማስተካከያው ሲጸድቅ ብቻ በራስ-ሰር የክምችቱ ቁጥር ይስተካከላል።' : 'Stock balance will only update once approved by manager.' ?></small>
            </div>
        </div>

        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr style="background:rgba(0,0,0,0.03)">
                        <th>የቀረበበት ቀን</th>
                        <th>የዕቃው ስም</th>
                        <th>የማስተካከያ ዓይነት</th>
                        <th>መጠን</th>
                        <th>ምክንያት (Justification)</th>
                        <th>የጠየቀው ሠራተኛ</th>
                        <th>እርምጃ (Action)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingAdjustments as $pa): ?>
                        <tr>
                            <td style="font-size:12px;white-space:nowrap">
                                📅 <?= eth_date($pa['created_at']) ?><br>
                                <small class="text-muted">⏰ <?= eth_time($pa['created_at']) ?></small>
                            </td>
                            <td><strong><?= h($pa['item_name']) ?></strong> <span class="text-muted" style="font-size:11px">(<?= h($pa['sku']) ?>)</span></td>
                            <td>
                                <?php if ($pa['adjustment_type'] === 'increase'): ?>
                                    <span class="badge" style="background:#e8f5e9;color:#2e7d32">➕ ክምችት መጨመር (Surplus)</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#ffebee;color:#c62828">➖ ክምችት መቀነስ (Damaged/Lost)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="font-size:15px;color:<?= $pa['adjustment_type'] === 'increase' ? '#2e7d32' : '#c62828' ?>">
                                    <?= $pa['adjustment_type'] === 'increase' ? '+' : '-' ?><?= number_format($pa['quantity'], 2) ?> <?= h($pa['unit']) ?>
                                </strong>
                            </td>
                            <td style="max-width:240px;font-size:13px"><?= h($pa['reason']) ?></td>
                            <td>
                                👤 <strong><?= h($pa['requester_name']) ?></strong><br>
                                <small class="text-muted"><?= h($pa['requester_role']) ?></small>
                            </td>
                            <td>
                                <?php if ($canApprove): ?>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <form method="post" onsubmit="return confirm('<?= current_locale() === 'am' ? 'እርግጠኛ ነዎት ይህ ማስተካከያ ይጽደቅ? የክምችት መጠኑ ወዲያውኑ ይስተካከላል።' : 'Approve adjustment? Stock will be updated immediately.' ?>');" style="margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="adjustment_id" value="<?= (int)$pa['id'] ?>">
                                            <button type="submit" name="approve_adjustment" class="btn success" style="padding:6px 12px;font-size:12px">
                                                ✓ <?= current_locale() === 'am' ? 'አጽድቅ' : 'Approve' ?>
                                            </button>
                                        </form>
                                        <button type="button" class="btn danger" style="padding:6px 12px;font-size:12px" onclick="openRejectModal(<?= (int)$pa['id'] ?>)">
                                            ✕ <?= current_locale() === 'am' ? 'ውድቅ አድርግ' : 'Reject' ?>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="badge warning">⏳ ስራ አስኪያጅ ይጠብቃል</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Reject Modal -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;max-width:460px;width:90%;padding:20px;box-shadow:0 10px 25px rgba(0,0,0,0.3)">
        <h3 style="margin-top:0;color:#c62828">✕ ማስተካከያውን ውድቅ ማድረጊያ</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="adjustment_id" id="rejectAdjId" value="">
            <div class="form-group">
                <label>የውድቅ የተደረገበት ምክንያት (Reason for Rejection) *</label>
                <textarea name="rejection_reason" required rows="3" style="width:100%" placeholder="ለምሳሌ፡ ቆጠራው እንደገና መካሄድ ስላለበት..."></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
                <button type="button" class="btn secondary" onclick="closeRejectModal()">ተመለስ</button>
                <button type="submit" name="reject_adjustment" class="btn danger">አረጋግጥና ውድቅ አድርግ</button>
            </div>
        </form>
    </div>
</div>

<!-- Adjustment Request Form -->
<div class="card" style="max-width:650px;margin-bottom:24px">
    <h3 style="margin-top:0">➕ አዲስ የክምችት ማስተካከያ ጥያቄ አቅርብ (Submit Stock Adjustment)</h3>
    <form method="post"><?= csrf_field() ?>
        
        <div class="form-group">
            <label>የሚስተካከለው ዕቃ (Stock Item) *</label>
            <select name="stock_item_id" id="adjStockItem" required onchange="updateLiveStockDisplay()" style="width:100%">
                <option value="">— ዕቃ ይምረጡ (Select Item) —</option>
                <?php foreach ($items as $i): ?>
                    <option value="<?= (int)$i['id'] ?>" data-stock="<?= (float)$i['current_stock'] ?>" data-unit="<?= h($i['unit']) ?>" <?= $preselectedItemId === (int)$i['id'] ? 'selected' : '' ?>>
                        <?= h($i['name']) ?> (ያለው ክምችት: <?= number_format((float)$i['current_stock'], 2) ?> <?= h($i['unit']) ?> - <?= h($i['sku']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Live Stock Display Box -->
        <div id="liveStockBox" style="display:none;background:var(--bg);border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:14px">
            በአሁኑ ሰዓት በመጋዘን ያለው ክምችት: <strong id="liveStockVal" style="color:#1b5e20;font-size:16px">0</strong>
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

        <button class="btn" type="submit" name="request_adjustment">📝 የማስተካከያ ጥያቄውን መዝግብ (Submit for Approval)</button>
    </form>
</div>

<!-- History Log -->
<div class="card">
    <h3 style="margin-top:0">📜 የተደረጉ ማስተካከያዎች እና የውሳኔዎች ማህደር (Adjustment Audit Log)</h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>ቀንና ሰዓት (ኢትዮጵያ)</th>
                    <th>የዕቃው ስም</th>
                    <th>የማስተካከያ ዓይነት</th>
                    <th>መጠን</th>
                    <th>ምክንያት</th>
                    <th>የጠየቀው ሠራተኛ</th>
                    <th>ሁኔታ (Status)</th>
                    <th>ያጸደቀው / ውሳኔ ሰጪ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adjustments as $a): ?>
                    <tr>
                        <td style="font-size:12px;white-space:nowrap">
                            📅 <?= eth_date($a['created_at']) ?><br>
                            <small class="text-muted">⏰ <?= eth_time($a['created_at']) ?></small>
                        </td>
                        <td><strong><?= h($a['item_name']) ?></strong> <span class="text-muted" style="font-size:11px">(<?= h($a['sku']) ?>)</span></td>
                        <td>
                            <?php if ($a['adjustment_type'] === 'increase'): ?>
                                <span class="badge" style="background:#e8f5e9;color:#2e7d32">➕ ጭማሪ</span>
                            <?php else: ?>
                                <span class="badge" style="background:#ffebee;color:#c62828">➖ ቅነሳ</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= number_format($a['quantity'], 2) ?> <?= h($a['unit']) ?></strong>
                        </td>
                        <td style="font-size:13px">
                            <?= h($a['reason']) ?>
                            <?php if (!empty($a['rejection_reason'])): ?>
                                <div style="color:#c62828;font-size:11px;margin-top:2px">⚠️ ምክንያት፡ <?= h($a['rejection_reason']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            👤 <strong><?= h($a['adjuster_name']) ?></strong><br>
                            <small class="text-muted"><?= h($a['adjuster_role']) ?></small>
                        </td>
                        <td>
                            <?php if ($a['status'] === 'approved'): ?>
                                <span class="badge" style="background:#e8f5e9;color:#2e7d32">✓ የጸደቀ (Approved)</span>
                            <?php elseif ($a['status'] === 'rejected'): ?>
                                <span class="badge" style="background:#ffebee;color:#c62828">✕ ውድቅ የሆነ (Rejected)</span>
                            <?php else: ?>
                                <span class="badge" style="background:#fff8e1;color:#f57c00">⏳ ውሳኔ የሚጠብቅ (Pending)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($a['approver_name'])): ?>
                                🛡️ <strong><?= h($a['approver_name']) ?></strong><br>
                                <small class="text-muted"><?= eth_date($a['approved_at']) ?> <?= eth_time($a['approved_at']) ?></small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($adjustments)): ?>
                    <tr><td colspan="8" class="text-muted" style="text-align:center">እስካሁን የተመዘገበ ማስተካከያ የለም።</td></tr>
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

function openRejectModal(id) {
    document.getElementById('rejectAdjId').value = id;
    const modal = document.getElementById('rejectModal');
    modal.style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    updateLiveStockDisplay();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
