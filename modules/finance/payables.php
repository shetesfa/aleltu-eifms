<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

$currentUser = current_user();

// Handle Payment to Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['record_supplier_payment'])) {
            $poId = (int)$_POST['purchase_order_id'];
            $amount = (float)$_POST['payment_amount'];
            $bankAccountId = (int)$_POST['bank_account_id'];
            $refNo = trim($_POST['reference_no'] ?? '');
            $notes = trim($_POST['payment_notes'] ?? '');

            if ($amount <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የክፍያው መጠን ከ 0 በላይ መሆን አለበት።' : 'Payment amount must be greater than zero.');
            }
            if ($bankAccountId <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ የተከፈለበትን የባንክ አካውንት ይምረጡ።' : 'Please select bank account.');
            }

            // Get PO
            $po = db()->prepare("SELECT * FROM fin_purchase_orders WHERE id = ?");
            $po->execute([$poId]);
            $p = $po->fetch();
            if (!$p) {
                throw new RuntimeException(current_locale() === 'am' ? 'የግዢ ትዕዛዙ አልተገኘም።' : 'Purchase order not found.');
            }

            $remaining = max(0, (float)$p['total_amount'] - (float)$p['amount_paid']);
            if ($amount > $remaining + 0.01) {
                throw new RuntimeException(current_locale() === 'am' ? "የክፍያው መጠን ከቀረው ዕዳ ({$remaining} ብር) በላይ መሆን አይችልም።" : "Payment exceeds remaining balance.");
            }

            $newPaid = (float)$p['amount_paid'] + $amount;
            $newStatus = ($newPaid >= (float)$p['total_amount'] - 0.01) ? 'paid' : 'partial';

            // Bank details
            $bank = db()->prepare("SELECT * FROM fin_bank_accounts WHERE id = ?");
            $bank->execute([$bankAccountId]);
            $b = $bank->fetch();
            $method = ($b && $b['account_type'] === 'cash') ? 'cash' : 'bank';

            $ownsTx = false;
            if (!db()->inTransaction()) {
                db()->beginTransaction();
                $ownsTx = true;
            }

            // Update PO
            db()->prepare("UPDATE fin_purchase_orders SET amount_paid = ?, payment_status = ? WHERE id = ?")
                ->execute([$newPaid, $newStatus, $poId]);

            // Record transaction outflow in finance
            finance_record_transaction(
                'outflow',
                $amount,
                'purchasing',
                'supplier_payment',
                date('Y-m-d'),
                "ለአቅራቢ የተፈጸመ ክፍያ (PO #{$poId} - {$p['supplier_name']})",
                'fin_purchase_orders',
                $poId,
                null,
                $method,
                $bankAccountId
            );

            if ($ownsTx && db()->inTransaction()) {
                db()->commit();
            }

            log_activity('finance', 'create', 'fin_purchase_orders', $poId, "Recorded {$amount} ETB payment to supplier {$p['supplier_name']} for PO #{$poId}");
            flash(current_locale() === 'am' ? "✓ ለአቅራቢው {$amount} ብር ክፍያ በተሳካ ሁኔታ ተፈጽሞ ከባንኩ ተቀንሷል!" : "Payment recorded successfully.");
            header('Location: ' . BASE_URL . '/modules/finance/payables.php');
            exit;
        }
    } catch (Throwable $e) {
        if (isset($ownsTx) && $ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        flash(current_locale() === 'am' ? 'ስህተት፦ ' . $e->getMessage() : 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$data = finance_payables_summary();
$bankAccounts = db()->query("SELECT * FROM fin_bank_accounts ORDER BY id ASC")->fetchAll();

// Full list of unpaid / partial POs
$pos = db()->query(
    "SELECT po.*, i.name AS item_name, i.unit, u.name AS created_by_name, r.name AS created_by_role
     FROM fin_purchase_orders po
     JOIN wh_stock_items i ON i.id = po.stock_item_id
     JOIN users u ON u.id = po.created_by
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE po.payment_status != 'paid' AND po.status NOT IN ('draft','cancelled')
     ORDER BY (po.total_amount - po.amount_paid) DESC, po.created_at DESC"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            📋 <?= current_locale() === 'am' ? 'የአቅራቢዎች ዕዳ መከታተያ እና ተከፋይ ሂሳቦች (Accounts Payable)' : 'Supplier Payables & Outstanding Balances' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'ለመኖ፣ ለመድሃኒትና ለጥሬ ዕቃ አቅራቢዎች የሚፈለጉ ያልተከፈሉ የግዢ ሂሳቦች እና የክፍያ ማስተዳደሪያ ማዕከል።' : 'Approved purchase orders with outstanding balances owed to suppliers.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/finance/purchasing.php" class="btn secondary">🛒 <?= current_locale() === 'am' ? 'ወደ ግዢዎች ዝርዝር' : 'Purchasing' ?></a>
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">📊 <?= current_locale() === 'am' ? 'የፋይናንስ ዳሽቦርድ' : 'Finance Dashboard' ?></a>
    </div>
</div>

<!-- Helpful Educational Box -->
<div class="card" style="background:#e8f5e9;border-left:4px solid #2e7d32;color:#1b5e20;padding:12px 16px;margin-bottom:20px">
    <strong>💡 ይህ ገጽ ለምን ይጠቅማል? ከሲስተሙ ጋር እንዴት ይገናኛል?</strong>
    <p style="margin:4px 0 0 0;font-size:13px;color:#2e7d32;line-height:1.5">
        ይህ ገጽ በፋይናንስ <strong>"ተከፋይ ሂሳቦች" (Accounts Payable)</strong> ይባላል። ድርጅታችን መኖ፣ መድሃኒት፣ የእንስሳት ክትባትና መለዋወጫዎችን በዱቤ ወይም በከፊል ክፍያ ከአቅራቢዎች ሲገዛ፣ ለማን አቅራቢ ምን ያህል ዕዳ እንዳለብን፣ በምን ግዥ እንደተፈጠረ እና መቼ መከፈል እንዳለበት ያሳያል። እዚህ የሚፈጸም ክፍያ በቀጥታ ከተመረጠው የድርጅቱ ባንክ ሂሳብ ላይ ይቀነሳል።
    </p>
</div>

<!-- Total KPI -->
<div class="kpi-row" style="margin-bottom:20px;display:flex;gap:16px;flex-wrap:wrap">
    <div class="kpi card" style="border-left:5px solid #c62828;flex:1;min-width:240px">
        <div class="label" style="color:#c62828;font-size:13px">🔴 <?= current_locale() === 'am' ? 'ጠቅላላ ለአቅራቢዎች የሚፈለግ ዕዳ' : 'Total Outstanding to Suppliers' ?></div>
        <div class="value" style="color:#c62828;font-size:24px;font-weight:800;margin-top:4px">
            <?= number_format($data['total_payable'], 2) ?> ETB
        </div>
    </div>
    <div class="kpi card" style="border-left:5px solid #0288d1;flex:1;min-width:240px">
        <div class="label" style="color:#0288d1;font-size:13px">🏢 <?= current_locale() === 'am' ? 'ዕዳ ያለባቸው አቅራቢዎች ብዛት' : 'Suppliers with Pending Balances' ?></div>
        <div class="value" style="color:#0288d1;font-size:24px;font-weight:800;margin-top:4px">
            <?= count($data['by_supplier']) ?> አቅራቢዎች
        </div>
    </div>
</div>

<!-- By Supplier Summary -->
<div class="card" style="margin-bottom:24px">
    <h3 style="margin-top:0">🏢 <?= current_locale() === 'am' ? 'በአቅራቢዎች የተደራጀ የዕዳ ማጠቃለያ' : 'Outstanding Balances by Supplier' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>የአቅራቢው ስም</th>
                    <th>ያልተከፈሉ ትዕዛዞች ብዛት</th>
                    <th>ጠቅላላ የግዢ ዋጋ</th>
                    <th>እስካሁን የተከፈለ</th>
                    <th>ለአቅራቢው የሚፈለግ ቀሪ ዕዳ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['by_supplier'] as $sup): ?>
                    <tr>
                        <td><strong>🏢 <?= h($sup['supplier_name']) ?></strong></td>
                        <td><?= (int)($sup['po_count'] ?? 0) ?> ትዕዛዞች</td>
                        <td><?= number_format((float)($sup['total_ordered'] ?? 0), 2) ?> ብር</td>
                        <td style="color:#2e7d32"><?= number_format((float)($sup['paid'] ?? 0), 2) ?> ብር</td>
                        <td style="color:#c62828;font-weight:800;font-size:15px">
                            <?= number_format((float)($sup['outstanding'] ?? 0), 2) ?> ብር
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($data['by_supplier'])): ?>
                    <tr><td colspan="5" class="text-muted" style="text-align:center">ምንም አይነት ያልተከፈለ የአቅራቢ ዕዳ የለም! ሁሉም ተከፍሏል።</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detailed Unpaid Purchase Orders -->
<div class="card">
    <h3 style="margin-top:0">📝 <?= current_locale() === 'am' ? 'ያልተከፈሉ የግዢ ትዕዛዞች ዝርዝር (Unpaid Purchase Orders)' : 'Unpaid Purchase Orders' ?> (<?= count($pos) ?>)</h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>የግዢ ትዕዛዝ #</th>
                    <th>ቀንና ሰዓት</th>
                    <th>አቅራቢ</th>
                    <th>የተገዛው ዕቃ</th>
                    <th>መጠን</th>
                    <th>ጠቅላላ ዋጋ</th>
                    <th>የተከፈለ</th>
                    <th>ቀሪ ዕዳ (Remaining)</th>
                    <th>ያዘጋጀው ሠራተኛ</th>
                    <th>እርምጃ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pos as $p): 
                    $rem = max(0, (float)$p['total_amount'] - (float)$p['amount_paid']);
                ?>
                    <tr>
                        <td><strong>PO-<?= (int)$p['id'] ?></strong></td>
                        <td style="font-size:12px;white-space:nowrap">
                            📅 <?= eth_date($p['created_at']) ?><br>
                            <small class="text-muted">⏰ <?= eth_time($p['created_at']) ?></small>
                        </td>
                        <td><strong><?= h($p['supplier_name']) ?></strong></td>
                        <td><?= h($p['item_name']) ?></td>
                        <td><?= number_format($p['quantity'], 2) ?> <?= h($p['unit']) ?></td>
                        <td><strong><?= number_format($p['total_amount'], 2) ?> ብር</strong></td>
                        <td style="color:#2e7d32"><?= number_format($p['amount_paid'], 2) ?> ብር</td>
                        <td style="color:#c62828;font-weight:800;font-size:15px"><?= number_format($rem, 2) ?> ብር</td>
                        <td>
                            👤 <?= h($p['created_by_name']) ?><br>
                            <small class="text-muted"><?= h($p['created_by_role']) ?></small>
                        </td>
                        <td>
                            <button type="button" class="btn success" style="padding:6px 12px;font-size:12px" onclick="openSupplierPayModal(<?= (int)$p['id'] ?>, <?= $rem ?>, '<?= h($p['supplier_name']) ?>', '<?= h($p['item_name']) ?>')">
                                💳 ክፍያ ፈጽም
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pos)): ?>
                    <tr><td colspan="10" class="text-muted" style="text-align:center">ምንም አይነት ያልተከፈለ የግዢ ትዕዛዝ የለም።</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Supplier Payment Modal -->
<div id="supplierPayModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;max-width:500px;width:90%;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.3)">
        <h3 style="margin-top:0;color:#c62828">💳 ለአቅራቢ ክፍያ መፈጸሚያ</h3>
        <p class="text-muted" style="font-size:13px;margin-bottom:14px">
            አቅራቢ፡ <strong id="modalSupName"></strong> | ዕቃ፡ <strong id="modalSupItem"></strong>
        </p>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="record_supplier_payment" value="1">
            <input type="hidden" name="purchase_order_id" id="modalPoId" value="">

            <div class="form-group">
                <label>የሚከፈለው ገንዘብ መጠን በብር *</label>
                <input type="number" step="0.01" min="0.01" name="payment_amount" id="modalSupAmount" required style="font-size:18px;font-weight:700;color:#c62828">
                <small class="text-muted">ቀሪ ዕዳ፡ <strong id="modalSupRemaining">0.00</strong> ETB</small>
            </div>

            <div class="form-group">
                <label>ክፍያው የሚወጣበት የድርጅቱ ባንክ / ካዝና *</label>
                <select name="bank_account_id" required style="width:100%;font-size:14px;padding:8px">
                    <option value="">— ባንክ / ካዝና ይምረጡ —</option>
                    <?php foreach ($bankAccounts as $b): ?>
                        <option value="<?= (int)$b['id'] ?>">
                            🏦 <?= h($b['bank_name']) ?> — <?= h($b['account_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>የቼክ / የባንክ ማመሳከሪያ ቁጥር (Ref / Cheque No)</label>
                <input type="text" name="reference_no" placeholder="ለምሳሌ፡ CHQ-9901...">
            </div>

            <div class="form-group">
                <label>ተጨማሪ ማስታወሻ</label>
                <input type="text" name="payment_notes" placeholder="ለምሳሌ፡ ለመኖ አቅራቢ የተከፈለ...">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
                <button type="button" class="btn secondary" onclick="closeSupplierPayModal()">ተመለስ</button>
                <button type="submit" class="btn success" style="font-weight:700">✓ ክፍያውን ፈጽም</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSupplierPayModal(poId, remaining, supName, itemName) {
    document.getElementById('modalPoId').value = poId;
    document.getElementById('modalSupName').innerText = supName;
    document.getElementById('modalSupItem').innerText = itemName;
    document.getElementById('modalSupAmount').value = remaining.toFixed(2);
    document.getElementById('modalSupAmount').max = remaining.toFixed(2);
    document.getElementById('modalSupRemaining').innerText = remaining.toFixed(2);
    document.getElementById('supplierPayModal').style.display = 'flex';
}

function closeSupplierPayModal() {
    document.getElementById('supplierPayModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
