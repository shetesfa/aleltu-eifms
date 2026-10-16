<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);
require_once __DIR__ . '/../../includes/header.php';

$data = finance_receivables_summary();
$revenue = finance_revenue_vs_collected();

// By Customer / Destination Summary (ዱቤ የደንበኛ እንጂ የአሽከርካሪ አይደለም)
$byCustomer = db()->query(
    "SELECT destination_pos_location AS customer_name,
            COUNT(*) AS dispatch_count,
            COALESCE(SUM(total_price), 0) AS total_value,
            COALESCE(SUM(amount_paid), 0) AS paid,
            COALESCE(SUM(total_price - amount_paid), 0) AS outstanding
     FROM dist_dispatches
     WHERE payment_status != 'paid'
     GROUP BY destination_pos_location
     ORDER BY outstanding DESC"
)->fetchAll();

// Full list of unpaid dispatches
$dispatches = db()->query(
    "SELECT d.*, v.plate_number, i.name AS item_name, i.unit, u.name AS created_by_name
     FROM dist_dispatches d
     JOIN dist_vehicles v ON v.id = d.vehicle_id
     JOIN wh_stock_items i ON i.id = d.stock_item_id
     JOIN users u ON u.id = d.created_by
     WHERE d.payment_status != 'paid'
     ORDER BY (d.total_price - d.amount_paid) DESC, d.created_at DESC"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            👥 <?= current_locale()==='am' ? 'ተሰብሳቢ ክፍያዎች — ደንበኞች ሊከፍሉን ያለ ገንዘብ' : 'Customer Receivables — Outstanding Customer Balances' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale()==='am' ? 'ዕቃ ተልኳል ገና ያልተከፈለ የደንበኞች ዱቤ — ገቢ በሽያጭ እንጂ በካዝና ገና ያልገባ' : 'Goods dispatched but payment not yet fully collected from customers.' ?>
        </p>
    </div>
    <a href="/aleltu-eifms/modules/finance/dashboard.php" class="btn secondary">← <?= current_locale()==='am'?'ወደ ፋይናንስ ማዕከል':'Finance Dashboard' ?></a>
</div>

<!-- Summary KPIs -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi">
        <div class="label"><?= current_locale()==='am'?'ጠቅላላ የሽያጭ ዋጋ':'Total Revenue (Dispatched)' ?></div>
        <div class="value"><?= number_format((float)$revenue['total_revenue'], 2) ?> ETB</div>
    </div>
    <div class="kpi">
        <div class="label" style="color:#2e7d32">💵 <?= current_locale()==='am'?'የተሰበሰበ ገቢ':'Collected Cash' ?></div>
        <div class="value" style="color:#2e7d32"><?= number_format((float)$revenue['cash_collected'], 2) ?> ETB</div>
    </div>
    <div class="kpi" style="border-left:4px solid #c62828">
        <div class="label" style="color:#c62828">⏳ <?= current_locale()==='am'?'ያልተሰበሰበ ቀሪ ዱቤ':'Total Outstanding' ?></div>
        <div class="value" style="color:#c62828;font-size:22px"><strong><?= number_format($data['total_receivable'], 2) ?> ETB</strong></div>
    </div>
</div>

<!-- By Customer Summary -->
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">🏪 <?= current_locale()==='am'?'ያልተከፈለ ዱቤ በደንበኛ / ሽያጭ መዳረሻ':'Outstanding Balance by Customer / Location' ?></h3>
    <?php if (empty($byCustomer)): ?>
        <p class="text-muted">✅ <?= current_locale()==='am'?'ሁሉም ደንበኞች ሙሉ በሙሉ ከፍለዋል!':'All customer accounts are settled.' ?></p>
    <?php else: ?>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale()==='am'?'ደንበኛ / መዳረሻ':'Customer / Destination' ?></th>
                    <th><?= current_locale()==='am'?'ያልተጠናቀቁ ትዕዛዞች':'Pending Dispatches' ?></th>
                    <th><?= current_locale()==='am'?'ጠቅላላ ዋጋ':'Total Value' ?></th>
                    <th><?= current_locale()==='am'?'የተከፈለ':'Paid' ?></th>
                    <th style="color:#c62828"><?= current_locale()==='am'?'ያልተከፈለ ቀሪ ዕዳ':'Outstanding Owed' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($byCustomer as $cust): ?>
                <tr>
                    <td><strong>🏪 <?= h($cust['customer_name'] ?: (current_locale()==='am'?'ያልተገለጸ ደንበኛ':'Unknown')) ?></strong></td>
                    <td><?= (int)$cust['dispatch_count'] ?> <?= current_locale()==='am'?'ትዕዛዝ':'orders' ?></td>
                    <td><?= number_format((float)$cust['total_value'], 2) ?> ETB</td>
                    <td style="color:#2e7d32"><?= number_format((float)$cust['paid'], 2) ?> ETB</td>
                    <td style="color:#c62828;font-weight:700;font-size:15px"><?= number_format((float)$cust['outstanding'], 2) ?> ETB</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Dispatch-level detail -->
<div class="card">
    <h3 style="margin-top:0">📋 <?= current_locale()==='am'?'ያልተከፈሉ ነጠላ ትዕዛዞች ዝርዝር':'All Unpaid Dispatches (Drill-Down)' ?></h3>
    <?php if (empty($dispatches)): ?>
        <p class="text-muted">✅ <?= current_locale()==='am'?'ሁሉም ክፍያዎች ተሰብስበዋል!':'All dispatches are fully paid.' ?></p>
    <?php else: ?>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= current_locale()==='am'?'ቀን (ኢትዮጵያ)':'Date (Eth)' ?></th>
                    <th><?= current_locale()==='am'?'ደንበኛ / ተሽከርካሪ':'Customer / Transport' ?></th>
                    <th><?= current_locale()==='am'?'ምርት':'Product' ?></th>
                    <th><?= current_locale()==='am'?'ጠቅላላ ዋጋ':'Total Value' ?></th>
                    <th><?= current_locale()==='am'?'የተከፈለ':'Paid' ?></th>
                    <th style="color:#c62828"><?= current_locale()==='am'?'ቀሪ ዕዳ':'Owed' ?></th>
                    <th><?= current_locale()==='am'?'ሁኔታ':'Status' ?></th>
                    <th><?= current_locale()==='am'?'እርምጃ':'Action' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dispatches as $d):
                    $owed = max(0, (float)$d['total_price'] - (float)$d['amount_paid']);
                    $stBadge = $d['payment_status'] === 'partial' ? 'dry' : 'sold';
                    $stLabel = $d['payment_status'] === 'partial' ? (current_locale()==='am'?'ከፊል የተከፈለ':'Partial') : (current_locale()==='am'?'ያልተከፈለ':'Unpaid');
                ?>
                <tr>
                    <td><code>#<?= (int)$d['id'] ?></code></td>
                    <td style="font-size:13px;white-space:nowrap">
                        📅 <?= eth_date($d['created_at']) ?><br>
                        <small class="text-muted">⏰ <?= eth_time($d['created_at']) ?></small>
                    </td>
                    <td>
                        <strong>🏪 <?= h($d['destination_pos_location']) ?></strong><br>
                        <small class="text-muted">🚚 <?= h($d['driver_name'] ?? '—') ?> (<?= h($d['plate_number']) ?>)</small>
                    </td>
                    <td>
                        <strong><?= h($d['item_name']) ?></strong> — <?= number_format($d['quantity_dispatched'], 1) ?> <?= h($d['unit']) ?><br>
                        <small class="text-muted">@ <?= number_format($d['unit_price'], 2) ?> ETB</small>
                    </td>
                    <td><?= number_format((float)$d['total_price'], 2) ?> ETB</td>
                    <td style="color:#2e7d32"><?= number_format((float)$d['amount_paid'], 2) ?> ETB</td>
                    <td style="color:#c62828;font-weight:700"><?= number_format($owed, 2) ?> ETB</td>
                    <td><span class="badge <?= $stBadge ?>"><?= h($stLabel) ?></span></td>
                    <td>
                        <a href="/aleltu-eifms/modules/distribution/dispatches.php" class="btn secondary" style="padding:4px 10px;font-size:12px;white-space:nowrap">
                            💰 <?= current_locale()==='am'?'ክፍያ ተቀበል':'Receive Payment' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

