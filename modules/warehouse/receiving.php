<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['confirm_receipt'])) {
            $receivingId = (int) $_POST['receiving_id'];
            $receivedQty = (float) $_POST['received_quantity'];
            $reason = trim($_POST['variance_reason'] ?? '');
            $postAction = $_POST['post_action'] ?? 'store'; // 'store' or 'send_to_dept'
            
            store_confirm_receipt($receivingId, $receivedQty, $reason, current_user()['id'], $postAction);
            flash(current_locale() === 'am' ? '✓ ዕቃው ተረጋግጦ ወደ ዋናው መጋዘን ክምችት ገብቷል!' : 'Delivery verified and added to stock.');
        } elseif (isset($_POST['manual_owner_receive'])) {
            $warehouseId = 1; // 1 Unified Main Central Warehouse
            $itemSelectionType = $_POST['item_selection_type'] ?? 'catalog';
            $itemId = ($itemSelectionType === 'catalog') ? (int) ($_POST['stock_item_id'] ?? 0) : 0;
            $customItemName = trim($_POST['custom_item_name'] ?? '');
            $qty = (float) $_POST['quantity'];
            $sourceType = trim($_POST['source_type'] ?? '');
            $refNote = trim($_POST['reference_note'] ?? '');

            if ($qty <= 0) {
                throw new InvalidArgumentException('የዕቃው መጠን ከ 0 በላይ መሆን አለበት (Quantity must be greater than zero).');
            }
            if (empty($sourceType)) {
                throw new InvalidArgumentException('የዕቃው ምንጭ መገለጽ አለበት (Delivery source is required).');
            }
            if (empty($refNote)) {
                throw new InvalidArgumentException('ምክንያት ወይም ማብራሪያ ማስገባት ግዴታ ነው (Reason / note is required).');
            }

            // Handle custom entered item name
            if ($itemSelectionType === 'custom' || $itemId <= 0) {
                if ($customItemName === '') {
                    throw new InvalidArgumentException('እባክዎ የዕቃውን ስም ያስገቡ (Please enter item name).');
                }
                // Check if already exists in wh_stock_items
                $chk = db()->prepare("SELECT id FROM wh_stock_items WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $chk->execute([$customItemName]);
                $existingId = $chk->fetchColumn();
                if ($existingId) {
                    $itemId = (int) $existingId;
                } else {
                    $cleanSku = 'ITM-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $customItemName), 0, 5)) . '-' . rand(100, 999);
                    $ins = db()->prepare("INSERT INTO wh_stock_items (sku, name, product_class, unit, is_perishable) VALUES (?, ?, 'general', 'KG', 0)");
                    $ins->execute([$cleanSku, $customItemName]);
                    $itemId = (int) db()->lastInsertId();
                }
            }

            db()->beginTransaction();
            stock_receive($warehouseId, $itemId, $qty, $sourceType, null, 'manual_owner');
            $code = 'RCV-MAN-' . date('ymd') . '-' . rand(100, 999);
            $stmt = db()->prepare(
                "INSERT INTO wh_store_receiving (dispatch_code, source_type, source_reference, warehouse_id, stock_item_id, sent_quantity, received_quantity, status, sent_by, received_by, received_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'stored', ?, ?, NOW())"
            );
            $stmt->execute([$code, $sourceType, $refNote, $warehouseId, $itemId, $qty, $qty, current_user()['id'], current_user()['id']]);
            $rcvId = (int) db()->lastInsertId();
            db()->commit();

            log_activity('warehouse', 'create', 'wh_store_receiving', $rcvId, "Direct intake: {$qty} units from {$sourceType}. Ref: {$refNote}");
            flash('✓ ዕቃው ተረጋግጦ ወደ ዋና መጋዘን ክምችት ገብቷል (Delivery verified and added to stock).');
        }
        header('Location: ' . BASE_URL . '/modules/warehouse/receiving.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$tab = $_GET['tab'] ?? 'all';

// Pending shipments query with tab filtering
$whereClause = "r.status = 'pending_receipt'";
if ($tab === 'purchase') {
    $whereClause .= " AND r.source_type IN ('purchase', 'purchase_order')";
} elseif ($tab === 'production') {
    $whereClause .= " AND r.source_type IN ('dairy', 'poultry', 'fish', 'feed_production')";
} elseif ($tab === 'manual') {
    $whereClause .= " AND r.source_type NOT IN ('purchase', 'purchase_order', 'dairy', 'poultry', 'fish', 'feed_production')";
}

$pendingReceipts = db()->query(
    "SELECT r.*, i.name AS item_name, i.unit, i.partition_code, u.name AS sender_name,
            d.name AS requesting_dept_name
     FROM wh_store_receiving r
     JOIN wh_stock_items i ON i.id = r.stock_item_id
     JOIN users u ON u.id = r.sent_by
     LEFT JOIN dept_material_requests mr ON mr.id = r.material_request_id
     LEFT JOIN departments d ON d.id = mr.department_id
     WHERE {$whereClause}
     ORDER BY r.sent_at ASC"
)->fetchAll();

// Completed history query
$completedReceipts = db()->query(
    "SELECT r.*, i.name AS item_name, i.unit, i.partition_code, u.name AS receiver_name
     FROM wh_store_receiving r
     JOIN wh_stock_items i ON i.id = r.stock_item_id
     LEFT JOIN users u ON u.id = r.received_by
     WHERE r.status != 'pending_receipt'
     ORDER BY r.received_at DESC LIMIT 25"
)->fetchAll();

$items = db()->query("SELECT id, name, sku, unit, partition_code FROM wh_stock_items ORDER BY name")->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">📥 <?= current_locale() === 'am' ? 'የመጋዘን ዕቃ ርክክብ መቆጣጠሪያ' : 'Warehouse Receiving Control' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'ዋና ማዕከላዊ መጋዘን — ከእርሻ ዕለታዊ ምርት (ወተት፣ ዕንቁላል)፣ ከግዥ ወይም ከባለቤት የሚመጡ ዕቃዎችን በ3ቱ ክፍሎች መረከቢያ' : 'Main Central Warehouse — Receive daily farm harvest, purchases, and supplies into 3 partitions' ?></p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary">📊 <?= current_locale() === 'am' ? 'የክምችት ዝርዝር' : 'Stock' ?></a>
        <a href="<?= BASE_URL ?>/modules/warehouse/requisitions.php" class="btn secondary">📦 <?= current_locale() === 'am' ? 'የክፍሎች ጥያቄ' : 'Requisitions' ?></a>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap">
    <a href="?tab=all" class="btn <?= $tab === 'all' ? '' : 'secondary' ?>" style="padding:6px 14px">
        <?= current_locale() === 'am' ? 'ሁሉም የሚጠበቁ ዕቃዎች' : 'All Pending' ?> (<?= (int)db()->query("SELECT COUNT(*) FROM wh_store_receiving WHERE status='pending_receipt'")->fetchColumn() ?>)
    </a>
    <a href="?tab=production" class="btn <?= $tab === 'production' ? '' : 'secondary' ?>" style="padding:6px 14px">
        🥛🥚 <?= current_locale() === 'am' ? 'ዕለታዊ የእርሻ ምርት (ወተትና ዕንቁላል)' : 'Daily Farm Production' ?>
    </a>
    <a href="?tab=purchase" class="btn <?= $tab === 'purchase' ? '' : 'secondary' ?>" style="padding:6px 14px">
        🛍️ <?= current_locale() === 'am' ? 'ከፋይናንስ ግዥ (PO)' : 'Purchase Orders' ?>
    </a>
    <a href="?tab=manual" class="btn <?= $tab === 'manual' ? '' : 'secondary' ?>" style="padding:6px 14px">
        📦 <?= current_locale() === 'am' ? 'ቀጥታ / ከባለቤት' : 'Manual / Owner' ?>
    </a>
</div>

<!-- Pending Incoming Shipments Table -->
<div class="card" style="margin-bottom:24px">
    <h3 style="margin-top:0">🚚 <?= current_locale() === 'am' ? 'ወደ መጋዘን የደረሱና ማረጋገጫ የሚጠብቁ ዕቃዎች' : 'Pending Incoming Shipments' ?></h3>
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'የመጋዘን ክፍል' : 'Partition' ?></th>
                <th><?= current_locale() === 'am' ? 'መለያ ኮድ' : 'Code' ?></th>
                <th><?= current_locale() === 'am' ? 'የመጣበት ምንጭ' : 'Source' ?></th>
                <th><?= current_locale() === 'am' ? 'የዕቃው ስም' : 'Item Name' ?></th>
                <th><?= current_locale() === 'am' ? 'የተላከው መጠን' : 'Sent Qty' ?></th>
                <th><?= current_locale() === 'am' ? 'የላከው / ቀን' : 'Sent By' ?></th>
                <th><?= current_locale() === 'am' ? 'ትክክለኛ ርክክብና እርምጃ' : 'Confirmation' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingReceipts as $p): 
                $pBadge = match($p['partition_code'] ?? 'finished_products') {
                    'finished_products'  => '<span class="badge milking" style="font-size:11px">' . (current_locale() === 'am' ? 'ክፍል 1፡ ምርት' : 'Part 1: Product') . '</span>',
                    'feed_nutrition'     => '<span class="badge" style="background:#fef3c7;color:#92400e;font-size:11px">' . (current_locale() === 'am' ? 'ክፍል 2፡ መኖ' : 'Part 2: Feed') . '</span>',
                    'materials_supplies' => '<span class="badge dry" style="font-size:11px">' . (current_locale() === 'am' ? 'ክፍል 3፡ ዕቃ/መድኃኒት' : 'Part 3: Material') . '</span>',
                    default              => '<span class="badge">' . h($p['partition_code']) . '</span>'
                };
            ?>
                <tr id="rcv-<?= (int)$p['id'] ?>">
                    <td><?= $pBadge ?></td>
                    <td><code><?= h($p['dispatch_code']) ?></code></td>
                    <td>
                        <span class="badge active"><?= h(ucfirst(str_replace('_', ' ', $p['source_type']))) ?></span>
                        <?php if ($p['source_reference']): ?>
                            <br><small class="text-muted"><?= h($p['source_reference']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= h($p['item_name']) ?></strong>
                        <?php if ($p['requesting_dept_name']): ?>
                            <br><span class="badge dry" style="font-size:10px">ለ <?= h($p['requesting_dept_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><strong style="font-size:15px"><?= number_format($p['sent_quantity'], 2) ?> <?= h($p['unit']) ?></strong></td>
                    <td style="font-size:12px">
                        <?= h($p['sender_name']) ?><br>
                        <span class="text-muted"><?= h(eth_date(substr($p['sent_at'], 0, 10))) ?> <?= h(eth_time($p['sent_at'])) ?></span>
                    </td>
                    <td>
                        <form method="post" style="display:flex;flex-direction:column;gap:6px;background:var(--bg);padding:8px;border-radius:6px"><?= csrf_field() ?>
                            <input type="hidden" name="receiving_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="confirm_receipt" value="1">
                            
                            <div style="display:flex;gap:6px;align-items:center">
                                <label style="font-size:12px;font-weight:600"><?= current_locale() === 'am' ? 'የተረከቡት:' : 'Received:' ?></label>
                                <input type="number" step="0.01" min="0.01" name="received_quantity" value="<?= $p['sent_quantity'] ?>" required style="width:95px;padding:3px 6px;font-size:13px" title="የተረከቡት መጠን">
                                
                                <input type="text" name="variance_reason" placeholder="<?= current_locale() === 'am' ? 'ልዩነት ካለ ምክንያቱን ይጻፉ' : 'Variance reason if any' ?>" style="flex:1;padding:3px 6px;font-size:12px">
                            </div>

                            <div style="display:flex;gap:8px;align-items:center;margin-top:2px;flex-wrap:wrap">
                                <?php if ($p['material_request_id']): ?>
                                    <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer">
                                        <input type="radio" name="post_action" value="send_to_dept" checked>
                                        🚚 <?= current_locale() === 'am' ? 'ለጠየቀው ክፍል ይላክ' : 'Send to dept' ?>
                                    </label>
                                    <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer">
                                        <input type="radio" name="post_action" value="store">
                                        📦 <?= current_locale() === 'am' ? 'በመጋዘን ይቀመጥ' : 'Store in warehouse' ?>
                                    </label>
                                <?php else: ?>
                                    <input type="hidden" name="post_action" value="store">
                                    <span style="font-size:12px;color:var(--text-muted)">👉 <?= current_locale() === 'am' ? 'ወደ መጋዘን ክምችት ይገባል' : 'Destination: Main store inventory' ?></span>
                                <?php endif; ?>

                                <button class="btn" type="submit" style="padding:4px 12px;font-size:12px;margin-left:auto;background:#2e7d32;border-color:#2e7d32">
                                    ✓ <?= current_locale() === 'am' ? 'ተረክቤያለሁ አረጋግጥ' : 'Confirm Intake' ?>
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pendingReceipts)): ?>
                <tr>
                    <td colspan="7" class="text-muted" style="padding:20px;text-align:center">
                        📦 <?= current_locale() === 'am' ? 'በአሁኑ ሰዓት ማረጋገጫ የሚጠብቅ አዲስ የዕቃ ርክክብ የለም።' : 'No pending receipts waiting verification.' ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Manual / Direct Intake Form -->
<div class="card" style="max-width:680px;margin-bottom:24px">
    <h3>📦 ቀጥታ የዕቃ ርክክብ መመዝገቢያ (Direct / Manual Delivery Entry)</h3>
    <p class="text-muted" style="margin-top:-6px">ከባለቤት፣ ከስጦታ ወይም ከድንገተኛ ግዥ የመጡ ዕቃዎችን በቀጥታ ወደ ዋናው መጋዘን ማስገቢያ።</p>
    
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="manual_owner_receive" value="1">
        
        <!-- Selection or Write Item -->
        <div style="margin-bottom:12px;display:flex;gap:16px">
            <label style="font-weight:600;display:flex;align-items:center;gap:6px;cursor:pointer">
                <input type="radio" name="item_selection_type" value="catalog" checked onchange="toggleItemInput('catalog')">
                ከነባር ዕቃዎች ምረጥ (Select Catalog)
            </label>
            <label style="font-weight:600;display:flex;align-items:center;gap:6px;cursor:pointer">
                <input type="radio" name="item_selection_type" value="custom" onchange="toggleItemInput('custom')">
                የዕቃውን ስም በቀጥታ ጻፍ (Write Item Name)
            </label>
        </div>

        <div class="form-group" id="catalogItemGroup">
            <label>የዕቃው ስም (Catalog Item) *</label>
            <select name="stock_item_id" id="catalogItemSelect" style="width:100%">
                <?php foreach ($items as $i): ?>
                    <option value="<?= (int)$i['id'] ?>"><?= h($i['name']) ?> (<?= h($i['unit']) ?> - <?= h($i['sku']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" id="customItemGroup" style="display:none">
            <label>የዕቃው ስም በጽሑፍ (Custom Item Name) *</label>
            <input type="text" name="custom_item_name" id="customItemInput" placeholder="ለምሳሌ፡ የከብቶች መኖ፣ አዲስ ማዳበሪያ">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>የተረከቡት መጠን (Quantity) *</label>
                <input type="number" step="0.01" min="0.1" name="quantity" required placeholder="ለምሳሌ፡ 50">
            </div>
            <div class="form-group">
                <label>የዕቃው ምንጭ በጽሑፍ (Delivery Source) *</label>
                <input type="text" name="source_type" list="sourceSuggestions" required placeholder="ለምሳሌ፡ የባለቤት አቅርቦት፣ ድንገተኛ ዕርዳታ">
                <datalist id="sourceSuggestions">
                    <option value="የባለቤት ቀጥታ አቅርቦት (Owner Direct)">
                    <option value="የአቅራቢ ድጋፍ (Supplier Donation)">
                    <option value="ድንገተኛ ግዥ (Emergency Purchase)">
                    <option value="የእርሻ ዝውውር (Farm Internal Transfer)">
                </datalist>
            </div>
        </div>

        <div class="form-group">
            <label>ምክንያት / ዝርዝር ማብራሪያ (Reference / Reason) *</label>
            <input type="text" name="reference_note" required placeholder="ለምሳሌ፡ ከባለቤት የተላከ ወርሃዊ የላም መኖ ባች #105">
        </div>

        <button class="btn" type="submit">✓ መዝግበህ ወደ ዋና መጋዘን ጨምር (Add to Stock)</button>
    </form>
</div>

<!-- Completed Receiving History -->
<div class="card">
    <h3 style="margin-top:0">📜 የቅርብ ጊዜ የተረከቧቸው ዕቃዎች ታሪክ (Recent Receiving Log)</h3>
    <table>
        <thead>
            <tr>
                <th>መለያ ኮድ</th>
                <th>ምንጭ</th>
                <th>የዕቃ ስም</th>
                <th>የተላከ</th>
                <th>የተረከቡት</th>
                <th>ልዩነት</th>
                <th>መዳረሻ</th>
                <th>ተረካቢ ሠራተኛ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($completedReceipts as $c): ?>
                <tr>
                    <td><code><?= h($c['dispatch_code']) ?></code></td>
                    <td><span class="badge"><?= h(ucfirst(str_replace('_', ' ', $c['source_type']))) ?></span></td>
                    <td><strong><?= h($c['item_name']) ?></strong></td>
                    <td><?= number_format($c['sent_quantity'], 2) ?></td>
                    <td><strong><?= number_format($c['received_quantity'], 2) ?> <?= h($c['unit']) ?></strong></td>
                    <td>
                        <?php if ($c['sent_quantity'] != $c['received_quantity']): ?>
                            <span class="badge sold">-<?= number_format($c['sent_quantity'] - $c['received_quantity'], 2) ?></span>
                            <?php if ($c['variance_reason']): ?><br><small class="text-muted"><?= h($c['variance_reason']) ?></small><?php endif; ?>
                        <?php else: ?>
                            <span class="badge milking">✓ ሙሉ ርክክብ</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $c['status'] === 'sent_to_department' ? 'milking' : 'active' ?>">
                            <?= h($c['status'] === 'sent_to_department' ? '🚚 ለክፍል የተላከ' : '📦 በዋና መጋዘን') ?>
                        </span>
                    </td>
                    <td style="font-size:12px">
                        <?= h($c['receiver_name'] ?: 'የመጋዘን ኃላፊ') ?><br>
                        <span class="text-muted"><?= h(eth_date(substr($c['received_at'], 0, 10))) ?> <?= h(eth_time($c['received_at'])) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($completedReceipts)): ?>
                <tr><td colspan="8" class="text-muted">እስካሁን የተረከቡት ዕቃ የለም።</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function toggleItemInput(type) {
    const catalogGroup = document.getElementById('catalogItemGroup');
    const customGroup = document.getElementById('customItemGroup');
    const customInput = document.getElementById('customItemInput');
    const catalogSelect = document.getElementById('catalogItemSelect');

    if (type === 'custom') {
        catalogGroup.style.display = 'none';
        customGroup.style.display = 'block';
        customInput.required = true;
        catalogSelect.required = false;
    } else {
        catalogGroup.style.display = 'block';
        customGroup.style.display = 'none';
        customInput.required = false;
        catalogSelect.required = true;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
