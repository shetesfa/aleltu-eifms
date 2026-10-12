<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper']);

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$partition = trim($_GET['partition'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT i.*, 
               COALESCE(SUM(b.quantity_on_hand), 0) AS current_stock,
               r.minimum_threshold
        FROM wh_stock_items i
        LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.warehouse_id = 1 AND b.quantity_on_hand > 0
        LEFT JOIN wh_minimum_stock_rules r ON r.stock_item_id = i.id AND r.warehouse_id = 1
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (i.name LIKE ? OR i.sku LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category !== '') {
    $sql .= " AND i.product_class = ?";
    $params[] = $category;
}

if ($partition !== '' && in_array($partition, ['finished_products', 'feed_nutrition', 'materials_supplies'], true)) {
    $sql .= " AND i.partition_code = ?";
    $params[] = $partition;
}

$sql .= " GROUP BY i.id ORDER BY i.partition_code ASC, i.name ASC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Partition counts and KPIs
$partitionStats = [
    'finished_products' => ['count' => 0, 'low' => 0],
    'feed_nutrition' => ['count' => 0, 'low' => 0],
    'materials_supplies' => ['count' => 0, 'low' => 0],
];

$totalItems = count($items);
$inStockCount = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

$filteredItems = [];
foreach ($items as $item) {
    $stock = (float)$item['current_stock'];
    $min = $item['minimum_threshold'] !== null ? (float)$item['minimum_threshold'] : 10.0;
    
    if ($stock <= 0) {
        $status = 'out_of_stock';
        $outOfStockCount++;
    } elseif ($stock < $min) {
        $status = 'low_stock';
        $lowStockCount++;
    } else {
        $status = 'adequate';
        $inStockCount++;
    }
    
    $pCode = $item['partition_code'] ?? 'finished_products';
    if (isset($partitionStats[$pCode])) {
        $partitionStats[$pCode]['count']++;
        if ($status === 'low_stock' || $status === 'out_of_stock') {
            $partitionStats[$pCode]['low']++;
        }
    }

    $item['calculated_status'] = $status;
    $item['effective_min'] = $min;

    if ($statusFilter !== '' && $status !== $statusFilter) {
        continue;
    }
    $filteredItems[] = $item;
}

$smartFeeds = smart_get_feed_depletion_forecast();

if (($_GET['export'] ?? '') === 'csv') {
    require_once __DIR__ . '/../../includes/export.php';
    $exportData = [];
    foreach ($filteredItems as $f) {
        $exportData[] = [
            'Partition' => stock_partition_label($f['partition_code'] ?? 'finished_products'),
            'Item Name' => $f['name'],
            'SKU' => $f['sku'],
            'Category' => $f['product_class'],
            'Current Stock' => $f['current_stock'],
            'Unit' => $f['unit'],
            'Min Threshold' => $f['effective_min'],
            'Status' => $f['calculated_status']
        ];
    }
    export_csv($exportData, 'warehouse_inventory_' . date('Y-m-d') . '.csv');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🏪 <?= current_locale() === 'am' ? 'የማዕከላዊ መጋዘን ክምችት በ3 ዋና ክፍሎች' : 'Central Warehouse 3-Partition Stock Inventory' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'ዋና ማዕከላዊ መጋዘን — በ3 የተከፋፈሉ አካላዊ ክፍሎች የተደራጀ ትክክለኛ ክምችት' : 'Central Main Store — Organized into 3 physical inventory partitions' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/aleltu-eifms/modules/warehouse/receiving.php" class="btn">📥 <?= current_locale() === 'am' ? 'ዕቃ ተቀበል / አስገባ' : 'Receive Stock' ?></a>
        <a href="/aleltu-eifms/modules/warehouse/issue.php" class="btn secondary">📤 <?= current_locale() === 'am' ? 'ዕቃ ወጪ አድርግ' : 'Issue Stock' ?></a>
        <a href="/aleltu-eifms/modules/warehouse/stock_adjust.php" class="btn secondary">⚖️ <?= current_locale() === 'am' ? 'ክምችት አስተካክል' : 'Stock Adjust' ?></a>
        <a href="?export=csv" class="btn secondary">⬇️ <?= current_locale() === 'am' ? 'ኤክስፖርት (CSV)' : 'Export CSV' ?></a>
    </div>
</div>

<!-- 3 Partitions Switcher Tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <a href="?partition=" class="btn <?= $partition === '' ? '' : 'secondary' ?>" style="padding:8px 16px;border-radius:6px">
        🏢 <?= current_locale() === 'am' ? 'ሙሉ መጋዘን (ሁሉም ክፍሎች)' : 'All Partitions' ?> (<?= $totalItems ?>)
    </a>
    <a href="?partition=finished_products" class="btn <?= $partition === 'finished_products' ? '' : 'secondary' ?>" style="padding:8px 16px;border-radius:6px">
        🥛🥚 <?= current_locale() === 'am' ? 'ክፍል 1፡ የተጠናቀቁ ምርቶች (ወተት፣ ዕንቁላል፣ ዓሳ)' : 'Partition 1: Finished Products' ?>
    </a>
    <a href="?partition=feed_nutrition" class="btn <?= $partition === 'feed_nutrition' ? '' : 'secondary' ?>" style="padding:8px 16px;border-radius:6px">
        🌾 <?= current_locale() === 'am' ? 'ክፍል 2፡ የእንስሳትና ዓሳ መኖዎች (የዶሮ፣ ላም፣ ዓሳ መኖ)' : 'Partition 2: Feeds & Nutrition' ?>
    </a>
    <a href="?partition=materials_supplies" class="btn <?= $partition === 'materials_supplies' ? '' : 'secondary' ?>" style="padding:8px 16px;border-radius:6px">
        📦🛠️ <?= current_locale() === 'am' ? 'ክፍል 3፡ ዕቃዎችና የግንባታ ቁሳቁስ (መድኃኒት፣ ጣሳ፣ መለዋወጫ)' : 'Partition 3: Materials & Supplies' ?>
    </a>
</div>

<!-- Smart Feed Depletion Forecast Banner (If in All or Feed view) -->
<?php if (($partition === '' || $partition === 'feed_nutrition') && !empty($smartFeeds)): ?>
<div class="card" style="margin-bottom:20px;border-left:4px solid #006837;background:rgba(0,104,55,0.03);padding:14px 18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
        <strong>🧠 <?= current_locale() === 'am' ? 'ብልህ የእርሻ ትንበያ፡ የመኖ ክምችት ማለቂያ ግምት (Smart Feed Depletion Forecast)' : 'Smart Farm Intelligence: Feed Depletion Forecast' ?></strong>
        <span class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'በቀን በሚበላው መጠን መሰረት የተሰላ' : 'Calculated by live herd/flock consumption rate' ?></span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px">
        <?php foreach ($smartFeeds as $sf): 
            $cardBorder = $sf['urgency'] === 'critical' ? '#b3261e' : ($sf['urgency'] === 'warning' ? '#e65100' : '#2e7d32');
            $daysBadge = $sf['urgency'] === 'critical' ? 'sold' : ($sf['urgency'] === 'warning' ? 'dry' : 'milking');
        ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-left:3px solid <?= $cardBorder ?>;padding:10px 12px;border-radius:4px">
                <div style="font-weight:600;font-size:13px;margin-bottom:4px"><?= h($sf['name']) ?></div>
                <div style="font-size:12px;color:#555">
                    <?= current_locale() === 'am' ? 'ያለ ክምችት:' : 'Stock:' ?> <strong><?= number_format($sf['stock'], 1) ?> <?= h($sf['unit']) ?></strong>
                </div>
                <div style="font-size:12px;color:#555;margin-top:2px">
                    <?= current_locale() === 'am' ? 'ዕለታዊ ፍጆታ:' : 'Daily Burn:' ?> <strong>~<?= number_format($sf['daily_burn'], 1) ?> <?= h($sf['unit']) ?>/ቀን</strong>
                </div>
                <div style="margin-top:6px;display:flex;justify-content:space-between;align-items:center">
                    <span class="badge <?= $daysBadge ?>">
                        <?= $sf['days_left'] ?> <?= current_locale() === 'am' ? 'ቀናት ይበቃል' : 'days left' ?>
                    </span>
                    <?php if ($sf['urgency'] !== 'ok'): ?>
                        <span style="font-size:11px;color:<?= $cardBorder ?>;font-weight:600">⚠️ <?= current_locale() === 'am' ? 'ትዕዛዝ ያስፈልጋል' : 'Reorder soon' ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'ጠቅላላ የዕቃ ዓይነቶች' : 'Total Item Types' ?></div>
        <div class="value"><?= $totalItems ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'በቂ ክምችት ያላቸው' : 'Adequate Stock' ?></div>
        <div class="value" style="color:#2e7d32"><?= $inStockCount ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'ያነሱ / የሚገዙ' : 'Low Stock Alert' ?></div>
        <div class="value" style="color:<?= $lowStockCount > 0 ? '#e65100' : 'inherit' ?>"><?= $lowStockCount ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'ያለቁ ዕቃዎች' : 'Out of Stock' ?></div>
        <div class="value" style="color:<?= $outOfStockCount > 0 ? '#b3261e' : 'inherit' ?>"><?= $outOfStockCount ?></div>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card" style="margin-bottom:20px;padding:14px 18px">
    <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="partition" value="<?= h($partition) ?>">
        <div class="form-group" style="margin:0;flex:1;min-width:200px">
            <label style="font-size:12px"><?= current_locale() === 'am' ? 'የዕቃ ስም ወይም SKU ፈልግ' : 'Search Item Name or SKU' ?></label>
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ መኖ፣ ወተት፣ ዕንቁላል...' : 'e.g. Milk, Eggs, Feed...' ?>">
        </div>
        <div class="form-group" style="margin:0;width:170px">
            <label style="font-size:12px"><?= current_locale() === 'am' ? 'ምድብ' : 'Category' ?></label>
            <select name="category">
                <option value=""><?= current_locale() === 'am' ? 'ሁሉም ምድቦች' : 'All Categories' ?></option>
                <option value="feed" <?= $category === 'feed' ? 'selected' : '' ?>>🌾 <?= current_locale() === 'am' ? 'መኖ (Feed)' : 'Feed' ?></option>
                <option value="milk" <?= $category === 'milk' ? 'selected' : '' ?>>🥛 <?= current_locale() === 'am' ? 'ወተት (Milk)' : 'Milk' ?></option>
                <option value="general" <?= $category === 'general' ? 'selected' : '' ?>>📦 <?= current_locale() === 'am' ? 'ጠቅላላ/ዕንቁላል' : 'General / Eggs' ?></option>
                <option value="medicine" <?= $category === 'medicine' ? 'selected' : '' ?>>💊 <?= current_locale() === 'am' ? 'መድኃኒት (Medicine)' : 'Medicine' ?></option>
            </select>
        </div>
        <div class="form-group" style="margin:0;width:170px">
            <label style="font-size:12px"><?= current_locale() === 'am' ? 'የክምችት ሁኔታ' : 'Stock Status' ?></label>
            <select name="status">
                <option value=""><?= current_locale() === 'am' ? 'ሁሉም ሁኔታዎች' : 'All Statuses' ?></option>
                <option value="adequate" <?= $statusFilter === 'adequate' ? 'selected' : '' ?>>🟢 <?= current_locale() === 'am' ? 'በቂ ክምችት' : 'Adequate' ?></option>
                <option value="low_stock" <?= $statusFilter === 'low_stock' ? 'selected' : '' ?>>🟠 <?= current_locale() === 'am' ? 'አነስተኛ ክምችት' : 'Low Stock' ?></option>
                <option value="out_of_stock" <?= $statusFilter === 'out_of_stock' ? 'selected' : '' ?>>🔴 <?= current_locale() === 'am' ? 'ያለቀ ክምችት' : 'Out of Stock' ?></option>
            </select>
        </div>
        <button class="btn" type="submit" style="padding:7px 16px">🔍 <?= current_locale() === 'am' ? 'ፈልግ' : 'Search' ?></button>
        <?php if ($search !== '' || $category !== '' || $statusFilter !== '' || $partition !== ''): ?>
            <a href="/aleltu-eifms/modules/warehouse/stock.php" class="btn secondary" style="padding:7px 12px"><?= current_locale() === 'am' ? 'አጽዳ' : 'Clear' ?></a>
        <?php endif; ?>
    </form>
</div>

<!-- Stock Table -->
<div class="card">
    <div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'የመጋዘን ክፍል' : 'Store Partition' ?></th>
                <th>SKU</th>
                <th><?= current_locale() === 'am' ? 'የዕቃው ስም' : 'Item Name' ?></th>
                <th><?= current_locale() === 'am' ? 'ምድብ' : 'Category' ?></th>
                <th><?= current_locale() === 'am' ? 'በመጋዘን ያለው መጠን' : 'Current Stock' ?></th>
                <th><?= current_locale() === 'am' ? 'ዝቅተኛ መጠን' : 'Min Limit' ?></th>
                <th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                <th><?= current_locale() === 'am' ? 'ፈጣን እርምጃ' : 'Actions' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filteredItems as $r): 
                $stockVal = (float)$r['current_stock'];
                $statusBadge = match($r['calculated_status']) {
                    'adequate' => '<span class="badge milking">' . (current_locale() === 'am' ? '✓ በቂ ክምችት' : 'Adequate') . '</span>',
                    'low_stock' => '<span class="badge sold">' . (current_locale() === 'am' ? '⚠️ አነስተኛ' : 'Low Stock') . '</span>',
                    'out_of_stock' => '<span class="badge sold" style="background:#b3261e;color:#fff">' . (current_locale() === 'am' ? '🔴 ያለቀ' : 'Out of Stock') . '</span>',
                };
                $pBadge = match($r['partition_code'] ?? 'finished_products') {
                    'finished_products'  => '<span class="badge milking" style="font-size:11px">' . (current_locale() === 'am' ? 'ክፍል 1፡ ምርት' : 'Part 1: Product') . '</span>',
                    'feed_nutrition'     => '<span class="badge" style="background:#fef3c7;color:#92400e;font-size:11px">' . (current_locale() === 'am' ? 'ክፍል 2፡ መኖ' : 'Part 2: Feed') . '</span>',
                    'materials_supplies' => '<span class="badge dry" style="font-size:11px">' . (current_locale() === 'am' ? 'ክፍል 3፡ ዕቃ/መድኃኒት' : 'Part 3: Material') . '</span>',
                    default              => '<span class="badge">' . h($r['partition_code']) . '</span>'
                };
            ?>
                <tr>
                    <td><?= $pBadge ?></td>
                    <td><code><?= h($r['sku']) ?></code></td>
                    <td><strong><?= h($r['name']) ?></strong></td>
                    <td><span class="badge"><?= h(ucfirst($r['product_class'])) ?></span></td>
                    <td>
                        <strong style="font-size:16px;color:<?= $stockVal > 0 ? '#1b5e20' : '#b3261e' ?>">
                            <?= number_format($stockVal, 2) ?> <?= h($r['unit']) ?>
                        </strong>
                    </td>
                    <td class="text-muted"><?= number_format($r['effective_min'], 2) ?> <?= h($r['unit']) ?></td>
                    <td><?= $statusBadge ?></td>
                    <td>
                        <a href="/aleltu-eifms/modules/warehouse/issue.php?item_id=<?= (int)$r['id'] ?>" class="btn secondary" style="padding:3px 8px;font-size:11px">📤 <?= current_locale() === 'am' ? 'ወጪ አድርግ' : 'Issue' ?></a>
                        <a href="/aleltu-eifms/modules/warehouse/stock_adjust.php?item_id=<?= (int)$r['id'] ?>" class="btn secondary" style="padding:3px 8px;font-size:11px">⚖️ <?= current_locale() === 'am' ? 'አስተካክል' : 'Adjust' ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filteredItems)): ?>
                <tr>
                    <td colspan="8" class="text-muted" style="padding:24px;text-align:center">
                        <?= current_locale() === 'am' ? 'ምንም የተገኘ የዕቃ መረጃ የለም።' : 'No stock items found.' ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
