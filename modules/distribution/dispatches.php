<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper', 'finance_officer', 'distribution_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['create_dispatch'])) {
            $stockItemId = (int) $_POST['stock_item_id'];
            $quantity = (float) $_POST['quantity'];
            $unitPrice = (float) ($_POST['unit_price'] ?? 0);

            $deliveryType = $_POST['delivery_type'] ?? 'shop';
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
                $shopId = (int)($_POST['shop_id'] ?? 0);
                if ($shopId > 0) {
                    $shop = db()->query("SELECT * FROM dist_shops WHERE id = {$shopId}")->fetch();
                    $shopName = $shop['shop_name'] ?? 'የድርጅቱ ሱቅ';
                    $managerName = $shop['manager_name'] ?? 'ተረካቢ ኃላፊ';
                    $phone = $shop['phone'] ?? '';
                } else {
                    $shopName = trim($_POST['custom_shop_name'] ?? 'የድርጅቱ ሱቅ');
                    $managerName = trim($_POST['custom_manager_name'] ?? 'ተረካቢ ኃላፊ');
                    $phone = trim($_POST['custom_phone'] ?? '');
                    if (!empty($shopName)) {
                        db()->prepare("INSERT INTO dist_shops (shop_name, manager_name, phone) VALUES (?, ?, ?)")->execute([$shopName, $managerName, $phone ?: null]);
                    }
                }
                // Save manager name snapshot so future manager changes NEVER change this historical record
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

            // No vehicle or driver selected, pass null
            dispatch_create(null, null, $destination, $warehouseId, $stockItemId, $quantity, $unitPrice,
                $deliveryType === 'gift_protocol' ? 'internal_use' : ($deliveryType === 'shop' ? 'shop_transfer' : 'direct_sale'));
            flash(current_locale() === 'am' ? '✓ የምርት ስርጭቱና የሽያጭ ትዕዛዙ ተመዝግቧል!' : 'Dispatch and sales order created.');
        } elseif (isset($_POST['record_payment'])) {
            $dispatchId = (int) $_POST['dispatch_id'];
            $amount = (float) $_POST['payment_amount'];
            $method = $_POST['payment_method'] ?? 'cash';
            $refNo = trim($_POST['reference_no'] ?? '');
            $notes = trim($_POST['payment_notes'] ?? '');

            dispatch_record_payment($dispatchId, $amount, $method, $refNo, $notes, current_user()['id']);
            flash(current_locale() === 'am' ? '✓ ክፍያው በተሳካ ሁኔታ ተመዝግቧል!' : 'Payment recorded successfully.');
        } elseif (isset($_POST['confirm_delivery'])) {
            $dispatchId = (int) $_POST['dispatch_id'];
            $deliveredQty = (float) $_POST['delivered_quantity'];
            dispatch_confirm_delivery($dispatchId, $deliveredQty);
            flash(current_locale() === 'am' ? '✓ ርክክቡ ተረጋግጧል!' : 'Delivery confirmed.');
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
    "SELECT d.*, i.name AS item_name, i.unit, u.name AS creator_name
     FROM dist_dispatches d
     JOIN wh_stock_items i ON i.id = d.stock_item_id
     JOIN users u ON u.id = d.created_by
     ORDER BY d.created_at DESC LIMIT 50"
)->fetchAll();

// Payment history list
$payments = db()->query(
    "SELECT p.*, d.destination_pos_location, d.quantity_dispatched, d.unit_price, i.name AS item_name, i.unit, u.name AS receiver_name
     FROM dist_dispatch_payments p
     JOIN dist_dispatches d ON d.id = p.dispatch_id
     JOIN wh_stock_items i ON i.id = d.stock_item_id
     JOIN users u ON u.id = p.received_by
     ORDER BY p.created_at DESC LIMIT 50"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">📦 <?= current_locale() === 'am' ? 'የምርት ስርጭት እና የሽያጭ ክፍያ መከታተያ' : 'Dispatches & Sales Payment Flow' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'ወደ ቅርንጫፍ መሸጫ ሱቆች የሚላኩ ምርቶች፣ የተሸጡበት ዋጋ እና የክፍያ ሁኔታ (ሙሉ / ከፊል ክፍያ) መከታተያ።' : 'Track farm products dispatched to destination branches, sales values, and payments.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/distribution/vehicles.php" class="btn secondary">🏢 <?= current_locale() === 'am' ? 'የመዳረሻ ሱቆች (Shops)' : 'Shops & Outlets' ?></a>
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary">📊 <?= current_locale() === 'am' ? 'ክምችት (Stock)' : 'Stock' ?></a>
    </div>
</div>

<!-- Dispatch & Sales Registration Form -->
<div class="card" style="max-width:680px;margin-bottom:24px">
    <h3>➕ <?= current_locale() === 'am' ? 'አዲስ የምርት ስርጭትና ሽያጭ መዝግብ' : 'New Product Dispatch & Sale' ?></h3>
    <form method="post" id="dispatchForm"><?= csrf_field() ?>
        <input type="hidden" name="create_dispatch" value="1">

        <div style="background:var(--card);padding:14px;border-radius:8px;margin-bottom:14px;border:1px solid var(--border)">
            <label style="font-weight:bold;display:block;margin-bottom:8px">🎯 <?= current_locale() === 'am' ? 'የመዳረሻ ዓይነት ይምረጡ *' : 'Delivery Destination Type *' ?></label>
            <div style="display:flex;gap:18px;margin-bottom:12px;flex-wrap:wrap">
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                    <input type="radio" name="delivery_type" value="shop" checked onchange="toggleDeliveryType(this.value)">
                    <strong>🏢 <?= current_locale() === 'am' ? 'ለድርጅቱ ሱቅ / ቅርንጫፍ' : 'To Branch / Shop' ?></strong>
                </label>
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                    <input type="radio" name="delivery_type" value="buyer" onchange="toggleDeliveryType(this.value)">
                    <strong>👤 <?= current_locale() === 'am' ? 'ለቀጥታ ደንበኛ / ገዢ' : 'Direct Buyer' ?></strong>
                </label>
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                    <input type="radio" name="delivery_type" value="gift_protocol" onchange="toggleDeliveryType(this.value)">
                    <strong>🎁 <?= current_locale() === 'am' ? 'የበዓል ስጦታ / ለአስተዳደር' : 'Gift / Protocol' ?></strong>
                </label>
            </div>

            <!-- Shop Selection Panel -->
            <div id="shopPanel">
                <div class="form-group" style="margin-bottom:6px">
                    <label style="font-size:12px;font-weight:700"><?= current_locale() === 'am' ? 'መዳረሻ ሱቅ / ቅርንጫፍ ምረጥ *' : 'Select Branch / Shop *' ?></label>
                    <select name="shop_id" id="shopSelect" onchange="checkCustomShop(this.value)" style="width:100%;padding:8px">
                        <?php foreach ($shops as $s): ?>
                            <option value="<?= (int)$s['id'] ?>">
                                <?= h($s['shop_name']) ?> — ተረካቢ ኃላፊ: <?= h($s['manager_name']) ?> (<?= h($s['phone'] ?: 'ስልክ የለም') ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="0">➕ <?= current_locale() === 'am' ? 'አዲስ ሱቅ መዝግብ...' : 'Register New Shop...' ?></option>
                    </select>
                </div>
                <div id="customShopFields" style="display:none;gap:10px;margin-top:8px">
                    <div style="flex:1">
                        <label style="font-size:11px"><?= current_locale() === 'am' ? 'አዲስ የሱቅ ስም *' : 'Shop Name *' ?></label>
                        <input type="text" name="custom_shop_name" placeholder="ለምሳሌ፡ ካዛንቺስ ሱቅ">
                    </div>
                    <div style="flex:1">
                        <label style="font-size:11px"><?= current_locale() === 'am' ? 'የሱቁ ተረካቢ ኃላፊ ስም *' : 'Manager Name *' ?></label>
                        <input type="text" name="custom_manager_name" placeholder="ለምሳሌ፡ አቶ ዳዊት ከበደ">
                    </div>
                </div>
            </div>

            <!-- Direct Buyer Panel -->
            <div id="buyerPanel" style="display:none;margin-top:6px">
                <label style="font-size:12px;font-weight:700"><?= current_locale() === 'am' ? 'የገዢው / የደንበኛው ስም *' : 'Buyer Name *' ?></label>
                <input type="text" name="buyer_name" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ አቶ ታደሰ ግርማ / ደንበኛ ሆቴል' : 'e.g. Tadesse Girma' ?>" style="width:100%;padding:8px">
            </div>

            <!-- Gift & Protocol Panel -->
            <div id="giftPanel" style="display:none;margin-top:6px;background:#fdf2f8;padding:10px;border-radius:6px;border:1px solid #f472b6">
                <label style="font-size:12px;font-weight:700;color:#9d174d">🎁 <?= current_locale() === 'am' ? 'የስጦታ ተቀባይ / የአስተዳደር ስም / አላማ *' : 'Recipient / Purpose *' ?></label>
                <input type="text" name="gift_recipient" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ለአዲስ ዓመት በዓል ለቀበሌ አስተዳደር' : 'e.g. Community holiday gift' ?>" style="width:100%;margin-top:4px;padding:8px">
            </div>
        </div>

        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'የሚላከው ምርት *' : 'Product to Dispatch *' ?></label>
            <select name="stock_item_id" id="dispStockItem" required onchange="updateDispStockHelper()" style="width:100%;padding:8px">
                <option value="">— <?= current_locale() === 'am' ? 'ምርት ይምረጡ' : 'Select Product' ?> —</option>
                <?php foreach ($items as $i): ?>
                    <option value="<?= (int)$i['id'] ?>" data-stock="<?= (float)$i['current_stock'] ?>" data-unit="<?= h($i['unit']) ?>">
                        <?= h($i['name']) ?> (<?= current_locale() === 'am' ? 'ክምችት፡' : 'Stock:' ?> <?= number_format((float)$i['current_stock'], 2) ?> <?= h($i['unit']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Live Stock Helper Banner -->
        <div id="dispStockBanner" style="display:none;background:#e8f5e9;border-left:4px solid #2e7d32;border-radius:6px;padding:10px 14px;margin-bottom:16px">
            <div style="font-size:12px;color:#2e7d32;font-weight:600"><?= current_locale() === 'am' ? 'በመጋዘን ውስጥ ያለው የቀረ ክምችት፦' : 'Available in Main Warehouse:' ?></div>
            <div style="font-size:18px;font-weight:800;color:#1b5e20" id="dispStockValue">0</div>
        </div>

        <div id="dispOutOfStock" style="display:none;background:#ffebee;border-left:4px solid #b3261e;color:#b3261e;border-radius:6px;padding:10px 14px;margin-bottom:16px;font-weight:600">
            ⚠️ <?= current_locale() === 'am' ? 'ይህ ምርት በመጋዘን ውስጥ አልቋል! መላክ አይቻልም።' : 'Out of stock! Cannot dispatch.' ?>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label><?= current_locale() === 'am' ? 'የሚላከው መጠን *' : 'Quantity *' ?></label>
                <input type="number" step="0.01" min="0.01" name="quantity" id="dispQty" required placeholder="100" oninput="calculateTotalValue()" style="padding:8px">
            </div>
            <div class="form-group" style="flex:1">
                <label><?= current_locale() === 'am' ? 'የአንዱ መሸጫ ዋጋ (ETB) *' : 'Unit Selling Price (ETB) *' ?></label>
                <input type="number" step="0.01" min="0.01" name="unit_price" id="dispUnitPrice" required placeholder="70.00" oninput="calculateTotalValue()" style="padding:8px">
            </div>
        </div>

        <!-- Live Total Sales Value Calculation -->
        <div style="background:var(--hover);border-radius:6px;padding:12px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center">
            <span style="font-weight:600;color:var(--text)"><?= current_locale() === 'am' ? 'ጠቅላላ የሚጠበቅ የሽያጭ ዋጋ፦' : 'Total Expected Sales Value:' ?></span>
            <span style="font-size:19px;font-weight:800;color:var(--primary)" id="dispTotalValDisplay">0.00 ETB</span>
        </div>

        <button class="btn" type="submit" id="submitDispBtn" style="background:#1e5631;border-color:#1e5631;padding:9px 20px">
            ✓ <?= current_locale() === 'am' ? 'ምርቱን ላክና የሽያጭ ትዕዛዝ ፍጠር' : 'Dispatch Product & Create Order' ?>
        </button>
    </form>
</div>

<!-- Active Dispatches & Payment Tracking Table -->
<div class="card" style="margin-bottom:24px">
    <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'የተላኩ ምርቶችና የክፍያ ሁኔታ' : 'Dispatches & Payment Status' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'መለያና ቀን' : 'ID & Date' ?></th>
                    <th><?= current_locale() === 'am' ? 'መዳረሻ ሱቅ / ደንበኛ' : 'Destination Shop / Buyer' ?></th>
                    <th><?= current_locale() === 'am' ? 'ምርትና መጠን' : 'Product & Qty' ?></th>
                    <th><?= current_locale() === 'am' ? 'ጠቅላላ ዋጋ' : 'Total Price' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተከፈለ' : 'Paid' ?></th>
                    <th><?= current_locale() === 'am' ? 'ቀሪ ያልተከፈለ' : 'Remaining' ?></th>
                    <th><?= current_locale() === 'am' ? 'የክፍያ ሁኔታ' : 'Status' ?></th>
                    <th><?= current_locale() === 'am' ? 'ክፍያ መመዝገቢያ' : 'Record Payment' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dispatches as $d): 
                    $totalVal = (float)$d['total_price'];
                    $paidVal  = (float)$d['amount_paid'];
                    $remVal   = max(0, $totalVal - $paidVal);
                    $pStatusBadge = match($d['payment_status']) {
                        'paid' => 'badge milking',
                        'partial' => 'badge dry',
                        default => 'badge sold'
                    };
                ?>
                    <tr>
                        <td style="font-size:12px;white-space:nowrap">
                            <code>#<?= (int)$d['id'] ?></code><br>
                            📅 <?= eth_date($d['dispatched_at']) ?><br>
                            <small class="text-muted">⏰ <?= eth_time($d['dispatched_at']) ?></small>
                        </td>
                        <td><strong><?= h($d['destination_pos_location']) ?></strong></td>
                        <td>
                            <strong><?= h($d['item_name']) ?></strong>
                            <br><span style="font-weight:700"><?= number_format($d['quantity_dispatched'], 1) ?> <?= h($d['unit']) ?></span>
                            <small class="text-muted">(@ <?= number_format($d['unit_price'], 2) ?> ETB)</small>
                        </td>
                        <td><strong style="font-size:14px"><?= number_format($totalVal, 2) ?> ETB</strong></td>
                        <td style="color:#2e7d32;font-weight:700"><?= number_format($paidVal, 2) ?> ETB</td>
                        <td style="color:<?= $remVal > 0 ? '#b3261e' : '#2e7d32' ?>;font-weight:700">
                            <?= number_format($remVal, 2) ?> ETB
                        </td>
                        <td><span class="<?= $pStatusBadge ?>"><?= h(ucfirst($d['payment_status'])) ?></span></td>
                        <td>
                            <?php if ($remVal > 0.01): ?>
                                <!-- Record Payment Inline Form -->
                                <form method="post" style="display:flex;gap:4px;flex-direction:column;background:var(--hover);padding:6px;border-radius:6px"><?= csrf_field() ?>
                                    <input type="hidden" name="record_payment" value="1">
                                    <input type="hidden" name="dispatch_id" value="<?= (int)$d['id'] ?>">
                                    
                                    <div style="display:flex;gap:4px">
                                        <input type="number" step="0.01" min="0.01" max="<?= $remVal ?>" name="payment_amount" value="<?= $remVal ?>" required style="width:90px;padding:3px 5px;font-size:12px" title="የሚከፈለው መጠን">
                                        <select name="payment_method" style="padding:2px 4px;font-size:11px;width:80px">
                                            <option value="cash">💵 Cash</option>
                                            <option value="telebirr">📱 Telebirr</option>
                                            <option value="bank">🏦 Bank</option>
                                        </select>
                                    </div>
                                    <button class="btn" type="submit" style="padding:3px 8px;font-size:11px;background:#1e5631;border-color:#1e5631;margin-top:2px">✓ ክፍያ መዝግብ</button>
                                </form>
                            <?php else: ?>
                                <span class="badge milking" style="padding:5px 8px">✓ ሙሉ በሙሉ ተከፍሏል</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($dispatches)): ?>
                    <tr><td colspan="8" class="text-muted" style="text-align:center;padding:20px"><?= current_locale() === 'am' ? 'እስካሁን የተመዘገበ የስርጭትና ሽያጭ መረጃ የለም።' : 'No dispatches recorded yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Payments History Log Table with Printable Receipt -->
<div class="card">
    <h3 style="margin-top:0">💰 <?= current_locale() === 'am' ? 'የተሰበሰቡ የሽያጭ ክፍያዎች ማህደር እና ይፋዊ ደረሰኝ' : 'Sales Payments Received & Receipts' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'የክፍያ ቀንና ሰዓት' : 'Payment Date' ?></th>
                    <th><?= current_locale() === 'am' ? 'ስርጭት መለያ' : 'Dispatch #' ?></th>
                    <th><?= current_locale() === 'am' ? 'መዳረሻ ሱቅ / ደንበኛ' : 'Destination' ?></th>
                    <th><?= current_locale() === 'am' ? 'ምርት' : 'Item' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተከፈለው መጠን' : 'Amount Paid' ?></th>
                    <th><?= current_locale() === 'am' ? 'የክፍያ ዘዴ' : 'Method' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተረከበው ሠራተኛ' : 'Receiver' ?></th>
                    <th style="text-align:center"><?= current_locale() === 'am' ? 'ይፋዊ ደረሰኝ' : 'Receipt' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td style="font-size:12px;white-space:nowrap">
                            📅 <?= eth_date($p['created_at']) ?><br>
                            <small class="text-muted">⏰ <?= eth_time($p['created_at']) ?></small>
                        </td>
                        <td><code>#<?= (int)$p['dispatch_id'] ?></code></td>
                        <td><?= h($p['destination_pos_location']) ?></td>
                        <td><?= h($p['item_name']) ?></td>
                        <td><strong style="color:#2e7d32;font-size:15px">+<?= number_format($p['amount'], 2) ?> ETB</strong></td>
                        <td><span class="badge active"><?= h(ucfirst($p['payment_method'])) ?></span></td>
                        <td><strong><?= h($p['receiver_name']) ?></strong></td>
                        <td style="text-align:center">
                            <a href="<?= BASE_URL ?>/modules/distribution/receipt_print.php?payment_id=<?= (int)$p['id'] ?>" target="_blank" class="btn" style="padding:4px 10px;font-size:11.5px;background:#1e5631;border-color:#1e5631;text-decoration:none">
                                🖨️ <?= current_locale() === 'am' ? 'ደረሰኝ አትም' : 'Print Receipt' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="8" class="text-muted" style="text-align:center;padding:20px"><?= current_locale() === 'am' ? 'እስካሁን የተመዘገበ ክፍያ የለም።' : 'No payments recorded yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateDispStockHelper() {
    const sel = document.getElementById('dispStockItem');
    const opt = sel.options[sel.selectedIndex];
    const banner = document.getElementById('dispStockBanner');
    const valText = document.getElementById('dispStockValue');
    const alertBox = document.getElementById('dispOutOfStock');
    const qtyInput = document.getElementById('dispQty');
    const submitBtn = document.getElementById('submitDispBtn');

    if (opt && opt.value !== '') {
        const stock = parseFloat(opt.getAttribute('data-stock') || 0);
        const unit = opt.getAttribute('data-unit') || '';
        
        if (stock <= 0) {
            banner.style.display = 'none';
            alertBox.style.display = 'block';
            submitBtn.disabled = true;
            qtyInput.value = '';
            qtyInput.removeAttribute('max');
        } else {
            alertBox.style.display = 'none';
            banner.style.display = 'block';
            valText.innerText = stock.toFixed(2) + ' ' + unit;
            qtyInput.max = stock;
            submitBtn.disabled = false;
        }
    } else {
        banner.style.display = 'none';
        alertBox.style.display = 'none';
        submitBtn.disabled = false;
        qtyInput.removeAttribute('max');
    }
    calculateTotalValue();
}

function calculateTotalValue() {
    const qty = parseFloat(document.getElementById('dispQty').value || 0);
    const unitPrice = parseFloat(document.getElementById('dispUnitPrice').value || 0);
    const total = qty * unitPrice;
    document.getElementById('dispTotalValDisplay').innerText = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
}

function toggleDeliveryType(val) {
    const shopPanel = document.getElementById('shopPanel');
    const buyerPanel = document.getElementById('buyerPanel');
    const giftPanel = document.getElementById('giftPanel');
    const unitPrice = document.getElementById('dispUnitPrice');

    if (val === 'shop') {
        shopPanel.style.display = 'block';
        buyerPanel.style.display = 'none';
        giftPanel.style.display = 'none';
        unitPrice.readOnly = false;
        unitPrice.min = '0.01';
    } else if (val === 'gift_protocol') {
        shopPanel.style.display = 'none';
        buyerPanel.style.display = 'none';
        giftPanel.style.display = 'block';
        unitPrice.value = '0.00';
        unitPrice.readOnly = true;
        unitPrice.min = '0.00';
        calculateTotalValue();
    } else {
        shopPanel.style.display = 'none';
        buyerPanel.style.display = 'block';
        giftPanel.style.display = 'none';
        unitPrice.readOnly = false;
        unitPrice.min = '0.01';
    }
}

function checkCustomShop(val) {
    const customFields = document.getElementById('customShopFields');
    if (val === '0') {
        customFields.style.display = 'flex';
    } else {
        customFields.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
