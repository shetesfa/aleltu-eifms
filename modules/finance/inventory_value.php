<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'finance_officer', 'warehouse_keeper']);
require_once __DIR__ . '/../../includes/header.php';

$data = finance_inventory_value();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            ?? <?= current_locale()==='am' ? '??? ???? ?? (Inventory Valuation)' : 'Inventory Valuation — Current Stock Value' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale()==='am'
                ? '???? = (?? ???? × ??? ??) — ?? ?? ??? ? PO ?? ????'
                : 'Total = quantity on hand × unit cost at time of receipt. Unit cost fills when goods are received via approved Purchase Orders.' ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">? <?= current_locale()==='am'?'?? ?????':'Finance Dashboard' ?></a>
</div>

<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi" style="border-left:4px solid #1565c0">
        <div class="label" style="color:#1565c0">?? <?= current_locale()==='am'?'???? ??? ??':'Total Inventory Value' ?></div>
        <div class="value" style="color:#1565c0;font-size:22px"><strong><?= number_format($data['total_inventory_value'],0) ?> ETB</strong></div>
        <?php if ($data['total_inventory_value']==0): ?>
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
            <?= current_locale()==='am'?'?? ? PO ???? unit_cost ????':'Unit cost populates when goods are received via Purchase Orders' ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0">?? <?= current_locale()==='am'?'??? ????':'By Stock Item' ?></h3>
    <?php if (empty($data['by_item'])): ?>
        <p class="text-muted"><?= current_locale()==='am'?'???? ?????????':'No stock on hand.' ?></p>
    <?php else: ?>
    <table>
        <tr>
            <th><?= current_locale()==='am'?'??':'Item' ?></th>
            <th><?= current_locale()==='am'?'???':'Partition' ?></th>
            <th><?= current_locale()==='am'?'????':'Qty on Hand' ?></th>
            <th><?= current_locale()==='am'?'???? ??':'Avg Unit Cost' ?></th>
            <th><?= current_locale()==='am'?'??. ??':'Total Value' ?></th>
        </tr>
        <?php foreach ($data['by_item'] as $row): ?>
        <tr>
            <td><strong><?= h($row['name']) ?></strong></td>
            <td><span class="badge"><?= h(stock_partition_label($row['partition_code'] ?? '')) ?></span></td>
            <td><?= number_format($row['qty'],2) ?> <?= h($row['unit']) ?></td>
            <td><?= $row['avg_cost']>0 ? number_format((float)$row['avg_cost'],2).' ETB' : '<em class="text-muted">—</em>' ?></td>
            <td style="font-weight:700;color:#1565c0"><?= number_format((float)$row['total_value'],0) ?> ETB</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
