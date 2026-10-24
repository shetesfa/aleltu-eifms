<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'finance_officer']);
require_once __DIR__ . '/../../includes/header.php';

$data = finance_payables_summary();

// Full list of unpaid / partial POs
$pos = db()->query(
    "SELECT po.*, i.name AS item_name, i.unit, u.name AS created_by_name
     FROM fin_purchase_orders po
     JOIN wh_stock_items i ON i.id = po.stock_item_id
     JOIN users u ON u.id = po.created_by
     WHERE po.payment_status != 'paid' AND po.status NOT IN ('draft','cancelled')
     ORDER BY (po.total_amount - po.amount_paid) DESC, po.created_at DESC"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            ?? <?= current_locale()==='am' ? '???? ???? — ??????? ???????' : 'Supplier Payables — Outstanding Purchase Order Balances' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale()==='am' ? '???? ?? ????? ?? ?? ??????' : 'Approved purchase orders with outstanding balances.' ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">? <?= current_locale()==='am'?'?? ?????':'Finance Dashboard' ?></a>
</div>

<!-- Total KPI -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi" style="border-left:4px solid #c62828">
        <div class="label" style="color:#c62828">?? <?= current_locale()==='am'?'???? ??????? ?? ??':'Total Outstanding to Suppliers' ?></div>
        <div class="value" style="color:#c62828;font-size:22px"><strong><?= number_format($data['total_payable'],0) ?> ETB</strong></div>
    </div>
</div>

<!-- By Supplier -->
<?php if (!empty($data['by_supplier'])): ?>
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">?? <?= current_locale()==='am'?'?????':'By Supplier' ?></h3>
    <table>
        <tr>
            <th><?= current_locale()==='am'?'????':'Supplier' ?></th>
            <th><?= current_locale()==='am'?'?????':'POs' ?></th>
            <th><?= current_locale()==='am'?'??. ????':'Total Ordered' ?></th>
            <th><?= current_locale()==='am'?'?????':'Paid' ?></th>
            <th style="color:#c62828"><?= current_locale()==='am'?'??':'Outstanding' ?></th>
        </tr>
        <?php foreach ($data['by_supplier'] as $s): ?>
        <tr>
            <td><strong><?= h($s['supplier_name']) ?></strong></td>
            <td><?= (int)$s['po_count'] ?></td>
            <td><?= number_format((float)$s['total_ordered'],0) ?> ETB</td>
            <td style="color:#2e7d32"><?= number_format((float)$s['paid'],0) ?> ETB</td>
            <td style="color:#c62828;font-weight:700"><?= number_format((float)$s['outstanding'],0) ?> ETB</td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<!-- PO Detail -->
<div class="card">
    <h3 style="margin-top:0">?? <?= current_locale()==='am'?'??? ?????? ?? ?????':'All Unpaid Purchase Orders (Drill-Down)' ?></h3>
    <?php if (empty($pos)): ?>
        <p class="text-muted">? <?= current_locale()==='am'?'??? ???? ??????!':'All supplier payables are settled.' ?></p>
    <?php else: ?>
    <table>
        <tr>
            <th>PO #</th>
            <th><?= current_locale()==='am'?'????':'Supplier' ?></th>
            <th><?= current_locale()==='am'?'???':'Item' ?></th>
            <th><?= current_locale()==='am'?'??. ????':'Total Ordered' ?></th>
            <th><?= current_locale()==='am'?'?????':'Paid' ?></th>
            <th style="color:#c62828"><?= current_locale()==='am'?'??':'Owed' ?></th>
            <th><?= current_locale()==='am'?'???':'Status' ?></th>
            <th></th>
        </tr>
        <?php foreach ($pos as $po):
            $owed = max(0, (float)$po['total_amount'] - (float)$po['amount_paid']);
        ?>
        <tr>
            <td><code>#<?= (int)$po['id'] ?></code></td>
            <td><strong><?= h($po['supplier_name']) ?></strong></td>
            <td><?= h($po['item_name']) ?><br><small class="text-muted"><?= number_format($po['quantity'],2) ?> <?= h($po['unit']) ?> @ <?= number_format($po['unit_price'],2) ?></small></td>
            <td><?= number_format((float)$po['total_amount'],2) ?> ETB</td>
            <td style="color:#2e7d32"><?= number_format((float)$po['amount_paid'],2) ?> ETB</td>
            <td style="color:#c62828;font-weight:700"><?= number_format($owed,2) ?> ETB</td>
            <td><span class="badge <?= $po['payment_status']==='partial'?'dry':'sold' ?>"><?= h(ucfirst($po['payment_status'] ?? 'unpaid')) ?></span></td>
            <td>
                <a href="<?= BASE_URL ?>/modules/finance/purchasing.php" class="btn secondary" style="padding:3px 8px;font-size:12px">
                    ?? <?= current_locale()==='am'?'???':'Pay' ?>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
