<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper', 'distribution_officer']);

$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['create_dispatch'])) {
            $stockItemId = (int) $_POST['stock_item_id'];
            $quantity = (float) $_POST['quantity'];
            $unitPrice = (float) ($_POST['unit_price'] ?? 0);
            $deliveryType = $_POST['delivery_type'] ?? 'shop';
            $shopId = null;

            if ($deliveryType === 'gift_protocol') {
                $recipient = trim($_POST['gift_recipient'] ?? '');
                if (empty($recipient)) {
                    throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ የስጦታውን ወይም የአስተዳደር ተቀባይ ስም ያስገቡ' : 'Recipient name is required.');
                }
                $destination = "🎁 ስጦታ/አስተዳደር/ምግብ፡ {$recipient}";
                $unitPrice = 0.00;
            } elseif ($deliveryType === 'shop') {
                if ($unitPrice <= 0) {
                    throw new InvalidArgumentException(current_locale() === 'am' ? 'የአንዱ መሸጫ ዋጋ ከ 0 በላይ መሆን አለበት' : 'Unit selling price is mandatory.');
                }
                $selectedShopId = (int)($_POST['shop_id'] ?? 0);
                if ($selectedShopId > 0) {
                    $shop = db()->query("SELECT * FROM dist_shops WHERE id = {$selectedShopId}")->fetch();
                    $shopId = (int)$shop['id'];
                    $shopName = $shop['shop_name'] ?? 'የድርጅቱ ሱቅ';
                    $managerName = $shop['manager_name'] ?? 'ተረካቢ ኃላፊ';
                    $phone = $shop['phone'] ?? '';
                } else {
                    $shopName = trim($_POST['custom_shop_name'] ?? 'የድርጅቱ ሱቅ');
                    $managerName = trim($_POST['custom_manager_name'] ?? 'ተረካቢ ኃላፊ');
                    $phone = trim($_POST['custom_phone'] ?? '');
                    if (!empty($shopName)) {
                        $stmt = db()->prepare("INSERT INTO dist_shops (shop_name, manager_name, phone) VALUES (?, ?, ?)");
                        $stmt->execute([$shopName, $managerName, $phone ?: null]);
                        $shopId = (int) db()->lastInsertId();
                    }
                }
                $destination = "🏢 ሱቅ፡ {$shopName} (ተረካቢ ኃላፊ፡ {$managerName}" . ($phone ? " — ስልክ፡ {$phone}" : "") . ")";
            } else {
                if ($unitPrice <= 0) {
                    throw new InvalidArgumentException(current_locale() === 'am' ? 'የአንዱ መሸጫ ዋጋ ከ 0 በላይ መሆን አለበት' : 'Unit selling price is mandatory.');
                }
                $buyerName = trim($_POST['buyer_name'] ?? '');
                if (empty($buyerName)) {
                    throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ የገዢውን ስም ያስገቡ' : 'Buyer name is required.');
                }
                $destination = "👤 ቀጥታ ገዢ፡ {$buyerName}";
            }

            $warehouseId = 1; // 1 Main Central Warehouse
            $avail = stock_current($warehouseId, $stockItemId);
            if ($quantity > $avail) {
                throw new RuntimeException(current_locale() === 'am' ? "ያለዎት ክምችት {$avail} ብቻ ስለሆነ {$quantity} መላክ አይቻልም" : "Insufficient stock in warehouse.");
            }

            dispatch_create(null, null, $destination, $warehouseId, $stockItemId, $quantity, $unitPrice,
                $deliveryType === 'gift_protocol' ? 'internal_use' : ($deliveryType === 'shop' ? 'shop_transfer' : 'direct_sale'),
                $shopId);

            flash(current_locale() === 'am' ? '✓ የምርት ስርጭቱና የሽያጭ ትዕዛዙ ተመዝግቧል! ምርቱ ወጪ ሆኗል። የክፍያ ሂደቱን ፋይናንስ ያጠናቅቃል።' : 'Dispatch created successfully.');
        } elseif (isset($_POST['confirm_delivery'])) {
            $dispatchId = (int) $_POST['dispatch_id'];
            $deliveredQty = (float) $_POST['delivered_quantity'];
            dispatch_confirm_delivery($dispatchId, $deliveredQty);
            flash(current_locale() === 'am' ? '✓ ርክክቡ ተረጋግጧል!' : 'Delivery confirmed.');
        } elseif (isset($_POST['record_payment'])) {
            if (!has_role(['super_admin', 'general_manager', 'finance_officer', 'distribution_officer'])) {
                throw new RuntimeException(current_locale() === 'am' ? 'ይህንን ተግባር ለማከናወን ፈቃድ የለዎትም።' : 'Unauthorized to record payments.');
            }
            $dispatchId = (int) $_POST['dispatch_id'];
            $amount = (float) $_POST['amount'];
            $method = $_POST['payment_method'] ?? 'cash';
            $refNo = $_POST['reference_number'] ?? null;
            $notes = $_POST['notes'] ?? null;
            $bankAccountId = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
            dispatch_record_payment($dispatchId, $amount, $method, $refNo, $notes, (int)$currentUser['id'], $bankAccountId);
            flash(current_locale() === 'am' ? '✓ ክፍያው በተሳካ ሁኔታ ተመዝግቧል!' : 'Payment recorded successfully.');
        } elseif (isset($_POST['cancel_dispatch'])) {
            if (!has_role(['super_admin', 'general_manager', 'distribution_officer'])) {
                throw new RuntimeException(current_locale() === 'am' ? 'ይህንን ተግባር ለማከናወን ፈቃድ የለዎትም።' : 'Unauthorized.');
            }
            $dispatchId = (int)$_POST['dispatch_id'];
            $reason = trim($_POST['cancel_reason'] ?? 'Cancelled by user');
            dispatch_cancel($dispatchId, $reason, (int)$currentUser['id']);
            flash(current_locale() === 'am' ? '✓ መላኪያው ተሰርዟል፤ የወጣው ክምችት ወደ መጋዘን ተመልሷል!' : 'Dispatch cancelled and stock returned to warehouse.');
        }
        header('Location: ' . BASE_URL . '/modules/distribution/dispatches.php');
        exit;
    } catch (Throwable $e) {
        flash(current_locale() === 'am' ? 'ስህተት፦ ' . $e->getMessage() : 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$shops = db()->query("SELECT * FROM dist_shops ORDER BY shop_name")->fetchAll();

$items = db()->query(
    "SELECT i.id, i.name, i.sku, i.unit, COALESCE(SUM(b.quantity_on_hand), 0) AS current_stock
     FROM wh_stock_items i
     LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.warehouse_id = 1 AND b.quantity_on_hand > 0
     GROUP BY i.id ORDER BY i.name"
)->fetchAll();

// Dispatches list
$dispatches = db()->query(
    "SELECT d.*, i.name AS item_name, i.unit, u.name AS creator_name, r.name AS creator_role, s.shop_name
     FROM dist_dispatches d
     JOIN wh_stock_items i ON i.id = d.stock_item_id
     JOIN users u ON u.id = d.created_by
     LEFT JOIN roles r ON r.id = u.role_id
     LEFT JOIN dist_shops s ON s.id = d.shop_id
     ORDER BY d.created_at DESC LIMIT 50"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">📦 <?= current_locale() === 'am' ? 'የምርት ስርጭት እና ሽያጭ መላኪያ' : 'Dispatches & Product Distribution' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'ከመጋዘን ወደ ቅርንጫፍ መሸጫ ሱቆች ወይም ለቀጥታ ገዢዎች የተላኩ ምርቶች መዝገብ። (የክፍያ አሰባሰብ በፋይናንስ ገጽ ይከናወናል)' : 'Dispatch farm products to branch shops or direct buyers.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/finance/sales_payments.php" class="btn" style="background:#1b5e20;color:#fff">
            💰 <?= current_locale() === 'am' ? 'ወደ ፋይናንስ ሽያጭና ክፍያ መቀበያ' : 'Go to Finance Sales Payments' ?> &rarr;
        </a>
    </div>
</div>

<div class="card" style="max-width:700px;margin-bottom:24px">
    <h3 style="margin-top:0">🚚 <?= current_locale() === 'am' ? 'አዲስ የምርት ስርጭት / ሽያጭ መዝግብ' : 'Record New Dispatch' ?></h3>
    <form method="post" id="dispatchForm"><?= csrf_field() ?>
        <input type="hidden" name="create_dispatch" value="1">

        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'የስርጭት / የመላኪያ ዓይነት *' : 'Delivery Type *' ?></label>
            <select name="delivery_type" id="deliveryType" required onchange="toggleDeliveryFields()" style="width:100%;font-size:15px;padding:8px">
                <option value="shop">🏢 ወደ ቅርንጫፍ መሸጫ ሱቅ (Branch Shop Transfer)</option>
                <option value="direct">👤 ለቀጥታ ገዢ / ደንበኛ (Direct Sale to Customer)</option>
                <option value="gift_protocol">🎁 ስጦታ / ፕሮቶኮል / አስተዳደር (Free/Protocol Gift)</option>
            </select>
        </div>

        <!-- Shop Selection -->
        <div id="shopFields">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'ቅርንጫፍ መሸጫ ሱቅ ምረጥ *' : 'Select Branch Shop *' ?></label>
                <select name="shop_id" id="shopSelect" onchange="toggleCustomShop()" style="width:100%">
                    <option value="">— ነባር ሱቅ ይምረጡ —</option>
                    <?php foreach ($shops as $s): ?>
                        <option value="<?= (int)$s['id'] ?>">
                            🏢 <?= h($s['shop_name']) ?> (ተረካቢ፡ <?= h($s['manager_name']) ?><?= $s['phone'] ? ' - '.$s['phone'] : '' ?>)
                        </option>
                    <?php endforeach; ?>
                    <option value="0">➕ አዲስ ሱቅ መዝግብ (Add New Shop)</option>
                </select>
            </div>

            <div id="customShopBox" style="display:none;background:var(--bg);border:1px dashed var(--border);border-radius:6px;padding:12px;margin-bottom:14px">
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የአዲሱ ሱቅ ስም *' : 'New Shop Name *' ?></label>
                    <input type="text" name="custom_shop_name" placeholder="ለምሳሌ፡ አለልቱ ሱቅ ቁጥር 3">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የሱቁ ተረካቢ ኃላፊ ስም *' : 'Shop Manager Name *' ?></label>
                        <input type="text" name="custom_manager_name" placeholder="ለምሳሌ፡ አቶ በቀለ">
                    </div>
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የስልክ ቁጥር' : 'Phone Number' ?></label>
                        <input type="text" name="custom_phone" placeholder="09...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Direct Customer Fields -->
        <div id="directCustomerFields" style="display:none">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የገዢው / የደንበኛው ሙሉ ስም *' : 'Buyer Name *' ?></label>
                <input type="text" name="buyer_name" placeholder="ለምሳሌ፡ አቶ ከበደ ለማ">
            </div>
        </div>

        <!-- Gift Protocol Fields -->
        <div id="giftFields" style="display:none">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የስጦታው / የአስተዳደር ተቀባይ ስም *' : 'Recipient Name *' ?></label>
                <input type="text" name="gift_recipient" placeholder="ለምሳሌ፡ ለአካባቢው ት/ቤት የተሰጠ ስጦታ">
            </div>
        </div>

        <!-- Product Selection -->
        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'የሚላከው ምርት (Product) *' : 'Product to Dispatch *' ?></label>
            <select name="stock_item_id" id="stockItemSelect" required onchange="updateStockDisplay()" style="width:100%">
                <option value="">— ምርት ይምረጡ (Select Product) —</option>
                <?php foreach ($items as $i): ?>
                    <option value="<?= (int)$i['id'] ?>" data-stock="<?= (float)$i['current_stock'] ?>" data-unit="<?= h($i['unit']) ?>">
                        <?= h($i['name']) ?> (በመጋዘን ያለ፡ <?= number_format((float)$i['current_stock'], 2) ?> <?= h($i['unit']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="stockNotice" style="display:none;background:#e8f5e9;padding:8px 12px;border-radius:4px;margin-bottom:12px;font-size:13px;color:#2e7d32">
            በአሁኑ ሰዓት በመጋዘን ያለው ክምችት: <strong id="stockNoticeVal">0</strong>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የሚላከው መጠን (Quantity) *' : 'Quantity *' ?></label>
                <input type="number" step="0.01" min="0.01" name="quantity" id="dispQty" required placeholder="ለምሳሌ፡ 100">
            </div>
            <div class="form-group" id="unitPriceBox">
                <label><?= current_locale() === 'am' ? 'የአንዱ መሸጫ ዋጋ በብር (Unit Price) *' : 'Unit Price (ETB) *' ?></label>
                <input type="number" step="0.01" min="0" name="unit_price" id="dispUnitPrice" required placeholder="ለምሳሌ፡ 75.00">
            </div>
        </div>

        <button class="btn" type="submit" style="font-weight:700">
            🚀 <?= current_locale() === 'am' ? 'ምርቱን ወጪ አድርግና ላክ (Dispatch Now)' : 'Dispatch Now' ?>
        </button>
    </form>
</div>

<!-- Dispatches History -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0">📜 <?= current_locale() === 'am' ? 'የተላኩ ምርቶች ማህደር (Dispatch History)' : 'Dispatch History' ?></h3>
        <span class="text-muted" style="font-size:12px">የቅርብ 50 ስርጭቶች</span>
    </div>

    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>መላኪያ #</th>
                    <th>የተላከበት ቀንና ሰዓት</th>
                    <th>መዳረሻ (ሱቅ / ገዢ)</th>
                    <th>የምርት ስም</th>
                    <th>የተላከ መጠን</th>
                    <th>የአንዱ ዋጋ</th>
                    <th>ጠቅላላ ሽያጭ ዋጋ</th>
                    <th>የክፍያ ሁኔታ (ፋይናንስ)</th>
                    <th>ያወጣው ሠራተኛ</th>
                    <th>ተግባር</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dispatches as $d): ?>
                    <tr>
                        <td><strong>#<?= (int)$d['id'] ?></strong></td>
                        <td style="font-size:12px;white-space:nowrap">
                            📅 <?= eth_date($d['created_at']) ?><br>
                            <small class="text-muted">⏰ <?= eth_time($d['created_at']) ?></small>
                        </td>
                        <td><strong><?= h($d['destination_pos_location']) ?></strong></td>
                        <td><?= h($d['item_name']) ?></td>
                        <td><strong><?= number_format($d['quantity_dispatched'], 2) ?> <?= h($d['unit']) ?></strong></td>
                        <td><?= number_format($d['unit_price'], 2) ?> ብር</td>
                        <td><strong style="color:#1b5e20"><?= number_format($d['total_price'], 2) ?> ብር</strong></td>
                        <td>
                            <?php if ($d['payment_status'] === 'paid'): ?>
                                <span class="badge" style="background:#e8f5e9;color:#2e7d32">✓ ሙሉ ተከፍሏል</span>
                            <?php elseif ($d['payment_status'] === 'partial'): ?>
                                <span class="badge" style="background:#fff8e1;color:#f57c00">⏳ ከፊል (<?= number_format($d['amount_paid'], 2) ?>)</span>
                            <?php else: ?>
                                <span class="badge" style="background:#ffebee;color:#c62828">🔴 አልተከፈለም (ዕዳ)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            👤 <strong><?= h($d['creator_name']) ?></strong><br>
                            <small class="text-muted"><?= h($d['creator_role']) ?></small>
                        </td>
                        <td>
                            <?php if ($d['status'] === 'cancelled'): ?>
                                <span class="badge" style="background:#ffebee;color:#c62828">የተሰረዘ</span>
                            <?php elseif ((float)$d['amount_paid'] <= 0 && has_role(['super_admin', 'general_manager', 'distribution_officer'])): ?>
                                <form method="post" onsubmit="return confirm('መላኪያውን መሰረዝ እና ምርቱን ወደ መጋዘን መመለስ ይፈልጋሉ?');" style="display:inline;margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="cancel_dispatch" value="1">
                                    <input type="hidden" name="dispatch_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn secondary" style="padding:3px 8px;font-size:11px;color:#c62828">❌ ሰርዝ</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:12px">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($dispatches)): ?>
                    <tr><td colspan="9" class="text-muted" style="text-align:center">ምንም የተላከ ምርት የለም።</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleDeliveryFields() {
    const type = document.getElementById('deliveryType').value;
    const shopFields = document.getElementById('shopFields');
    const directFields = document.getElementById('directCustomerFields');
    const giftFields = document.getElementById('giftFields');
    const unitPriceBox = document.getElementById('unitPriceBox');
    const unitPriceInput = document.getElementById('dispUnitPrice');

    shopFields.style.display = type === 'shop' ? 'block' : 'none';
    directFields.style.display = type === 'direct' ? 'block' : 'none';
    giftFields.style.display = type === 'gift_protocol' ? 'block' : 'none';

    if (type === 'gift_protocol') {
        unitPriceBox.style.display = 'none';
        unitPriceInput.value = '0';
        unitPriceInput.removeAttribute('required');
    } else {
        unitPriceBox.style.display = 'block';
        unitPriceInput.setAttribute('required', 'required');
    }
}

function toggleCustomShop() {
    const sel = document.getElementById('shopSelect').value;
    const customBox = document.getElementById('customShopBox');
    customBox.style.display = sel === '0' ? 'block' : 'none';
}

function updateStockDisplay() {
    const sel = document.getElementById('stockItemSelect');
    const opt = sel.options[sel.selectedIndex];
    const notice = document.getElementById('stockNotice');
    const val = document.getElementById('stockNoticeVal');
    const qtyInput = document.getElementById('dispQty');

    if (opt && opt.value !== '') {
        const stock = parseFloat(opt.getAttribute('data-stock') || 0);
        const unit = opt.getAttribute('data-unit') || '';
        val.innerText = stock.toFixed(2) + ' ' + unit;
        notice.style.display = 'block';
        qtyInput.max = stock;
    } else {
        notice.style.display = 'none';
        qtyInput.removeAttribute('max');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleDeliveryFields();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
