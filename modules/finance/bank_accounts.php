<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['add_bank_account'])) {
            $bankName = trim($_POST['bank_name'] ?? '');
            $accType  = trim($_POST['account_type'] ?? 'bank');
            $accName  = trim($_POST['account_name'] ?? '');
            $accNum   = trim($_POST['account_number'] ?? '');
            $openBal  = (float)($_POST['opening_balance'] ?? 0);

            if (empty($bankName) || empty($accNum)) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የባንኩ ስም እና የሂሳብ ቁጥር ማስገባት ግዴታ ነው።' : 'Bank name and account number are required.');
            }

            // Check if already exists
            $dup = db()->prepare("SELECT id FROM fin_bank_accounts WHERE account_number = ?");
            $dup->execute([$accNum]);
            if ($dup->fetch()) {
                throw new RuntimeException(current_locale() === 'am' ? 'ይህ የሂሳብ ቁጥር አስቀድሞ በሲስተሙ ተመዝግቧል።' : 'Account number already exists.');
            }

            $stmt = db()->prepare(
                "INSERT INTO fin_bank_accounts (bank_name, account_type, account_name, account_number, opening_balance, current_balance, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$bankName, $accType, $accName ?: $bankName, $accNum, $openBal, $openBal]);
            $newAccId = (int) db()->lastInsertId();

            // Record initial transaction if opening balance > 0
            if ($openBal > 0) {
                finance_record_transaction(
                    'inflow',
                    $openBal,
                    'general_overhead',
                    'opening_balance',
                    date('Y-m-d'),
                    "መነሻ ቀሪ ሂሳብ (Opening Balance) ለ {$bankName} - {$accNum}",
                    'fin_bank_accounts',
                    $newAccId,
                    null,
                    $accType === 'cash' ? 'cash' : ($accType === 'mobile' ? 'telebirr' : 'bank'),
                    $newAccId
                );
            }

            log_activity('finance', 'create', 'fin_bank_accounts', $newAccId, "Added new bank account: {$bankName} ({$accNum}) with opening balance {$openBal}");
            flash(current_locale() === 'am' ? '✓ አዲሱ የባንክ ሂሳብ በተሳካ ሁኔታ ተመዝግቧል! አሁን በስርዓቱ ውስጥ በደረሰኝና ክፍያዎች ላይ ይታወቃል።' : 'New bank account added successfully.');
        } elseif (isset($_POST['record_bank_fee'])) {
            // Deduct Bank Service Charge / Fee
            $bankId = (int)$_POST['bank_account_id'];
            $feeAmount = (float)$_POST['fee_amount'];
            $feeDate = trim($_POST['fee_date'] ?? date('Y-m-d'));
            $feeReason = trim($_POST['fee_reason'] ?? 'የወርሃዊ የባንክ አገልግሎት ክፍያ (Monthly Bank Service Fee)');
            $refNo = trim($_POST['reference_no'] ?? '');

            if ($bankId <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ ባንክ ይምረጡ።' : 'Select a bank account.');
            }
            if ($feeAmount <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የሚቆረጠው የገንዘብ መጠን ከ 0 በላይ መሆን አለበት።' : 'Fee amount must be greater than zero.');
            }

            $bankStmt = db()->prepare("SELECT * FROM fin_bank_accounts WHERE id = ?");
            $bankStmt->execute([$bankId]);
            $bank = $bankStmt->fetch();
            if (!$bank) {
                throw new RuntimeException(current_locale() === 'am' ? 'የተመረጠው የባንክ ሂሳብ አልተገኘም።' : 'Bank account not found.');
            }

            $ownsTx = false;
            if (!db()->inTransaction()) {
                db()->beginTransaction();
                $ownsTx = true;
            }

            // 1. Record expense
            $desc = "የባንክ አገልግሎት ክፍያ ቅነሳ: {$feeReason}" . ($refNo ? " (Ref: {$refNo})" : "");
            $expStmt = db()->prepare(
                "INSERT INTO fin_expenses (unit_tag, category, amount, bank_account_id, withdrawal_type, expense_date, description, recipient_name, requested_by, approved_by, status, created_at)
                 VALUES ('general_overhead', 'የባንክ አገልግሎት ክፍያ (Bank Fee)', ?, ?, 'operating_expense', ?, ?, ?, ?, ?, 'approved', NOW())"
            );
            $expStmt->execute([$feeAmount, $bankId, $feeDate, $desc, $bank['bank_name'], $currentUser['id'], $currentUser['id']]);
            $expId = (int) db()->lastInsertId();

            // 2. Record transaction outflow from bank
            finance_record_transaction(
                'outflow',
                $feeAmount,
                'general_overhead',
                'bank_service_fee',
                $feeDate,
                $desc,
                'fin_expenses',
                $expId,
                null,
                'bank',
                $bankId
            );

            if ($ownsTx && db()->inTransaction()) {
                db()->commit();
            }

            log_activity('finance', 'create', 'fin_bank_accounts', $bankId, "Deducted bank service fee of {$feeAmount} ETB from {$bank['bank_name']} ({$bank['account_number']})");
            flash(current_locale() === 'am' ? "✓ የባንክ አገልግሎት ክፍያ {$feeAmount} ብር በተሳካ ሁኔታ ከባንኩ ቀሪ ላይ ተቀንሷል!" : "Bank fee deducted successfully.");
        }
        header('Location: ' . BASE_URL . '/modules/finance/bank_accounts.php');
        exit;
    } catch (Throwable $e) {
        if (isset($ownsTx) && $ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        flash(current_locale() === 'am' ? 'ስህተት፦ ' . $e->getMessage() : 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

// Fetch all bank accounts with calculated live balances
$accounts = db()->query("SELECT * FROM fin_bank_accounts ORDER BY id ASC")->fetchAll();

$accountsWithBalance = [];
$totalAllBanks = 0;

foreach ($accounts as $acc) {
    $inflow = (float) db()->query("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE direction = 'inflow' AND bank_account_id = {$acc['id']}")->fetchColumn();
    $outflow = (float) db()->query("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE direction = 'outflow' AND bank_account_id = {$acc['id']}")->fetchColumn();
    
    // Live calculated balance
    $currentBalance = (float)$acc['opening_balance'] + $inflow - $outflow;
    $acc['current_balance'] = $currentBalance;
    $acc['total_inflow'] = $inflow;
    $acc['total_outflow'] = $outflow;
    $accountsWithBalance[] = $acc;
    $totalAllBanks += $currentBalance;
}

// Recent Bank Fee Deductions
$recentFees = db()->query(
    "SELECT e.*, b.bank_name, b.account_number, u.name AS approved_by_name
     FROM fin_expenses e
     JOIN fin_bank_accounts b ON b.id = e.bank_account_id
     LEFT JOIN users u ON u.id = e.approved_by
     WHERE e.category LIKE '%የባንክ አገልግሎት%' OR e.category LIKE '%Bank Fee%'
     ORDER BY e.expense_date DESC, e.id DESC LIMIT 20"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            🏦 <?= current_locale() === 'am' ? 'የባንክ ሂሳቦች አስተዳደር እና የአገልግሎት ክፍያ (Bank Fee) ቅነሳ' : 'Bank Accounts & Bank Fee Reconciliation' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'የድርጅቱ እውነተኛ የባንክ አካውንቶች መመዝገቢያ፣ የአሁኑ ቀሪ ሂሳብ መከታተያ፣ እና በየወሩ ከባንክ የሚቆረጡ ክፍያዎችን ማስተካከያ።' : 'Manage bank accounts and reconcile monthly bank service fee deductions.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">📊 <?= current_locale() === 'am' ? 'የፋይናንስ ዳሽቦርድ' : 'Finance Dashboard' ?></a>
    </div>
</div>

<!-- Total Treasury KPI -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi card" style="border-left:5px solid #1b5e20">
        <div class="label" style="color:#1b5e20;font-size:13px">💰 <?= current_locale() === 'am' ? 'ጠቅላላ በሁሉም ባንኮችና ካዝና ያለው ጥሬ ገንዘብ' : 'Total Company Cash & Bank Balance' ?></div>
        <div class="value" style="color:#1b5e20;font-size:26px;font-weight:800;margin-top:4px">
            <?= number_format($totalAllBanks, 2) ?> ETB
        </div>
    </div>
</div>

<!-- BANK ACCOUNTS LIST CARDS -->
<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;margin-bottom:24px">
    <?php foreach ($accountsWithBalance as $acc): ?>
        <div class="card" style="border-top:4px solid #0288d1;display:flex;flex-direction:column;justify-content:space-between">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
                    <h3 style="margin:0;font-size:16px">🏦 <?= h($acc['bank_name']) ?></h3>
                    <span class="badge" style="background:#e1f5fe;color:#0288d1"><?= h(strtoupper($acc['account_type'])) ?></span>
                </div>
                <div style="font-size:13px;color:var(--text-muted);margin-bottom:10px">
                    የሂሳብ ቁጥር፡ <strong style="color:#0f172a"><?= h($acc['account_number']) ?></strong><br>
                    የባለቤቱ ስም፡ <?= h($acc['account_name']) ?>
                </div>

                <div style="background:var(--bg);border-radius:6px;padding:10px;margin-bottom:12px">
                    <div style="font-size:11px;color:var(--text-muted)">የአሁኑ ትክክለኛ ቀሪ (Current Live Balance):</div>
                    <div style="font-size:22px;font-weight:800;color:<?= $acc['current_balance'] >= 0 ? '#1b5e20' : '#c62828' ?>">
                        <?= number_format($acc['current_balance'], 2) ?> ETB
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end">
                <button type="button" class="btn danger" style="padding:4px 10px;font-size:12px" onclick="openFeeModal(<?= (int)$acc['id'] ?>, '<?= h($acc['bank_name']) ?>', '<?= h($acc['account_number']) ?>')">
                    ✂️ የባንክ የሚቆረጥ ገንዘብ ቀንስ
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- TWO FORMS ROW: ADD NEW BANK & RECORD BANK FEE -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));gap:20px;margin-bottom:24px">
    
    <!-- 1. ADD NEW BANK ACCOUNT FORM -->
    <div class="card" style="border-top:3px solid #1b5e20">
        <h3 style="margin-top:0">➕ አዲስ የባንክ ሂሳብ መዝግብ (Add Bank Account)</h3>
        <p class="text-muted" style="font-size:13px">አዲስ የተከፈተ የባንክ ሂሳብ በሲስተሙ ውስጥ ለመመዝገቢያና ለክፍያ መቀበያ ዝግጁ ለማድረግ ይጠቀሙ።</p>

        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="add_bank_account" value="1">

            <div class="form-group">
                <label>የባንኩ ስም (Bank Name) *</label>
                <input type="text" name="bank_name" required placeholder="ለምሳሌ፡ አዋሽ ባንክ፣ ንብ ባንክ፣ ዳሽን ባንክ...">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>የሂሳብ ዓይነት *</label>
                    <select name="account_type" required>
                        <option value="bank">የባንክ ሂሳብ (Bank)</option>
                        <option value="mobile">የሞባይል ባንኪንግ / ቴሌብር (Mobile)</option>
                        <option value="cash">ጥሬ ገንዘብ ካዝና (Cash Safe)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>የሂሳብ ቁጥር (Account Number) *</label>
                    <input type="text" name="account_number" required placeholder="ለምሳሌ፡ 0132089...">
                </div>
            </div>

            <div class="form-group">
                <label>የሂሳቡ ባለቤት ስም (Account Holder Name)</label>
                <input type="text" name="account_name" placeholder="ለምሳሌ፡ አለልቱ የተቀናጀ እርሻ...">
            </div>

            <div class="form-group">
                <label>መነሻ ቀሪ ሂሳብ በብር (Opening Balance) — ባዶም ሊሆን ይችላል</label>
                <input type="number" step="0.01" min="0" name="opening_balance" value="0.00">
            </div>

            <button class="btn primary" type="submit" style="font-weight:700">✓ የባንክ ሂሳቡን መዝግብ</button>
        </form>
    </div>

    <!-- 2. DEDUCT BANK SERVICE FEE FORM -->
    <div class="card" style="border-top:3px solid #c62828">
        <h3 style="margin-top:0;color:#c62828">✂️ የባንክ የሚቆረጥ ገንዘብ ቅነሳ (Deduct Bank Fee)</h3>
        <p class="text-muted" style="font-size:13px">ባንክ በየወሩ ወይም በየወቅቱ ከአካውንቱ የሚቆርጠውን ክፍያ (ለምሳሌ፡ ከ1,000 ብር በላይ) ከሲስተሙ ቀሪ ላይ በመቀነስ ትክክለኛውን የባንክ ቀሪ እኩል ለማድረግ።</p>

        <form method="post" id="feeForm"><?= csrf_field() ?>
            <input type="hidden" name="record_bank_fee" value="1">

            <div class="form-group">
                <label>የተቆረጠበት የባንክ ሂሳብ *</label>
                <select name="bank_account_id" id="feeBankSelect" required style="width:100%;padding:8px">
                    <option value="">— ባንክ ይምረጡ —</option>
                    <?php foreach ($accountsWithBalance as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>">
                            🏦 <?= h($acc['bank_name']) ?> (<?= h($acc['account_number']) ?>) — ቀሪ: <?= number_format($acc['current_balance'], 2) ?> ETB
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>የተቆረጠው መጠን በብር (Amount) *</label>
                    <input type="number" step="0.01" min="0.01" name="fee_amount" id="feeAmountInput" required placeholder="ለምሳሌ፡ 1200.00" style="font-size:16px;font-weight:700;color:#c62828">
                </div>
                <div class="form-group">
                    <label>የተቆረጠበት ቀን (Date)</label>
                    <input type="date" name="fee_date" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>ምክንያት / ማብራሪያ (Reason / Description) *</label>
                <input type="text" name="fee_reason" required value="የወርሃዊ የባንክ አገልግሎት ክፍያ (Monthly Bank Service Charge)">
            </div>

            <div class="form-group">
                <label>የባንክ ማመሳከሪያ ቁጥር (Ref / Txn No)</label>
                <input type="text" name="reference_no" placeholder="ለምሳሌ፡ STMT-OCT-2026...">
            </div>

            <button class="btn danger" type="submit" style="font-weight:700">✂️ ክፍያውን ከባንኩ ቀሪ ላይ ቀንስ</button>
        </form>
    </div>
</div>

<!-- RECENT BANK FEES DEDUCTIONS LOG -->
<div class="card">
    <h3 style="margin-top:0">📜 በቅርቡ የተቆረጡ የባንክ አገልግሎት ክፍያዎች ማህደር (Bank Fee Audit Trail)</h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>ቀን</th>
                    <th>ባንክ</th>
                    <th>የሂሳብ ቁጥር</th>
                    <th>የተቆረጠው መጠን</th>
                    <th>ዝርዝር ምክንያት</th>
                    <th>ያጸደቀው / የመዘገበው ኃላፊ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentFees as $f): ?>
                    <tr>
                        <td style="font-size:12px;white-space:nowrap">📅 <?= eth_date($f['expense_date']) ?></td>
                        <td><strong>🏦 <?= h($f['bank_name']) ?></strong></td>
                        <td><?= h($f['account_number']) ?></td>
                        <td><strong style="color:#c62828;font-size:15px">-<?= number_format($f['amount'], 2) ?> ETB</strong></td>
                        <td><?= h($f['description']) ?></td>
                        <td>🛡️ <?= h($f['approved_by_name'] ?: 'ሲስተም') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentFees)): ?>
                    <tr><td colspan="6" class="text-muted" style="text-align:center">እስካሁን የተመዘገበ የባንክ አገልግሎት ክፍያ ቅነሳ የለም።</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openFeeModal(bankId, bankName, accNum) {
    const sel = document.getElementById('feeBankSelect');
    sel.value = bankId;
    document.getElementById('feeAmountInput').focus();
    window.scrollTo({ top: document.getElementById('feeForm').offsetTop - 100, behavior: 'smooth' });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
