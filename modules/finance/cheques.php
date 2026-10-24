<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['record_cheque'])) {
            $chequeId = cheque_create([
                'cheque_type'     => $_POST['cheque_type'],
                'cheque_number'   => $_POST['cheque_number'],
                'bank_name'       => $_POST['bank_name'],
                'bank_account_id' => !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null,
                'payee_or_drawer' => $_POST['payee_or_drawer'],
                'amount'          => (float)$_POST['amount'],
                'issue_date'      => $_POST['issue_date'],
                'due_date'        => $_POST['due_date'],
                'related_module'  => $_POST['related_module'] ?? 'finance',
                'notes'           => $_POST['notes'] ?? '',
                'recorded_by'     => current_user()['id'],
            ]);
            $typeLbl = $_POST['cheque_type'] === 'received' 
                ? (current_locale() === 'am' ? 'የተቀበልነው ቼክ' : 'Received Cheque')
                : (current_locale() === 'am' ? 'የተሰጠ ቼክ' : 'Issued Cheque');
            flash(current_locale() === 'am' ? "✓ {$typeLbl} #{$_POST['cheque_number']} በተሳካ ሁኔታ ተመዝግቧል!" : "Cheque #{$_POST['cheque_number']} recorded successfully.");
        } elseif (isset($_POST['clear_cheque'])) {
            $chequeId      = (int)$_POST['cheque_id'];
            $bankAccountId = (int)$_POST['clear_bank_account_id'];
            $clearDate     = !empty($_POST['clear_date']) ? $_POST['clear_date'] : date('Y-m-d');
            $clearNotes    = trim($_POST['clear_notes'] ?? '');

            cheque_clear($chequeId, $bankAccountId, $clearDate, $clearNotes);
            flash(current_locale() === 'am' ? '✓ ቼኩ በተሳካ ሁኔታ ተመንዝሮ በባንክ ሒሳብ ውስጥ እንቅስቃሴው ተመዝግቧል!' : 'Cheque cleared and recorded in bank account transactions.');
        } elseif (isset($_POST['bounce_cheque'])) {
            $chequeId = (int)$_POST['cheque_id'];
            $reason   = trim($_POST['bounce_reason'] ?? 'ሒሳብ የለውም / Bounced');
            cheque_bounce($chequeId, $reason);
            flash(current_locale() === 'am' ? '⚠️ ቼኩ እንደተመለሰ (Bounced) ተመዝግቧል!' : 'Cheque marked as bounced.', 'warning');
        } elseif (isset($_POST['cancel_cheque'])) {
            $chequeId = (int)$_POST['cheque_id'];
            $reason   = trim($_POST['cancel_reason'] ?? 'የተሰረዘ / Cancelled');
            cheque_cancel($chequeId, $reason);
            flash(current_locale() === 'am' ? 'ቼኩ በተሳካ ሁኔታ ተሰርዟል!' : 'Cheque cancelled.');
        }
        header('Location: ' . BASE_URL . '/modules/finance/cheques.php');
        exit;
    } catch (Throwable $e) {
        flash(current_locale() === 'am' ? 'ስህተት፦ ' . $e->getMessage() : 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

// Bank accounts for selection
$bankAccounts = db()->query("SELECT id, bank_name, account_name, account_number, account_type FROM fin_bank_accounts ORDER BY id ASC")->fetchAll();

// Filters
$typeFilter   = $_GET['type'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');

// Build query
$where = ["1=1"];
$params = [];

if ($typeFilter === 'issued' || $typeFilter === 'received') {
    $where[] = "c.cheque_type = ?";
    $params[] = $typeFilter;
}

if (in_array($statusFilter, ['pending', 'cleared', 'bounced', 'cancelled'])) {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $where[] = "(c.cheque_number LIKE ? OR c.payee_or_drawer LIKE ? OR c.bank_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereSql = implode(' AND ', $where);

$chequesStmt = db()->prepare(
    "SELECT c.*, b.bank_name AS org_bank_name, b.account_number AS org_account_number,
            cb.bank_name AS cleared_bank_name, u.name AS recorded_by_name
     FROM fin_cheques c
     LEFT JOIN fin_bank_accounts b ON b.id = c.bank_account_id
     LEFT JOIN fin_bank_accounts cb ON cb.id = c.cleared_bank_account_id
     LEFT JOIN users u ON u.id = c.recorded_by
     WHERE {$whereSql}
     ORDER BY c.due_date DESC, c.id DESC"
);
$chequesStmt->execute($params);
$cheques = $chequesStmt->fetchAll();

// KPI Calculations
$kpis = db()->query(
    "SELECT 
        SUM(CASE WHEN cheque_type = 'issued' AND status = 'pending' THEN amount ELSE 0 END) AS pending_issued_amount,
        SUM(CASE WHEN cheque_type = 'issued' AND status = 'pending' THEN 1 ELSE 0 END) AS pending_issued_count,
        SUM(CASE WHEN cheque_type = 'received' AND status = 'pending' THEN amount ELSE 0 END) AS pending_received_amount,
        SUM(CASE WHEN cheque_type = 'received' AND status = 'pending' THEN 1 ELSE 0 END) AS pending_received_count,
        SUM(CASE WHEN status = 'cleared' THEN amount ELSE 0 END) AS cleared_total_amount,
        SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) AS bounced_count
     FROM fin_cheques"
)->fetch();

$pendingIssuedAmt   = (float)($kpis['pending_issued_amount'] ?? 0);
$pendingIssuedCnt   = (int)($kpis['pending_issued_count'] ?? 0);
$pendingReceivedAmt = (float)($kpis['pending_received_amount'] ?? 0);
$pendingReceivedCnt = (int)($kpis['pending_received_count'] ?? 0);
$clearedTotalAmt    = (float)($kpis['cleared_total_amount'] ?? 0);
$bouncedCnt         = (int)($kpis['bounced_count'] ?? 0);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">📑 <?= current_locale() === 'am' ? 'የቼክ ሂሳቦችና እንቅስቃሴ ማዕከል (Cheque Management)' : 'Cheque Management System' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' 
                ? 'ድርጅቱ የሚሰጣቸውን (Issued/Payable) እና ከደንበኞች የሚቀበላቸውን (Received/Receivable) ቼኮች መመዝገቢያ፣ ማጽደቂያ፣ መመንዘሪያ እና መከታተያ።' 
                : 'Track, issue, receive, clear, and manage bank cheques for inbound and outbound farm operations.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn" onclick="openIssueModal()" style="background:#1565c0;border-color:#1565c0">
            📤 <?= current_locale() === 'am' ? 'የተሰጠ ቼክ መዝግብ (Issue Cheque)' : 'Issue Cheque' ?>
        </button>
        <button type="button" class="btn" onclick="openReceiveModal()" style="background:#2e7d32;border-color:#2e7d32">
            📥 <?= current_locale() === 'am' ? 'የተቀበልነው ቼክ መዝግብ (Receive Cheque)' : 'Receive Cheque' ?>
        </button>
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">
            &larr; <?= current_locale() === 'am' ? 'ወደ ፋይናንስ ማዕከል' : 'Finance Dashboard' ?>
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:22px">
    <!-- Pending Issued (Outflow to Pay) -->
    <div class="card" style="border-left:4px solid #c62828;box-shadow:0 2px 6px rgba(0,0,0,0.04);padding:14px">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:var(--text-muted);font-weight:700">📤 <?= current_locale() === 'am' ? 'በሂደት ላይ ያሉ የተሰጡ ቼኮች' : 'Pending Issued Cheques' ?></span>
            <span class="badge" style="background:#ffebee;color:#c62828"><?= $pendingIssuedCnt ?> <?= current_locale() === 'am' ? 'ቼኮች' : 'cheques' ?></span>
        </div>
        <div style="font-size:20px;font-weight:800;color:#c62828;margin-top:8px">
            <?= number_format($pendingIssuedAmt, 2) ?> <small style="font-size:12px">ETB</small>
        </div>
        <small class="text-muted"><?= current_locale() === 'am' ? 'ከባንክ ሊወጣ የሚጠባበቅ ክፍያ' : 'Awaiting clearance from bank' ?></small>
    </div>

    <!-- Pending Received (Inflow to Collect) -->
    <div class="card" style="border-left:4px solid #2e7d32;box-shadow:0 2px 6px rgba(0,0,0,0.04);padding:14px">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:var(--text-muted);font-weight:700">📥 <?= current_locale() === 'am' ? 'በሂደት ላይ ያሉ የተቀበልናቸው' : 'Pending Received Cheques' ?></span>
            <span class="badge" style="background:#e8f5e9;color:#2e7d32"><?= $pendingReceivedCnt ?> <?= current_locale() === 'am' ? 'ቼኮች' : 'cheques' ?></span>
        </div>
        <div style="font-size:20px;font-weight:800;color:#2e7d32;margin-top:8px">
            <?= number_format($pendingReceivedAmt, 2) ?> <small style="font-size:12px">ETB</small>
        </div>
        <small class="text-muted"><?= current_locale() === 'am' ? 'ወደ ባንክ ገቢ የሚደረግ ገንዘብ' : 'Awaiting deposit into bank' ?></small>
    </div>

    <!-- Cleared Total -->
    <div class="card" style="border-left:4px solid #1565c0;box-shadow:0 2px 6px rgba(0,0,0,0.04);padding:14px">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:var(--text-muted);font-weight:700">✓ <?= current_locale() === 'am' ? 'የተመነዘሩ / የተጣሩ ቼኮች ድምር' : 'Cleared Cheques Total' ?></span>
            <span class="badge" style="background:#e3f2fd;color:#1565c0">✓</span>
        </div>
        <div style="font-size:20px;font-weight:800;color:#1565c0;margin-top:8px">
            <?= number_format($clearedTotalAmt, 2) ?> <small style="font-size:12px">ETB</small>
        </div>
        <small class="text-muted"><?= current_locale() === 'am' ? 'በተሳካ ሁኔታ በባንክ የተጣራ' : 'Successfully processed in bank' ?></small>
    </div>

    <!-- Bounced Cheques -->
    <div class="card" style="border-left:4px solid #ef6c00;box-shadow:0 2px 6px rgba(0,0,0,0.04);padding:14px">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:var(--text-muted);font-weight:700">⚠️ <?= current_locale() === 'am' ? 'የተመለሱ ቼኮች (Bounced)' : 'Bounced Cheques' ?></span>
            <span class="badge" style="background:#fff3e0;color:#ef6c00"><?= $bouncedCnt ?></span>
        </div>
        <div style="font-size:20px;font-weight:800;color:#ef6c00;margin-top:8px">
            <?= $bouncedCnt ?> <small style="font-size:12px"><?= current_locale() === 'am' ? 'ያልተከፈሉ' : 'records' ?></small>
        </div>
        <small class="text-muted"><?= current_locale() === 'am' ? 'ሒሳብ የሌላቸው ወይም ችግር ያለባቸው' : 'Insufficient funds / returned' ?></small>
    </div>
</div>

<!-- Filters Toolbar -->
<div class="card" style="padding:14px 18px;margin-bottom:20px">
    <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <!-- Type Filter -->
        <div style="display:flex;gap:6px;align-items:center">
            <label style="font-size:12px;font-weight:700">📌 <?= current_locale() === 'am' ? 'አይነት፦' : 'Type:' ?></label>
            <select name="type" onchange="this.form.submit()" style="padding:6px 10px;font-size:12.5px;border-radius:6px">
                <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>><?= current_locale() === 'am' ? 'ሁሉም ዓይነቶች' : 'All Types' ?></option>
                <option value="issued" <?= $typeFilter === 'issued' ? 'selected' : '' ?>>📤 <?= current_locale() === 'am' ? 'የተሰጡ ቼኮች (Issued/Payable)' : 'Issued Cheques' ?></option>
                <option value="received" <?= $typeFilter === 'received' ? 'selected' : '' ?>>📥 <?= current_locale() === 'am' ? 'የተቀበልናቸው ቼኮች (Received/Receivable)' : 'Received Cheques' ?></option>
            </select>
        </div>

        <!-- Status Filter -->
        <div style="display:flex;gap:6px;align-items:center">
            <label style="font-size:12px;font-weight:700">⏳ <?= current_locale() === 'am' ? 'ሁኔታ፦' : 'Status:' ?></label>
            <select name="status" onchange="this.form.submit()" style="padding:6px 10px;font-size:12.5px;border-radius:6px">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>><?= current_locale() === 'am' ? 'ሁሉም ሁኔታዎች' : 'All Statuses' ?></option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>⏳ <?= current_locale() === 'am' ? 'በሂደት ላይ (Pending)' : 'Pending' ?></option>
                <option value="cleared" <?= $statusFilter === 'cleared' ? 'selected' : '' ?>>✓ <?= current_locale() === 'am' ? 'የተመነዘረ / የገባ (Cleared)' : 'Cleared' ?></option>
                <option value="bounced" <?= $statusFilter === 'bounced' ? 'selected' : '' ?>>⚠️ <?= current_locale() === 'am' ? 'የተመለሰ (Bounced)' : 'Bounced' ?></option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>🚫 <?= current_locale() === 'am' ? 'የተሰረዘ (Cancelled)' : 'Cancelled' ?></option>
            </select>
        </div>

        <!-- Search Box -->
        <div style="display:flex;gap:6px;align-items:center;flex:1;min-width:220px">
            <input type="text" name="q" value="<?= h($search) ?>" placeholder="<?= current_locale() === 'am' ? 'በቼክ ቁጥር፣ በባንክ ወይም በሰው ስም ፈልግ...' : 'Search by cheque #, bank, or person...' ?>" style="width:100%;padding:6px 12px;font-size:12.5px">
            <button type="submit" class="btn secondary" style="padding:6px 14px;font-size:12.5px">🔍</button>
        </div>

        <?php if ($typeFilter !== 'all' || $statusFilter !== 'all' || !empty($search)): ?>
            <a href="<?= BASE_URL ?>/modules/finance/cheques.php" class="btn secondary" style="padding:6px 12px;font-size:12px">✕ <?= current_locale() === 'am' ? 'አጽዳ' : 'Reset' ?></a>
        <?php endif; ?>
    </form>
</div>

<!-- Cheques Table -->
<div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0">📋 <?= current_locale() === 'am' ? 'የቼኮች ዝርዝር መዝገብ' : 'Cheques Register' ?> (<?= count($cheques) ?>)</h3>
    </div>

    <?php if (empty($cheques)): ?>
        <p class="text-muted" style="text-align:center;padding:30px 10px">
            <?= current_locale() === 'am' ? 'ምንም አይነት የተመዘገበ ቼክ አልተገኘም። ከላይ "የተሰጠ ቼክ" ወይም "የተቀበልነው ቼክ" የሚለውን በመጫን ይመዝግቡ።' : 'No cheque records found. Use the buttons above to record a cheque.' ?>
        </p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);background:var(--hover);text-align:left">
                        <th style="padding:9px 10px"><?= current_locale() === 'am' ? 'የቼክ ቁጥር' : 'Cheque #' ?></th>
                        <th style="padding:9px 10px"><?= current_locale() === 'am' ? 'አይነት' : 'Type' ?></th>
                        <th style="padding:9px 10px"><?= current_locale() === 'am' ? 'የባንክ ስም' : 'Bank Name' ?></th>
                        <th style="padding:9px 10px"><?= current_locale() === 'am' ? 'የተሰጠለት / የሰጠን አካል' : 'Payee / Drawer' ?></th>
                        <th style="padding:9px 10px;text-align:right"><?= current_locale() === 'am' ? 'መጠን (ETB)' : 'Amount (ETB)' ?></th>
                        <th style="padding:9px 10px"><?= current_locale() === 'am' ? 'የተጻፈበት ቀን' : 'Issue Date' ?></th>
                        <th style="padding:9px 10px"><?= current_locale() === 'am' ? 'የሚመነዘርበት ቀን' : 'Due Date' ?></th>
                        <th style="padding:9px 10px;text-align:center"><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                        <th style="padding:9px 10px;text-align:center"><?= current_locale() === 'am' ? 'እርምጃዎች' : 'Actions' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cheques as $c): ?>
                        <?php
                        $isIssued = ($c['cheque_type'] === 'issued');
                        $isPending = ($c['status'] === 'pending');
                        $isCleared = ($c['status'] === 'cleared');
                        $isBounced = ($c['status'] === 'bounced');
                        $isCancelled = ($c['status'] === 'cancelled');

                        $badgeColor = match($c['status']) {
                            'pending'   => '#f57c00; background:#fff3e0',
                            'cleared'   => '#2e7d32; background:#e8f5e9',
                            'bounced'   => '#c62828; background:#ffebee',
                            'cancelled' => '#757575; background:#eeeeee',
                            default     => '#555; background:#eee'
                        };

                        $statusAm = match($c['status']) {
                            'pending'   => '⏳ በሂደት ላይ',
                            'cleared'   => '✓ ተመንዝሯል',
                            'bounced'   => '⚠️ ተመልሷል',
                            'cancelled' => '🚫 የተሰረዘ',
                            default     => $c['status']
                        };
                        ?>
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:10px">
                                <strong style="font-size:13.5px;color:var(--text)">#<?= h($c['cheque_number']) ?></strong>
                                <?php if (!empty($c['notes'])): ?>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px"><?= h($c['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px">
                                <?php if ($isIssued): ?>
                                    <span class="badge" style="background:#ffebee;color:#c62828;font-size:11px">📤 <?= current_locale() === 'am' ? 'የተሰጠ' : 'Issued' ?></span>
                                <?php else: ?>
                                    <span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:11px">📥 <?= current_locale() === 'am' ? 'የተቀበልነው' : 'Received' ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px">
                                <div><strong><?= h($c['bank_name']) ?></strong></div>
                                <?php if ($c['bank_account_id'] && !empty($c['org_account_number'])): ?>
                                    <small class="text-muted"><?= h($c['org_bank_name']) ?> (<?= h($c['org_account_number']) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px">
                                <strong><?= h($c['payee_or_drawer']) ?></strong>
                            </td>
                            <td style="padding:10px;text-align:right">
                                <strong style="font-size:14px;color:<?= $isIssued ? '#c62828' : '#2e7d32' ?>">
                                    <?= $isIssued ? '-' : '+' ?><?= number_format((float)$c['amount'], 2) ?> ETB
                                </strong>
                            </td>
                            <td style="padding:10px">
                                <?= eth_date($c['issue_date']) ?>
                            </td>
                            <td style="padding:10px">
                                <span style="font-weight:<?= $isPending ? '700' : 'normal' ?>">
                                    <?= eth_date($c['due_date']) ?>
                                </span>
                            </td>
                            <td style="padding:10px;text-align:center">
                                <span class="badge" style="padding:4px 8px;font-size:11px;font-weight:700;color:<?= $badgeColor ?>">
                                    <?= current_locale() === 'am' ? $statusAm : ucfirst($c['status']) ?>
                                </span>
                                <?php if ($isCleared && !empty($c['cleared_date'])): ?>
                                    <div style="font-size:10.5px;color:var(--text-muted);margin-top:2px">
                                        <?= eth_date($c['cleared_date']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px;text-align:center">
                                <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
                                    <?php if ($isPending): ?>
                                        <button type="button" class="btn" onclick="openClearModal(<?= (int)$c['id'] ?>, '<?= h($c['cheque_number']) ?>', <?= (float)$c['amount'] ?>, '<?= h($c['cheque_type']) ?>', '<?= h($c['payee_or_drawer']) ?>')" style="padding:3px 8px;font-size:11.5px;background:#2e7d32;border-color:#2e7d32" title="<?= current_locale() === 'am' ? 'ቼኩን ወደ ባንክ ገቢ አድርግ ወይም ክፍያውን አጽድቅ' : 'Clear Cheque' ?>">
                                            ✓ <?= current_locale() === 'am' ? 'ተመንዝሯል' : 'Clear' ?>
                                        </button>
                                        <button type="button" class="btn secondary" onclick="openBounceModal(<?= (int)$c['id'] ?>, '<?= h($c['cheque_number']) ?>')" style="padding:3px 8px;font-size:11.5px;color:#ef6c00" title="<?= current_locale() === 'am' ? 'የተመለሰ ቼክ' : 'Bounce Cheque' ?>">
                                            ⚠️ <?= current_locale() === 'am' ? 'ተመልሷል' : 'Bounce' ?>
                                        </button>
                                        <button type="button" class="btn secondary" onclick="openCancelModal(<?= (int)$c['id'] ?>, '<?= h($c['cheque_number']) ?>')" style="padding:3px 8px;font-size:11.5px;color:#c62828" title="<?= current_locale() === 'am' ? 'ሰርዝ' : 'Cancel Cheque' ?>">
                                            ✕
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?= BASE_URL ?>/modules/finance/cheque_print.php?id=<?= (int)$c['id'] ?>" target="_blank" class="btn secondary" style="padding:3px 8px;font-size:11.5px" title="<?= current_locale() === 'am' ? 'የቼክ ቫውቸር አትም' : 'Print Voucher' ?>">
                                        🖨️ <?= current_locale() === 'am' ? 'ቫውቸር' : 'Voucher' ?>
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

<!-- 1. MODAL: ISSUE NEW CHEQUE (የተሰጠ ቼክ) -->
<div id="issueModal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
    <div class="card" style="max-width:580px;width:100%;margin:30px auto;background:var(--card);border-radius:10px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,0.3)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid var(--border);padding-bottom:10px">
            <h3 style="margin:0;color:#1565c0">📤 <?= current_locale() === 'am' ? 'አዲስ የተሰጠ ቼክ መዝግብ (Issue Cheque)' : 'Issue Cheque (Outflow)' ?></h3>
            <button type="button" onclick="closeIssueModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-muted)">&times;</button>
        </div>

        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="record_cheque" value="1">
            <input type="hidden" name="cheque_type" value="issued">

            <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px">
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">🔢 <?= current_locale() === 'am' ? 'የቼክ ቁጥር *' : 'Cheque Number *' ?></label>
                    <input type="text" name="cheque_number" required placeholder="ለምሳሌ፡ CHQ-889102" style="width:100%;padding:8px">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">💰 <?= current_locale() === 'am' ? 'የገንዘብ መጠን (ETB) *' : 'Amount (ETB) *' ?></label>
                    <input type="number" step="0.01" min="1" name="amount" required placeholder="0.00" style="width:100%;padding:8px;font-weight:bold">
                </div>
            </div>

            <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px">
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">🏦 <?= current_locale() === 'am' ? 'የድርጅቱ የባንክ አካውንት *' : 'Farm Bank Account *' ?></label>
                    <select name="bank_account_id" id="issueBankSelect" required style="width:100%;padding:8px" onchange="updateIssueBankName(this)">
                        <option value=""><?= current_locale() === 'am' ? '-- ባንክ ይምረጡ --' : '-- Select Bank --' ?></option>
                        <?php foreach ($bankAccounts as $ba): ?>
                            <option value="<?= (int)$ba['id'] ?>" data-bankname="<?= h($ba['bank_name']) ?>">
                                <?= h($ba['bank_name']) ?> (<?= h($ba['account_number']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="bank_name" id="issueBankName" value="Oromia Bank">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">👤 <?= current_locale() === 'am' ? 'የተሰጠለት ሰው/ድርጅት (Payee) *' : 'Payee Name *' ?></label>
                    <input type="text" name="payee_or_drawer" required placeholder="<?= current_locale() === 'am' ? 'የአቅራቢው ወይም የሰውየው ስም' : 'Supplier or Person Name' ?>" style="width:100%;padding:8px">
                </div>
            </div>

            <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px">
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">📅 <?= current_locale() === 'am' ? 'የተጻፈበት ቀን *' : 'Issue Date *' ?></label>
                    <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required style="width:100%;padding:8px">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">⏳ <?= current_locale() === 'am' ? 'የሚመነዘርበት ቀን (Due Date) *' : 'Due Date *' ?></label>
                    <input type="date" name="due_date" value="<?= date('Y-m-d') ?>" required style="width:100%;padding:8px">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-weight:700">🏢 <?= current_locale() === 'am' ? 'ተዛማጅ የስራ ክፍል / ሞጁል' : 'Related Module' ?></label>
                <select name="related_module" style="width:100%;padding:8px">
                    <option value="feed_production"><?= current_locale() === 'am' ? 'የመኖ ምርት ግዢ (Feed Production)' : 'Feed Production' ?></option>
                    <option value="dairy"><?= current_locale() === 'am' ? 'የወተትና ከብቶች (Dairy)' : 'Dairy' ?></option>
                    <option value="warehouse"><?= current_locale() === 'am' ? 'መጋዘንና ግዢ (Warehouse)' : 'Warehouse' ?></option>
                    <option value="distribution"><?= current_locale() === 'am' ? 'ስርጭትና ሽያጭ (Distribution)' : 'Distribution' ?></option>
                    <option value="finance" selected><?= current_locale() === 'am' ? 'አጠቃላይ ፋይናንስና ወጪ (Finance/Overhead)' : 'General Finance' ?></option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:16px">
                <label style="font-weight:700">📝 <?= current_locale() === 'am' ? 'ምክንያት / ማስታወሻ' : 'Notes / Purpose' ?></label>
                <input type="text" name="notes" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ለመኖ ግዢ የተሰጠ ክፍያ' : 'e.g. Payment for feed supply' ?>" style="width:100%;padding:8px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn secondary" onclick="closeIssueModal()"><?= current_locale() === 'am' ? 'ይቅር' : 'Cancel' ?></button>
                <button type="submit" class="btn" style="background:#1565c0;border-color:#1565c0;padding:8px 22px">✓ <?= current_locale() === 'am' ? 'የተሰጠውን ቼክ መዝግብ' : 'Save Cheque' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL: RECEIVE NEW CHEQUE (የተቀበልነው ቼክ) -->
<div id="receiveModal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
    <div class="card" style="max-width:580px;width:100%;margin:30px auto;background:var(--card);border-radius:10px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,0.3)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid var(--border);padding-bottom:10px">
            <h3 style="margin:0;color:#2e7d32">📥 <?= current_locale() === 'am' ? 'አዲስ የተቀበልነው ቼክ መዝግብ (Receive Cheque)' : 'Receive Cheque (Inflow)' ?></h3>
            <button type="button" onclick="closeReceiveModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-muted)">&times;</button>
        </div>

        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="record_cheque" value="1">
            <input type="hidden" name="cheque_type" value="received">

            <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px">
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">🔢 <?= current_locale() === 'am' ? 'የቼክ ቁጥር *' : 'Cheque Number *' ?></label>
                    <input type="text" name="cheque_number" required placeholder="ለምሳሌ፡ CBE-445890" style="width:100%;padding:8px">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">💰 <?= current_locale() === 'am' ? 'የገንዘብ መጠን (ETB) *' : 'Amount (ETB) *' ?></label>
                    <input type="number" step="0.01" min="1" name="amount" required placeholder="0.00" style="width:100%;padding:8px;font-weight:bold">
                </div>
            </div>

            <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px">
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">🏦 <?= current_locale() === 'am' ? 'የደንበኛው ባንክ ስም *' : 'Client Bank Name *' ?></label>
                    <input type="text" name="bank_name" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የኢትዮጵያ ንግድ ባንክ፣ አዋሽ ባንክ' : 'e.g. Commercial Bank of Ethiopia' ?>" style="width:100%;padding:8px">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">👤 <?= current_locale() === 'am' ? 'ቼኩን የሰጠን ደንበኛ/ድርጅት (Drawer) *' : 'Client / Drawer Name *' ?></label>
                    <input type="text" name="payee_or_drawer" required placeholder="<?= current_locale() === 'am' ? 'የደንበኛው ወይም የድርጅቱ ስም' : 'Client or Company Name' ?>" style="width:100%;padding:8px">
                </div>
            </div>

            <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px">
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">📅 <?= current_locale() === 'am' ? 'የተጻፈበት ቀን *' : 'Issue Date *' ?></label>
                    <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required style="width:100%;padding:8px">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-weight:700">⏳ <?= current_locale() === 'am' ? 'የሚመነዘርበት ቀን (Due Date) *' : 'Due Date *' ?></label>
                    <input type="date" name="due_date" value="<?= date('Y-m-d') ?>" required style="width:100%;padding:8px">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-weight:700">🏢 <?= current_locale() === 'am' ? 'ተዛማጅ የስራ ክፍል / ሞጁል' : 'Related Module' ?></label>
                <select name="related_module" style="width:100%;padding:8px">
                    <option value="distribution" selected><?= current_locale() === 'am' ? 'የወተትና ምርት ሽያጭ (Distribution/Sales)' : 'Distribution/Sales' ?></option>
                    <option value="dairy"><?= current_locale() === 'am' ? 'የወተት እርሻ (Dairy)' : 'Dairy' ?></option>
                    <option value="poultry"><?= current_locale() === 'am' ? 'የዶሮና እንቁላል ሽያጭ (Poultry)' : 'Poultry' ?></option>
                    <option value="warehouse"><?= current_locale() === 'am' ? 'መጋዘን (Warehouse)' : 'Warehouse' ?></option>
                    <option value="finance"><?= current_locale() === 'am' ? 'አጠቃላይ ገቢ (General Inflow)' : 'General Inflow' ?></option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:16px">
                <label style="font-weight:700">📝 <?= current_locale() === 'am' ? 'ምክንያት / ማስታወሻ' : 'Notes / Purpose' ?></label>
                <input type="text" name="notes" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ለወተት ሽያጭ የተሰጠ ቼክ' : 'e.g. Cheque for milk delivery' ?>" style="width:100%;padding:8px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn secondary" onclick="closeReceiveModal()"><?= current_locale() === 'am' ? 'ይቅር' : 'Cancel' ?></button>
                <button type="submit" class="btn" style="background:#2e7d32;border-color:#2e7d32;padding:8px 22px">✓ <?= current_locale() === 'am' ? 'የተቀበልነውን ቼክ መዝግብ' : 'Save Cheque' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 3. MODAL: CLEAR CHEQUE (ቼክ መመንዘሪያ / ገቢ ማድረጊያ) -->
<div id="clearModal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
    <div class="card" style="max-width:500px;width:100%;margin:30px auto;background:var(--card);border-radius:10px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,0.3)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid var(--border);padding-bottom:10px">
            <h3 style="margin:0;color:#2e7d32">✓ <?= current_locale() === 'am' ? 'ቼኩን ወደ ባንክ ገቢ አድርግ / አጽድቅ (Clear Cheque)' : 'Clear Cheque' ?></h3>
            <button type="button" onclick="closeClearModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-muted)">&times;</button>
        </div>

        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="clear_cheque" value="1">
            <input type="hidden" name="cheque_id" id="clearChequeId" value="">

            <div style="background:var(--hover);padding:10px 14px;border-radius:6px;margin-bottom:14px">
                <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'የቼክ መረጃ፦' : 'Cheque Details:' ?></div>
                <strong id="clearChequeNumber" style="font-size:15px"></strong>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
                    <?= current_locale() === 'am' ? 'አካል፦' : 'Person:' ?> <strong id="clearPayee" style="color:var(--text)"></strong> | 
                    <?= current_locale() === 'am' ? 'መጠን፦' : 'Amount:' ?> <strong id="clearAmount" style="color:#2e7d32;font-size:14px"></strong> ETB
                </div>
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-weight:700">🏦 <?= current_locale() === 'am' ? 'ገንዘቡ የገባበት / የወጣበት የድርጅቱ ባንክ አካውንት *' : 'Target Farm Bank Account *' ?></label>
                <select name="clear_bank_account_id" required style="width:100%;padding:8px">
                    <?php foreach ($bankAccounts as $ba): ?>
                        <option value="<?= (int)$ba['id'] ?>">
                            <?= h($ba['bank_name']) ?> — <?= h($ba['account_number']) ?> (<?= h($ba['account_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted"><?= current_locale() === 'am' ? 'ይህ ቼክ ሲጸድቅ ወዲያውኑ በባንክ ሒሳብ መዝገብ ላይ ገቢ/ወጪ ይሆናል።' : 'Transactions will be automatically posted to this bank account.' ?></small>
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-weight:700">📅 <?= current_locale() === 'am' ? 'የተመነዘረበት / ገቢ የሆነበት ቀን *' : 'Clearance Date *' ?></label>
                <input type="date" name="clear_date" value="<?= date('Y-m-d') ?>" required style="width:100%;padding:8px">
            </div>

            <div class="form-group" style="margin-bottom:16px">
                <label style="font-weight:700">📝 <?= current_locale() === 'am' ? 'ተጨማሪ ማስታወሻ' : 'Notes' ?></label>
                <input type="text" name="clear_notes" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ በባንክ ገቢ ደረሰኝ ቁጥር 99182' : 'e.g. Bank slip reference 99182' ?>" style="width:100%;padding:8px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn secondary" onclick="closeClearModal()"><?= current_locale() === 'am' ? 'ይቅር' : 'Cancel' ?></button>
                <button type="submit" class="btn" style="background:#2e7d32;border-color:#2e7d32;padding:8px 22px">✓ <?= current_locale() === 'am' ? 'አረጋግጥና አጽድቅ (Confirm Clear)' : 'Confirm Clear' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 4. MODAL: BOUNCE CHEQUE (የተመለሰ ቼክ) -->
<div id="bounceModal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
    <div class="card" style="max-width:460px;width:100%;margin:30px auto;background:var(--card);border-radius:10px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,0.3)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid var(--border);padding-bottom:10px">
            <h3 style="margin:0;color:#ef6c00">⚠️ <?= current_locale() === 'am' ? 'የተመለሰ ቼክ (Bounce Cheque)' : 'Mark Cheque as Bounced' ?></h3>
            <button type="button" onclick="closeBounceModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-muted)">&times;</button>
        </div>

        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="bounce_cheque" value="1">
            <input type="hidden" name="cheque_id" id="bounceChequeId" value="">

            <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
                <?= current_locale() === 'am' ? 'ቼክ ቁጥር፦' : 'Cheque #' ?> <strong id="bounceChequeNumber" style="color:var(--text)"></strong>
            </p>

            <div class="form-group" style="margin-bottom:16px">
                <label style="font-weight:700">⚠️ <?= current_locale() === 'am' ? 'የተመለሰበት ምክንያት *' : 'Bounce Reason *' ?></label>
                <input type="text" name="bounce_reason" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ በቂ ሒሳብ የለውም / የፊርማ ስህተት' : 'e.g. Insufficient funds / Signature mismatch' ?>" style="width:100%;padding:8px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn secondary" onclick="closeBounceModal()"><?= current_locale() === 'am' ? 'ይቅር' : 'Cancel' ?></button>
                <button type="submit" class="btn" style="background:#ef6c00;border-color:#ef6c00;padding:8px 20px">⚠️ <?= current_locale() === 'am' ? 'ተመልሷል ብለህ መዝግብ' : 'Confirm Bounced' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 5. MODAL: CANCEL CHEQUE (የተሰረዘ ቼክ) -->
<div id="cancelModal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
    <div class="card" style="max-width:460px;width:100%;margin:30px auto;background:var(--card);border-radius:10px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,0.3)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid var(--border);padding-bottom:10px">
            <h3 style="margin:0;color:#c62828">🚫 <?= current_locale() === 'am' ? 'ቼክ ሰርዝ (Cancel Cheque)' : 'Cancel Cheque' ?></h3>
            <button type="button" onclick="closeCancelModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-muted)">&times;</button>
        </div>

        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="cancel_cheque" value="1">
            <input type="hidden" name="cheque_id" id="cancelChequeId" value="">

            <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
                <?= current_locale() === 'am' ? 'ቼክ ቁጥር፦' : 'Cheque #' ?> <strong id="cancelChequeNumber" style="color:var(--text)"></strong>
            </p>

            <div class="form-group" style="margin-bottom:16px">
                <label style="font-weight:700">🚫 <?= current_locale() === 'am' ? 'የተሰረዘበት ምክንያት *' : 'Cancellation Reason *' ?></label>
                <input type="text" name="cancel_reason" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ በስህተት ተጽፎ የተበላሸ / የተለወጠ' : 'e.g. Written in error / replaced' ?>" style="width:100%;padding:8px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn secondary" onclick="closeCancelModal()"><?= current_locale() === 'am' ? 'ተመለስ' : 'Back' ?></button>
                <button type="submit" class="btn" style="background:#c62828;border-color:#c62828;padding:8px 20px">🚫 <?= current_locale() === 'am' ? 'ቼኩን ሰርዝ' : 'Confirm Cancel' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openIssueModal() {
    document.getElementById('issueModal').style.display = 'flex';
}
function closeIssueModal() {
    document.getElementById('issueModal').style.display = 'none';
}
function updateIssueBankName(sel) {
    const opt = sel.options[sel.selectedIndex];
    const bName = opt ? opt.getAttribute('data-bankname') : '';
    if (bName) {
        document.getElementById('issueBankName').value = bName;
    }
}

function openReceiveModal() {
    document.getElementById('receiveModal').style.display = 'flex';
}
function closeReceiveModal() {
    document.getElementById('receiveModal').style.display = 'none';
}

function openClearModal(id, num, amount, type, payee) {
    document.getElementById('clearChequeId').value = id;
    document.getElementById('clearChequeNumber').textContent = '#' + num;
    document.getElementById('clearPayee').textContent = payee;
    document.getElementById('clearAmount').textContent = amount.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('clearModal').style.display = 'flex';
}
function closeClearModal() {
    document.getElementById('clearModal').style.display = 'none';
}

function openBounceModal(id, num) {
    document.getElementById('bounceChequeId').value = id;
    document.getElementById('bounceChequeNumber').textContent = '#' + num;
    document.getElementById('bounceModal').style.display = 'flex';
}
function closeBounceModal() {
    document.getElementById('bounceModal').style.display = 'none';
}

function openCancelModal(id, num) {
    document.getElementById('cancelChequeId').value = id;
    document.getElementById('cancelChequeNumber').textContent = '#' + num;
    document.getElementById('cancelModal').style.display = 'flex';
}
function closeCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>