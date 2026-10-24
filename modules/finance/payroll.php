<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

$curEthYearNum  = eth_current_year();
$curEthMonthNum = eth_current_month();

$ethMonths = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['finalize_run'])) {
            payroll_finalize((int) $_POST['finalize_run']);
            flash(current_locale() === 'am' ? '✓ የደመወዝ ክፍያ ዙሩ በሰራተኞች የመገኘት መዝገብ መሠረት ጸድቋል!' : 'Payroll finalized successfully.');
        } elseif (isset($_POST['record_payment'])) {
            $payslipId = (int)$_POST['payslip_id'];
            $amount    = (float)$_POST['amount_paid'];
            $bankAccId = (int)($_POST['bank_account_id'] ?? 0);
            $notes     = trim($_POST['notes'] ?? '');
            
            $accName = 'Cash';
            if ($bankAccId > 0) {
                $accStmt = db()->prepare("SELECT bank_name FROM fin_bank_accounts WHERE id = ?");
                $accStmt->execute([$bankAccId]);
                $acc = $accStmt->fetch();
                if ($acc) $accName = $acc['bank_name'];
            }
            
            payroll_record_payment($payslipId, $amount, $accName, $notes, current_user()['id'], $bankAccId);
            flash(current_locale() === 'am' ? '✓ የደመወዝ ክፍያ በተሳካ ሁኔታ ተመዝግቧል!' : 'Salary payment transaction recorded.');
        } elseif (isset($_POST['add_bonus_allowance'])) {
            $payslipId = (int)$_POST['payslip_id'];
            $bonusAmount = (float)$_POST['bonus_amount'];
            $reason = trim($_POST['bonus_reason'] ?? '');

            if ($bonusAmount <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የቦነስ ወይም አበል መጠን ከ 0 በላይ መሆን አለበት' : 'Bonus amount must be positive.');
            }

            $ps = db()->prepare("SELECT * FROM fin_payslips WHERE id = ?");
            $ps->execute([$payslipId]);
            $ps = $ps->fetch();
            if (!$ps) {
                throw new RuntimeException('Payslip not found.');
            }

            $newAllowances = (float)$ps['allowances'] + $bonusAmount;
            $newGross = (float)$ps['gross_pay'] + $bonusAmount;
            
            // Recalculate tax
            $taxable = max(0.0, $newGross - (float)$ps['pension_employee']);
            $newTax = payroll_calculate_tax($taxable);
            $newTotalDeductions = round((float)$ps['pension_employee'] + $newTax, 2);
            $newNet = max(0.0, round($newGross - $newTotalDeductions, 2));

            $stmt = db()->prepare("UPDATE fin_payslips SET allowances = ?, gross_pay = ?, tax_amount = ?, deductions = ?, net_pay = ? WHERE id = ?");
            $stmt->execute([$newAllowances, $newGross, $newTax, $newTotalDeductions, $newNet, $payslipId]);

            log_activity('finance', 'update', 'fin_payslips', $payslipId, "Added bonus of {$bonusAmount} ETB: {$reason}");
            flash(current_locale() === 'am' ? "✓ ተጨማሪ ቦነስ/አበል {$bonusAmount} ETB በተሳካ ሁኔታ ተደምሯል!" : "Bonus of {$bonusAmount} ETB added.");
        } elseif (isset($_POST['create_eth_run'])) {
            $ey = (int)$_POST['eth_year'];
            $em = (int)$_POST['eth_month'];
            $mRange = eth_month_range($ey, $em);
            payroll_run_create($mRange['from'], $mRange['to']);
            flash("✓ አዲስ የደመወዝ ክፍያ ዙር ለ {$ethMonths[$em]} {$ey} ዓ.ም በተሳካ ሁኔታ ተፈጥሯል!");
        } elseif (isset($_POST['create_custom_run'])) {
            $startDate = trim($_POST['start_date'] ?? '');
            $endDate   = trim($_POST['end_date'] ?? '');
            if (empty($startDate) || empty($endDate) || $endDate < $startDate) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ ትክክለኛ የመነሻና የማብቂያ ቀን ያስገቡ' : 'Valid date range required.');
            }
            payroll_run_create($startDate, $endDate);
            flash(current_locale() === 'am' ? "✓ አዲስ የደመወዝ ክፍያ ዙር ከ " . eth_date($startDate) . " እስከ " . eth_date($endDate) . " ተፈጥሯል!" : "Custom payroll run created.");
        } elseif (isset($_POST['save_pension_settings'])) {
            require_role(['super_admin', 'general_manager']);
            $empRate = (float)$_POST['pension_employee_rate'];
            $emprRate = (float)$_POST['pension_employer_rate'];

            setting_set('pension_employee_rate', (string)$empRate);
            setting_set('pension_employer_rate', (string)$emprRate);
            flash(current_locale() === 'am' ? '✓ የጡረታ መዋጮ ተመን ቅንብሮች በተሳካ ሁኔታ ተሻሽለዋል!' : 'Pension settings saved.');
        } elseif (isset($_POST['save_tax_brackets'])) {
            require_role(['super_admin', 'general_manager']);
            $rawBrackets = $_POST['brackets'] ?? [];
            if (!is_array($rawBrackets) || empty($rawBrackets)) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ ቢያንስ አንድ የግብር እርከን ያስገቡ' : 'At least one tax bracket required.');
            }
            payroll_save_tax_brackets($rawBrackets);
            flash(current_locale() === 'am' ? '✓ ሁሉም የስራ ግብር እርከኖች (Tax Brackets) በተሳካ ሁኔታ ተስተካክለው ተቀምጠዋል!' : 'All income tax brackets saved.');
        } elseif (isset($_POST['recalculate_run'])) {
            require_role(['super_admin', 'general_manager', 'finance_officer']);
            $runId = (int)$_POST['run_id'];
            $runCheck = db()->prepare("SELECT status FROM fin_payroll_runs WHERE id = ?");
            $runCheck->execute([$runId]);
            $runCheck = $runCheck->fetch();
            if ($runCheck && $runCheck['status'] === 'draft') {
                $psStmt = db()->prepare("SELECT * FROM fin_payslips WHERE payroll_run_id = ?");
                $psStmt->execute([$runId]);
                $payslips = $psStmt->fetchAll();
                foreach ($payslips as $ps) {
                    $gross = (float)$ps['gross_pay'];
                    $pensionEmp = (float)$ps['pension_employee'];
                    $taxable = max(0.0, $gross - $pensionEmp);
                    $newTax = payroll_calculate_tax($taxable);
                    $newDed = round($pensionEmp + $newTax, 2);
                    $newNet = max(0.0, round($gross - $newDed, 2));
                    db()->prepare("UPDATE fin_payslips SET tax_amount = ?, deductions = ?, net_pay = ? WHERE id = ?")
                        ->execute([$newTax, $newDed, $newNet, $ps['id']]);
                }
                flash(current_locale() === 'am' ? '✓ የደመወዝ ዙሩ ስሌቶች በአዲሶቹ የስራ ግብር እርከኖች መሠረት ታድሰዋል!' : 'Draft payroll recalculated with updated tax brackets.');
            }
        }
        header('Location: ' . BASE_URL . '/modules/finance/payroll.php' . (isset($_GET['run_id']) ? '?run_id=' . (int)$_GET['run_id'] : ''));
        exit;
    } catch (Throwable $e) {
        flash(current_locale() === 'am' ? 'ስህተት፦ ' . $e->getMessage() : 'Error: ' . $e->getMessage(), 'error');
    }
}

if (($_GET['export'] ?? '') === 'csv' && isset($_GET['run_id'])) {
    require_once __DIR__ . '/../../includes/export.php';
    $stmt = db()->prepare(
        "SELECT e.full_name AS 'የሰራተኛ ስም', e.employee_number AS 'መለያ ቁጥር',
                p.base_salary AS 'መሰረታዊ ደመወዝ', p.daily_rate AS 'የቀን ተመን',
                p.days_present AS 'የተገኙበት ቀን', p.gross_pay AS 'ጠቅላላ ገቢ (Gross)',
                p.allowances AS 'ተጨማሪ ቦነስ/አበል',
                p.pension_employee AS 'የጡረታ መዋጮ', p.tax_amount AS 'የስራ ግብር',
                p.deductions AS 'ጠቅላላ ተቀናሽ', p.net_pay AS 'የተጣራ ደመወዝ (Net)',
                p.total_paid AS 'የተከፈለ', (p.net_pay - p.total_paid) AS 'ቀሪ ሂሳብ'
         FROM fin_payslips p JOIN employees e ON e.id = p.employee_id WHERE p.payroll_run_id = ?"
    );
    $stmt->execute([(int) $_GET['run_id']]);
    export_csv($stmt->fetchAll(), 'payroll_run_' . (int) $_GET['run_id'] . '.csv');
}

require_once __DIR__ . '/../../includes/header.php';

// Accounts for salary disbursement
$bankAccounts = db()->query("SELECT id, bank_name, account_type, account_number, opening_balance FROM fin_bank_accounts ORDER BY id ASC")->fetchAll();

// Pension and Tax settings
$pensionEmployeeRate = (float)(setting_get('pension_employee_rate') ?? 7.0);
$pensionEmployerRate = (float)(setting_get('pension_employer_rate') ?? 11.0);
$taxExemptThreshold  = (float)(setting_get('tax_exempt_threshold') ?? 2000.0);
$taxBrackets         = payroll_get_tax_brackets();

// Fetch all runs
$allRuns = db()->query("SELECT * FROM fin_payroll_runs ORDER BY period_end DESC, id DESC")->fetchAll();

// Last finalized run info for HR sequencing
$lastFinalizedRun = db()->query("SELECT * FROM fin_payroll_runs WHERE status = 'finalized' ORDER BY period_end DESC LIMIT 1")->fetch();

// Determine recommended completed month to run (In Arrears)
$today = date('Y-m-d');
$defaultEthYear = $curEthYearNum;
$defaultEthMonth = ($curEthMonthNum > 1) ? ($curEthMonthNum - 1) : 13;
if ($defaultEthMonth === 13) {
    $defaultEthYear = $curEthYearNum - 1;
}

$selectedRunId = isset($_GET['run_id']) && $_GET['run_id'] !== 'all' ? (int)$_GET['run_id'] : 0;
if ($selectedRunId > 0) {
    $runs = array_filter($allRuns, fn($r) => (int)$r['id'] === $selectedRunId);
} else {
    $runs = $allRuns;
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">💳 <?= current_locale() === 'am' ? 'የሰራተኞች ወርሃዊ ደመወዝ ክፍያ ማዕከል' : 'Staff Payroll & Salaries Control' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'በኢትዮጵያ ወራት የቀን መቁጠሪያ መሰረት የሰራተኞች ደመወዝ ስሌት፣ ቦነስ/አበል መጨመሪያ፣ ግብርና ጡረታ ማስተካከያ፣ እና ክፍያ መፈጸሚያ።' : 'Ethiopian calendar monthly payroll runs, automated tax & pension computations, bonus allowances, and payment disbursement.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/modules/employees/dashboard.php" class="btn secondary">👥 <?= current_locale() === 'am' ? 'የሰራተኞች ማዕከል' : 'Employees' ?></a>
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ፋይናንስ ማዕከል' : 'Finance Dashboard' ?></a>
    </div>
</div>

<!-- Run Periods Filter Bar -->
<?php if (!empty($allRuns)): ?>
<div class="card no-print" style="padding:10px 16px;margin-bottom:20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <strong style="font-size:13px">📅 <?= current_locale() === 'am' ? 'የደመወዝ ዙር ይምረጡ፦' : 'Select Run:' ?></strong>
    <a href="?run_id=all" class="btn <?= $selectedRunId === 0 ? '' : 'secondary' ?>" style="padding:4px 12px;font-size:12.5px">
        <?= current_locale() === 'am' ? 'ሁሉንም አሳይ' : 'All Runs' ?> (<?= count($allRuns) ?>)
    </a>
    <?php foreach ($allRuns as $r): ?>
        <?php
        $ethPeriod = eth_date($r['period_start']) . ' — ' . eth_date($r['period_end']);
        $isActiveTab = ($selectedRunId === (int)$r['id']);
        ?>
        <a href="?run_id=<?= (int)$r['id'] ?>" class="btn <?= $isActiveTab ? '' : 'secondary' ?>" style="padding:4px 12px;font-size:12.5px">
            <?= h($ethPeriod) ?> <?= $r['status'] === 'finalized' ? '✓' : '⏳' ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));gap:20px;margin-bottom:24px;align-items:start">
    
    <!-- 1. CREATE NEW PAYROLL RUN (ETHIOPIAN CALENDAR) -->
    <!-- 1. CREATE NEW PAYROLL RUN -->
    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.06);border-top:3px solid var(--primary)">
        <h3 style="margin-top:0">➕ <?= current_locale() === 'am' ? 'አዲስ የደመወዝ ክፍያ ዙር ፍጠር' : 'Create Payroll Run' ?></h3>
        
        <?php if ($lastFinalizedRun): ?>
            <?php
            $suggestedStartDate = date('Y-m-d', strtotime('+1 day', strtotime($lastFinalizedRun['period_end'])));
            ?>
            <div style="background:var(--hover);padding:8px 12px;border-radius:6px;font-size:12px;margin-bottom:12px;border-left:3px solid #2e7d32">
                <strong>📌 <?= current_locale() === 'am' ? 'የመጨረሻው የተከፈለበት ወቅት፦' : 'Last Paid Period:' ?></strong> 
                <span><?= eth_date($lastFinalizedRun['period_start']) ?> — <?= eth_date($lastFinalizedRun['period_end']) ?></span>
                <br><small class="text-muted">ቀጣዩ ክፍያ የሚጀምረው ከ፦ <strong><?= eth_date($suggestedStartDate) ?></strong> ነው</small>
            </div>
        <?php else: ?>
            <?php $suggestedStartDate = date('Y-m-d', strtotime('-6 days')); ?>
        <?php endif; ?>

        <!-- Mode Selector Buttons -->
        <div style="display:flex;gap:6px;margin-bottom:14px">
            <button type="button" class="btn" id="btnRunModeMonth" onclick="switchRunMode('month')" style="padding:4px 10px;font-size:12px">
                🗓️ <?= current_locale() === 'am' ? 'ሙሉ የኢትዮጵያ ወር' : 'Full Eth Month' ?>
            </button>
            <button type="button" class="btn secondary" id="btnRunModeCustom" onclick="switchRunMode('custom')" style="padding:4px 10px;font-size:12px">
                📅 <?= current_locale() === 'am' ? 'ለተሰሩ ቀናት (e.g. 6 ቀናት)' : 'Custom Days (e.g. 6 Days)' ?>
            </button>
        </div>

        <!-- Mode 1: Full Ethiopian Month Form -->
        <div id="runFormMonth">
            <p class="text-muted" style="font-size:12px;margin-bottom:10px">
                <?= current_locale() === 'am' ? 'የተጠናቀቀ ሙሉ ወር ሲመርጡ የወሩ መነሻና ማብቂያ ቀን በራስ-ሰር ይዘጋጃል።' : 'Select completed month to process in arrears.' ?>
            </p>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="create_eth_run" value="1">
                <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px">
                    <div class="form-group" style="flex:1">
                        <label style="font-weight:700">📅 <?= current_locale() === 'am' ? 'ዓመተ ምህረት *' : 'Year *' ?></label>
                        <select name="eth_year" id="payrollYearSelect" required style="width:100%;padding:8px;font-size:13.5px;font-weight:600">
                            <?php for ($y = $curEthYearNum; $y >= $curEthYearNum - 2; $y--): ?>
                                <option value="<?= $y ?>" <?= $y === $defaultEthYear ? 'selected' : '' ?>><?= $y ?> ዓ.ም</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label style="font-weight:700">🗓️ <?= current_locale() === 'am' ? 'የክፍያ ወር *' : 'Month *' ?></label>
                        <select name="eth_month" required style="width:100%;padding:8px;font-size:13.5px;font-weight:600">
                            <?php foreach ($ethMonths as $mNum => $mName): ?>
                                <?php
                                $mR = eth_month_range($curEthYearNum, $mNum);
                                $isFuture = ($mR['from'] > $today);
                                $isOngoing = ($mR['from'] <= $today && $mR['to'] > $today);
                                ?>
                                <option value="<?= $mNum ?>" 
                                        <?= $mNum === $defaultEthMonth ? 'selected' : '' ?>
                                        <?= $isFuture ? 'disabled style="color:#aaa"' : '' ?>>
                                    <?= $mName ?> ወር 
                                    <?= $isFuture ? '(⏳ ወደፊት አልተሰራም)' : ($isOngoing ? '(⌛ የወቅቱ ወር)' : '(✓ የተጠናቀቀ ወር)') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button class="btn" type="submit" style="width:100%;padding:9px;background:#1e5631;border-color:#1e5631">
                    ✓ <?= current_locale() === 'am' ? 'የወሩን ደመወዝ ዙር ጀምር' : 'Create Month Run' ?>
                </button>
            </form>
        </div>

        <!-- Mode 2: Custom Worked Days Range Form (e.g. 6 days) -->
        <div id="runFormCustom" style="display:none">
            <p class="text-muted" style="font-size:12px;margin-bottom:10px">
                <?= current_locale() === 'am' ? 'ሰራተኞች የመጨረሻ ከተከፈላቸው በኋላ የሰሩትን ትክክለኛ የቀን ገደብ (ለምሳሌ 6 ቀናት) ያስገቡ። ደመወዙ ለተሰሩት ቀናት ብቻ ይሰላል።' : 'Pay strictly by worked days after last paid date.' ?>
            </p>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="create_custom_run" value="1">
                <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px">
                    <div class="form-group" style="flex:1">
                        <label style="font-weight:700">📅 <?= current_locale() === 'am' ? 'የመነሻ ቀን *' : 'Start Date *' ?></label>
                        <input type="date" name="start_date" value="<?= h($suggestedStartDate) ?>" max="<?= date('Y-m-d') ?>" required style="width:100%;padding:8px">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label style="font-weight:700">📅 <?= current_locale() === 'am' ? 'የማብቂያ ቀን *' : 'End Date *' ?></label>
                        <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required style="width:100%;padding:8px">
                    </div>
                </div>
                <button class="btn" type="submit" style="width:100%;padding:9px;background:#1565c0;border-color:#1565c0">
                    ✓ <?= current_locale() === 'am' ? 'የተሰሩ ቀናት የደመወዝ ዙር ፍጠር (Prorated)' : 'Create Prorated Run' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- 2. PENSION & TAX CUSTOMIZABLE SETTINGS -->
    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.06);border-top:3px solid #1565c0">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <h3 style="margin:0">⚙️ <?= current_locale() === 'am' ? 'የጡረታ እና የስራ ግብር ተመን ቅንብሮች' : 'Pension & Tax Rates' ?></h3>
            <span class="badge" style="background:#e3f2fd;color:#1565c0;font-size:11.5px"><?= count($taxBrackets) ?> <?= current_locale() === 'am' ? 'እርከኖች' : 'Brackets' ?></span>
        </div>
        <p class="text-muted" style="font-size:12px;margin-bottom:12px">
            <?= current_locale() === 'am' ? 'የጡረታ መዋጮዎችን እና እያንዳንዱን የስራ ግብር እርከን እንደ ድርጅቱ ወይም እንደ ህጉ ማስተካከል ይችላሉ።' : 'Customize pension rates and progressive income tax brackets.' ?>
        </p>

        <!-- Pension Rates Form -->
        <form method="post" style="margin-bottom:14px"><?= csrf_field() ?>
            <input type="hidden" name="save_pension_settings" value="1">
            <div class="form-row" style="display:flex;gap:10px;margin-bottom:8px">
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700;font-size:12px">👤 <?= current_locale() === 'am' ? 'የሰራተኛ ጡረታ (%)' : 'Employee Pension %' ?></label>
                    <input type="number" step="0.5" min="0" max="30" name="pension_employee_rate" value="<?= $pensionEmployeeRate ?>" required style="width:100%;padding:6px;font-weight:bold;font-size:13px">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700;font-size:12px">🏢 <?= current_locale() === 'am' ? 'የአሰሪ ድርሻ (%)' : 'Employer Pension %' ?></label>
                    <input type="number" step="0.5" min="0" max="30" name="pension_employer_rate" value="<?= $pensionEmployerRate ?>" required style="width:100%;padding:6px;font-weight:bold;font-size:13px">
                </div>
            </div>
            <?php if (has_role(['super_admin', 'general_manager'])): ?>
                <button class="btn secondary" type="submit" style="width:100%;padding:6px;font-size:12px">
                    💾 <?= current_locale() === 'am' ? 'የጡረታ ተመን አስቀምጥ' : 'Save Pension Rates' ?>
                </button>
            <?php endif; ?>
        </form>

        <!-- Income Tax Brackets Summary Preview -->
        <div style="background:var(--hover);border-radius:6px;padding:10px;margin-bottom:12px;border:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <strong style="font-size:12px">📊 <?= current_locale() === 'am' ? 'የስራ ግብር እርከኖች ዝርዝር (Tax Brackets)' : 'Current Tax Brackets' ?></strong>
                <small class="text-muted"><?= current_locale() === 'am' ? 'ተራማጅ ስሌት' : 'Progressive' ?></small>
            </div>
            <table style="width:100%;font-size:11.5px;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);text-align:left;color:var(--text-muted)">
                        <th style="padding:3px 4px"><?= current_locale() === 'am' ? 'የገቢ ክልል (ETB)' : 'Income Range' ?></th>
                        <th style="padding:3px 4px;text-align:center"><?= current_locale() === 'am' ? 'ተመን' : 'Rate' ?></th>
                        <th style="padding:3px 4px;text-align:right"><?= current_locale() === 'am' ? 'ተቀናሽ' : 'Deduction' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($taxBrackets as $b): ?>
                        <tr style="border-bottom:1px solid rgba(0,0,0,0.05)">
                            <td style="padding:3px 4px">
                                <?= number_format($b['min']) ?> — <?= ($b['max'] !== null && $b['max'] !== '') ? number_format((float)$b['max']) . ' ብር' : 'ከዚህ በላይ' ?>
                            </td>
                            <td style="padding:3px 4px;text-align:center;font-weight:700;color:<?= (float)$b['rate'] === 0.0 ? '#2e7d32' : '#c62828' ?>">
                                <?= $b['rate'] ?>%
                            </td>
                            <td style="padding:3px 4px;text-align:right;color:var(--text-muted)">
                                -<?= number_format((float)$b['deduction'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (has_role(['super_admin', 'general_manager'])): ?>
            <button type="button" class="btn" onclick="openTaxModal()" style="width:100%;padding:9px;background:#1565c0;border-color:#1565c0;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px">
                ⚙️ <?= current_locale() === 'am' ? 'ሁሉንም የስራ ግብር እርከኖች በ Popup አስተካክል' : 'Customize All Tax Brackets' ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- PAYROLL RUNS & PAYSLIPS -->
<div class="card">
    <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'የደመወዝ ክፍያ ዙሮች፣ ቦነስ መጨመሪያ እና የክፍያ መዝገብ' : 'Payroll Runs & Payout Center' ?></h3>
    <?php if (empty($runs)): ?>
        <p class="text-muted" style="padding:20px;text-align:center"><?= current_locale() === 'am' ? 'ምንም አይነት የተመዘገበ የደመወዝ ዙር የለም። ከላይ አዲስ ዙር ይጀምሩ።' : 'No payroll runs recorded.' ?></p>
    <?php else: ?>
        <?php foreach ($runs as $run): ?>
            <?php
            $payslipsStmt = db()->prepare("SELECT p.*, e.full_name, e.employee_number FROM fin_payslips p JOIN employees e ON e.id = p.employee_id WHERE p.payroll_run_id = ? ORDER BY e.full_name ASC");
            $payslipsStmt->execute([$run['id']]);
            $payslips = $payslipsStmt->fetchAll();
            $canFinalize = payroll_can_finalize($run['period_start'], $run['period_end']);
            $isFinalized = ($run['status'] === 'finalized');
            
            $totalGross     = array_sum(array_column($payslips, 'gross_pay'));
            $totalAllowances= array_sum(array_column($payslips, 'allowances'));
            $totalPension   = array_sum(array_column($payslips, 'pension_employee'));
            $totalTax       = array_sum(array_column($payslips, 'tax_amount'));
            $totalNet       = array_sum(array_column($payslips, 'net_pay'));
            $totalPaid      = array_sum(array_column($payslips, 'total_paid'));
            $totalRemaining = max(0, $totalNet - $totalPaid);
            ?>
            <div style="border:1px solid var(--border);border-radius:10px;padding:18px;margin-bottom:24px;background:var(--card);box-shadow:0 2px 6px rgba(0,0,0,0.04)">
                
                <!-- Run Header -->
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;border-bottom:1px solid var(--border);padding-bottom:12px">
                    <div>
                        <span style="font-size:17px;font-weight:800;color:var(--text)">
                            📅 <?= eth_date($run['period_start']) ?> — <?= eth_date($run['period_end']) ?>
                        </span>
                        <span class="badge <?= $isFinalized ? 'milking' : 'dry' ?>" style="margin-left:8px;font-size:12px">
                            <?= $isFinalized ? (current_locale() === 'am' ? '✓ የጸደቀ / ክፍያ የተጀመረ' : 'Finalized / Active') : (current_locale() === 'am' ? '⏳ ረቂቅ (በዝግጅት ላይ)' : 'Draft') ?>
                        </span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <?php if (!$isFinalized): ?>
                            <form method="post" style="display:inline"><?= csrf_field() ?>
                                <input type="hidden" name="recalculate_run" value="1">
                                <input type="hidden" name="run_id" value="<?= (int)$run['id'] ?>">
                                <button class="btn secondary" type="submit" style="padding:6px 12px;font-size:12px" title="የግብር እርከኖች ከተቀየሩ ስሌቶቹን በአዲሱ ተመን ያድሳል">
                                    🔄 <?= current_locale() === 'am' ? 'ስሌቶችን አድስ' : 'Recalculate' ?>
                                </button>
                            </form>
                            <?php if ($canFinalize): ?>
                                <form method="post" style="display:inline"><?= csrf_field() ?>
                                    <input type="hidden" name="finalize_run" value="<?= (int)$run['id'] ?>">
                                    <button class="btn" type="submit" style="padding:6px 16px;background:#1e5631;border-color:#1e5631" onclick="return confirm('ደመወዙን በኢትዮጵያ የግብርና የጡረታ ህግ መሠረት አጽድቀህ የክፍያ ሰነዶችን ማመንጨት ትፈልጋለህ?');">
                                        ✓ <?= current_locale() === 'am' ? 'ደመወዙን አጽድቅ (Finalize)' : 'Finalize Run' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge dry" style="padding:6px 12px">⏳ <?= current_locale() === 'am' ? 'የሰራተኞች መገኘት (Attendance) ገና አልተጠናቀቀም' : 'Attendance pending' ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="?export=csv&run_id=<?= (int)$run['id'] ?>" class="btn secondary" style="padding:6px 14px;font-size:12.5px">
                                ⬇️ <?= current_locale() === 'am' ? 'በ CSV አውርድ (Export)' : 'Export CSV' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Run KPI Tiles -->
                <?php if (!empty($payslips)): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:10px;margin-bottom:16px">
                    <div style="background:var(--hover);padding:10px;border-radius:6px;border-left:3px solid var(--text-muted)">
                        <div style="font-size:11px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'ጠቅላላ ገቢ (Gross)' : 'Gross Pay' ?></div>
                        <div style="font-size:14px;font-weight:700"><?= number_format($totalGross, 2) ?> ETB</div>
                    </div>
                    <?php if ($totalAllowances > 0): ?>
                    <div style="background:var(--hover);padding:10px;border-radius:6px;border-left:3px solid #00897b">
                        <div style="font-size:11px;color:#00897b"><?= current_locale() === 'am' ? 'የተጨመረ ቦነስ/አበል' : 'Total Bonuses' ?></div>
                        <div style="font-size:14px;font-weight:700;color:#00897b">+<?= number_format($totalAllowances, 2) ?> ETB</div>
                    </div>
                    <?php endif; ?>
                    <div style="background:var(--hover);padding:10px;border-radius:6px;border-left:3px solid #e65100">
                        <div style="font-size:11px;color:#e65100"><?= current_locale() === 'am' ? "ጡረታ {$pensionEmployeeRate}%" : 'Pension' ?></div>
                        <div style="font-size:14px;font-weight:700;color:#e65100"><?= number_format($totalPension, 2) ?> ETB</div>
                    </div>
                    <div style="background:var(--hover);padding:10px;border-radius:6px;border-left:3px solid #c2185b">
                        <div style="font-size:11px;color:#c2185b"><?= current_locale() === 'am' ? 'የስራ ግብር (Tax)' : 'Tax' ?></div>
                        <div style="font-size:14px;font-weight:700;color:#c2185b"><?= number_format($totalTax, 2) ?> ETB</div>
                    </div>
                    <div style="background:var(--hover);padding:10px;border-radius:6px;border-left:3px solid var(--primary)">
                        <div style="font-size:11px;color:var(--primary)"><?= current_locale() === 'am' ? 'የተጣራ ደሞዝ (Net)' : 'Net Pay' ?></div>
                        <div style="font-size:14px;font-weight:700;color:var(--primary)"><?= number_format($totalNet, 2) ?> ETB</div>
                    </div>
                    <div style="background:var(--hover);padding:10px;border-radius:6px;border-left:3px solid #2e7d32">
                        <div style="font-size:11px;color:#2e7d32"><?= current_locale() === 'am' ? 'የተከፈለ' : 'Paid' ?></div>
                        <div style="font-size:14px;font-weight:700;color:#2e7d32"><?= number_format($totalPaid, 2) ?> ETB</div>
                    </div>
                    <div style="background:var(--hover);padding:10px;border-radius:6px;border-left:3px solid <?= $totalRemaining > 0 ? '#c62828' : '#2e7d32' ?>">
                        <div style="font-size:11px;color:<?= $totalRemaining > 0 ? '#c62828' : '#2e7d32' ?>"><?= current_locale() === 'am' ? 'ቀሪ ያልተከፈለ' : 'Unpaid' ?></div>
                        <div style="font-size:14px;font-weight:700;color:<?= $totalRemaining > 0 ? '#c62828' : '#2e7d32' ?>"><?= number_format($totalRemaining, 2) ?> ETB</div>
                    </div>
                </div>

                <!-- Payout Filter Toolbar -->
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
                    <div style="display:flex;gap:6px">
                        <button type="button" class="btn secondary" style="padding:4px 12px;font-size:12px" onclick="filterPayslipTable(this, 'all')">
                            🔘 <?= current_locale() === 'am' ? 'ሁሉንም ሰራተኞች አሳይ' : 'All Staff' ?> (<?= count($payslips) ?>)
                        </button>
                        <button type="button" class="btn" style="padding:4px 12px;font-size:12px;background:#c62828;border-color:#c62828" onclick="filterPayslipTable(this, 'unpaid')">
                            🔴 <?= current_locale() === 'am' ? 'ያልተከፈላቸውን ብቻ አሳይ' : 'Unpaid Only' ?> (<?= count(array_filter($payslips, fn($x) => ((float)$x['net_pay'] - (float)$x['total_paid']) > 0.01)) ?>)
                        </button>
                    </div>
                    <span class="text-muted" style="font-size:12px">
                        💡 <?= current_locale() === 'am' ? 'ደመወዝ ለመክፈል ለእያንዳንዱ ሰራተኛ <strong>"💳 ክፈል"</strong> የሚለውን አዝራር ይጫኑ።' : 'Click "Pay" to record salary disbursement.' ?>
                    </span>
                </div>

                <!-- Payslips Table -->
                <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th><?= current_locale() === 'am' ? 'ሰራተኛ' : 'Employee' ?></th>
                                <th style="text-align:right"><?= current_locale() === 'am' ? 'መሰረታዊ' : 'Base' ?></th>
                                <th style="text-align:center"><?= current_locale() === 'am' ? 'ቀን' : 'Days' ?></th>
                                <th style="text-align:right"><?= current_locale() === 'am' ? 'ጠቅላላ ገቢ' : 'Gross' ?></th>
                                <th style="text-align:right;color:#00897b"><?= current_locale() === 'am' ? 'ቦነስ/አበል' : 'Bonus' ?></th>
                                <th style="text-align:right;color:#e65100"><?= current_locale() === 'am' ? "ጡረታ {$pensionEmployeeRate}%" : 'Pension' ?></th>
                                <th style="text-align:right;color:#c2185b"><?= current_locale() === 'am' ? 'ስራ ግብር' : 'Tax' ?></th>
                                <th style="text-align:right;color:var(--primary)"><?= current_locale() === 'am' ? 'የተጣራ ደሞዝ' : 'Net' ?></th>
                                <th style="text-align:right;color:#2e7d32"><?= current_locale() === 'am' ? 'የተከፈለ' : 'Paid' ?></th>
                                <th style="text-align:right"><?= current_locale() === 'am' ? 'ቀሪ' : 'Remaining' ?></th>
                                <th style="text-align:center"><?= current_locale() === 'am' ? 'ተግባር (ክፍያና ቦነስ)' : 'Actions' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payslips as $p): ?>
                                <?php $remaining = max(0, (float)$p['net_pay'] - (float)$p['total_paid']); ?>
                                <tr data-unpaid="<?= $remaining > 0.01 ? '1' : '0' ?>">
                                    <td>
                                        <strong><?= h($p['full_name']) ?></strong><br>
                                        <small class="text-muted"><?= h($p['employee_number'] ?? '') ?></small>
                                    </td>
                                    <td style="text-align:right"><?= number_format($p['base_salary'], 2) ?></td>
                                    <td style="text-align:center"><?= (int)$p['days_present'] ?> ቀ</td>
                                    <td style="text-align:right"><strong><?= number_format($p['gross_pay'] > 0 ? $p['gross_pay'] : $p['base_salary'], 2) ?></strong></td>
                                    <td style="text-align:right;color:#00897b">
                                        <?php if ((float)$p['allowances'] > 0): ?>
                                            <strong>+<?= number_format($p['allowances'], 2) ?></strong>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;color:#e65100"><?= number_format($p['pension_employee'], 2) ?></td>
                                    <td style="text-align:right;color:#c2185b"><?= number_format($p['tax_amount'], 2) ?></td>
                                    <td style="text-align:right"><strong style="color:var(--primary)"><?= number_format($p['net_pay'], 2) ?> ETB</strong></td>
                                    <td style="text-align:right;color:#2e7d32;font-weight:600"><?= number_format($p['total_paid'], 2) ?></td>
                                    <td style="text-align:right">
                                        <?php if ($remaining > 0): ?>
                                            <span class="badge sold"><?= number_format($remaining, 2) ?> ETB</span>
                                        <?php else: ?>
                                            <span class="badge milking">✓ 100% ተከፍሏል</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;white-space:nowrap">
                                        <div style="display:flex;gap:4px;justify-content:center;align-items:center;flex-wrap:wrap">
                                            <!-- Add Bonus/Allowance Button -->
                                            <button class="btn secondary" type="button" 
                                                    onclick="openBonusModal(<?= (int)$p['id'] ?>, '<?= addslashes(h($p['full_name'])) ?>', <?= (float)$p['allowances'] ?>)" 
                                                    style="padding:3px 7px;font-size:11px" title="በዚህ ወር ጥሩ ስለሰራ ቦነስ ወይም አበል ጨምር">
                                                🎁 ቦነስ
                                            </button>

                                            <!-- Pay Button -->
                                            <?php if ($remaining > 0 && $isFinalized): ?>
                                                <button class="btn" type="button" 
                                                        onclick="openPaymentModal(<?= (int)$p['id'] ?>, '<?= addslashes(h($p['full_name'])) ?>', <?= $remaining ?>)" 
                                                        style="padding:3px 8px;font-size:11px;background:#1e5631;border-color:#1e5631">
                                                    💳 ክፈል
                                                </button>
                                            <?php elseif (!$isFinalized): ?>
                                                <span class="text-muted" style="font-size:11px">ይጽደቅ</span>
                                            <?php else: ?>
                                                <span style="color:#2e7d32;font-size:11px;font-weight:700">✓ ተጠናቋል</span>
                                            <?php endif; ?>

                                            <!-- Payslip Print Link -->
                                            <a href="<?= BASE_URL ?>/modules/finance/payslip_print.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn secondary" style="padding:3px 7px;font-size:11px" title="የደመወዝ ወረቀት አትም">
                                                🖨️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- SALARY PAYMENT MODAL -->
<div id="paymentModal" class="no-print" style="display:none;position:fixed;z-index:2000;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.55);overflow-y:auto;padding:20px 10px;">
    <div style="background:var(--card);max-width:480px;margin:50px auto;padding:24px;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.25);border:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:10px">
            <h3 style="margin:0">💳 <?= current_locale() === 'am' ? 'የሰራተኛ ደመወዝ ክፍያ መዝግብ' : 'Record Salary Payment' ?></h3>
            <button type="button" onclick="closePaymentModal()" style="background:none;border:none;font-size:22px;cursor:pointer">&times;</button>
        </div>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="record_payment" value="1">
            <input type="hidden" name="payslip_id" id="modalPayslipId" value="">

            <div style="background:var(--hover);padding:10px 14px;border-radius:6px;margin-bottom:14px">
                <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የሰራተኛ ስም፦' : 'Employee:' ?></div>
                <strong id="modalEmpName" style="font-size:16px"></strong>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale() === 'am' ? 'ቀሪ ያልተከፈለ ደመወዝ፦' : 'Unpaid Balance:' ?> <strong id="modalRemaining" style="color:#c62828"></strong> ETB</div>
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-weight:700">💵 <?= current_locale() === 'am' ? 'አሁን የሚከፈለው የገንዘብ መጠን (ETB) *' : 'Amount to Pay (ETB) *' ?></label>
                <input type="number" step="0.01" min="0.01" name="amount_paid" id="modalAmountPaid" required style="width:100%;padding:10px;font-size:18px;font-weight:800;color:var(--primary)">
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-weight:700">🏦 <?= current_locale() === 'am' ? 'ክፍያው የሚወጣበት አካውንት / ካዝና *' : 'Payment Account / Bank *' ?></label>
                <select name="bank_account_id" required style="width:100%;padding:9px;font-size:13.5px;font-weight:600">
                    <?php foreach ($bankAccounts as $acc): 
                        $icon = match($acc['account_type']) { 'bank' => '🏦', 'mobile' => '📱', default => '💵' };
                    ?>
                        <option value="<?= (int)$acc['id'] ?>">
                            <?= $icon ?> <?= h($acc['bank_name']) ?> (<?= h($acc['account_number']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:16px">
                <label style="font-weight:700">📝 <?= current_locale() === 'am' ? 'የክፍያ ማስታወሻ / ቼክ ወይም ደረሰኝ ቁጥር' : 'Notes / Reference' ?></label>
                <input type="text" name="notes" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የደሞዝ ሙሉ ክፍያ' : 'e.g. Full salary payment' ?>" style="width:100%;padding:8px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn secondary" onclick="closePaymentModal()"><?= current_locale() === 'am' ? 'ይቅር' : 'Cancel' ?></button>
                <button type="submit" class="btn" style="background:#1e5631;border-color:#1e5631;padding:8px 20px">✓ <?= current_locale() === 'am' ? 'ክፍያውን መዝግብ' : 'Record Payment' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- ADD BONUS / ALLOWANCE MODAL -->
<div id="bonusModal" class="no-print" style="display:none;position:fixed;z-index:2000;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.55);overflow-y:auto;padding:20px 10px;">
    <div style="background:var(--card);max-width:460px;margin:60px auto;padding:24px;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.25);border:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:10px">
            <h3 style="margin:0">🎁 <?= current_locale() === 'am' ? 'ተጨማሪ ቦነስ / አበል ጨምር' : 'Add Employee Bonus' ?></h3>
            <button type="button" onclick="closeBonusModal()" style="background:none;border:none;font-size:22px;cursor:pointer">&times;</button>
        </div>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="add_bonus_allowance" value="1">
            <input type="hidden" name="payslip_id" id="bonusPayslipId" value="">

            <div style="background:var(--hover);padding:10px 14px;border-radius:6px;margin-bottom:14px">
                <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የሰራተኛ ስም፦' : 'Employee:' ?></div>
                <strong id="bonusEmpName" style="font-size:16px"></strong>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale() === 'am' ? 'አሁን ያለው አበል፦' : 'Current Bonus:' ?> <strong id="bonusCurrent" style="color:#00897b"></strong> ETB</div>
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-weight:700">💰 <?= current_locale() === 'am' ? 'የሚጨመረው የቦነስ / አበል መጠን (ETB) *' : 'Bonus Amount (ETB) *' ?></label>
                <input type="number" step="0.01" min="1" name="bonus_amount" required placeholder="ለምሳሌ፡ 1000.00" style="width:100%;padding:10px;font-size:16px;font-weight:700">
            </div>

            <div class="form-group" style="margin-bottom:16px">
                <label style="font-weight:700">📝 <?= current_locale() === 'am' ? 'ምክንያት / ማስታወሻ (ለምን ተሰጠ?)' : 'Reason / Note' ?></label>
                <input type="text" name="bonus_reason" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ በዚህ ወር የላቀ አፈጻጸም ስላሳየ' : 'e.g. Excellent performance this month' ?>" style="width:100%;padding:8px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn secondary" onclick="closeBonusModal()"><?= current_locale() === 'am' ? 'ይቅር' : 'Cancel' ?></button>
                <button type="submit" class="btn" style="background:#00897b;border-color:#00897b;padding:8px 20px">✓ <?= current_locale() === 'am' ? 'ቦነሱን ጨምር' : 'Add Bonus' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 4. TAX BRACKETS CUSTOMIZATION MODAL (POPUP) -->
<div id="taxModal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
    <div class="card" style="max-width:860px;width:100%;margin:30px auto;background:var(--card);border-radius:10px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,0.3);position:relative">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:1px solid var(--border);padding-bottom:10px">
            <div>
                <h3 style="margin:0;display:flex;align-items:center;gap:8px">
                    ⚙️ <?= current_locale() === 'am' ? 'የስራ ግብር እርከኖችን በሙሉ ማስተካከያ (All Tax Brackets)' : 'Income Tax Brackets Customizer' ?>
                </h3>
                <small class="text-muted">
                    <?= current_locale() === 'am' ? 'እያንዳንዱን የደመወዝ እርከን፣ የመነሻና የማብቂያ ብር፣ የግብር መቶኛ (%) እና ተቀናሽ መጠን በነፃነት ማስተካከል ወይም አዳዲስ እርከኖች መጨመር ይችላሉ።' : 'Customize progressive thresholds, rates, deductions or add new brackets.' ?>
                </small>
            </div>
            <button type="button" onclick="closeTaxModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-muted)">&times;</button>
        </div>

        <form method="post" id="taxBracketsForm"><?= csrf_field() ?>
            <input type="hidden" name="save_tax_brackets" value="1">
            
            <div style="overflow-x:auto;margin-bottom:16px">
                <table style="width:100%;border-collapse:collapse;font-size:13px" id="taxBracketsTable">
                    <thead>
                        <tr style="background:var(--hover);border-bottom:2px solid var(--border);text-align:left">
                            <th style="padding:8px"><?= current_locale() === 'am' ? 'የእርከን ስም' : 'Bracket Name' ?></th>
                            <th style="padding:8px"><?= current_locale() === 'am' ? 'መነሻ ብር (Min ETB)' : 'Min ETB' ?></th>
                            <th style="padding:8px"><?= current_locale() === 'am' ? 'ማብቂያ ብር (Max ETB)' : 'Max ETB' ?></th>
                            <th style="padding:8px;text-align:center"><?= current_locale() === 'am' ? 'ተመን (%)' : 'Rate (%)' ?></th>
                            <th style="padding:8px"><?= current_locale() === 'am' ? 'ተቀናሽ (Deduction ETB)' : 'Deduction ETB' ?></th>
                            <th style="padding:8px;text-align:center"><?= current_locale() === 'am' ? 'እርምጃ' : 'Action' ?></th>
                        </tr>
                    </thead>
                    <tbody id="taxBracketsTbody">
                        <?php foreach ($taxBrackets as $idx => $b): ?>
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:6px">
                                <input type="text" name="brackets[<?= $idx ?>][label]" value="<?= h($b['label'] ?? '') ?>" placeholder="እርከን <?= $idx ?>" style="width:100%;padding:6px;font-size:12.5px" required>
                            </td>
                            <td style="padding:6px">
                                <input type="number" step="0.01" min="0" name="brackets[<?= $idx ?>][min]" value="<?= (float)($b['min'] ?? 0) ?>" style="width:100%;padding:6px;font-size:12.5px" required>
                            </td>
                            <td style="padding:6px">
                                <input type="text" name="brackets[<?= $idx ?>][max]" value="<?= ($b['max'] !== null && $b['max'] !== '') ? (float)$b['max'] : '' ?>" placeholder="<?= current_locale() === 'am' ? 'ያልተገደበ (ባዶ)' : 'Unlimited (Empty)' ?>" style="width:100%;padding:6px;font-size:12.5px">
                            </td>
                            <td style="padding:6px;text-align:center">
                                <div style="display:inline-flex;align-items:center;gap:4px">
                                    <input type="number" step="0.1" min="0" max="100" name="brackets[<?= $idx ?>][rate]" value="<?= (float)($b['rate'] ?? 0) ?>" style="width:65px;padding:6px;font-size:12.5px;font-weight:700;text-align:center" required> %
                                </div>
                            </td>
                            <td style="padding:6px">
                                <input type="number" step="0.01" min="0" name="brackets[<?= $idx ?>][deduction]" value="<?= (float)($b['deduction'] ?? 0) ?>" style="width:100%;padding:6px;font-size:12.5px" required>
                            </td>
                            <td style="padding:6px;text-align:center">
                                <button type="button" class="btn secondary" onclick="removeTaxBracketRow(this)" style="padding:4px 8px;color:#c62828" title="ሰርዝ">&times;</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:18px">
                <button type="button" class="btn secondary" onclick="addTaxBracketRow()" style="padding:6px 14px;font-size:12.5px">
                    ➕ <?= current_locale() === 'am' ? 'አዲስ እርከን ጨምር (Add Bracket)' : 'Add Bracket' ?>
                </button>
                <button type="button" class="btn secondary" onclick="resetTaxBracketsDefault()" style="padding:6px 14px;font-size:12.5px;color:#d84315">
                    🔄 <?= current_locale() === 'am' ? 'ወደ መደበኛው የኢትዮጵያ ህግ መልስ (Reset Default)' : 'Reset Default' ?>
                </button>
            </div>

            <div style="background:var(--hover);padding:10px 14px;border-radius:6px;font-size:12px;margin-bottom:18px;color:var(--text-muted);border-left:3px solid #1565c0">
                💡 <strong><?= current_locale() === 'am' ? 'የስሌት መመሪያ፦' : 'Note:' ?></strong>
                <?= current_locale() === 'am' ? 'የመጨረሻው እርከን ማብቂያ ብር (Max ETB) ላይ ባዶ ሲሆን <strong>"ከዚህ በላይ/ያልተገደበ"</strong> ይሆናል። ስሌቱ፡ (የተጣራ ግብር የሚከፈልበት ገቢ × ተመን %) - ተቀናሽ ነው።' : 'Leave Max ETB empty for the highest bracket. Tax = (Taxable Income * Rate%) - Deduction.' ?>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn secondary" onclick="closeTaxModal()"><?= current_locale() === 'am' ? 'ይቅር' : 'Cancel' ?></button>
                <button type="submit" class="btn" style="background:#1565c0;border-color:#1565c0;padding:8px 24px;font-weight:700">
                    💾 <?= current_locale() === 'am' ? 'ሁሉንም እርከኖች መዝግብና አስቀምጥ (Save Tax Brackets)' : 'Save All Tax Brackets' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openTaxModal() {
    document.getElementById('taxModal').style.display = 'flex';
}

function closeTaxModal() {
    document.getElementById('taxModal').style.display = 'none';
}

function removeTaxBracketRow(btn) {
    const tbody = document.getElementById('taxBracketsTbody');
    if (tbody.querySelectorAll('tr').length <= 1) {
        alert('ቢያንስ አንድ እርከን መኖር አለበት!');
        return;
    }
    btn.closest('tr').remove();
    reindexTaxBracketRows();
}

function addTaxBracketRow() {
    const tbody = document.getElementById('taxBracketsTbody');
    const newIdx = tbody.querySelectorAll('tr').length;
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border)';
    tr.innerHTML = `
        <td style="padding:6px">
            <input type="text" name="brackets[${newIdx}][label]" value="እርከን ${newIdx}" placeholder="እርከን ስም" style="width:100%;padding:6px;font-size:12.5px" required>
        </td>
        <td style="padding:6px">
            <input type="number" step="0.01" min="0" name="brackets[${newIdx}][min]" value="0" style="width:100%;padding:6px;font-size:12.5px" required>
        </td>
        <td style="padding:6px">
            <input type="text" name="brackets[${newIdx}][max]" value="" placeholder="ያልተገደበ (ባዶ)" style="width:100%;padding:6px;font-size:12.5px">
        </td>
        <td style="padding:6px;text-align:center">
            <div style="display:inline-flex;align-items:center;gap:4px">
                <input type="number" step="0.1" min="0" max="100" name="brackets[${newIdx}][rate]" value="0" style="width:65px;padding:6px;font-size:12.5px;font-weight:700;text-align:center" required> %
            </div>
        </td>
        <td style="padding:6px">
            <input type="number" step="0.01" min="0" name="brackets[${newIdx}][deduction]" value="0" style="width:100%;padding:6px;font-size:12.5px" required>
        </td>
        <td style="padding:6px;text-align:center">
            <button type="button" class="btn secondary" onclick="removeTaxBracketRow(this)" style="padding:4px 8px;color:#c62828" title="ሰርዝ">&times;</button>
        </td>
    `;
    tbody.appendChild(tr);
}

function reindexTaxBracketRows() {
    const rows = document.querySelectorAll('#taxBracketsTbody tr');
    rows.forEach((tr, idx) => {
        tr.querySelectorAll('input').forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/brackets\[\d+\]/, `brackets[${idx}]`));
            }
        });
    });
}

function resetTaxBracketsDefault() {
    if (!confirm('ወደ መደበኛው የኢትዮጵያ የግብር ህግ (ከ 0-2,000 ነፃ፣ 2,001-4,000 15%፣ 4,001-7,000 20%፣ 7,001-10,000 25%፣ 10,001-14,000 30%፣ 14,001+ 35%) መመለስ ይፈልጋሉ?')) {
        return;
    }
    const defaults = [
        {label: 'ከስራ ግብር ነፃ (Exempt)', min: 0, max: '2000', rate: 0, deduction: 0},
        {label: 'እርከን 1 (15%)', min: 2001, max: '4000', rate: 15, deduction: 300},
        {label: 'እርከን 2 (20%)', min: 4001, max: '7000', rate: 20, deduction: 500},
        {label: 'እርከን 3 (25%)', min: 7001, max: '10000', rate: 25, deduction: 850},
        {label: 'እርከን 4 (30%)', min: 10001, max: '14000', rate: 30, deduction: 1350},
        {label: 'እርከን 5 (35% እና ከዚያ በላይ)', min: 14001, max: '', rate: 35, deduction: 2050}
    ];
    const tbody = document.getElementById('taxBracketsTbody');
    tbody.innerHTML = '';
    defaults.forEach((d, idx) => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--border)';
        tr.innerHTML = `
            <td style="padding:6px">
                <input type="text" name="brackets[${idx}][label]" value="${d.label}" style="width:100%;padding:6px;font-size:12.5px" required>
            </td>
            <td style="padding:6px">
                <input type="number" step="0.01" min="0" name="brackets[${idx}][min]" value="${d.min}" style="width:100%;padding:6px;font-size:12.5px" required>
            </td>
            <td style="padding:6px">
                <input type="text" name="brackets[${idx}][max]" value="${d.max}" placeholder="ያልተገደበ (ባዶ)" style="width:100%;padding:6px;font-size:12.5px">
            </td>
            <td style="padding:6px;text-align:center">
                <div style="display:inline-flex;align-items:center;gap:4px">
                    <input type="number" step="0.1" min="0" max="100" name="brackets[${idx}][rate]" value="${d.rate}" style="width:65px;padding:6px;font-size:12.5px;font-weight:700;text-align:center" required> %
                </div>
            </td>
            <td style="padding:6px">
                <input type="number" step="0.01" min="0" name="brackets[${idx}][deduction]" value="${d.deduction}" style="width:100%;padding:6px;font-size:12.5px" required>
            </td>
            <td style="padding:6px;text-align:center">
                <button type="button" class="btn secondary" onclick="removeTaxBracketRow(this)" style="padding:4px 8px;color:#c62828" title="ሰርዝ">&times;</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openPaymentModal(payslipId, empName, remaining) {
    document.getElementById('modalPayslipId').value = payslipId;
    document.getElementById('modalEmpName').textContent = empName;
    document.getElementById('modalRemaining').textContent = remaining.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('modalAmountPaid').value = remaining.toFixed(2);
    document.getElementById('paymentModal').style.display = 'block';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function openBonusModal(payslipId, empName, currentBonus) {
    document.getElementById('bonusPayslipId').value = payslipId;
    document.getElementById('bonusEmpName').textContent = empName;
    document.getElementById('bonusCurrent').textContent = currentBonus.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('bonusModal').style.display = 'block';
}

function closeBonusModal() {
    document.getElementById('bonusModal').style.display = 'none';
}

function filterPayslipTable(btn, mode) {
    const parent = btn.closest('div[style*="border:1px solid"]');
    if (!parent) return;
    const rows = parent.querySelectorAll('tbody tr');
    rows.forEach(r => {
        if (mode === 'all') {
            r.style.display = '';
        } else {
            const isUnpaid = r.getAttribute('data-unpaid') === '1';
            r.style.display = isUnpaid ? '' : 'none';
        }
    });
}
function switchRunMode(mode) {
    const fMonth = document.getElementById('runFormMonth');
    const fCustom = document.getElementById('runFormCustom');
    const bMonth = document.getElementById('btnRunModeMonth');
    const bCustom = document.getElementById('btnRunModeCustom');

    if (mode === 'month') {
        fMonth.style.display = 'block';
        fCustom.style.display = 'none';
        bMonth.className = 'btn';
        bCustom.className = 'btn secondary';
    } else {
        fMonth.style.display = 'none';
        fCustom.style.display = 'block';
        bMonth.className = 'btn secondary';
        bCustom.className = 'btn';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
