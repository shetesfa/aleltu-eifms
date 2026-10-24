<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);
require_once __DIR__ . '/../../includes/header.php';

$unitFilter = $_GET['unit'] ?? null;
$methodFilter = $_GET['method'] ?? null;
$accountId = (int)($_GET['account_id'] ?? 0);

// Resolve bank/mobile/cash account info
$bankAccount = null;
if ($accountId > 0) {
    $bankAccount = db()->query("SELECT * FROM fin_bank_accounts WHERE id = {$accountId}")->fetch();
} elseif ($methodFilter === 'telebirr') {
    $bankAccount = db()->query("SELECT * FROM fin_bank_accounts WHERE account_type = 'mobile' OR bank_name LIKE '%telebirr%' ORDER BY id ASC LIMIT 1")->fetch();
} elseif ($methodFilter === 'cash') {
    $bankAccount = db()->query("SELECT * FROM fin_bank_accounts WHERE account_type = 'cash' OR bank_name LIKE '%cash%' ORDER BY id ASC LIMIT 1")->fetch();
} elseif ($methodFilter === 'bank') {
    $bankAccount = db()->query("SELECT * FROM fin_bank_accounts WHERE account_type = 'bank' ORDER BY id ASC LIMIT 1")->fetch();
}

$openingBalance = 0.0;
if ($bankAccount) {
    $openingBalance = (float)($bankAccount['opening_balance'] ?? 0);
} elseif (empty($unitFilter) && empty($methodFilter)) {
    $openingBalance = (float)db()->query("SELECT COALESCE(SUM(opening_balance), 0) FROM fin_bank_accounts")->fetchColumn();
}

$transactions = finance_transactions_list($unitFilter ?: null, $methodFilter ?: null, 300);

$totalIn = 0; $totalOut = 0;
foreach ($transactions as $t) {
    if ($t['direction'] === 'inflow') $totalIn += (float) $t['amount']; else $totalOut += (float) $t['amount'];
}
$currentBalance = $openingBalance + $totalIn - $totalOut;

$isBankView = !empty($bankAccount);
$viewTitle = '📒 ' . (current_locale() === 'am' ? 'የፋይናንስ ጠቅላላ መዝገብ (Finance Full Ledger)' : 'Finance Full Ledger');
if ($bankAccount) {
    $icon = match($bankAccount['account_type']) { 'bank' => '🏦', 'mobile' => '📱', default => '💵' };
    $viewTitle = $icon . ' ' . h($bankAccount['bank_name']) . ' ' . (current_locale() === 'am' ? 'የሂሳብ መግለጫ (Statement)' : 'Statement');
}
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px"><?= $viewTitle ?></h2>
        <?php if ($bankAccount): ?>
            <p class="text-muted" style="margin:0">
                <?= h($bankAccount['account_name'] ?: $bankAccount['bank_name']) ?> — ቁጥር፡ <strong><?= h($bankAccount['account_number']) ?></strong> | የአካውንት ባለቤት፡ <strong>ተክሉ ጌታቸው የከብት እርባታ እና የወተት ተዋጽኦ ማቀነባበሪያ ድርጅት</strong>
            </p>
        <?php else: ?>
            <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የገቢና ወጪ የፋይናንስ ዝውውሮች ዝርዝር' : 'Complete financial cashflow and bank transactions ledger.' ?></p>
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px">
        <button class="btn secondary" onclick="window.print()">🖨️ መግለጫውን አትም (Print)</button>
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ዳሽቦርድ ተመለስ' : 'Back to Dashboard' ?></a>
    </div>
</div>

<div class="card no-print" style="margin-bottom:16px">
    <form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
        <div class="form-group" style="margin:0">
            <label><?= current_locale() === 'am' ? 'የስራ ክፍል (Unit)' : 'Business Unit' ?></label>
            <select name="unit" style="padding:6px 10px">
                <option value=""><?= current_locale() === 'am' ? '— ሁሉም ክፍሎች —' : 'All Units' ?></option>
                <?php foreach (FINANCE_UNIT_TAGS as $u): ?>
                    <option value="<?= h($u) ?>" <?= $unitFilter === $u ? 'selected' : '' ?>><?= h(t('section.' . $u, ucwords(str_replace('_',' ',$u)))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label><?= current_locale() === 'am' ? 'የመክፈያ መንገድ / አካውንት' : 'Payment Method / Account' ?></label>
            <select name="method" style="padding:6px 10px">
                <option value=""><?= current_locale() === 'am' ? 'ሁሉም (ባንክ + ጥሬ ገንዘብ + ቴሌብር)' : 'All Methods' ?></option>
                <option value="bank" <?= $methodFilter === 'bank' ? 'selected' : '' ?>>🏦 ባንክ (ኦሮሚያ / ንግድ ባንክ)</option>
                <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>💵 ጥሬ ገንዘብ ካዝና (Cash Safe)</option>
                <option value="telebirr" <?= $methodFilter === 'telebirr' ? 'selected' : '' ?>>📱 ቴሌብር (Telebirr)</option>
            </select>
        </div>
        <button class="btn secondary" type="submit"><?= current_locale() === 'am' ? 'አጣራ' : 'Filter' ?></button>
        <?php if ($unitFilter || $methodFilter || $accountId): ?>
            <a href="ledger.php" class="btn secondary" style="padding:6px 10px"><?= current_locale() === 'am' ? 'አጽዳ' : 'Reset' ?></a>
        <?php endif; ?>
    </form>
</div>

<!-- KPI Summary -->
<div class="kpi-row" style="margin:16px 0 20px 0">
    <div class="kpi" style="border-left:5px solid #1565c0">
        <div class="label">🏁 <?= current_locale() === 'am' ? 'የመነሻ ቀሪ ሒሳብ (Opening Balance)' : 'Opening Balance' ?></div>
        <div class="value" style="color:#1565c0"><?= number_format($openingBalance, 2) ?> ETB</div>
    </div>
    <div class="kpi" style="border-left:5px solid #2e7d32">
        <div class="label"><?= current_locale() === 'am' ? '🟢 የተደረገ ገቢ (Inflow / Deposits)' : 'Total Inflow' ?></div>
        <div class="value" style="color:#2e7d32">+<?= number_format($totalIn, 2) ?> ETB</div>
    </div>
    <div class="kpi" style="border-left:5px solid #c62828">
        <div class="label"><?= current_locale() === 'am' ? '🔴 የተደረገ ወጪ (Outflow / Withdrawals)' : 'Total Outflow' ?></div>
        <div class="value" style="color:#c62828">-<?= number_format($totalOut, 2) ?> ETB</div>
    </div>
    <div class="kpi" style="border-left:5px solid var(--primary);background:rgba(43,92,51,0.05)">
        <div class="label"><?= current_locale() === 'am' ? '💰 የወቅቱ ትክክለኛ ቀሪ ሒሳብ (Current Balance)' : 'Current Balance' ?></div>
        <div class="value" style="color:<?= $currentBalance >= 0 ? '#2e7d32' : '#b3261e' ?>">
            <?= number_format($currentBalance, 2) ?> ETB
        </div>
    </div>
</div>

<!-- Bank Statement / Ledger Table -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">📄 <?= $isBankView ? 'የባንክ ዝውውሮች ዝርዝር ሰንጠረዥ (Bank Ledger Entries)' : 'የፋይናንስ እንቅስቃሴዎች ዝርዝር' ?></h3>
        <span class="text-muted" style="font-size:12px"><?= count($transactions) ?> ዝውውሮች ተገኝተዋል</span>
    </div>
    <table>
        <thead>
            <tr style="border-bottom:2px solid var(--border)">
                <th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th>
                <th><?= current_locale() === 'am' ? 'የዝውውር ማብራሪያ' : 'Description / Reference' ?></th>
                <th><?= current_locale() === 'am' ? 'የስራ ክፍል' : 'Unit' ?></th>
                <th><?= current_locale() === 'am' ? 'ምድብ' : 'Category' ?></th>
                <th><?= current_locale() === 'am' ? 'መንገድ' : 'Method' ?></th>
                <th style="text-align:right;color:#2e7d32"><?= current_locale() === 'am' ? 'ገቢ (+)' : 'Deposit (+)' ?></th>
                <th style="text-align:right;color:#c62828"><?= current_locale() === 'am' ? 'ወጪ (-)' : 'Withdrawal (-)' ?></th>
                <th><?= current_locale() === 'am' ? 'መዝጋቢ' : 'By' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($openingBalance > 0): ?>
                <tr style="background:rgba(43,92,51,0.06);font-weight:600">
                    <td style="white-space:nowrap;font-style:italic">🏁 መነሻ</td>
                    <td>
                        <strong>🏁 <?= current_locale() === 'am' ? 'የመነሻ ቀሪ ሒሳብ (Opening Balance)' : 'Opening Balance' ?></strong>
                        <br><small class="text-muted"><?= $bankAccount ? h($bankAccount['bank_name']) : 'የሁሉም አካውንቶች ድምር መነሻ' ?></small>
                    </td>
                    <td><span class="badge">መነሻ ክምችት</span></td>
                    <td>መነሻ ካፒታል</td>
                    <td><span class="badge active">ካዝና/ባንክ</span></td>
                    <td style="text-align:right;color:#2e7d32;font-weight:700">
                        +<?= number_format($openingBalance, 2) ?>
                    </td>
                    <td style="text-align:right;color:var(--text-muted)">—</td>
                    <td style="font-size:12px;color:var(--text-muted)">ስርዓት (Initial)</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($transactions as $t): 
                $isIn = ($t['direction'] === 'inflow');
                $methodText = match(strtolower($t['payment_method'])) {
                    'cash' => (current_locale() === 'am' ? '💵 ጥሬ ገንዘብ' : 'Cash'),
                    'bank' => (current_locale() === 'am' ? '🏦 ኦሮሚያ ባንክ' : 'Bank'),
                    'telebirr', 'mobile' => (current_locale() === 'am' ? '📱 ቴሌብር' : 'Telebirr'),
                    default => ucfirst($t['payment_method'])
                };
            ?>
                <tr>
                    <td style="font-weight:600;white-space:nowrap"><?= h(eth_date($t['transaction_date'])) ?></td>
                    <td>
                        <strong><?= h($t['description'] ?: 'የፋይናንስ ዝውውር') ?></strong>
                        <?php if (!empty($t['reference_number'])): ?>
                            <br><small class="text-muted">Ref: <?= h($t['reference_number']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge"><?= h(t('section.' . $t['unit_tag'], ucwords(str_replace('_',' ',$t['unit_tag'])))) ?></span></td>
                    <td><?= h($t['category'] ?: '—') ?></td>
                    <td><span class="badge <?= $t['payment_method']==='cash'?'dry':'active' ?>"><?= h($methodText) ?></span></td>
                    <td style="text-align:right;color:#2e7d32;font-weight:700">
                        <?= $isIn ? '+' . number_format((float)$t['amount'], 2) : '—' ?>
                    </td>
                    <td style="text-align:right;color:#c62828;font-weight:700">
                        <?= !$isIn ? '-' . number_format((float)$t['amount'], 2) : '—' ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted)"><?= h($t['recorded_by_name'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="8" class="text-muted" style="text-align:center;padding:24px">ምንም አይነት የተመዘገበ ዝውውር አልተገኘም።</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="border-top:2px solid var(--border);font-weight:bold">
                <td colspan="5" style="text-align:right"><?= current_locale() === 'am' ? 'ጠቅላላ ድምር:' : 'Total:' ?></td>
                <td style="text-align:right;color:#2e7d32">+<?= number_format($totalIn, 2) ?> ETB</td>
                <td style="text-align:right;color:#c62828">-<?= number_format($totalOut, 2) ?> ETB</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
