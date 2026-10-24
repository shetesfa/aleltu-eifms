<?php
/**
 * Aleltu Farm EIFMS - Internal Account Transfers (PHASE 4 - Finance)
 * Master Prompt 6.1: Atomic transfers between Oromia Bank, CBE, Telebirr, and Cash.
 * Debits source, credits destination with a shared transfer reference.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_transfer'])) {
    csrf_verify();
    try {
        $fromId = (int)$_POST['from_account_id'];
        $toId   = (int)$_POST['to_account_id'];
        $amount = (float)$_POST['amount'];
        $notes  = trim($_POST['notes'] ?? '');

        $ref = finance_internal_transfer($fromId, $toId, $amount, $notes, (int)current_user()['id']);
        flash(current_locale() === 'am' 
            ? "✓ የ {$amount} ብር የውስጥ ዝውውር በማጣቀሻ ቁጥር {$ref} በተሳካ ሁኔታ ተጠናቋል!" 
            : "Internal transfer {$ref} of {$amount} ETB completed successfully!");
        header('Location: ' . BASE_URL . '/modules/finance/transfer.php');
        exit;
    } catch (Throwable $e) {
        flash('ስህተት፦ ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$balances = finance_balance_by_method();
$accounts = $balances['_accounts'] ?? [];

// Recent transfers
$recentTransfers = db()->query(
    "SELECT t.*, u.name AS worker_name, a.bank_name, a.account_number 
     FROM fin_transactions t 
     LEFT JOIN users u ON u.id = t.recorded_by 
     LEFT JOIN fin_bank_accounts a ON a.id = t.bank_account_id
     WHERE t.category = 'internal_transfer' 
     ORDER BY t.id DESC LIMIT 30"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🔄 <?= current_locale() === 'am' ? 'የውስጥ የሂሳብ አካውንቶች ዝውውር (Internal Account Transfers)' : 'Internal Account Transfers' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'በኦሮሚያ ባንክ፣ በንግድ ባንክ (CBE)፣ በቴሌብር እና በጥሬ ገንዘብ ካዝና መካከል የሚደረግ የገንዘብ ዝውውር መቆጣጠሪያ።' : 'Move money between Oromia Bank, CBE, Telebirr, and Cash Safe with atomic debit/credit ledgers.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">← <?= current_locale() === 'am' ? 'ወደ ፋይናንስ ማዕከል' : 'Finance Dashboard' ?></a>
        <a href="<?= BASE_URL ?>/modules/finance/ledger.php" class="btn secondary">📒 <?= current_locale() === 'am' ? 'ጠቅላላ ሌጀር' : 'Full Ledger' ?></a>
    </div>
</div>

<!-- Account Balances Row -->
<div class="kpi-row" style="margin-bottom:20px">
    <?php foreach ($accounts as $acc): ?>
        <div class="kpi" style="border-top:3px solid var(--primary)">
            <div class="label"><?= $acc['type']==='bank' ? '🏦' : ($acc['type']==='mobile'?'📱':'💵') ?> <?= h($acc['name']) ?></div>
            <div class="value" style="color:<?= $acc['balance']>=0 ? 'var(--primary)' : '#b3261e' ?>">
                <?= number_format($acc['balance'], 2) ?> ETB
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px">የወቅቱ ቀሪ ሂሳብ</div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card" style="max-width:580px;margin-bottom:24px">
    <h3 style="margin-top:0">💸 <?= current_locale() === 'am' ? 'አዲስ የገንዘብ ዝውውር መዝግብ' : 'Record New Transfer' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="execute_transfer" value="1">
        
        <div class="form-row" style="display:flex;gap:12px;flex-wrap:wrap">
            <div class="form-group" style="flex:1;min-width:200px">
                <label>📤 <?= current_locale() === 'am' ? 'ገንዘብ የሚወጣበት አካውንት (From) *' : 'From Account *' ?></label>
                <select name="from_account_id" required style="font-size:14px;padding:8px">
                    <option value="">— ይምረጡ —</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>">
                            <?= h($acc['name']) ?> (ቀሪ፡ <?= number_format($acc['balance'], 0) ?> ETB)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;min-width:200px">
                <label>📥 <?= current_locale() === 'am' ? 'ገንዘብ የሚገባበት አካውንት (To) *' : 'To Account *' ?></label>
                <select name="to_account_id" required style="font-size:14px;padding:8px">
                    <option value="">— ይምረጡ —</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>">
                            <?= h($acc['name']) ?> (ቀሪ፡ <?= number_format($acc['balance'], 0) ?> ETB)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>💵 <?= current_locale() === 'am' ? 'የሚዘዋወረው የገንዘብ መጠን (ETB) *' : 'Transfer Amount (ETB) *' ?></label>
            <input type="number" step="0.01" name="amount" required placeholder="0.00" style="font-size:18px;font-weight:700">
        </div>

        <div class="form-group">
            <label>📝 <?= current_locale() === 'am' ? 'ማብራሪያ / ምክንያት (አማራጭ)' : 'Reason / Reference Notes (Optional)' ?></label>
            <input type="text" name="notes" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የቴሌብር ገቢ ወደ ኦሮሚያ ባንክ ማስገቢያ' : 'e.g. Daily telebirr collection deposited to Oromia Bank' ?>">
        </div>

        <button class="btn" type="submit" onclick="return confirm('<?= current_locale() === 'am' ? 'ይህንን የገንዘብ ዝውውር መፈጸም ይፈልጋሉ?' : 'Confirm internal transfer?' ?>');" style="background:#1e5631;color:#fff;padding:10px 20px;font-size:14px">
            ✓ <?= current_locale() === 'am' ? 'ዝውውሩን አከናውን (Execute Transfer)' : 'Execute Transfer' ?>
        </button>
    </form>
</div>

<!-- Recent Transfers Table -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">📋 <?= current_locale() === 'am' ? 'የቅርብ ጊዜ የውስጥ ዝውውሮች መዝገብ' : 'Recent Internal Transfers' ?></h3>
        <span class="text-muted" style="font-size:12px"><?= count($recentTransfers) ?> ግብይቶች</span>
    </div>

    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th>
                <th><?= current_locale() === 'am' ? 'ማጣቀሻ (Ref)' : 'Reference' ?></th>
                <th><?= current_locale() === 'am' ? 'አካውንት' : 'Account' ?></th>
                <th><?= current_locale() === 'am' ? 'አይነት' : 'Flow' ?></th>
                <th style="text-align:right"><?= current_locale() === 'am' ? 'መጠን (ETB)' : 'Amount' ?></th>
                <th><?= current_locale() === 'am' ? 'ዝርዝር ማብራሪያ' : 'Description' ?></th>
                <th><?= current_locale() === 'am' ? 'መዝጋቢ' : 'By' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentTransfers as $rt): 
                $isIn = ($rt['direction'] === 'inflow');
            ?>
                <tr>
                    <td style="white-space:nowrap;font-size:12.5px"><?= h(eth_date($rt['transaction_date'])) ?></td>
                    <td><span class="badge" style="font-family:monospace"><?= h($rt['reference_number']) ?></span></td>
                    <td><strong><?= h($rt['bank_name'] ?: 'የፋይናንስ አካውንት') ?></strong></td>
                    <td>
                        <span class="badge <?= $isIn ? 'active' : 'sold' ?>">
                            <?= $isIn ? '🟢 ገቢ (Inflow)' : '🔴 ወጪ (Outflow)' ?>
                        </span>
                    </td>
                    <td style="text-align:right;font-weight:700;color:<?= $isIn ? '#2e7d32' : '#c62828' ?>">
                        <?= $isIn ? '+' : '-' ?><?= number_format((float)$rt['amount'], 2) ?> ETB
                    </td>
                    <td><strong style="color:var(--text);font-size:13px"><?= h($rt['description']) ?></strong></td>
                    <td style="font-size:12px;color:var(--text-muted)"><?= h($rt['worker_name'] ?: 'System') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentTransfers)): ?>
                <tr><td colspan="7" class="text-muted" style="text-align:center;padding:20px"><?= current_locale() === 'am' ? 'ምንም አይነት የተመዘገበ ዝውውር የለም።' : 'No transfer records found.' ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
