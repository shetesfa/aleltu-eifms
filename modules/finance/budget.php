<?php
/**
 * Aleltu Farm EIFMS - Budget Management & Variance (PHASE 4 - Finance)
 * Allows management to propose, approve, and track unit budgets against actual operational spending.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['propose_budget'])) {
            $unitTag = $_POST['unit_tag'];
            $start   = $_POST['period_start'];
            $end     = $_POST['period_end'];
            $amount  = (float)$_POST['planned_amount'];

            if (!in_array($unitTag, FINANCE_UNIT_TAGS, true)) {
                throw new InvalidArgumentException("ልክ ያልሆነ የስራ ክፍል ተመርጧል (Invalid unit tag).");
            }
            if ($start > $end) {
                throw new InvalidArgumentException("የመጀመሪያው ቀን ከመጨረሻው ቀን መብለጥ የለበትም (Start date must precede end date).");
            }
            if ($amount <= 0) {
                throw new InvalidArgumentException("የበጀት መጠን ከ 0 በላይ መሆን አለበት (Budget amount must be greater than zero).");
            }

            $stmt = db()->prepare(
                "INSERT INTO fin_budgets (unit_tag, period_start, period_end, planned_amount, proposed_by, status)
                 VALUES (?, ?, ?, ?, ?, 'proposed')"
            );
            $stmt->execute([$unitTag, $start, $end, $amount, (int)current_user()['id']]);
            $budgetId = db()->lastInsertId();

            log_activity('finance', 'create', 'fin_budgets', (int)$budgetId, "Proposed {$amount} ETB budget for {$unitTag} ({$start} to {$end})");
            flash(current_locale() === 'am' ? '✓ አዲስ በጀት በተሳካ ሁኔታ ቀርቧል፤ ማጽደቅ ይጠበቅበታል።' : 'New budget proposed successfully.');
        } elseif (isset($_POST['approve_budget'])) {
            require_role(['super_admin', 'general_manager']);
            $budgetId = (int)$_POST['budget_id'];

            db()->prepare(
                "UPDATE fin_budgets SET status = 'approved', approved_by = ? WHERE id = ?"
            )->execute([(int)current_user()['id'], $budgetId]);

            log_activity('finance', 'update', 'fin_budgets', $budgetId, "Approved budget #{$budgetId}");
            flash(current_locale() === 'am' ? '✓ በጀቱ ጸድቋል!' : 'Budget approved successfully!');
        }
        header('Location: ' . BASE_URL . '/modules/finance/budget.php');
        exit;
    } catch (Throwable $e) {
        flash('ስህተት፦ ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$allBudgets = db()->query(
    "SELECT b.*, u1.name AS proposer_name, u2.name AS approver_name 
     FROM fin_budgets b 
     LEFT JOIN users u1 ON u1.id = b.proposed_by 
     LEFT JOIN users u2 ON u2.id = b.approved_by 
     ORDER BY b.period_start DESC, b.id DESC"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">📊 <?= current_locale() === 'am' ? 'የስራ ክፍሎች በጀትና አፈጻጸም (Budget & Variance Control)' : 'Department Budgets & Variance' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'ለየስራ ክፍሎቹ የሚመደብ የበጀት ዕቅድ እና የወጪ አፈጻጸም ቁጥጥር ማዕከል።' : 'Plan departmental budgets and monitor actual spending variance in real time.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">← <?= current_locale() === 'am' ? 'ወደ ፋይናንስ ማዕከል' : 'Finance Dashboard' ?></a>
    </div>
</div>

<!-- Propose Budget Form -->
<div class="card" style="max-width:600px;margin-bottom:24px">
    <h3 style="margin-top:0">📝 <?= current_locale() === 'am' ? 'አዲስ የበጀት ዕቅድ አቅርብ' : 'Propose New Budget' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="propose_budget" value="1">
        
        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'የስራ ክፍል (Business Unit) *' : 'Business Unit *' ?></label>
            <select name="unit_tag" required style="font-size:14px;padding:8px">
                <?php foreach (FINANCE_UNIT_TAGS as $u): ?>
                    <option value="<?= h($u) ?>"><?= h(t('section.' . $u, ucwords(str_replace('_',' ',$u)))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row" style="display:flex;gap:12px;flex-wrap:wrap">
            <div class="form-group" style="flex:1;min-width:140px">
                <label><?= current_locale() === 'am' ? 'መነሻ ቀን *' : 'Period Start *' ?></label>
                <input type="date" name="period_start" value="<?= date('Y-m-01') ?>" required>
            </div>
            <div class="form-group" style="flex:1;min-width:140px">
                <label><?= current_locale() === 'am' ? 'ማብቂያ ቀን *' : 'Period End *' ?></label>
                <input type="date" name="period_end" value="<?= date('Y-m-t') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>💰 <?= current_locale() === 'am' ? 'የታቀደው የበጀት መጠን (ETB) *' : 'Planned Budget Amount (ETB) *' ?></label>
            <input type="number" step="0.01" name="planned_amount" required placeholder="50000.00" style="font-size:17px;font-weight:700">
        </div>

        <button class="btn" type="submit" style="background:#1e5631;color:#fff;padding:9px 18px">
            ✓ <?= current_locale() === 'am' ? 'በጀቱን መዝግብ' : 'Submit Budget' ?>
        </button>
    </form>
</div>

<!-- Budgets List & Variance Table -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">📋 <?= current_locale() === 'am' ? 'የተመዘገቡ በጀቶችና የወጪ አፈጻጸም ንፅፅር' : 'Budgets & Variance Summary' ?></h3>
        <span class="text-muted" style="font-size:12px"><?= count($allBudgets) ?> በጀቶች</span>
    </div>

    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'የስራ ክፍል' : 'Unit' ?></th>
                <th><?= current_locale() === 'am' ? 'ክፍለ-ጊዜ (ኢትዮጵያ)' : 'Period' ?></th>
                <th style="text-align:right"><?= current_locale() === 'am' ? 'የታቀደ በጀት' : 'Planned' ?></th>
                <th style="text-align:right"><?= current_locale() === 'am' ? 'ትክክለኛ ወጪ' : 'Actual Spent' ?></th>
                <th style="text-align:right"><?= current_locale() === 'am' ? 'ቀሪ / ልዩነት' : 'Variance' ?></th>
                <th><?= current_locale() === 'am' ? 'አጠቃቀም' : 'Used %' ?></th>
                <th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                <th><?= current_locale() === 'am' ? 'ተግባር' : 'Action' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allBudgets as $b): 
                $var = budget_variance($b);
                $isOver = $var['variance'] < 0;
            ?>
                <tr>
                    <td><strong><?= h(t('section.' . $b['unit_tag'], ucwords(str_replace('_',' ',$b['unit_tag'])))) ?></strong></td>
                    <td style="white-space:nowrap;font-size:12.5px">
                        <?= h(eth_date($b['period_start'])) ?> — <?= h(eth_date($b['period_end'])) ?>
                    </td>
                    <td style="text-align:right;font-weight:700"><?= number_format((float)$b['planned_amount'], 2) ?> ETB</td>
                    <td style="text-align:right;color:#c62828"><?= number_format($var['actual'], 2) ?> ETB</td>
                    <td style="text-align:right;font-weight:700;color:<?= $isOver ? '#c62828' : '#2e7d32' ?>">
                        <?= $var['variance'] >= 0 ? '+' : '' ?><?= number_format($var['variance'], 2) ?> ETB
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px">
                            <div style="background:#eee;border-radius:4px;width:60px;height:8px;overflow:hidden">
                                <div style="width:<?= min(100, $var['percent_used']) ?>%;height:100%;background:<?= $isOver ? '#c62828' : ($var['percent_used'] > 85 ? '#e65100' : '#2e7d32') ?>"></div>
                            </div>
                            <span style="font-size:11.5px;font-weight:600"><?= $var['percent_used'] ?>%</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $b['status']==='approved' ? 'active' : 'dry' ?>">
                            <?= $b['status']==='approved' ? (current_locale()==='am'?'የጸደቀ':'Approved') : (current_locale()==='am'?'የቀረበ':'Proposed') ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($b['status'] !== 'approved' && has_role(['super_admin', 'general_manager'])): ?>
                            <form method="post" style="display:inline"><?= csrf_field() ?>
                                <input type="hidden" name="approve_budget" value="1">
                                <input type="hidden" name="budget_id" value="<?= (int)$b['id'] ?>">
                                <button class="btn secondary" type="submit" style="padding:2px 8px;font-size:11.5px;color:#2e7d32">
                                    ✓ <?= current_locale() === 'am' ? 'አጽድቅ' : 'Approve' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:11.5px"><?= h($b['approver_name'] ?: '—') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($allBudgets)): ?>
                <tr><td colspan="8" class="text-muted" style="text-align:center;padding:20px"><?= current_locale() === 'am' ? 'ምንም አይነት የተመዘገበ በጀት የለም።' : 'No budget records found.' ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
