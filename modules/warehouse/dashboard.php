<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'warehouse_keeper']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_feed_burn'])) {
    csrf_verify();
    $itemId = (int)$_POST['stock_item_id'];
    $burn = trim($_POST['daily_burn'] ?? '') !== '' ? max(0.1, (float)$_POST['daily_burn']) : null;
    
    $exists = db()->query("SELECT id FROM wh_minimum_stock_rules WHERE stock_item_id = {$itemId} AND warehouse_id = 1")->fetchColumn();
    if ($exists) {
        db()->prepare("UPDATE wh_minimum_stock_rules SET daily_burn_override = ? WHERE stock_item_id = ? AND warehouse_id = 1")->execute([$burn, $itemId]);
    } else {
        db()->prepare("INSERT INTO wh_minimum_stock_rules (warehouse_id, stock_item_id, minimum_threshold, daily_burn_override) VALUES (1, ?, 10.00, ?)")->execute([$itemId, $burn]);
    }
    flash(current_locale() === 'am' ? '✓ የመኖ ዕለታዊ ፍጆታ መጠን ተስተካክሏል።' : 'Daily feed burn rate updated successfully.');
    header('Location: ' . BASE_URL . '/modules/warehouse/dashboard.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

// Partition Summary Calculations
$part1Items = db()->query("SELECT COUNT(DISTINCT i.id) AS cnt, COALESCE(SUM(b.quantity_on_hand),0) AS total_qty FROM wh_stock_items i LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.quantity_on_hand > 0 WHERE i.partition_code = 'finished_products'")->fetch();
$part2Items = db()->query("SELECT COUNT(DISTINCT i.id) AS cnt, COALESCE(SUM(b.quantity_on_hand),0) AS total_qty FROM wh_stock_items i LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.quantity_on_hand > 0 WHERE i.partition_code = 'feed_nutrition'")->fetch();
$part3Items = db()->query("SELECT COUNT(DISTINCT i.id) AS cnt, COALESCE(SUM(b.quantity_on_hand),0) AS total_qty FROM wh_stock_items i LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.quantity_on_hand > 0 WHERE i.partition_code = 'materials_supplies'")->fetch();

$totalItems = (int) db()->query("SELECT COUNT(*) FROM wh_stock_items")->fetchColumn();
$pendingReceipts = (int) db()->query("SELECT COUNT(*) FROM wh_store_receiving WHERE status = 'pending_receipt'")->fetchColumn();
$pendingRequisitions = (int) db()->query("SELECT COUNT(*) FROM dept_material_requests WHERE status IN ('pending', 'partially_issued')")->fetchColumn();

// Low stock items count
$lowStockRows = db()->query(
    "SELECT i.id, COALESCE(SUM(b.quantity_on_hand), 0) AS cur_stock, COALESCE(MAX(r.minimum_threshold), 10.0) AS min_lim
     FROM wh_stock_items i
     LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.warehouse_id = 1 AND b.quantity_on_hand > 0
     LEFT JOIN wh_minimum_stock_rules r ON r.stock_item_id = i.id AND r.warehouse_id = 1
     GROUP BY i.id"
)->fetchAll();
$lowStockCount = 0;
foreach ($lowStockRows as $ls) {
    if ((float)$ls['cur_stock'] < (float)$ls['min_lim']) {
        $lowStockCount++;
    }
}

// Pending Deliveries awaiting physical check
$pendingShipments = db()->query(
    "SELECT r.*, i.name AS item_name, i.unit, i.partition_code, u.name AS sender_name
     FROM wh_store_receiving r
     JOIN wh_stock_items i ON i.id = r.stock_item_id
     JOIN users u ON u.id = r.sent_by
     WHERE r.status = 'pending_receipt'
     ORDER BY r.sent_at ASC LIMIT 8"
)->fetchAll();

// Incoming Department Material Requests awaiting action
$pendingReqs = db()->query(
    "SELECT r.*, d.name AS dept_name, i.name AS item_name, i.unit, u.name AS requester_name
     FROM dept_material_requests r
     JOIN departments d ON d.id = r.department_id
     JOIN wh_stock_items i ON i.id = r.stock_item_id
     JOIN users u ON u.id = r.requested_by
     WHERE r.status IN ('pending', 'partially_issued')
     ORDER BY r.created_at ASC LIMIT 6"
)->fetchAll();

$smartFeeds = smart_get_feed_depletion_forecast();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🏪 <?= current_locale() === 'am' ? 'የማዕከላዊ መጋዘን እና 3ቱ ክፍሎች ዳሽቦርድ' : 'Central Warehouse & 3-Partition Dashboard' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የተጠናቀቁ ምርቶች (ክፍል 1)፣ የእንስሳት መኖዎች (ክፍል 2) እና ዕቃዎችና መድኃኒት (ክፍል 3) የተቀናጀ አስተዳደር' : 'Consolidated store: Finished Products (Part 1), Feeds (Part 2), Materials & Supplies (Part 3)' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/modules/warehouse/receiving.php" class="btn">📥 <?= current_locale() === 'am' ? 'የዕቃ ርክክብ ገጽ' : 'Receiving' ?></a>
        <a href="<?= BASE_URL ?>/modules/warehouse/requisitions.php" class="btn secondary">📦 <?= current_locale() === 'am' ? 'የክፍሎች ጥያቄዎች' : 'Requisitions' ?> (<?= $pendingRequisitions ?>)</a>
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary">📊 <?= current_locale() === 'am' ? 'የክምችት ዝርዝር' : 'Stock Inventory' ?></a>
        <a href="<?= BASE_URL ?>/modules/warehouse/stock_adjust.php" class="btn secondary">⚖️ <?= current_locale() === 'am' ? 'ክምችት አስተካክል' : 'Stock Adjustment' ?></a>
    </div>
</div>

<!-- 3 Physical Partitions Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;margin-bottom:20px">
    <!-- Partition 1: Finished Products -->
    <div class="card" style="border-left:5px solid #2e7d32;background:#fbfefb;padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <span class="badge milking" style="font-size:12px">ክፍል 1 (Partition 1)</span>
                <h3 style="margin:8px 0 4px 0">🥛🥚 <?= current_locale() === 'am' ? 'የተጠናቀቁ ምርቶች' : 'Finished Products' ?></h3>
                <p class="text-muted" style="font-size:12px;margin:0"><?= current_locale() === 'am' ? 'ጥሬ ወተት፣ የዶሮ ዕንቁላል፣ ዓሳ' : 'Fresh Milk, Eggs, Fish' ?></p>
            </div>
            <span style="font-size:28px">📦</span>
        </div>
        <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="font-size:11px;color:#666"><?= current_locale() === 'am' ? 'የዕቃ ዓይነቶች' : 'Item Types' ?></div>
                <strong style="font-size:18px;color:#2e7d32"><?= (int)$part1Items['cnt'] ?></strong>
            </div>
            <a href="<?= BASE_URL ?>/modules/warehouse/stock.php?partition=finished_products" class="btn secondary" style="padding:4px 10px;font-size:12px">
                <?= current_locale() === 'am' ? 'ክፍል 1ን እይ →' : 'View Part 1 →' ?>
            </a>
        </div>
    </div>

    <!-- Partition 2: Feeds & Nutrition -->
    <div class="card" style="border-left:5px solid #f59e0b;background:#fffdfa;padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <span class="badge" style="background:#fef3c7;color:#92400e;font-size:12px">ክፍል 2 (Partition 2)</span>
                <h3 style="margin:8px 0 4px 0">🌾 <?= current_locale() === 'am' ? 'የእንስሳትና ዓሳ መኖዎች' : 'Feeds & Nutrition' ?></h3>
                <p class="text-muted" style="font-size:12px;margin:0"><?= current_locale() === 'am' ? 'የዶሮ፣ ላም፣ ጥጃ፣ ዓሳ መኖና ድርቆሽ' : 'Layer, Dairy, Calf, Fish Feed, Hay' ?></p>
            </div>
            <span style="font-size:28px">🌾</span>
        </div>
        <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="font-size:11px;color:#666"><?= current_locale() === 'am' ? 'የዕቃ ዓይነቶች' : 'Item Types' ?></div>
                <strong style="font-size:18px;color:#92400e"><?= (int)$part2Items['cnt'] ?></strong>
            </div>
            <a href="<?= BASE_URL ?>/modules/warehouse/stock.php?partition=feed_nutrition" class="btn secondary" style="padding:4px 10px;font-size:12px">
                <?= current_locale() === 'am' ? 'ክፍል 2ን እይ →' : 'View Part 2 →' ?>
            </a>
        </div>
    </div>

    <!-- Partition 3: Materials & Supplies -->
    <div class="card" style="border-left:5px solid #0284c7;background:#f8fafc;padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <span class="badge dry" style="font-size:12px">ክፍል 3 (Partition 3)</span>
                <h3 style="margin:8px 0 4px 0">🛠️💊 <?= current_locale() === 'am' ? 'ዕቃዎችና የግንባታ ቁሳቁስ' : 'Materials & Supplies' ?></h3>
                <p class="text-muted" style="font-size:12px;margin:0"><?= current_locale() === 'am' ? 'መድኃኒት፣ ጣሳ፣ መለዋወጫ፣ መሣሪያዎች' : 'Meds, Milk Cans, Spares, Tools' ?></p>
            </div>
            <span style="font-size:28px">⚙️</span>
        </div>
        <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="font-size:11px;color:#666"><?= current_locale() === 'am' ? 'የዕቃ ዓይነቶች' : 'Item Types' ?></div>
                <strong style="font-size:18px;color:#0369a1"><?= (int)$part3Items['cnt'] ?></strong>
            </div>
            <a href="<?= BASE_URL ?>/modules/warehouse/stock.php?partition=materials_supplies" class="btn secondary" style="padding:4px 10px;font-size:12px">
                <?= current_locale() === 'am' ? 'ክፍል 3ን እይ →' : 'View Part 3 →' ?>
            </a>
        </div>
    </div>
</div>

<!-- Smart Feed Depletion Forecast Widget -->
<?php if (!empty($smartFeeds)): ?>
<div class="card" style="margin-bottom:20px;border-left:4px solid #006837;background:rgba(0,104,55,0.03);padding:14px 18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
        <strong>🧠 <?= current_locale() === 'am' ? 'ብልህ የእርሻ ትንበያ፡ የመኖ ክምችት ማለቂያ ግምት (Feed Depletion Forecast)' : 'Smart Feed Depletion Forecast' ?></strong>
        <span class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'በቀን በሚበላው መጠን መሰረት የተሰላ' : 'Calculated by live herd/flock consumption rate' ?></span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px">
        <?php foreach ($smartFeeds as $sf): 
            $cardBorder = $sf['urgency'] === 'critical' ? '#b3261e' : ($sf['urgency'] === 'warning' ? '#e65100' : '#2e7d32');
            $daysBadge = $sf['urgency'] === 'critical' ? 'sold' : ($sf['urgency'] === 'warning' ? 'dry' : 'milking');
        ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-left:3px solid <?= $cardBorder ?>;padding:10px 12px;border-radius:4px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
                    <div style="font-weight:600;font-size:13px"><?= h($sf['name']) ?></div>
                    <?php if (!empty($sf['is_manual'])): ?>
                        <span class="badge" style="background:#e0f2fe;color:#0369a1;font-size:9px" title="<?= current_locale() === 'am' ? 'በአስተዳዳሪው በእጅ የተስተካከለ' : 'Manually set by manager' ?>">✍️ <?= current_locale() === 'am' ? 'በእጅ' : 'Manual' ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:#555">
                    <?= current_locale() === 'am' ? 'ያለ ክምችት:' : 'Stock:' ?> <strong><?= number_format($sf['stock'], 1) ?> <?= h($sf['unit']) ?></strong>
                </div>
                <div style="font-size:12px;color:#555;margin-top:2px">
                    <?= current_locale() === 'am' ? 'ዕለታዊ ፍጆታ:' : 'Daily Burn:' ?> <strong>~<?= number_format($sf['daily_burn'], 1) ?> <?= h($sf['unit']) ?>/ቀን</strong>
                </div>
                <div style="margin-top:6px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:4px">
                    <span class="badge <?= $daysBadge ?>">
                        <?= $sf['days_left'] ?> <?= current_locale() === 'am' ? 'ቀናት ይበቃል' : 'days left' ?>
                    </span>
                    <?php if ($sf['urgency'] !== 'ok'): ?>
                        <span style="font-size:11px;color:<?= $cardBorder ?>;font-weight:600">⚠️ <?= current_locale() === 'am' ? 'ትዕዛዝ ያስፈልጋል' : 'Reorder soon' ?></span>
                    <?php endif; ?>
                </div>
                <!-- Manual burn adjustment toggle -->
                <details style="margin-top:8px;font-size:11px;border-top:1px dashed #e2e8f0;padding-top:4px">
                    <summary style="cursor:pointer;color:var(--primary,#006837);font-weight:600">✏️ <?= current_locale() === 'am' ? 'ፍጆታን በእጅ አስተካክል' : 'Set Daily Burn' ?></summary>
                    <form method="post" style="margin-top:5px;display:flex;gap:4px;align-items:center"><?= csrf_field() ?>
                        <input type="hidden" name="save_feed_burn" value="1">
                        <input type="hidden" name="stock_item_id" value="<?= (int)$sf['item_id'] ?>">
                        <input type="number" step="0.1" min="0.1" name="daily_burn" placeholder="<?= number_format($sf['daily_burn'], 1) ?>" style="width:70px;padding:2px 4px;font-size:11px" required>
                        <button class="btn" type="submit" style="padding:2px 6px;font-size:10px"><?= current_locale() === 'am' ? 'መዝግብ' : 'Save' ?></button>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Pending Incoming Deliveries Card -->
<div class="card" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">📥 <?= current_locale() === 'am' ? 'ማረጋገጫ የሚጠብቁ የገቡ ጭነቶች (ዕለታዊ ወተት፣ ዕንቁላል እና ግዥዎች)' : 'Incoming Deliveries Awaiting Verification' ?></h3>
        <a href="<?= BASE_URL ?>/modules/warehouse/receiving.php" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'የበር ርክክብ ገጽ ክፈት →' : 'Open Receiving Portal →' ?></a>
    </div>
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'የመጋዘን ክፍል' : 'Partition' ?></th>
                <th><?= current_locale() === 'am' ? 'መለያ ኮድ' : 'Code' ?></th>
                <th><?= current_locale() === 'am' ? 'ምንጭ' : 'Source' ?></th>
                <th><?= current_locale() === 'am' ? 'የዕቃ ዓይነት' : 'Item' ?></th>
                <th><?= current_locale() === 'am' ? 'የተላከ መጠን' : 'Sent Qty' ?></th>
                <th><?= current_locale() === 'am' ? 'የተላከበት ቀን' : 'Sent Date' ?></th>
                <th><?= current_locale() === 'am' ? 'እርምጃ' : 'Action' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingShipments as $p): 
                $pBadge = match($p['partition_code'] ?? 'finished_products') {
                    'finished_products'  => '<span class="badge milking" style="font-size:11px">' . (current_locale() === 'am' ? 'ክፍል 1፡ ምርት' : 'Part 1: Product') . '</span>',
                    'feed_nutrition'     => '<span class="badge" style="background:#fef3c7;color:#92400e;font-size:11px">' . (current_locale() === 'am' ? 'ክፍል 2፡ መኖ' : 'Part 2: Feed') . '</span>',
                    'materials_supplies' => '<span class="badge dry" style="font-size:11px">' . (current_locale() === 'am' ? 'ክፍል 3፡ ዕቃ/መድኃኒት' : 'Part 3: Material') . '</span>',
                    default              => '<span class="badge">' . h($p['partition_code']) . '</span>'
                };
            ?>
                <tr>
                    <td><?= $pBadge ?></td>
                    <td><code><?= h($p['dispatch_code']) ?></code></td>
                    <td><span class="badge active"><?= h(ucfirst(str_replace('_', ' ', $p['source_type']))) ?></span></td>
                    <td><strong><?= h($p['item_name']) ?></strong></td>
                    <td><strong style="color:#006837"><?= number_format($p['sent_quantity'], 2) ?> <?= h($p['unit']) ?></strong></td>
                    <td style="font-size:12px;white-space:nowrap"><?= h(eth_datetime($p['sent_at'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/warehouse/receiving.php#rcv-<?= (int)$p['id'] ?>" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= current_locale() === 'am' ? 'መርምርና ተረከብ' : 'Review & Receive' ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pendingShipments)): ?>
                <tr><td colspan="7" class="text-muted">📦 <?= current_locale() === 'am' ? 'ምንም ርክክብ የሚጠብቅ ገቢ ጭነት የለም።' : 'No incoming deliveries waiting for warehouse approval.' ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Department Material Requests Card -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">📋 <?= current_locale() === 'am' ? 'የስራ ክፍሎች የዕቃ ጥያቄዎች ዝርዝር' : 'Department Requisitions Queue' ?></h3>
        <a href="<?= BASE_URL ?>/modules/warehouse/requisitions.php" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'ሁሉንም ጥያቄዎች እይ →' : 'View All Requisitions →' ?></a>
    </div>
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th>
                <th><?= current_locale() === 'am' ? 'የስራ ክፍል' : 'Department' ?></th>
                <th><?= current_locale() === 'am' ? 'የዕቃ ዓይነት' : 'Item' ?></th>
                <th><?= current_locale() === 'am' ? 'የተጠየቀ መጠን' : 'Requested' ?></th>
                <th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                <th><?= current_locale() === 'am' ? 'ጠያቂ ሰራተኛ' : 'Requested By' ?></th>
                <th><?= current_locale() === 'am' ? 'እርምጃ' : 'Action' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingReqs as $r): ?>
                <tr>
                    <td style="font-size:12px;white-space:nowrap"><?= eth_datetime($r['created_at']) ?></td>
                    <td><strong><?= h($r['dept_name']) ?></strong></td>
                    <td><?= h($r['item_name']) ?> <span class="text-muted">(<?= h($r['unit']) ?>)</span></td>
                    <td><?= number_format($r['requested_quantity'], 1) ?></td>
                    <td><span class="badge dry"><?= h(str_replace('_', ' ', $r['status'])) ?></span></td>
                    <td><?= h($r['requester_name']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/warehouse/requisitions.php" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= current_locale() === 'am' ? 'መርምርና ወጪ አድርግ' : 'Check & Issue' ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pendingReqs)): ?>
                <tr><td colspan="7" class="text-muted"><?= current_locale() === 'am' ? 'ምንም እርምጃ የሚጠብቅ የዕቃ ጥያቄ የለም።' : 'No pending material requisitions waiting for action.' ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
