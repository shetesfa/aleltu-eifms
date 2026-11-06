<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

$currentUser = current_user();
$selectedShopId = (int)($_GET['shop_id'] ?? 0);
$selectedItemFilter = (int)($_GET['item_id'] ?? 0);
$selectedStatusFilter = $_GET['payment_status'] ?? 'all';
$selectedMonthFilter = (int)($_GET['eth_month'] ?? 0);
$selectedYearFilter = (int)($_GET['eth_year'] ?? eth_current_year());

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['record_payment'])) {
            $dispatchId = (int)$_POST['dispatch_id'];
            $amount = (float)$_POST['payment_amount'];
            $bankAccountId = (int)$_POST['bank_account_id'];
            $refNo = trim($_POST['reference_no'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($amount <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የክፍያው መጠን ከ 0 በላይ መሆን አለበት።' : 'Payment amount must be greater than zero.');
            }
            if ($bankAccountId <= 0) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ ትክክለኛ የድርጅቱን የባንክ አካውንት ይምረጡ።' : 'Please select a company bank account.');
            }

            // Retrieve bank details
            $bankStmt = db()->prepare("SELECT * FROM fin_bank_accounts WHERE id = ?");
            $bankStmt->execute([$bankAccountId]);
            $bank = $bankStmt->fetch();
            if (!$bank) {
                throw new RuntimeException(current_locale() === 'am' ? 'የተመረጠው የባንክ አካውንት በሲስተሙ አልተገኘም።' : 'Bank account not found.');
            }

            $method = ($bank['account_type'] === 'cash') ? 'cash' : (($bank['account_type'] === 'mobile') ? 'telebirr' : 'bank');
            $fullNotes = "[$bank[bank_name] - $bank[account_number]] " . $notes;

            dispatch_record_payment($dispatchId, $amount, $method, $refNo, $fullNotes, $currentUser['id'], $bankAccountId);
            flash(current_locale() === 'am' ? '✓ ክፍያው በተሳካ ሁኔታ ተመዝግቧል! ወደ ባንኩ ሂሳብ ገቢ ሆኗል።' : 'Payment recorded successfully.');
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } catch (Throwable $e) {
        flash(current_locale() === 'am' ? 'ስህተት፦ ' . $e->getMessage() : 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

// Real Bank Accounts
$bankAccounts = db()->query("SELECT * FROM fin_bank_accounts ORDER BY id ASC")->fetchAll();

// Product items for filter (Milk, Eggs, Fish, Feed, etc.)
$stockItems = db()->query("SELECT id, name, unit FROM wh_stock_items ORDER BY name")->fetchAll();

// Shops List with aggregated financial balances
$shopsQuery = "
    SELECT s.*,
           COUNT(d.id) AS total_dispatches,
           COALESCE(SUM(d.total_price), 0) AS total_sales_value,
           COALESCE(SUM(d.amount_paid), 0) AS total_paid_value,
           COALESCE(SUM(d.total_price - d.amount_paid), 0) AS outstanding_balance
    FROM dist_shops s
    LEFT JOIN dist_dispatches d ON d.shop_id = s.id
    GROUP BY s.id
    ORDER BY outstanding_balance DESC, s.shop_name ASC
";
$shops = db()->query($shopsQuery)->fetchAll();

// Selected Shop details
$activeShop = null;
if ($selectedShopId > 0) {
    $stmt = db()->prepare("SELECT * FROM dist_shops WHERE id = ?");
    $stmt->execute([$selectedShopId]);
    $activeShop = $stmt->fetch();
}

// Build query for dispatches
$where = ["d.sale_channel != 'internal_use'"];
$params = [];

if ($selectedShopId > 0) {
    $where[] = "d.shop_id = ?";
    $params[] = $selectedShopId;
} elseif (isset($_GET['tab']) && $_GET['tab'] === 'direct_buyers') {
    $where[] = "d.shop_id IS NULL AND d.sale_channel = 'direct_sale'";
}

if ($selectedItemFilter > 0) {
    $where[] = "d.stock_item_id = ?";
    $params[] = $selectedItemFilter;
}

if ($selectedStatusFilter === 'unpaid') {
    $where[] = "d.payment_status IN ('unpaid', 'partial')";
} elseif ($selectedStatusFilter === 'paid') {
    $where[] = "d.payment_status = 'paid'";
}

$whereSql = implode(' AND ', $where);

$dispatchesStmt = db()->prepare("
    SELECT d.*, i.name AS item_name, i.unit, u.name AS creator_name, s.shop_name, s.manager_name AS shop_manager
    FROM dist_dispatches d
    JOIN wh_stock_items i ON i.id = d.stock_item_id
    LEFT JOIN dist_shops s ON s.id = d.shop_id
    JOIN users u ON u.id = d.created_by
    WHERE {$whereSql}
    ORDER BY d.created_at DESC
");
$dispatchesStmt->execute($params);
$shopDispatches = $dispatchesStmt->fetchAll();

// Payment Transactions History
$paymentsQuery = "
    SELECT p.*, d.destination_pos_location, d.quantity_dispatched, d.unit_price, d.total_price,
           i.name AS item_name, i.unit, u.name AS receiver_name, r.name AS receiver_role,
           b.bank_name, b.account_number, b.account_type
    FROM dist_dispatch_payments p
    JOIN dist_dispatches d ON d.id = p.dispatch_id
    JOIN wh_stock_items i ON i.id = d.stock_item_id
    JOIN users u ON u.id = p.received_by
    LEFT JOIN roles r ON r.id = u.role_id
    LEFT JOIN fin_bank_accounts b ON b.id = p.bank_account_id
";
if ($selectedShopId > 0) {
    $paymentsQuery .= " WHERE d.shop_id = " . (int)$selectedShopId;
}
$paymentsQuery .= " ORDER BY p.created_at DESC LIMIT 50";
$payments = db()->query($paymentsQuery)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">💰 <?= current_locale() === 'am' ? 'የሽያጭ ገቢ፣ የሱቆች ሂሳብ መዝገብና ክፍያ መቀበያ' : 'Sales Revenue & Shop Ledger Management' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'የቅርንጫፍ መሸጫ ሱቆች የሽያጭ ዝርዝር፣ ትክክለኛ የባንክ ሂሳቦች ክፍያ መቀበያ፣ እና የክፍያ መጠየቂያ ደረሰኝ ማዕከል።' : 'Collect payments into official company bank accounts and print invoices/receipts.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/distribution/dispatches.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ስርጭት መላኪያ' : 'Back to Dispatches' ?></a>
    </div>
</div>

<!-- Quick Navigation Tabs -->
<div style="display:flex;gap:10px;margin-bottom:20px;border-bottom:2px solid var(--border);padding-bottom:8px">
    <a href="?shop_id=0" class="btn <?= ($selectedShopId === 0 && !isset($_GET['tab'])) ? 'primary' : 'secondary' ?>" style="font-size:13px">
        🏢 <?= current_locale() === 'am' ? 'የሁሉም ሱቆች ማጠቃለያ (All Shops Overview)' : 'All Shops Overview' ?>
    </a>
    <a href="?tab=direct_buyers" class="btn <?= (isset($_GET['tab']) && $_GET['tab'] === 'direct_buyers') ? 'primary' : 'secondary' ?>" style="font-size:13px">
        👤 <?= current_locale() === 'am' ? 'የቀጥታ ገዢዎች ሂሳብ (Direct Customers)' : 'Direct Customers' ?>
    </a>
</div>

<?php if ($selectedShopId === 0 && !isset($_GET['tab'])): ?>
    <!-- ALL SHOPS SUMMARY CARDS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;margin-bottom:24px">
        <?php foreach ($shops as $s): ?>
            <div class="card" style="border-top:4px solid <?= $s['outstanding_balance'] > 0 ? '#c62828' : '#2e7d32' ?>;display:flex;flex-direction:column;justify-content:space-between">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
                        <h3 style="margin:0;font-size:17px">🏢 <?= h($s['shop_name']) ?></h3>
                        <?php if ($s['outstanding_balance'] > 0): ?>
                            <span class="badge" style="background:#ffebee;color:#c62828">ዕዳ አለበት</span>
                        <?php else: ?>
                            <span class="badge" style="background:#e8f5e9;color:#2e7d32">ሙሉ የተጣራ</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
                        👤 ተረካቢ ኃላፊ፡ <strong><?= h($s['manager_name']) ?></strong><br>
                        📞 ስልክ፡ <?= h($s['phone'] ?: '—') ?>
                    </div>

                    <div style="background:var(--bg);border-radius:6px;padding:10px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;margin-bottom:14px">
                        <div>
                            <span class="text-muted">ጠቅላላ ሽያጭ፡</span><br>
                            <strong><?= number_format($s['total_sales_value'], 2) ?> ETB</strong>
                        </div>
                        <div>
                            <span class="text-muted">የተከፈለ፡</span><br>
                            <strong style="color:#2e7d32"><?= number_format($s['total_paid_value'], 2) ?> ETB</strong>
                        </div>
                        <div style="grid-column:1 / -1;border-top:1px dashed var(--border);padding-top:6px">
                            <span class="text-muted">ለድርጅቱ የሚፈለግ ቀሪ ዕዳ (Remaining):</span><br>
                            <strong style="color:<?= $s['outstanding_balance'] > 0 ? '#c62828' : '#2e7d32' ?>;font-size:16px">
                                <?= number_format($s['outstanding_balance'], 2) ?> ETB
                            </strong>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:6px">
                    <a href="?shop_id=<?= (int)$s['id'] ?>" class="btn primary" style="flex:1;text-align:center;font-size:13px;padding:8px">
                        📂 ሂሳብ ክፈት / ዝርዝር ይመልከቱ &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($activeShop): ?>
    <!-- ACTIVE SHOP LEDGER VIEW -->
    <div class="card" style="border-left:5px solid #1b5e20;background:#f1f8e9;margin-bottom:20px;padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
                <h2 style="margin:0;color:#1b5e20">🏢 <?= h($activeShop['shop_name']) ?> — የተሟላ የሂሳብ መዝገብ</h2>
                <div style="font-size:14px;color:#2e7d32;margin-top:4px">
                    ተረካቢ ኃላፊ፡ <strong><?= h($activeShop['manager_name']) ?></strong> | ስልክ፡ <?= h($activeShop['phone'] ?: '—') ?>
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <button type="button" class="btn" style="background:#0288d1;color:#fff" onclick="printShopStatement()">
                    🖨️ የክፍያ መጠየቂያ ደረሰኝ አትም (Print Invoice/Statement)
                </button>
                <a href="?shop_id=0" class="btn secondary">&times; ሱቁን ዝጋ</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- FILTERS SECTION -->
<div class="card" style="margin-bottom:20px;padding:14px">
    <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <?php if ($selectedShopId > 0): ?>
            <input type="hidden" name="shop_id" value="<?= $selectedShopId ?>">
        <?php elseif (isset($_GET['tab'])): ?>
            <input type="hidden" name="tab" value="<?= h($_GET['tab']) ?>">
        <?php endif; ?>

        <div class="form-group" style="margin-bottom:0">
            <label style="font-size:12px"><?= current_locale() === 'am' ? 'በምርት ዓይነት ማጣሪያ' : 'Filter by Product' ?></label>
            <select name="item_id" onchange="this.form.submit()" style="font-size:13px;padding:6px">
                <option value="0">— ሁሉም ምርቶች (ወተት፣ እንቁላል፣ ዓሳ...) —</option>
                <?php foreach ($stockItems as $it): ?>
                    <option value="<?= (int)$it['id'] ?>" <?= $selectedItemFilter === (int)$it['id'] ? 'selected' : '' ?>>
                        <?= h($it['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0">
            <label style="font-size:12px"><?= current_locale() === 'am' ? 'የክፍያ ሁኔታ' : 'Payment Status' ?></label>
            <select name="payment_status" onchange="this.form.submit()" style="font-size:13px;padding:6px">
                <option value="all" <?= $selectedStatusFilter === 'all' ? 'selected' : '' ?>>— ሁሉም (የተከፈለም ያልተከፈለም) —</option>
                <option value="unpaid" <?= $selectedStatusFilter === 'unpaid' ? 'selected' : '' ?>>🔴 ያልተከፈሉ ብቻ (Unpaid / Partial)</option>
                <option value="paid" <?= $selectedStatusFilter === 'paid' ? 'selected' : '' ?>>🟢 ሙሉ የተከፈሉ ብቻ (Paid)</option>
            </select>
        </div>

        <div style="margin-bottom:0">
            <a href="?<?= $selectedShopId > 0 ? 'shop_id='.$selectedShopId : '' ?>" class="btn secondary" style="padding:6px 12px;font-size:13px">ማጣሪያውን አጽዳ</a>
        </div>
    </form>
</div>

<!-- DISPATCHES & PAYMENT TABLE -->
<div class="card" style="margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0">📋 <?= current_locale() === 'am' ? 'የምርት ስርጭት እና ሽያጭ ዝርዝር' : 'Dispatched Sales Ledger' ?> (<?= count($shopDispatches) ?>)</h3>
        <span class="text-muted" style="font-size:12px">ሁሉም ሰዓታት በኢትዮጵያ 12 ሰዓት አቆጣጠር (ጠዋት/ከሰዓት) ናቸው</span>
    </div>

    <div style="overflow-x:auto">
        <table id="salesTable">
            <thead>
                <tr>
                    <th>መላኪያ #</th>
                    <th>ቀንና ሰዓት (12-ሰዓት)</th>
                    <th>ሱቅ / ደንበኛ</th>
                    <th>የምርት ስም</th>
                    <th>መጠን</th>
                    <th>የአንዱ ዋጋ</th>
                    <th>ጠቅላላ ሽያጭ</th>
                    <th>የተከፈለ</th>
                    <th>ቀሪ ዕዳ (Remaining)</th>
                    <th>ሁኔታ</th>
                    <th>እርምጃ / ክፍያ ተቀበል</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sumTotal = 0; $sumPaid = 0; $sumRemaining = 0;
                foreach ($shopDispatches as $d): 
                    $rem = max(0, (float)$d['total_price'] - (float)$d['amount_paid']);
                    $sumTotal += (float)$d['total_price'];
                    $sumPaid += (float)$d['amount_paid'];
                    $sumRemaining += $rem;
                ?>
                    <tr>
                        <td><strong>#<?= (int)$d['id'] ?></strong></td>
                        <td style="font-size:12px;white-space:nowrap">
                            📅 <?= eth_date($d['created_at']) ?><br>
                            <small style="color:#0288d1;font-weight:600">⏰ <?= eth_time_12h($d['created_at']) ?></small>
                        </td>
                        <td>
                            <strong><?= h($d['shop_name'] ?: $d['destination_pos_location']) ?></strong>
                        </td>
                        <td><strong><?= h($d['item_name']) ?></strong></td>
                        <td><?= number_format($d['quantity_dispatched'], 2) ?> <?= h($d['unit']) ?></td>
                        <td><?= number_format($d['unit_price'], 2) ?> ብር</td>
                        <td><strong><?= number_format($d['total_price'], 2) ?> ብር</strong></td>
                        <td style="color:#2e7d32"><strong><?= number_format($d['amount_paid'], 2) ?> ብር</strong></td>
                        <td style="color:<?= $rem > 0 ? '#c62828' : '#2e7d32' ?>;font-weight:700">
                            <?= number_format($rem, 2) ?> ብር
                        </td>
                        <td>
                            <?php if ($d['payment_status'] === 'paid'): ?>
                                <span class="badge" style="background:#e8f5e9;color:#2e7d32">✓ የተከፈለ</span>
                            <?php elseif ($d['payment_status'] === 'partial'): ?>
                                <span class="badge" style="background:#fff8e1;color:#f57c00">⏳ ከፊል</span>
                            <?php else: ?>
                                <span class="badge" style="background:#ffebee;color:#c62828">🔴 አልተከፈለም</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;align-items:center">
                                <?php if ($rem > 0): ?>
                                    <button type="button" class="btn success" style="padding:5px 10px;font-size:12px" onclick="openPaymentModal(<?= (int)$d['id'] ?>, <?= $rem ?>, '<?= h($d['item_name']) ?>', '<?= h($d['shop_name'] ?: $d['destination_pos_location']) ?>')">
                                        💳 ክፍያ ተቀበል
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn secondary" style="padding:5px 10px;font-size:12px" onclick="printSingleReceipt(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>)">
                                    🧾 ደረሰኝ
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($shopDispatches)): ?>
                    <tr><td colspan="11" class="text-muted" style="text-align:center">ምንም መዝገብ አልተገኘም።</td></tr>
                <?php else: ?>
                    <tr style="background:var(--bg);font-weight:700;border-top:2px solid var(--border)">
                        <td colspan="6" style="text-align:right">ጠቅላላ ድምር፦</td>
                        <td><?= number_format($sumTotal, 2) ?> ብር</td>
                        <td style="color:#2e7d32"><?= number_format($sumPaid, 2) ?> ብር</td>
                        <td style="color:#c62828"><?= number_format($sumRemaining, 2) ?> ብር</td>
                        <td colspan="2"></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PAYMENT MODAL -->
<div id="paymentModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;max-width:520px;width:90%;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.3)">
        <h3 style="margin-top:0;color:#1b5e20">💳 የሽያጭ ክፍያ መቀበያ (Official Payment Entry)</h3>
        <p class="text-muted" style="font-size:13px;margin-bottom:16px">
            የተላከለት፡ <strong id="modalShopName"></strong> | ምርት፡ <strong id="modalItemName"></strong>
        </p>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="record_payment" value="1">
            <input type="hidden" name="dispatch_id" id="modalDispatchId" value="">

            <div class="form-group">
                <label>የሚከፈለው ገንዘብ መጠን በብር (Amount to Pay) *</label>
                <input type="number" step="0.01" min="0.01" name="payment_amount" id="modalPayAmount" required style="font-size:18px;font-weight:700;color:#1b5e20">
                <small class="text-muted">ቀሪ ዕዳ፡ <strong id="modalRemainingText">0.00</strong> ETB (ከፊል ወይም ሙሉ መክፈል ይቻላል)</small>
            </div>

            <div class="form-group">
                <label>ክፍያው የገባበት የድርጅቱ ባንክ / ካዝና (Company Bank Account) *</label>
                <select name="bank_account_id" required style="width:100%;font-size:14px;padding:8px">
                    <option value="">— የትኛውን ባንክ መረጡ? —</option>
                    <?php foreach ($bankAccounts as $b): ?>
                        <option value="<?= (int)$b['id'] ?>">
                            🏦 <?= h($b['bank_name']) ?> — ሂሳብ ቁጥር: <?= h($b['account_number']) ?> (<?= h($b['account_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>የባንክ ማመሳከሪያ ቁጥር / ትራንዛክሽን ቁጥር (Reference / Txn ID)</label>
                <input type="text" name="reference_no" placeholder="ለምሳሌ፡ FT241029...">
            </div>

            <div class="form-group">
                <label>ተጨማሪ ማስታወሻ (Notes)</label>
                <input type="text" name="notes" placeholder="ለምሳሌ፡ በከፊል የተከፈለ የወተት ክፍያ...">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
                <button type="button" class="btn secondary" onclick="closePaymentModal()">ተመለስ</button>
                <button type="submit" class="btn success" style="font-weight:700">✓ ክፍያውን አረጋግጥና መዝግብ</button>
            </div>
        </form>
    </div>
</div>

<!-- PRINTABLE RECEIPT TEMPLATE (Hidden, used for printing) -->
<div id="printReceiptSection" style="display:none"></div>

<script>
function openPaymentModal(dispatchId, remaining, itemName, shopName) {
    document.getElementById('modalDispatchId').value = dispatchId;
    document.getElementById('modalShopName').innerText = shopName;
    document.getElementById('modalItemName').innerText = itemName;
    document.getElementById('modalPayAmount').value = remaining.toFixed(2);
    document.getElementById('modalPayAmount').max = remaining.toFixed(2);
    document.getElementById('modalRemainingText').innerText = remaining.toFixed(2);
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

// Modern 12-Hour Ethiopian Time Receipt Printer
function printSingleReceipt(data) {
    const remaining = Math.max(0, parseFloat(data.total_price) - parseFloat(data.amount_paid));
    const content = `
        <div style="font-family:'Segoe UI',sans-serif;max-width:480px;margin:auto;padding:24px;border:2px solid #333;border-radius:8px">
            <div style="text-align:center;border-bottom:2px dashed #666;padding-bottom:12px;margin-bottom:14px">
                <h2 style="margin:0;font-size:20px">አለልቱ የተቀናጀ እርሻና የወተት ልማት</h2>
                <h4 style="margin:4px 0;color:#555">የክፍያ ማረጋገጫና መጠየቂያ ደረሰኝ</h4>
                <div style="font-size:12px;color:#777">ደረሰኝ #: REC-DISP-${data.id}</div>
            </div>

            <div style="font-size:13px;line-height:1.6;margin-bottom:14px">
                <div><strong>ለ (Customer/Shop):</strong> ${data.shop_name || data.destination_pos_location}</div>
                <div><strong>ቀንና ሰዓት:</strong> ${data.created_at}</div>
                <div><strong>የምርት ስም:</strong> ${data.item_name}</div>
                <div><strong>የተላከ መጠን:</strong> ${data.quantity_dispatched} ${data.unit}</div>
                <div><strong>የአንዱ ዋጋ:</strong> ${parseFloat(data.unit_price).toFixed(2)} ብር</div>
            </div>

            <div style="border-top:1px solid #ccc;border-bottom:1px solid #ccc;padding:8px 0;margin-bottom:14px;font-size:14px">
                <div style="display:flex;justify-content:space-between"><span>ጠቅላላ የሽያጭ ዋጋ:</span><strong>${parseFloat(data.total_price).toFixed(2)} ብር</strong></div>
                <div style="display:flex;justify-content:space-between;color:#2e7d32"><span>እስካሁን የተከፈለ:</span><strong>${parseFloat(data.amount_paid).toFixed(2)} ብር</strong></div>
                <div style="display:flex;justify-content:space-between;color:#c62828;font-size:16px;font-weight:bold;margin-top:4px">
                    <span>ለድርጅቱ የሚፈለግ ቀሪ ዕዳ:</span><span>${remaining.toFixed(2)} ብር</span>
                </div>
            </div>

            <div style="font-size:12px;background:#f9f9f9;padding:8px;border-radius:4px;margin-bottom:14px">
                <strong>የክፍያ መፈጸሚያ የድርጅታችን የባንክ ሂሳቦች፦</strong><br>
                • የኢትዮጵያ ንግድ ባንክ (CBE): 1000192837465<br>
                • ኦሮሚያ ባንክ: 1092837465001<br>
                • ቴሌብር ነጋዴ ቁጥር: 0911002233
            </div>

            <div style="display:flex;justify-content:space-between;margin-top:24px;font-size:12px">
                <div>ያዘጋጀው ፋይናንስ: ______________</div>
                <div>ተረካቢ ፊርማ: ______________</div>
            </div>
        </div>
    `;

    const win = window.open('', '_blank');
    win.document.write('<html><head><title>ደረሰኝ - #' + data.id + '</title></head><body onload="window.print();window.close();">' + content + '</body></html>');
    win.document.close();
}

function printShopStatement() {
    window.print();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
