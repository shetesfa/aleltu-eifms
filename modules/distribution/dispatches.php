<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper', 'finance_officer', 'distribution_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['create_dispatch'])) {
            $vehicleId = (int) $_POST['vehicle_id'];
            $stockItemId = (int) $_POST['stock_item_id'];
            $quantity = (float) $_POST['quantity'];
            $unitPrice = (float) ($_POST['unit_price'] ?? 0);

            $deliveryType = $_POST['delivery_type'] ?? 'shop';
            if ($deliveryType === 'gift_protocol') {
                $recipient = trim($_POST['gift_recipient'] ?? '');
                if (empty($recipient)) {
                    throw new InvalidArgumentException('እባክዎ የስጦታውን ወይም የአስተዳደር ተቀባይ ስም ያስገቡ (Recipient name is required).');
                }
                $destination = "🎁 ስጦታ/አስተዳደር/ምግብ፡ {$recipient}";
                $unitPrice = 0.00;
            } elseif ($deliveryType === 'shop') {
                if ($unitPrice <= 0) {
                    throw new InvalidArgumentException('የአንዱ መሸጫ ዋጋ (Unit Price) ከ 0 በላይ መሆን አለበት (Unit selling price is mandatory).');
                }
                $shopId = (int)($_POST['shop_id'] ?? 0);
                if ($shopId > 0) {
                    $shop = db()->query("SELECT * FROM dist_shops WHERE id = {$shopId}")->fetch();
                    $shopName = $shop['shop_name'] ?? 'የድርጅቱ ሱቅ';
                    $managerName = $shop['manager_name'] ?? 'ተረካቢ ኃላፊ';
                } else {
                    $shopName = trim($_POST['custom_shop_name'] ?? 'የድርጅቱ ሱቅ');
                    $managerName = trim($_POST['custom_manager_name'] ?? 'ተረካቢ ኃላፊ');
                    if (!empty($shopName)) {
                        db()->prepare("INSERT INTO dist_shops (shop_name, manager_name) VALUES (?, ?)")->execute([$shopName, $managerName]);
                    }
                }
                $destination = "🏢 ሱቅ፡ {$shopName} (ተረካቢ ኃላፊ፡ {$managerName})";
            } else {
                if ($unitPrice <= 0) {
                    throw new InvalidArgumentException('የአንዱ መሸጫ ዋጋ (Unit Price) ከ 0 በላይ መሆን አለበት (Unit selling price is mandatory).');
                }
                $buyerName = trim($_POST['buyer_name'] ?? '');
                if (empty($buyerName)) {
                    throw new InvalidArgumentException('እባክዎ የገዢውን ስም ያስገቡ (Buyer name is required).');
                }
                $destination = "👤 ቀጥታ ገዢ፡ {$buyerName}";
            }

            // Find driver name from vehicle
            $veh = db()->query("SELECT driver_name FROM dist_vehicles WHERE id = {$vehicleId}")->fetch();
            $driverName = $veh['driver_name'] ?? null;

            $warehouseId = 1; // 1 Main Central Warehouse
            $avail = stock_current($warehouseId, $stockItemId);
            if ($quantity > $avail) {
                throw new RuntimeException("ያለዎት ክምችት {$avail} ብቻ ስለሆነ {$quantity} መላክ አይቻልም (Insufficient stock in warehouse).");
            }

            dispatch_create($vehicleId, $driverName, $destination, $warehouseId, $stockItemId, $quantity, $unitPrice,
                $deliveryType === 'gift_protocol' ? 'internal_use' : ($deliveryType === 'shop' ? 'shop_transfer' : 'direct_sale'));
            flash('✓ የምርት ስርጭቱና የሽያጭ ትዕዛዙ ተመዝግቧል (Dispatch and sales order created).');
        } elseif (isset($_POST['record_payment'])) {
            $dispatchId = (int) $_POST['dispatch_id'];
            $amount = (float) $_POST['payment_amount'];
            $method = $_POST['payment_method'] ?? 'cash';
            $refNo = trim($_POST['reference_no'] ?? '');
            $notes = trim($_POST['payment_notes'] ?? '');

            dispatch_record_payment($dispatchId, $amount, $method, $refNo, $notes, current_user()['id']);
            flash('✓ ክፍያው በተሳካ ሁኔታ ተመዝግቧል (Payment recorded successfully).');
        } elseif (isset($_POST['confirm_delivery'])) {
            $dispatchId = (int) $_POST['dispatch_id'];
            $deliveredQty = (float) $_POST['delivered_quantity'];
            dispatch_confirm_delivery($dispatchId, $deliveredQty);
            flash('✓ ርክክቡ ተረጋግጧል (Delivery confirmed).');
        }
        header('Location: /aleltu-eifms/modules/distribution/dispatches.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$vehicles = db()->query("SELECT * FROM dist_vehicles WHERE status != 'maintenance' ORDER BY plate_number")->fetchAll();
$shops = db()->query("SELECT * FROM dist_shops ORDER BY shop_name")->fetchAll();

$items = db()->query(
    "SELECT i.id, i.name, i.sku, i.unit, COALESCE(SUM(b.quantity_on_hand), 0) AS current_stock
     FROM wh_stock_items i
     LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.warehouse_id = 1 AND b.quantity_on_hand > 0
     GROUP BY i.id ORDER BY i.name"
)->fetchAll();

// Dispatches list
$dispatches = db()->query(
    "SELECT d.*, v.plate_number, v.vehicle_type, i.name AS item_name, i.unit, u.name AS creator_name
     FROM dist_dispatches d
     JOIN dist_vehicles v ON v.id = d.vehicle_id
     JOIN wh_stock_items i ON i.id = d.stock_item_id
     JOIN users u ON u.id = d.created_by
     ORDER BY d.created_at DESC LIMIT 50"
)->fetchAll();

// Payment history list
$payments = db()->query(
    "SELECT p.*, d.destination_pos_location, d.quantity_dispatched, d.unit_price, i.name AS item_name, i.unit, v.plate_number, u.name AS receiver_name
     FROM dist_dispatch_payments p
     JOIN dist_dispatches d ON d.id = p.dispatch_id
     JOIN wh_stock_items i ON i.id = d.stock_item_id
     JOIN dist_vehicles v ON v.id = d.vehicle_id
     JOIN users u ON u.id = p.received_by
     ORDER BY p.created_at DESC LIMIT 30"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🚚 የምርት ስርጭት እና የሽያጭ ክፍያ መከታተያ (Dispatches &amp; Sales Payment Flow)</h2>
        <p class="text-muted" style="margin:0">በመኪና የሚላኩ ምርቶች፣ የተሸጡበት ዋጋ እና የገንዘብ ክፍያ (ሙሉ / ከፊል ክፍያ) መከታተያ።</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="/aleltu-eifms/modules/distribution/vehicles.php" class="btn secondary">🚚 ተሽከርካሪዎች (Vehicles)</a>
        <a href="/aleltu-eifms/modules/warehouse/stock.php" class="btn secondary">📊 ክምችት (Stock)</a>
    </div>
</div>

<!-- Dispatch & Sales Registration Form -->
<div class="card" style="max-width:680px;margin-bottom:24px">
    <h3>➕ አዲስ የምርት ስርጭትና ሽያጭ መዝግብ (New Product Dispatch &amp; Sale)</h3>
    <form method="post" id="dispatchForm"><?= csrf_field() ?>
        <input type="hidden" name="create_dispatch" value="1">
        
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label>የሚጭነው ተሽከርካሪ (Vehicle &amp; Driver) *</label>
                <select name="vehicle_id" required>
                    <option value="">— ተሽከርካሪ ይምረጡ —</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= (int)$v['id'] ?>">
                            <?= h($v['plate_number']) ?> (<?= h($v['vehicle_type']) ?> — አሽከርካሪ: <?= h($v['driver_name'] ?: 'ያልተመደበ') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="background:#f8fafc;padding:12px;border-radius:6px;margin-bottom:14px;border:1px solid #cbd5e1">
            <label style="font-weight:bold;display:block;margin-bottom:6px">🎯 የመዳረሻ ዓይነት (Delivery Destination) *</label>
            <div style="display:flex;gap:18px;margin-bottom:10px;flex-wrap:wrap">
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                    <input type="radio" name="delivery_type" value="shop" checked onchange="toggleDeliveryType(this.value)">
                    <strong>🏢 ለድርጅቱ ሱቅ አስረክብ (Deliver to Company Shop)</strong>
                </label>
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                    <input type="radio" name="delivery_type" value="buyer" onchange="toggleDeliveryType(this.value)">
                    <strong>👤 ለቀጥታ ደንበኛ/ገዢ ሽያጭ (Direct Buyer)</strong>
                </label>
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                    <input type="radio" name="delivery_type" value="gift_protocol" onchange="toggleDeliveryType(this.value)">
                    <strong>🎁 የበዓል ስጦታ / ለአስተዳደር ፕሮቶኮል / ምግብ (Gift &amp; Protocol)</strong>
                </label>
            </div>

            <!-- Shop Selection Panel -->
            <div id="shopPanel">
                <div class="form-group" style="margin-bottom:6px">
                    <label style="font-size:12px">የድርጅቱ ሱቅ ምረጥ (Select Shop) *</label>
                    <select name="shop_id" id="shopSelect" onchange="checkCustomShop(this.value)" style="width:100%">
                        <?php foreach ($shops as $s): ?>
                            <option value="<?= (int)$s['id'] ?>">
                                <?= h($s['shop_name']) ?> — ተረካቢ ኃላፊ: <?= h($s['manager_name']) ?> (<?= h($s['phone'] ?: 'ስልክ የለም') ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="0">➕ አዲስ ሱቅ መዝግብ (Register New Shop)...</option>
                    </select>
                </div>
                <div id="customShopFields" style="display:none;gap:10px;margin-top:8px">
                    <div style="flex:1">
                        <label style="font-size:11px">አዲስ የሱቅ ስም *</label>
                        <input type="text" name="custom_shop_name" placeholder="ለምሳሌ፡ ካዛንቺስ ሱቅ">
                    </div>
                    <div style="flex:1">
                        <label style="font-size:11px">የሱቁ ተረካቢ ኃላፊ ስም *</label>
                        <input type="text" name="custom_manager_name" placeholder="ለምሳሌ፡ አቶ ዳዊት ከበደ">
                    </div>
                </div>
            </div>

            <!-- Direct Buyer Panel -->
            <div id="buyerPanel" style="display:none;margin-top:6px">
                <label style="font-size:12px">የገዢው / የደንበኛው ስም (Buyer Name) *</label>
                <input type="text" name="buyer_name" placeholder="ለምሳሌ፡ አቶ ታደሰ ግርማ / ደንበኛ ሆቴል" style="width:100%">
            </div>

            <!-- Gift & Protocol Panel -->
            <div id="giftPanel" style="display:none;margin-top:6px;background:#fdf2f8;padding:10px;border-radius:6px;border:1px solid #f472b6">
                <label style="font-size:12px;font-weight:700;color:#9d174d">🎁 የስጦታ ተቀባይ / የአስተዳደር ስም / አላማ (Recipient / Protocol Purpose) *</label>
                <input type="text" name="gift_recipient" placeholder="ለምሳሌ፡ ለአዲስ ዓመት በዓል ለቀበሌ አስተዳደር፣ ለእርሻ እንግዶች ድግስ" style="width:100%;margin-top:4px">
                <small style="display:block;margin-top:4px;color:#be185d">💡 ማስታወሻ፡ ይህ ሲመረጥ መሸጫ ዋጋው 0.00 ሆኖ ምርቱ እንደ ስጦታ ይመዘገባል፤ የመጋዘን ክምችቱም በትክክል ይቀነሳል።</small>
            </div>
        </div>

        <div class="form-group">
            <label>የሚላከው ምርት (Product to Dispatch) *</label>
            <select name="stock_item_id" id="dispStockItem" required onchange="updateDispStockHelper()" style="width:100%">
                <option value="">— ምርት ይምረጡ (Select Product) —</option>
                <?php foreach ($items as $i): ?>
                    <option value="<?= (int)$i['id'] ?>" data-stock="<?= (float)$i['current_stock'] ?>" data-unit="<?= h($i['unit']) ?>">
                        <?= h($i['name']) ?> (በመጋዘን ያለው ክምችት: <?= number_format((float)$i['current_stock'], 2) ?> <?= h($i['unit']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Live Stock Helper Banner -->
        <div id="dispStockBanner" style="display:none;background:#e8f5e9;border-left:4px solid #2e7d32;border-radius:6px;padding:10px 14px;margin-bottom:16px">
            <div style="font-size:12px;color:#2e7d32;font-weight:600">በመጋዘን ውስጥ ያለው የቀረ ክምችት (Available in Main Warehouse):</div>
            <div style="font-size:18px;font-weight:800;color:#1b5e20" id="dispStockValue">0</div>
        </div>

        <div id="dispOutOfStock" style="display:none;background:#ffebee;border-left:4px solid #b3261e;color:#b3261e;border-radius:6px;padding:10px 14px;margin-bottom:16px;font-weight:600">
            ⚠️ ይህ ምርት በመጋዘን ውስጥ አልቋል (Out of Stock)! መላክ አይቻልም።
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>የሚላከው መጠን (Quantity) *</label>
                <input type="number" step="0.01" min="0.01" name="quantity" id="dispQty" required placeholder="ለምሳሌ፡ 100" oninput="calculateTotalValue()">
            </div>
            <div class="form-group">
                <label>የአንዱ መሸጫ ዋጋ በብር (Unit Selling Price ETB) *</label>
                <input type="number" step="0.01" min="0.01" name="unit_price" id="dispUnitPrice" required placeholder="ለምሳሌ፡ 70.00" oninput="calculateTotalValue()">
            </div>
        </div>

        <!-- Live Total Sales Value Calculation -->
        <div style="background:var(--bg);border-radius:6px;padding:12px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center">
            <span style="font-weight:600;color:var(--text)">ጠቅላላ የሚጠበቅ የሽያጭ ዋጋ (Total Sales Value):</span>
            <span style="font-size:19px;font-weight:800;color:var(--primary)" id="dispTotalValDisplay">0.00 ETB</span>
        </div>

        <button class="btn" type="submit" id="submitDispBtn" style="background:#2e7d32;border-color:#2e7d32">✓ ለሱቅ አስረክብ / ምርቱን ጫንና ላክ (Deliver to Shop &amp; Create Sales Order)</button>
    </form>
</div>

<!-- Active Dispatches & Payment Tracking Table -->
<div class="card" style="margin-bottom:24px">
    <h3 style="margin-top:0">📋 የተላኩ ምርቶችና የክፍያ ሁኔታ (Dispatches &amp; Payment Status)</h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>መለያና ቀን</th>
                    <th>ተሽከርካሪና አሽከርካሪ</th>
                    <th>ደንበኛ / መዳረሻ</th>
                    <th>ምርትና መጠን</th>
                    <th>ጠቅላላ ዋጋ</th>
                    <th>የተከፈለ</th>
                    <th>ቀሪ ያልተከፈለ</th>
                    <th>የክፍያ ሁኔታ</th>
                    <th>ክፍያ መመዝገቢያ እርምጃ</th>
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
                        <td>
                            <strong><?= h($d['plate_number']) ?></strong>
                            <br><small class="text-muted">👤 <?= h($d['driver_name'] ?: 'ያልተመደበ') ?></small>
                        </td>
                        <td><strong><?= h($d['destination_pos_location']) ?></strong></td>
                        <td>
                            <strong><?= h($d['item_name']) ?></strong>
                            <br><span style="font-weight:700"><?= number_format($d['quantity_dispatched'], 1) ?> <?= h($d['unit']) ?></span>
                            <small class="text-muted">(@ <?= number_format($d['unit_price'], 2) ?> ETB)</small>
                        </td>
                        <td><strong style="font-size:15px"><?= number_format($totalVal, 2) ?> ETB</strong></td>
                        <td style="color:#2e7d32;font-weight:700"><?= number_format($paidVal, 2) ?> ETB</td>
                        <td style="color:<?= $remVal > 0 ? '#b3261e' : '#2e7d32' ?>;font-weight:700">
                            <?= number_format($remVal, 2) ?> ETB
                        </td>
                        <td><span class="<?= $pStatusBadge ?>"><?= h(ucfirst($d['payment_status'])) ?></span></td>
                        <td>
                            <?php if ($remVal > 0.01): ?>
                                <!-- Record Payment Inline Form -->
                                <form method="post" style="display:flex;gap:4px;flex-direction:column;background:var(--bg);padding:6px;border-radius:6px"><?= csrf_field() ?>
                                    <input type="hidden" name="record_payment" value="1">
                                    <input type="hidden" name="dispatch_id" value="<?= (int)$d['id'] ?>">
                                    
                                    <div style="display:flex;gap:4px">
                                        <input type="number" step="0.01" min="0.01" max="<?= $remVal ?>" name="payment_amount" value="<?= $remVal ?>" required style="width:85px;padding:3px 5px;font-size:12px" title="የሚከፈለው መጠን">
                                        <select name="payment_method" style="padding:2px 4px;font-size:11px;width:75px">
                                            <option value="cash">💵 Cash</option>
                                            <option value="telebirr">📱 Telebirr</option>
                                            <option value="bank">🏦 Bank</option>
                                        </select>
                                    </div>
                                    <div style="display:flex;gap:4px">
                                        <input type="text" name="reference_no" placeholder="ደረሰኝ / Ref #" style="padding:2px 5px;font-size:11px;flex:1">
                                        <button class="btn" type="submit" style="padding:3px 8px;font-size:11px;background:#2e7d32;border-color:#2e7d32">✓ ክፈል</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <span class="badge milking" style="padding:5px 8px">✓ ሙሉ በሙሉ ተከፍሏል (Fully Paid)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($dispatches)): ?>
                    <tr><td colspan="9" class="text-muted" style="text-align:center;padding:20px">እስካሁን የተመዘገበ የስርጭትና ሽያጭ መረጃ የለም።</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Payments History Log Table -->
<div class="card">
    <h3 style="margin-top:0">💰 የተሰበሰቡ የሽያጭ ክፍያዎች ማህደር (Sales Payments Received History by Date)</h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>የክፍያ ቀንና ሰዓት (ኢትዮጵያ)</th>
                    <th>ስርጭት መለያ</th>
                    <th>ተሽከርካሪ</th>
                    <th>ደንበኛ</th>
                    <th>ምርት</th>
                    <th>የተከፈለው መጠን</th>
                    <th>የክፍያ ዘዴ</th>
                    <th>ደረሰኝ / Ref #</th>
                    <th>የተረከበው ሠራተኛ</th>
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
                        <td><strong><?= h($p['plate_number']) ?></strong></td>
                        <td><?= h($p['destination_pos_location']) ?></td>
                        <td><?= h($p['item_name']) ?></td>
                        <td><strong style="color:#2e7d32;font-size:15px">+<?= number_format($p['amount'], 2) ?> ETB</strong></td>
                        <td><span class="badge active"><?= h(ucfirst($p['payment_method'])) ?></span></td>
                        <td><?= h($p['reference_no'] ?: '—') ?></td>
                        <td><strong><?= h($p['receiver_name']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="9" class="text-muted">እስካሁን የተመዘገበ ክፍያ የለም።</td></tr>
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
