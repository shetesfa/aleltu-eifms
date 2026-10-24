<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['decide_id'])) {
            require_permission('finance', 'approve');
            if ($_POST['decision'] === 'approve') {
                expense_approve((int) $_POST['decide_id']);
                flash(current_locale() === 'am' ? '✓ ወጪው/ማውጣቱ ጸድቆ ወደ ካዝና መዝገብ ተመዝግቧል።' : 'Expense/Withdrawal approved and booked to cash flow.');
            } else {
                expense_reject((int) $_POST['decide_id']);
                flash(current_locale() === 'am' ? 'ወጪው/ማውጣቱ ውድቅ ተደርጓል።' : 'Expense/Withdrawal rejected.');
            }
        } else {
            require_permission('finance', 'create');
            $unit   = $_POST['unit_tag'];
            $amount = (float)$_POST['amount'];
            $newId  = expense_create($_POST);
            flash(current_locale() === 'am' ? '✓ ወጪው/ማውጣቱ በተሳካ ሁኔታ ተመዝግቧል፤ ለማጽደቅ ቀርቧል።' : 'Expense/Withdrawal submitted for approval.');
        }
        header('Location: ' . BASE_URL . '/modules/finance/expenses.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    require_once __DIR__ . '/../../includes/export.php';
    export_csv(
        db()->query(
            "SELECT e.expense_date, e.unit_tag, e.withdrawal_type, e.category, e.recipient_name, e.amount, b.bank_name, e.description, e.status 
             FROM fin_expenses e 
             LEFT JOIN fin_bank_accounts b ON b.id = e.bank_account_id 
             ORDER BY e.id DESC"
        )->fetchAll(),
        'expenses_withdrawals_' . date('Y-m-d') . '.csv'
    );
}

require_once __DIR__ . '/../../includes/header.php';

// Fetch the 3 accounts (Oromia Bank, Telebirr, Cash)
$bankAccounts = db()->query("SELECT id, bank_name, account_type, account_number FROM fin_bank_accounts ORDER BY id")->fetchAll();

// Withdrawal types definition
$withdrawalTypes = [
    'operating_expense' => [
        'label' => current_locale() === 'am' ? 'መደበኛ የስራ ማስኬጃ ወጪ' : 'Operating Expense',
        'badge' => 'badge-blue',
        'color' => '#1565c0',
        'icon'  => ''
    ],
    'holiday_gift' => [
        'label' => current_locale() === 'am' ? 'የበዓል ስጦታ (Holiday Gift)' : 'Holiday Gift',
        'badge' => 'badge-purple',
        'color' => '#7b1fa2',
        'icon'  => ''
    ],
    'admin_protocol' => [
        'label' => current_locale() === 'am' ? 'የመንግስት/ቀበሌ አስተዳደር ፕሮቶኮል' : 'Govt/Kebele Protocol',
        'badge' => 'badge-green',
        'color' => '#2e7d32',
        'icon'  => ''
    ],
    'food_withdrawal' => [
        'label' => current_locale() === 'am' ? 'የምግብና መስተንግዶ ፍጆታ' : 'Food & Hospitality',
        'badge' => 'badge-yellow',
        'color' => '#e65100',
        'icon'  => ''
    ],
    'owner_drawing' => [
        'label' => current_locale() === 'am' ? 'የባለቤት የግል ማውጣት' : 'Owner Drawing',
        'badge' => 'badge-orange',
        'color' => '#c2185b',
        'icon'  => ''
    ],
    'cash_withdrawal' => [
        'label' => current_locale() === 'am' ? 'አጠቃላይ የጥሬ ገንዘብ ማውጣት' : 'Cash Withdrawal',
        'badge' => 'badge-gray',
        'color' => '#455a64',
        'icon'  => ''
    ]
];

// Department / Unit Labels Definition
$unitLabels = [
    'dairy' => [
        'name' => current_locale() === 'am' ? 'የወተት እርሻ (Dairy)' : 'Dairy Farm',
        'icon' => ''
    ],
    'poultry' => [
        'name' => current_locale() === 'am' ? 'የዶሮና እንቁላል እርባታ (Poultry)' : 'Poultry & Eggs',
        'icon' => ''
    ],
    'fish' => [
        'name' => current_locale() === 'am' ? 'የዓሳ እርባታ (Fish)' : 'Fish Aquaculture',
        'icon' => ''
    ],
    'feed_production' => [
        'name' => current_locale() === 'am' ? 'የመኖ ምርትና እርሻ (Feed)' : 'Feed Production & Crops',
        'icon' => ''
    ],
    'warehouse' => [
        'name' => current_locale() === 'am' ? 'መጋዘንና ግዢ (Warehouse)' : 'Warehouse & Inventory',
        'icon' => ''
    ],
    'distribution' => [
        'name' => current_locale() === 'am' ? 'ስርጭትና መዳረሻ ሱቆች (Distribution)' : 'Distribution & Logistics',
        'icon' => ''
    ],
    'veterinary' => [
        'name' => current_locale() === 'am' ? 'የእንስሳት ህክምና (Veterinary)' : 'Veterinary Clinic',
        'icon' => ''
    ],
    'general_overhead' => [
        'name' => current_locale() === 'am' ? 'አጠቃላይ አስተዳደርና ደመወዝ (General Overhead)' : 'General Overhead & Admin',
        'icon' => ''
    ],
];

// Stats
$stats = db()->query(
    "SELECT 
        COALESCE(SUM(amount), 0) AS total_all,
        COALESCE(SUM(CASE WHEN withdrawal_type = 'operating_expense' THEN amount ELSE 0 END), 0) AS total_operating,
        COALESCE(SUM(CASE WHEN withdrawal_type IN ('holiday_gift', 'admin_protocol') THEN amount ELSE 0 END), 0) AS total_gifts,
        COALESCE(SUM(CASE WHEN withdrawal_type IN ('food_withdrawal', 'owner_drawing', 'cash_withdrawal') THEN amount ELSE 0 END), 0) AS total_special
     FROM fin_expenses WHERE status = 'approved'"
)->fetch();

$filterType = $_GET['filter_type'] ?? '';
$unitFilter = $_GET['unit'] ?? '';
$where = '1=1';
if ($filterType === 'operating') {
    $where .= " AND e.withdrawal_type = 'operating_expense'";
} elseif ($filterType === 'gifts') {
    $where .= " AND e.withdrawal_type IN ('holiday_gift', 'admin_protocol')";
} elseif ($filterType === 'special') {
    $where .= " AND e.withdrawal_type IN ('food_withdrawal', 'owner_drawing', 'cash_withdrawal')";
}
if (!empty($unitFilter)) {
    $where .= " AND e.unit_tag = " . db()->quote($unitFilter);
}

$expenses = db()->query(
    "SELECT e.*, b.bank_name, b.account_type, u.name AS requester_name
     FROM fin_expenses e
     LEFT JOIN fin_bank_accounts b ON b.id = e.bank_account_id
     LEFT JOIN users u ON u.id = e.requested_by
     WHERE {$where}
     ORDER BY e.expense_date DESC, e.id DESC LIMIT 60"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            💸 <?= current_locale() === 'am' ? 'ዕለታዊ ወጪዎችና ልዩ ጥሬ ገንዘብ ማውጫ ማዕከል' : 'Expenses & Cash Withdrawals Center' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' 
                ? 'መደበኛ የስራ ማስኬጃ ወጪዎች፣ የበዓል ስጦታዎች፣ የአስተዳደር ፕሮቶኮሎች፣ የምግብ ወጪዎችና የባለቤት ማውጫዎች መዝገብ።' 
                : 'Manage operating costs, holiday gifts, admin protocol, food hospitality, and personal drawings.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="?export=csv" class="btn secondary">⬇️ <?= h(t('common.export_csv', 'Export to CSV')) ?></a>
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">← <?= current_locale()==='am'?'ወደ ዳሽቦርድ':'Finance Dashboard' ?></a>
    </div>
</div>

<!-- KPI SUMMARY CARDS -->
<div class="kpi-row" style="margin-bottom:22px">
    <div class="kpi" style="border-left:4px solid var(--primary)">
        <div class="label"><?= current_locale()==='am'?'ጠቅላላ የጸደቀ ወጪና ማውጣት':'Total Approved Outflows' ?></div>
        <div class="value" style="color:var(--primary)"><?= number_format($stats['total_all'], 0) ?> ETB</div>
    </div>
    <div class="kpi" style="border-left:4px solid #1565c0">
        <div class="label"><?= current_locale()==='am'?'መደበኛ የስራ ማስኬጃ ወጪ':'Operating Costs' ?></div>
        <div class="value" style="color:#1565c0"><?= number_format($stats['total_operating'], 0) ?> ETB</div>
    </div>
    <div class="kpi" style="border-left:4px solid #7b1fa2">
        <div class="label"><?= current_locale()==='am'?'የበዓል ስጦታና አስተዳደር ፕሮቶኮል':'Holiday Gifts & Protocol' ?></div>
        <div class="value" style="color:#7b1fa2"><?= number_format($stats['total_gifts'], 0) ?> ETB</div>
    </div>
    <div class="kpi" style="border-left:4px solid #e65100">
        <div class="label"><?= current_locale()==='am'?'የምግብ፣ መስተንግዶና ልዩ ማውጫ':'Food & Special Withdrawals' ?></div>
        <div class="value" style="color:#e65100"><?= number_format($stats['total_special'], 0) ?> ETB</div>
    </div>
</div>

<!-- REGISTRATION FORM (COLLAPSIBLE / FULL-WIDTH MODERN CARD) -->
<div class="card" style="margin-bottom:24px;box-shadow:0 2px 8px rgba(0,0,0,0.06);border-top:3px solid var(--primary)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid var(--border);padding-bottom:10px">
        <h3 style="margin:0"><?= current_locale() === 'am' ? 'አዲስ ወጪ ወይም ጥሬ ገንዘብ ማውጫ መዝግብ' : 'Record Expense / Cash Withdrawal' ?></h3>
        <button type="button" class="btn secondary" onclick="toggleExpenseForm()" id="toggleFormBtn" style="padding:4px 12px;font-size:12px">
            <?= current_locale() === 'am' ? 'ቅጹን ሰብስብ / አሳይ' : 'Toggle Form' ?>
        </button>
    </div>
    <form method="post" id="expenseForm"><?= csrf_field() ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;margin-bottom:14px">
            
            <!-- WITHDRAWAL / EXPENSE TYPE -->
            <div class="form-group" style="margin:0">
                <label style="font-weight:700">
                    <?= current_locale() === 'am' ? 'የወጪው / የማውጣቱ አይነት *' : 'Transaction Type *' ?>
                </label>
                <select name="withdrawal_type" id="withdrawalTypeSelect" required onchange="handleTypeChange(this.value)" style="width:100%;font-weight:600;padding:8px">
                    <?php foreach ($withdrawalTypes as $k => $v): ?>
                        <option value="<?= h($k) ?>"><?= h($v['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- PAYMENT SOURCE (Oromia Bank, Telebirr, Cash Safe) -->
            <div class="form-group" style="margin:0">
                <label style="font-weight:700">
                    <?= current_locale() === 'am' ? 'የክፍያ/የማውጫ ምንጭ (አካውንት) *' : 'Payment Source *' ?>
                </label>
                <select name="bank_account_id" required style="width:100%;font-weight:600;padding:8px">
                    <?php foreach ($bankAccounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>">
                            <?= h($acc['bank_name']) ?> (<?= h($acc['account_number']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- RECIPIENT OR PURPOSE -->
            <div class="form-group" id="recipientGroup" style="margin:0">
                <label style="font-weight:700">
                    <?= current_locale() === 'am' ? 'ተቀባይ / የተሰጠበት አላማ' : 'Recipient / Specific Purpose' ?>
                </label>
                <input type="text" name="recipient_name" id="recipientInput" style="width:100%;padding:8px" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ለቀበሌ አስተዳዳሪ፣ ለበዓል ስጦታ...' : 'e.g. Kebele Admin, Staff holiday gift' ?>">
            </div>

            <!-- BUSINESS UNIT -->
            <div class="form-group" style="margin:0">
                <label style="font-weight:700">
                    <?= current_locale() === 'am' ? 'የስራ ክፍል * (Business Unit)' : 'Business Unit *' ?>
                </label>
                <select name="unit_tag" id="unitTagSelect" required style="width:100%;padding:9px;font-weight:700;font-size:13px;border:1px solid var(--border);border-radius:6px;background:var(--card)">
                    <?php foreach ($unitLabels as $uKey => $uMeta): ?>
                        <option value="<?= h($uKey) ?>" <?= $uKey==='dairy'?'selected':'' ?>><?= h($uMeta['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;margin-bottom:14px">
            <div class="form-group" style="margin:0">
                <label style="font-weight:700"><?= current_locale() === 'am' ? 'የወጪ ምድብ *' : 'Category *' ?></label>
                <input type="text" name="category" id="categoryInput" required placeholder="<?= current_locale() === 'am' ? 'የወጪ ምድብ' : 'Category' ?>" value="መደበኛ የስራ ማስኬጃ" style="width:100%;padding:8px">
            </div>

            <div class="form-group" style="margin:0">
                <label style="font-weight:700"><?= current_locale() === 'am' ? 'መጠን (በብር) *' : 'Amount (ETB) *' ?></label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" style="width:100%;padding:8px;font-size:16px;font-weight:700;color:var(--primary)">
            </div>

            <div class="form-group" style="margin:0">
                <label style="font-weight:700"><?= h(t('common.date', 'Date')) ?> *</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required style="width:100%;padding:8px">
            </div>

            <div class="form-group" style="margin:0">
                <label style="font-weight:700"><?= current_locale() === 'am' ? 'ዝርዝር ማብራሪያ' : 'Description' ?></label>
                <input type="text" name="description" placeholder="<?= current_locale() === 'am' ? 'ተጨማሪ ማብራሪያ ወይም ማስታወሻ...' : 'Additional notes...' ?>" style="width:100%;padding:8px">
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end">
            <button class="btn" type="submit" style="padding:9px 24px;font-size:14px;background:#2e7d32;border-color:#2e7d32">
                <?= current_locale() === 'am' ? 'መዝግብና ለማጽደቅ አቅርብ (Submit)' : 'Submit for Approval' ?>
            </button>
        </div>
    </form>
</div>

<script>
function toggleExpenseForm() {
    const f = document.getElementById('expenseForm');
    const btn = document.getElementById('toggleFormBtn');
    if (f.style.display === 'none') {
        f.style.display = 'block';
        btn.textContent = '<?= current_locale() === 'am' ? 'ቅጹን ሰብስብ' : 'Collapse Form' ?>';
    } else {
        f.style.display = 'none';
        btn.textContent = '<?= current_locale() === 'am' ? 'ቅጹን ክፈት' : 'Expand Form' ?>';
    }
}
</script>

<!-- EXPENSES & WITHDRAWALS TABLE (FULL WIDTH) -->
<div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.06);width:100%">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
            <h3 style="margin:0"><?= current_locale() === 'am' ? 'የቅርብ ጊዜ የወጪና የማውጣት መዝገቦች' : 'Recent Expenses & Withdrawals' ?></h3>
            
            <!-- Filter Tabs & Unit Filter -->
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                <form method="get" style="display:flex;gap:4px;margin:0;align-items:center">
                    <?php if ($filterType): ?><input type="hidden" name="filter_type" value="<?= h($filterType) ?>"><?php endif; ?>
                    <select name="unit" onchange="this.form.submit()" style="padding:5px 10px;font-size:12px;border-radius:4px;font-weight:600">
                        <option value=""><?= current_locale()==='am'?'— በስራ ክፍል አጣራ (ሁሉም) —':'Filter by Unit (All)' ?></option>
                        <?php foreach ($unitLabels as $uKey => $uMeta): ?>
                            <option value="<?= h($uKey) ?>" <?= $unitFilter===$uKey?'selected':'' ?>><?= h($uMeta['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a href="expenses.php<?= $unitFilter ? '?unit=' . urlencode($unitFilter) : '' ?>" class="btn secondary" style="padding:4px 8px;font-size:11.5px;<?= empty($filterType)?'background:var(--primary);color:white;':'' ?>">
                    <?= current_locale()==='am'?'ሁሉም':'All' ?>
                </a>
                <a href="expenses.php?filter_type=operating<?= $unitFilter ? '&unit=' . urlencode($unitFilter) : '' ?>" class="btn secondary" style="padding:4px 8px;font-size:11.5px;<?= $filterType==='operating'?'background:#1565c0;color:white;':'' ?>">
                    <?= current_locale()==='am'?'መደበኛ ወጪዎች':'Operating' ?>
                </a>
                <a href="expenses.php?filter_type=gifts<?= $unitFilter ? '&unit=' . urlencode($unitFilter) : '' ?>" class="btn secondary" style="padding:4px 8px;font-size:11.5px;<?= $filterType==='gifts'?'background:#7b1fa2;color:white;':'' ?>">
                    <?= current_locale()==='am'?'የበዓልና አስተዳደር ስጦታ':'Gifts & Protocol' ?>
                </a>
                <a href="expenses.php?filter_type=special<?= $unitFilter ? '&unit=' . urlencode($unitFilter) : '' ?>" class="btn secondary" style="padding:4px 8px;font-size:11.5px;<?= $filterType==='special'?'background:#e65100;color:white;':'' ?>">
                    <?= current_locale()==='am'?'ምግብና የግል ማውጣት':'Food & Drawings' ?>
                </a>
            </div>
        </div>

        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th><?= h(t('common.date', 'Date')) ?></th>
                        <th><?= current_locale() === 'am' ? 'አይነትና ተቀባይ / አላማ' : 'Type & Recipient' ?></th>
                        <th><?= current_locale() === 'am' ? 'የስራ ክፍል' : 'Unit' ?></th>
                        <th><?= current_locale() === 'am' ? 'የወጣበት ምንጭ' : 'Account' ?></th>
                        <th><?= current_locale() === 'am' ? 'መጠን (ብር)' : 'Amount' ?></th>
                        <th><?= h(t('common.status', 'Status')) ?></th>
                        <th><?= h(t('common.actions', 'Action')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): 
                        $typeMeta = $withdrawalTypes[$e['withdrawal_type'] ?? 'operating_expense'] ?? $withdrawalTypes['operating_expense'];
                        $accName = $e['bank_name'] ?: (current_locale() === 'am' ? 'ጥሬ ገንዘብ / ባንክ' : 'Cash / Bank');

                        $stText = match($e['status']) {
                            'approved' => (current_locale() === 'am' ? 'ጸድቋል' : 'Approved'),
                            'rejected' => (current_locale() === 'am' ? 'ውድቅ ተደርጓል' : 'Rejected'),
                            default => (current_locale() === 'am' ? 'በመጠባበቅ ላይ' : 'Pending')
                        };
                    ?>
                        <tr>
                            <td style="white-space:nowrap;font-weight:600">
                                <?= h(eth_date($e['expense_date'])) ?>
                            </td>
                            <td>
                                <span class="badge" style="background:<?= $typeMeta['color'] ?>15;color:<?= $typeMeta['color'] ?>;border:1px solid <?= $typeMeta['color'] ?>40">
                                    <?= h($typeMeta['label']) ?>
                                </span>
                                <?php if (!empty($e['recipient_name'])): ?>
                                    <div style="font-size:12px;font-weight:700;color:#0f172a;margin-top:2px">
                                        <?= h($e['recipient_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted" style="display:block"><?= h($e['category']) ?></small>
                                <?php if (!empty($e['description'])): ?>
                                    <small style="color:#64748b;font-style:italic"><?= h($e['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="font-size:11.5px;padding:3px 8px;font-weight:600">
                                    <?= h($unitLabels[$e['unit_tag']]['name'] ?? $e['unit_tag']) ?>
                                </span>
                            </td>
                            <td>
                                <small style="font-weight:600"><?= h($accName) ?></small>
                            </td>
                            <td>
                                <strong style="font-size:14px;color:#c62828"><?= number_format($e['amount'], 2) ?> ETB</strong>
                            </td>
                            <td>
                                <span class="badge <?= $e['status']==='approved'?'active':($e['status']==='rejected'?'sold':'dry') ?>">
                                    <?= h($stText) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($e['status'] === 'pending'): ?>
                                    <div style="display:flex;gap:4px">
                                        <form method="post" style="display:inline"><?= csrf_field() ?>
                                            <input type="hidden" name="decide_id" value="<?= (int) $e['id'] ?>">
                                            <input type="hidden" name="decision" value="approve">
                                            <button class="btn secondary" type="submit" style="padding:3px 8px;font-size:11px;background:#2e7d32;color:white;border-color:#2e7d32">✓ አጽድቅ</button>
                                        </form>
                                        <form method="post" style="display:inline"><?= csrf_field() ?>
                                            <input type="hidden" name="decide_id" value="<?= (int) $e['id'] ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <button class="btn danger" type="submit" style="padding:3px 8px;font-size:11px">✕ ውድቅ</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:11px">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="7" class="text-muted" style="text-align:center;padding:16px"><?= h(t('common.no_records', 'No records found.')) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function handleTypeChange(val) {
    const cat = document.getElementById('categoryInput');
    const rec = document.getElementById('recipientInput');
    const unit = document.getElementById('unitTagSelect');

    if (val === 'holiday_gift') {
        cat.value = 'የበዓል ስጦታ (Holiday Gift)';
        rec.placeholder = 'የበዓሉ ስም ወይም የተሰጣቸው ሰዎች (ለምሳሌ፡ ለአዲስ ዓመት፣ ለፋሲካ)';
        unit.value = 'general_overhead';
    } else if (val === 'admin_protocol') {
        cat.value = 'የአስተዳደር ፕሮቶኮል / ድጋፍ';
        rec.placeholder = 'የመንግስት/ቀበሌ አስተዳዳሪ ስም ወይም ቢሮ';
        unit.value = 'general_overhead';
    } else if (val === 'food_withdrawal') {
        cat.value = 'የምግብና እንግዳ መስተንግዶ';
        rec.placeholder = 'የምግብ አላማ (ለምሳሌ፡ ለበዓል ድግስ፣ ለእርሻ እንግዶች)';
        unit.value = 'general_overhead';
    } else if (val === 'owner_drawing') {
        cat.value = 'የባለቤት የግል ማውጣት';
        rec.placeholder = 'የባለቤቱ ስም / የግል ወጪ';
        unit.value = 'general_overhead';
    } else if (val === 'cash_withdrawal') {
        cat.value = 'ጥሬ ገንዘብ ማውጣት (Cash Safe)';
        rec.placeholder = 'የገንዘብ ማውጫ አላማ';
        unit.value = 'general_overhead';
    } else {
        cat.value = 'መደበኛ የስራ ማስኬጃ';
        rec.placeholder = 'ተቀባይ / አቅራቢ';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

