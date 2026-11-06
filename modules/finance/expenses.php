<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'finance_officer', 'super_admin']);

$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['record_expense'])) {
            $cat      = trim($_POST['category'] ?? '');
            $amount   = (float)$_POST['amount'];
            $bankId   = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
            $wType    = trim($_POST['withdrawal_type'] ?? 'operating_expense');
            $unitTag  = trim($_POST['unit_tag'] ?? 'dairy');
            $expDate  = trim($_POST['expense_date'] ?? date('Y-m-d'));
            $desc     = trim($_POST['description'] ?? '');
            $rcptName = trim($_POST['recipient_name'] ?? '');

            if ($amount <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የወጪው መጠን ከ 0 በላይ መሆን አለበት።' : 'Amount must be positive.');
            }
            if (empty($cat)) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የወጪ ምድብ ማስገባት ግዴታ ነው።' : 'Category is required.');
            }

            // GM or Super Admin direct entry is auto-approved, others pending
            $autoApprove = has_role(['super_admin', 'general_manager']);
            $status = $autoApprove ? 'approved' : 'pending';
            $approverId = $autoApprove ? $currentUser['id'] : null;

            $stmt = db()->prepare(
                "INSERT INTO fin_expenses (unit_tag, category, amount, bank_account_id, withdrawal_type, expense_date, description, recipient_name, requested_by, approved_by, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$unitTag, $cat, $amount, $bankId, $wType, $expDate, $desc ?: null, $rcptName ?: null, $currentUser['id'], $approverId, $status]);
            $expId = (int) db()->lastInsertId();

            if ($autoApprove) {
                // Book transaction outflow immediately
                finance_record_transaction(
                    'outflow',
                    $amount,
                    $unitTag,
                    $cat,
                    $expDate,
                    $desc ?: "የወጪ ክፍያ #{$expId}",
                    'fin_expenses',
                    $expId,
                    null,
                    'cash',
                    $bankId
                );
            }

            log_activity('finance', 'create', 'fin_expenses', $expId, "Recorded {$amount} ETB expense for {$unitTag} (Status: {$status})");
            flash(current_locale() === 'am' ? '✓ የወጪ መዝገቡ በተሳካ ሁኔታ ተመዝግቧል!' : 'Expense recorded successfully.');
        } elseif (isset($_POST['approve_expense'])) {
            require_role(['super_admin', 'general_manager']);
            $expId = (int)$_POST['expense_id'];

            $expStmt = db()->prepare("SELECT * FROM fin_expenses WHERE id = ?");
            $expStmt->execute([$expId]);
            $exp = $expStmt->fetch();

            if ($exp && $exp['status'] === 'pending') {
                db()->prepare("UPDATE fin_expenses SET status = 'approved', approved_by = ? WHERE id = ?")
                    ->execute([$currentUser['id'], $expId]);

                finance_record_transaction(
                    'outflow',
                    (float)$exp['amount'],
                    $exp['unit_tag'],
                    $exp['category'],
                    $exp['expense_date'],
                    $exp['description'] ?: "የጸደቀ ወጪ #{$expId}",
                    'fin_expenses',
                    $expId,
                    null,
                    'cash',
                    $exp['bank_account_id']
                );

                log_activity('finance', 'approve', 'fin_expenses', $expId, "Approved expense #{$expId} by User #{$currentUser['id']}");
                flash(current_locale() === 'am' ? '✓ ወጪው ጸድቆ ከሂሳብ መዝገብ ላይ ተቀናሽ ተደርጓል!' : 'Expense approved and booked.');
            }
        } elseif (isset($_POST['reject_expense'])) {
            require_role(['super_admin', 'general_manager']);
            $expId = (int)$_POST['expense_id'];
            db()->prepare("UPDATE fin_expenses SET status = 'rejected', approved_by = ? WHERE id = ?")
                ->execute([$currentUser['id'], $expId]);
            log_activity('finance', 'reject', 'fin_expenses', $expId, "Rejected expense #{$expId} by User #{$currentUser['id']}");
            flash(current_locale() === 'am' ? '✓ ወጪው ውድቅ ተደርጓል።' : 'Expense rejected.');
        }
        header('Location: ' . BASE_URL . '/modules/finance/expenses.php');
        exit;
    } catch (Throwable $e) {
        flash(current_locale() === 'am' ? 'ስህተት፦ ' . $e->getMessage() : 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$bankAccounts = db()->query("SELECT id, bank_name, account_type, account_number FROM fin_bank_accounts ORDER BY id ASC")->fetchAll();

$unitLabels = [
    'dairy'            => ['name' => 'የላም እርባታ (Dairy)', 'color' => '#1b5e20'],
    'poultry'          => ['name' => 'የዶሮ እርባታ (Poultry)', 'color' => '#b71c1c'],
    'fish'             => ['name' => 'የዓሣ እርባታ (Fish Farm)', 'color' => '#0288d1'],
    'feed_production'  => ['name' => 'የመኖና ሰብል (Feed Production)', 'color' => '#f57f17'],
    'veterinary'       => ['name' => 'የእንስሳት ሕክምና (Veterinary)', 'color' => '#4a148c'],
    'warehouse'        => ['name' => 'መጋዘንና ግዢ (Warehouse)', 'color' => '#546e7a'],
    'distribution'     => ['name' => 'ስርጭትና ሱቆች (Distribution)', 'color' => '#00838f'],
    'general_overhead' => ['name' => 'ጠቅላላ አስተዳደር (General Overhead)', 'color' => '#37474f']
];

$filterUnit = trim($_GET['unit'] ?? '');
$where = "1=1";
$params = [];
if (!empty($filterUnit)) {
    $where .= " AND e.unit_tag = ?";
    $params[] = $filterUnit;
}

$expensesStmt = db()->prepare("
    SELECT e.*, b.bank_name, b.account_type, b.account_number,
           u.name AS requester_name, ru.name AS requester_role,
           app.name AS approver_name, rapp.name AS approver_role
    FROM fin_expenses e
    LEFT JOIN fin_bank_accounts b ON b.id = e.bank_account_id
    LEFT JOIN users u ON u.id = e.requested_by
    LEFT JOIN roles ru ON ru.id = u.role_id
    LEFT JOIN users app ON app.id = e.approved_by
    LEFT JOIN roles rapp ON rapp.id = app.role_id
    WHERE {$where}
    ORDER BY e.expense_date DESC, e.id DESC LIMIT 60
");
$expensesStmt->execute($params);
$expenses = $expensesStmt->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            💸 <?= current_locale() === 'am' ? 'ዕለታዊ ወጪዎች፣ ጥሬ ገንዘብ ማውጫ እና ማጽደቂያ' : 'Expenses & Cash Disbursements' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'የስራ ማስኬጃ ወጪዎች መመዝገቢያ፣ ማጽደቂያ፣ እና ማን እንዳጸደቀው የሚያሳይ ሙሉ የኦዲት መዝገብ።' : 'Manage operating expenses with approver tracking.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">📊 <?= current_locale() === 'am' ? 'የፋይናንስ ዳሽቦርድ' : 'Finance Dashboard' ?></a>
    </div>
</div>

<!-- Expense Form -->
<div class="card" style="max-width:700px;margin-bottom:24px">
    <h3 style="margin-top:0">➕ <?= current_locale() === 'am' ? 'አዲስ ወጪ መዝግብ (Record Expense)' : 'Record Expense' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="record_expense" value="1">

        <div class="form-row">
            <div class="form-group">
                <label>የስራ ክፍል (Department / Unit) *</label>
                <select name="unit_tag" required style="width:100%;padding:8px">
                    <?php foreach ($unitLabels as $uKey => $uMeta): ?>
                        <option value="<?= h($uKey) ?>"><?= h($uMeta['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>የወጪ ምድብ (Category) *</label>
                <input type="text" name="category" required placeholder="ለምሳሌ፡ የመብራት ክፍያ፣ የቀን ሰራተኛ፣ ነዳጅ...">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>የገንዘብ መጠን በብር (Amount) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" style="font-size:16px;font-weight:700">
            </div>
            <div class="form-group">
                <label>የወጣበት ባንክ / ካዝና (Bank / Cash Source) *</label>
                <select name="bank_account_id" required style="width:100%;padding:8px">
                    <option value="">— ምንጭ ይምረጡ —</option>
                    <?php foreach ($bankAccounts as $b): ?>
                        <option value="<?= (int)$b['id'] ?>">
                            🏦 <?= h($b['bank_name']) ?> (<?= h($b['account_number']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>የወጪ ቀን (Date)</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>ገንዘቡን የተቀበለው ሰው / ድርጅት (Recipient)</label>
                <input type="text" name="recipient_name" placeholder="ለምሳሌ፡ አቶ ዳንኤል (ነዳጅ ማደያ)">
            </div>
        </div>

        <div class="form-group">
            <label>ማብራሪያ / ዝርዝር ምክንያት</label>
            <input type="text" name="description" placeholder="ለምሳሌ፡ ለትራክተሩ 100 ሊትር ናፍጣ የተገዛበት...">
        </div>

        <button class="btn" type="submit" style="font-weight:700">✓ ወጪውን መዝግብ</button>
    </form>
</div>

<!-- Expenses History with Approver Tracking -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0">📜 <?= current_locale() === 'am' ? 'የወጪዎች ማህደር እና ያጸደቀው ኃላፊ ዝርዝር' : 'Expenses Audit Log & Approvers' ?></h3>
        
        <form method="get" style="margin:0">
            <select name="unit" onchange="this.form.submit()" style="padding:6px;font-size:13px">
                <option value="">— በሁሉም የስራ ክፍሎች —</option>
                <?php foreach ($unitLabels as $uKey => $uMeta): ?>
                    <option value="<?= h($uKey) ?>" <?= $filterUnit === $uKey ? 'selected' : '' ?>><?= h($uMeta['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>ቀን</th>
                    <th>የስራ ክፍል</th>
                    <th>ምድብ እና ተቀባይ</th>
                    <th>የወጣበት ባንክ/ካዝና</th>
                    <th>መጠን</th>
                    <th>ሁኔታ</th>
                    <th>ያስመዘገበው</th>
                    <th>ያጸደቀው ኃላፊ (Approved By)</th>
                    <th>እርምጃ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $e): 
                    $uInfo = $unitLabels[$e['unit_tag']] ?? ['name' => $e['unit_tag'], 'color' => '#555'];
                ?>
                    <tr>
                        <td style="font-size:12px;white-space:nowrap">
                            📅 <?= eth_date($e['expense_date']) ?>
                        </td>
                        <td>
                            <span class="badge" style="background:<?= $uInfo['color'] ?>15;color:<?= $uInfo['color'] ?>;font-weight:700">
                                <?= h($uInfo['name']) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= h($e['category']) ?></strong>
                            <?php if (!empty($e['recipient_name'])): ?>
                                <div style="font-size:12px;color:#1b5e20">ተቀባይ፡ 👤 <?= h($e['recipient_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($e['description'])): ?>
                                <small class="text-muted" style="display:block;font-style:italic"><?= h($e['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            🏦 <?= h($e['bank_name'] ?: 'ካዝና') ?>
                        </td>
                        <td>
                            <strong style="color:#c62828;font-size:15px"><?= number_format($e['amount'], 2) ?> ETB</strong>
                        </td>
                        <td>
                            <?php if ($e['status'] === 'approved'): ?>
                                <span class="badge" style="background:#e8f5e9;color:#2e7d32">✓ የጸደቀ</span>
                            <?php elseif ($e['status'] === 'rejected'): ?>
                                <span class="badge" style="background:#ffebee;color:#c62828">✕ ውድቅ</span>
                            <?php else: ?>
                                <span class="badge" style="background:#fff8e1;color:#f57c00">⏳ ውሳኔ ይጠብቃል</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            👤 <strong><?= h($e['requester_name']) ?></strong><br>
                            <small class="text-muted"><?= h($e['requester_role']) ?></small>
                        </td>
                        <td>
                            <?php if (!empty($e['approver_name'])): ?>
                                <span style="color:#1b5e20;font-weight:700">🛡️ <?= h($e['approver_name']) ?></span><br>
                                <small class="text-muted"><?= h($e['approver_role']) ?></small>
                            <?php elseif ($e['status'] === 'pending'): ?>
                                <span class="text-muted" style="font-style:italic">⏳ ውሳኔ በመጠባበቅ ላይ</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($e['status'] === 'pending' && has_role(['super_admin', 'general_manager'])): ?>
                                <div style="display:flex;gap:4px">
                                    <form method="post" onsubmit="return confirm('ወጪውን ማጽደቅ ይፈልጋሉ?');" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="expense_id" value="<?= (int)$e['id'] ?>">
                                        <button class="btn success" type="submit" name="approve_expense" style="padding:4px 8px;font-size:11px">✓ አጽድቅ</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('ወጪውን ውድቅ ማድረግ ይፈልጋሉ?');" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="expense_id" value="<?= (int)$e['id'] ?>">
                                        <button class="btn danger" type="submit" name="reject_expense" style="padding:4px 8px;font-size:11px">✕ ውድቅ</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="9" class="text-muted" style="text-align:center">ምንም የተመዘገበ ወጪ የለም።</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
