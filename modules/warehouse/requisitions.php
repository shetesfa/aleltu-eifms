<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['create_requisition'])) {
            $deptId = (int) $_POST['department_id'];
            $itemId = (int) $_POST['stock_item_id'];
            $qty = (float) $_POST['requested_quantity'];
            $note = trim($_POST['note'] ?? '');
            dept_material_request_create($deptId, $itemId, $qty, current_user()['id'], null, $note);
            flash(t('reqs.submitted', 'Material requisition submitted to Store.'));
        } elseif (isset($_POST['fulfill_requisition'])) {
            $reqId = (int) $_POST['requisition_id'];
            $req = db()->query("SELECT stock_item_id FROM dept_material_requests WHERE id = {$reqId}")->fetch();
            if (!$req) {
                throw new RuntimeException('Request not found.');
            }
            $warehouseId = resolve_warehouse_for_item((int) $req['stock_item_id']);
            dept_material_request_fulfill($reqId, $warehouseId, current_user()['id']);
            flash('Requisition processed successfully.');
        }
        header('Location: ' . BASE_URL . '/modules/warehouse/requisitions.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$requisitions = db()->query(
    "SELECT r.*, d.name AS dept_name, i.name AS item_name, i.unit, u.name AS requester_name
     FROM dept_material_requests r
     JOIN departments d ON d.id = r.department_id
     JOIN wh_stock_items i ON i.id = r.stock_item_id
     JOIN users u ON u.id = r.requested_by
     ORDER BY r.created_at DESC
     LIMIT 100"
)->fetchAll();

$departments = db()->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$items = db()->query("SELECT id, name, sku, unit FROM wh_stock_items ORDER BY name")->fetchAll();

$pendingCount = 0;
foreach ($requisitions as $r) {
    if ($r['status'] === 'pending' || $r['status'] === 'partially_issued') {
        $pendingCount++;
    }
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">📥 <?= h(t('reqs.store_queue', 'Department Material Requests Queue')) ?></h2>
        <p class="text-muted" style="margin:0">Evaluate incoming requests against live warehouse inventory. Issue available stock or route shortages to Finance.</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/warehouse/receiving.php" class="btn secondary">📥 Store Receiving</a>
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary">📊 Stock Inventory</a>
    </div>
</div>

<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi">
        <div class="label"><?= h(t('reqs.awaiting_action', 'Awaiting Action')) ?></div>
        <div class="value" style="color:<?= $pendingCount > 0 ? '#b3261e' : 'inherit' ?>"><?= $pendingCount ?> requests</div>
    </div>
</div>

<?php if (has_role('general_manager')): ?>
<div class="card" style="max-width:640px;margin-bottom:20px">
    <h3><?= h(t('reqs.raise_on_behalf', 'Raise Request on Behalf of a Department')) ?></h3>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="create_requisition" value="1">
        <div class="form-row">
            <div class="form-group">
                <label><?= h(t('reqs.department', 'Department')) ?> *</label>
                <select name="department_id" required>
                    <?php foreach ($departments as $d): ?><option value="<?= (int) $d['id'] ?>"><?= h($d['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= h(t('reqs.item', 'Item')) ?> *</label>
                <select name="stock_item_id" required>
                    <?php foreach ($items as $i): ?><option value="<?= (int) $i['id'] ?>"><?= h($i['name']) ?> (<?= h($i['unit']) ?>)</option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><?= h(t('reqs.quantity', 'Quantity')) ?> *</label>
                <input type="number" step="0.1" min="0.1" name="requested_quantity" required>
            </div>
            <div class="form-group">
                <label>Note / Purpose</label>
                <input type="text" name="note" placeholder="e.g. Regular stock replenishment">
            </div>
        </div>
        <button class="btn" type="submit"><?= h(t('reqs.send_request', 'Submit Request')) ?></button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-top:0">📋 <?= current_locale()==='am'?'ከስራ ክፍሎች የመጡ የእቃ መጠየቂያዎች (Requisitions Queue)':'Incoming Department Requests' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale()==='am'?'ቀንና ሰዓት (ኢትዮጵያ)':'Date & Time' ?></th>
                    <th><?= current_locale()==='am'?'የጠየቀው ክፍል':'Department' ?></th>
                    <th><?= current_locale()==='am'?'የእቃው ስም':'Item' ?></th>
                    <th><?= current_locale()==='am'?'የተጠየቀው':'Requested' ?></th>
                    <th><?= current_locale()==='am'?'በመጋዘን ያለው':'Available in Stock' ?></th>
                    <th><?= current_locale()==='am'?'እጥረት (ጎዶሎ)':'Shortage' ?></th>
                    <th><?= current_locale()==='am'?'ሁኔታ':'Status' ?></th>
                    <th><?= current_locale()==='am'?'እርምጃ':'Action' ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requisitions as $r):
                $whId = resolve_warehouse_for_item((int) $r['stock_item_id']);
                $avail = stock_current($whId, (int) $r['stock_item_id']);
                $reqQty = (float) $r['requested_quantity'];
                $shortageCalc = max(0, $reqQty - $avail);

                $statusLabels = [
                    'pending' => ['label' => (current_locale()==='am'?'ክምችት ማረጋገጫ ይጠብቃል':'Pending Stock Check'), 'cls' => 'dry'],
                    'partially_issued' => ['label' => (current_locale()==='am'?'በከፊል የወጣ':'Partly Issued'), 'cls' => 'dry'],
                    'fulfilled' => ['label' => (current_locale()==='am'?'ሙሉ ክምችት የወጣ':'Full Stock Issued'), 'cls' => 'milking'],
                    'sent_to_department' => ['label' => (current_locale()==='am'?'🚚 ለክፍሉ የተላከ':'🚚 Sent to Dept'), 'cls' => 'milking'],
                    'shortage_purchase_raised' => ['label' => (current_locale()==='am'?'⚠️ ለግዢ ወደ ፋይናንስ የተላከ':'⚠️ Sent to Finance for Purchase'), 'cls' => 'sold'],
                    'completed' => ['label' => (current_locale()==='am'?'✓ የተጠናቀቀ':'✓ Completed'), 'cls' => 'milking'],
                    'rejected' => ['label' => (current_locale()==='am'?'ውድቅ የተደረገ':'Rejected'), 'cls' => 'sold'],
                ];
                $st = $statusLabels[$r['status']] ?? ['label' => $r['status'], 'cls' => 'badge'];
                $canProcess = in_array($r['status'], ['pending'], true);
                $displayName = $r['custom_item_name'] ?: $r['item_name'];
            ?>
                <tr>
                    <td style="font-size:12px;white-space:nowrap">
                        📅 <?= eth_date($r['created_at']) ?><br>
                        <small class="text-muted">⏰ <?= eth_time($r['created_at']) ?></small>
                    </td>
                    <td>
                        <strong>🏢 <?= h($r['dept_name']) ?></strong><br>
                        <small class="text-muted">👤 <?= h($r['requester_name']) ?></small>
                    </td>
                    <td><strong><?= h($displayName) ?></strong> <span class="text-muted">(<?= h($r['unit']) ?>)</span></td>
                    <td><strong style="font-size:15px"><?= number_format($reqQty, 1) ?> <?= h($r['unit']) ?></strong></td>
                    <td>
                        <span class="<?= $avail >= $reqQty ? 'badge milking' : ($avail > 0 ? 'badge dry' : 'badge sold') ?>">
                            <?= number_format($avail, 1) ?> <?= h($r['unit']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($canProcess): ?>
                            <?= $shortageCalc > 0 ? '<strong style="color:#b3261e">'.number_format($shortageCalc, 1).' '.$r['unit'].'</strong>' : '<span class="text-muted">0</span>' ?>
                        <?php else: ?>
                            <?= (float)$r['shortage_quantity'] > 0 ? '<strong style="color:#b3261e">'.number_format($r['shortage_quantity'], 1).' '.$r['unit'].'</strong>' : '<span class="text-muted">0</span>' ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $st['cls'] ?>"><?= h($st['label']) ?></span>
                        <?php if ($r['received_confirmed_at']): ?>
                            <br><span class="badge milking" style="margin-top:4px;font-size:10px">✓ <?= current_locale()==='am'?'ክፍሉ መረከቡን አረጋግጧል':'Dept Confirmed Receipt' ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($canProcess): ?>
                            <form method="post" onsubmit="return confirm('<?= current_locale()==='am'?'ይህን ጥያቄ ማስተናገድ ይፈልጋሉ?':'Process this request?' ?>');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="fulfill_requisition" value="1">
                                <input type="hidden" name="requisition_id" value="<?= (int) $r['id'] ?>">
                                <?php if ($avail >= $reqQty): ?>
                                    <button class="btn" type="submit" style="padding:5px 12px;font-size:12px;background:#2e7d32;border-color:#2e7d32;white-space:nowrap">
                                        ✓ <?= current_locale()==='am'?'ክምችት አስረክብ':'Issue Stock' ?> (<?= number_format($reqQty, 1) ?>)
                                    </button>
                                <?php elseif ($avail > 0): ?>
                                    <button class="btn" type="submit" style="padding:5px 12px;font-size:12px;background:#e65100;border-color:#e65100;white-space:nowrap">
                                        ⚡ <?= number_format($avail, 1) ?> <?= current_locale()==='am'?'አስረክብ + ቀሪውን አዘዝ':'Issue + Buy Remainder' ?>
                                    </button>
                                <?php else: ?>
                                    <button class="btn secondary" type="submit" style="padding:5px 12px;font-size:12px;color:#b3261e;border-color:#b3261e;white-space:nowrap">
                                        🔴 <?= current_locale()==='am'?'ለግዢ ወደ ፋይናንስ ላክ':'Forward to Finance' ?>
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php elseif ($r['status'] === 'completed' || $r['received_confirmed_at']): ?>
                            <span style="color:#2e7d32;font-size:12px;font-weight:600">✓ <?= current_locale()==='am'?'ተጠናቋል':'Closed' ?></span>
                        <?php elseif ($r['status'] === 'sent_to_department'): ?>
                            <span class="text-muted" style="font-size:12px">⏳ <?= current_locale()==='am'?'የክፍሉን ርክክብ ይጠብቃል':'Waiting for dept receipt' ?></span>
                        <?php elseif ($r['status'] === 'shortage_purchase_raised'): ?>
                            <span class="text-muted" style="font-size:12px">🛒 <?= current_locale()==='am'?'በፋይናንስ ግዢ ሂደት ላይ':'In Finance Purchase' ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($requisitions)): ?>
                <tr><td colspan="8" class="text-muted"><?= current_locale()==='am'?'ምንም በመጠባበቅ ላይ ያለ የእቃ ጥያቄ የለም።':'No material requests in queue.' ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
