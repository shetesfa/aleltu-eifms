<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['create_po_from'])) {
            $supplierName = trim($_POST['supplier_name'] ?? '');
            $unitPrice = (float) ($_POST['unit_price'] ?? 0);
            if (empty($supplierName)) {
                throw new InvalidArgumentException('Supplier name is required.');
            }
            if ($unitPrice <= 0) {
                throw new InvalidArgumentException('Unit price must be greater than zero.');
            }
            purchase_order_create_from_request((int) $_POST['create_po_from'], $supplierName, $unitPrice);
            flash('Purchase order created successfully.');
        } elseif (isset($_POST['approve_po'])) {
            purchase_order_approve((int) $_POST['approve_po']);
            flash('Purchase order approved.');
        } elseif (isset($_POST['receive_po'])) {
            $poId = (int) $_POST['receive_po'];
            $po = db()->query("SELECT stock_item_id FROM fin_purchase_orders WHERE id = " . $poId)->fetch();
            if (!$po) {
                throw new RuntimeException('Purchase order not found.');
            }
            purchase_order_receive($poId, resolve_warehouse_for_item((int) $po['stock_item_id']));
            flash('Goods marked purchased and sent to Storekeeper for physical verification.');
        } elseif (isset($_POST['pay_po'])) {
            po_record_payment((int) $_POST['pay_po'], (float) $_POST['payment_amount'], $_POST['payment_method'], $_POST['payment_notes'] ?? null);
            flash('Payment recorded successfully.');
        } else {
            purchase_request_create($_POST);
            flash('Purchase request raised.');
        }
        header('Location: ' . BASE_URL . '/modules/finance/purchasing.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$items = db()->query("SELECT id, name, sku, unit FROM wh_stock_items ORDER BY name")->fetchAll();

$requests = db()->query(
    "SELECT pr.*, i.name AS item_name, i.unit, d.name AS dept_name, mr.id AS mat_req_id
     FROM fin_purchase_requests pr 
     LEFT JOIN wh_stock_items i ON i.id = pr.stock_item_id
     LEFT JOIN dept_material_requests mr ON mr.id = pr.material_request_id
     LEFT JOIN departments d ON d.id = mr.department_id
     ORDER BY pr.id DESC LIMIT 30"
)->fetchAll();

$orders = db()->query(
    "SELECT po.*, i.name AS item_name, i.unit, d.name AS dept_name
     FROM fin_purchase_orders po 
     JOIN wh_stock_items i ON i.id = po.stock_item_id 
     LEFT JOIN dept_material_requests mr ON mr.id = po.material_request_id
     LEFT JOIN departments d ON d.id = mr.department_id
     ORDER BY po.id DESC LIMIT 30"
)->fetchAll();

$threshold = setting_get('po_approval_threshold') ?? 10000;
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🛒 <?= h(t('fin.purchasing_title', 'Finance Purchasing & Supply Chain')) ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የግዢ ሂደት፡ የክፍሎች የዕቃ እጥረት ጥያቄ &rarr; የግዢ ትዕዛዝ (PO) ማዘጋጀት &rarr; ወደ መጋዘን መላክ &rarr; የክፍያ ክትትል።' : 'Procurement cycle: Department Shortage &rarr; Purchase Request &rarr; Purchase Order &rarr; Send to Warehouse &rarr; Payment Tracking.' ?></p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">&larr; <?= h(t('fin.back_to_dashboard', 'Cash Flow Dashboard')) ?></a>
    </div>
</div>

<div class="card" style="background:#e8f5e9;border-left:4px solid #2e7d32;color:#111111;margin-bottom:20px;padding:12px 16px">
    <strong style="color:#1b5e20">📦 <?= current_locale() === 'am' ? 'የመጋዘን ርክክብ ደንብ:' : 'Warehouse Receiving Protection Rule:' ?></strong> <?= current_locale() === 'am' ? 'ፋይናንስ ግዢ ፈጽሞ <strong>"ወደ መጋዘን ላክ"</strong> ሲል ለመጋዘን ኃላፊው የርክክብ ጥያቄ ይፈጠራል። ዕቃው ወደ ክምችት የሚደመረው የመጋዘን ኃላፊው በአካል ተረክቦ ሲያረጋግጥ ብቻ ነው።' : 'When Finance completes a purchase and clicks "Send to Warehouse", an incoming shipment record is automatically created for the Storekeeper.' ?>
</div>

<!-- Manual Request Form (Finance Initiated) -->
<div class="card" style="max-width:560px;margin-bottom:24px">
    <h3>➕ <?= h(t('fin.raise_request', 'Raise Internal Purchase Request')) ?></h3>
    <form method="post"><?= csrf_field() ?>
        <div class="form-group">
            <label><?= h(t('fin.item', 'Item')) ?> *</label>
            <select name="stock_item_id" required>
                <?php foreach ($items as $i): ?>
                    <option value="<?= (int) $i['id'] ?>"><?= h($i['name']) ?> (<?= h($i['unit']) ?> - <?= h($i['sku']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><?= h(t('fin.quantity', 'Quantity')) ?> *</label>
                <input type="number" step="0.01" min="0.1" name="quantity" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ 100' : 'e.g. 100' ?>">
            </div>
            <div class="form-group">
                <label><?= h(t('fin.justification', 'Justification / Purpose')) ?></label>
                <input type="text" name="justification" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የክምችት ማነስ' : 'e.g. Low inventory reorder' ?>">
            </div>
        </div>
        <button class="btn" type="submit"><?= h(t('fin.raise_request', 'Submit Purchase Request')) ?></button>
    </form>
</div>

<!-- Incoming Purchase Requests Queue -->
<div class="card" style="margin-bottom:24px">
    <h3 style="margin-top:0">📋 <?= h(t('fin.purchase_requests', 'Purchase Requests Queue')) ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'የጥያቄ #' : 'Req #' ?></th>
                    <th><?= current_locale() === 'am' ? 'የጠየቀው ክፍል' : 'Origin / Department' ?></th>
                    <th><?= current_locale() === 'am' ? 'ዕቃ' : 'Item' ?></th>
                    <th><?= current_locale() === 'am' ? 'መጠን' : 'Quantity' ?></th>
                    <th><?= current_locale() === 'am' ? 'ምክንያት' : 'Justification' ?></th>
                    <th><?= h(t('common.status', 'Status')) ?></th>
                    <th><?= current_locale() === 'am' ? 'የግዢ ትዕዛዝ ፍጠር (PO)' : 'Process PO' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): 
                    $reqStText = match($r['status']) {
                        'converted_to_po' => (current_locale() === 'am' ? 'ትዕዛዝ ተፈጥሯል' : 'PO Created'),
                        'approved' => (current_locale() === 'am' ? 'ጸድቋል' : 'Approved'),
                        default => (current_locale() === 'am' ? 'በመጠባበቅ ላይ' : 'Pending')
                    };
                ?>
                    <tr>
                        <td><code>#<?= (int)$r['id'] ?></code></td>
                        <td>
                            <?php if ($r['mat_req_id']): ?>
                                <strong style="color:var(--primary)"><?= h($r['dept_name'] ?: (current_locale() === 'am' ? 'የስራ ክፍል' : 'Department')) ?></strong>
                                <br><small class="text-muted"><?= current_locale() === 'am' ? 'ከጥያቄ #' : 'Linked to Req #' ?><?= (int)$r['mat_req_id'] ?></small>
                            <?php else: ?>
                                <span class="text-muted"><?= current_locale() === 'am' ? 'የውስጥ ፋይናንስ' : 'Internal Finance' ?></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= h($r['item_name']) ?></strong></td>
                        <td><strong style="font-size:14px"><?= number_format($r['quantity'], 2) ?> <?= h($r['unit']) ?></strong></td>
                        <td class="text-muted"><?= h($r['justification'] ?: '—') ?></td>
                        <td>
                            <span class="badge <?= $r['status'] === 'pending' ? 'dry' : 'milking' ?>">
                                <?= h($reqStText) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="post" style="display:flex;gap:6px;flex-wrap:wrap"><?= csrf_field() ?>
                                    <input type="hidden" name="create_po_from" value="<?= (int) $r['id'] ?>">
                                    <input type="text" name="supplier_name" placeholder="<?= current_locale() === 'am' ? 'አቅራቢ' : 'Supplier' ?>" required style="width:110px;padding:4px 6px">
                                    <input type="number" step="0.01" min="0.01" name="unit_price" placeholder="<?= current_locale() === 'am' ? 'ነጠላ ዋጋ' : 'Unit Price' ?>" required style="width:100px;padding:4px 6px">
                                    <button class="btn secondary" type="submit" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'ትዕዛዝ አውጣ' : 'Create PO' ?></button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">✓ <?= current_locale() === 'am' ? 'ተቀይሯል' : 'Converted' ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="7" class="text-muted"><?= h(t('common.no_records', 'No purchase requests waiting in queue.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Purchase Orders Table -->
<div class="card">
    <h3 style="margin-top:0">🛍️ <?= h(t('fin.purchase_orders', 'Purchase Orders & Warehouse Dispatch')) ?></h3>
    <div style="overflow-x:auto">
        <table>

        <thead>
            <tr>
                <th>PO #</th>
                <th><?= current_locale() === 'am' ? 'አቅራቢ ድርጅት' : 'Supplier' ?></th>
                <th><?= current_locale() === 'am' ? 'ዕቃ እና ተቀባይ ክፍል' : 'Item & Destination' ?></th>
                <th><?= current_locale() === 'am' ? 'መጠን' : 'Quantity' ?></th>
                <th><?= current_locale() === 'am' ? 'አጠቃላይ ዋጋ' : 'Total Value' ?></th>
                <th><?= current_locale() === 'am' ? 'የተከፈለ' : 'Paid Amount' ?></th>
                <th><?= current_locale() === 'am' ? 'የትዕዛዝ ሁኔታ' : 'Order Status' ?></th>
                <th><?= current_locale() === 'am' ? 'የመጋዘን ርክክብ እና እርምጃዎች' : 'Warehouse Dispatch / Actions' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o):
                $remaining = (float) $o['total_amount'] - (float) $o['amount_paid'];
                $payStatusBadge = ['unpaid' => 'badge sold', 'partial' => 'badge dry', 'paid' => 'badge milking'][$o['payment_status']] ?? 'badge';
                $payStatusText = match($o['payment_status']) {
                    'paid' => (current_locale() === 'am' ? 'ሙሉ በሙሉ ተከፍሏል' : 'Paid'),
                    'partial' => (current_locale() === 'am' ? 'በከፊል ተከፍሏል' : 'Partial'),
                    default => (current_locale() === 'am' ? 'ያልተከፈለ' : 'Unpaid')
                };
                $orderStatusBadge = match($o['status']) {
                    'received' => 'badge milking',
                    'pending_receipt', 'sent_to_warehouse' => 'badge dry',
                    'pending_approval' => 'badge sold',
                    'approved' => 'badge active',
                    default => 'badge'
                };
                $orderStatusText = match($o['status']) {
                    'received' => (current_locale() === 'am' ? 'የደረሰ / የተረከበ' : 'Received'),
                    'pending_receipt', 'sent_to_warehouse' => (current_locale() === 'am' ? 'ወደ መጋዘን የተላከ' : 'Sent to Warehouse'),
                    'pending_approval' => (current_locale() === 'am' ? 'ማጽደቅ ይጠብቃል' : 'Pending Approval'),
                    'approved' => (current_locale() === 'am' ? 'የጸደቀ' : 'Approved'),
                    default => str_replace('_', ' ', $o['status'])
                };
            ?>
                <tr>
                    <td><code>PO-<?= (int)$o['id'] ?></code></td>
                    <td><strong><?= h($o['supplier_name']) ?></strong></td>
                    <td>
                        <?= h($o['item_name']) ?>
                        <?php if ($o['dept_name']): ?>
                            <br><small class="text-muted"><?= current_locale() === 'am' ? 'ለ' : 'For' ?>: <?= h($o['dept_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format($o['quantity'], 2) ?> <?= h($o['unit']) ?></td>
                    <td><strong><?= number_format($o['total_amount'], 2) ?> <?= h(t('common.etb', 'ETB')) ?></strong></td>
                    <td>
                        <?= number_format($o['amount_paid'], 2) ?> <?= h(t('common.etb', 'ETB')) ?>
                        <br><span class="<?= $payStatusBadge ?>" style="font-size:10px"><?= h($payStatusText) ?></span>
                    </td>
                    <td>
                        <span class="<?= $orderStatusBadge ?>"><?= h($orderStatusText) ?></span>
                    </td>
                    <td>
                        <?php if ($o['status'] === 'pending_approval'): ?>
                            <form method="post"><?= csrf_field() ?>
                                <input type="hidden" name="approve_po" value="<?= (int) $o['id'] ?>">
                                <button class="btn secondary" type="submit" style="padding:4px 10px;font-size:12px">✓ <?= current_locale() === 'am' ? 'ትዕዛዙን አጽድቅ' : 'Approve PO' ?></button>
                            </form>
                        <?php elseif ($o['status'] === 'approved'): ?>
                            <form method="post"><?= csrf_field() ?>
                                <input type="hidden" name="receive_po" value="<?= (int) $o['id'] ?>">
                                <button class="btn" type="submit" style="padding:4px 10px;font-size:12px;background:#2e7d32;border-color:#2e7d32" onclick="return confirm('<?= current_locale() === 'am' ? 'ይህ የተገዛ ዕቃ ለመጋዘን ኃላፊው ለርክክብ እንዲላክ ይፈልጋሉ?' : 'Dispatch this purchased shipment to Warehouse for physical verification?' ?>')">
                                    🚚 <?= current_locale() === 'am' ? 'ወደ መጋዘን ላክ' : 'Send to Warehouse' ?>
                                </button>
                            </form>
                        <?php elseif (in_array($o['status'], ['pending_receipt', 'sent_to_warehouse'], true)): ?>
                            <span class="text-muted" style="font-size:12px">
                                ⏳ <?= current_locale() === 'am' ? 'የርክክብ ማረጋገጫ ይጠብቃል &rarr; ' : 'Waiting for Storekeeper receipt &rarr; ' ?>
                                <a href="<?= BASE_URL ?>/modules/warehouse/receiving.php" style="text-decoration:underline"><?= current_locale() === 'am' ? 'የርክክብ ገጽ' : 'Receiving Portal' ?></a>
                            </span>
                            <div style="font-size:11px;color:#b45309;background:#fef3c7;padding:3px 6px;border-radius:4px;margin-top:4px;font-weight:600;display:inline-block">
                                🔒 <?= current_locale() === 'am' ? 'ክፍያ ተቆልፏል፡ እቃው በመጋዘን መረከቡ እስኪረጋገጥ ክፍያ አይፈቀድም' : 'Payment locked until Storekeeper confirms receipt' ?>
                            </div>
                        <?php elseif ($o['status'] === 'received'): ?>
                            <span class="badge milking">✓ <?= current_locale() === 'am' ? 'ዕቃው ተረጋግጦ ተረክቧል' : 'Stock Received &amp; Verified' ?></span>
                        <?php endif; ?>

                        <!-- Supplier Payment Form (Only allowed after physical storekeeper receipt) -->
                        <?php if ($remaining > 0.01 && $o['status'] === 'received'): ?>
                            <form method="post" style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap;align-items:center"><?= csrf_field() ?>
                                <input type="hidden" name="pay_po" value="<?= (int) $o['id'] ?>">
                                <input type="number" step="0.01" min="0.01" name="payment_amount" placeholder="<?= current_locale() === 'am' ? 'መጠን' : 'Amount' ?>" max="<?= $remaining ?>" required style="width:85px;padding:3px 6px;font-size:12px">
                                <select name="payment_method" style="width:85px;padding:3px 4px;font-size:12px">
                                    <option value="bank"><?= current_locale() === 'am' ? 'ባንክ' : 'Bank' ?></option>
                                    <option value="cash"><?= current_locale() === 'am' ? 'ጥሬ ገንዘብ' : 'Cash' ?></option>
                                    <option value="check"><?= current_locale() === 'am' ? 'ቼክ' : 'Cheque' ?></option>
                                </select>
                                <button class="btn secondary" type="submit" style="padding:3px 8px;font-size:11px">💳 <?= current_locale() === 'am' ? 'ክፈል' : 'Pay' ?></button>
                            </form>
                            <span class="text-muted" style="font-size:11px"><?= current_locale() === 'am' ? 'ያልተከፈለ' : 'Unpaid' ?>: <?= number_format($remaining, 2) ?> <?= h(t('common.etb', 'ETB')) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="text-muted"><?= h(t('common.no_records', 'No purchase orders found.')) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
