<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer', 'warehouse_keeper']);

$data = finance_inventory_value();

// Optional CSV Export
if (($_GET['export'] ?? '') === 'csv') {
    require_once __DIR__ . '/../../includes/export.php';
    $exportData = [];
    foreach ($data['by_item'] as $row) {
        $exportData[] = [
            'የዕቃው ስም'        => $row['name'],
            'የክምችት ክፍልፍል'   => stock_partition_label($row['partition_code'] ?? ''),
            'በክምችት ላይ ያለ መጠን' => number_format((float)$row['qty'], 2) . ' ' . ($row['unit'] ?? ''),
            'አማካይ የአንዱ ዋጋ (ETB)' => number_format((float)$row['avg_cost'], 2),
            'ጠቅላላ የክምችት ዋጋ (ETB)' => number_format((float)$row['total_value'], 2),
        ];
    }
    export_csv($exportData, 'inventory_valuation_' . date('Y-m-d') . '.csv');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$totalItemsCount = count($data['by_item']);
$itemsWithValue = count(array_filter($data['by_item'], fn($x) => (float)$x['total_value'] > 0));
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            📦 <?= current_locale() === 'am' ? 'የክምችት ጠቅላላ ዋጋ (Inventory Valuation)' : 'Inventory Valuation — Current Stock Value' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am'
                ? 'ጠቅላላ ዋጋ = (የክምችት መጠን × የአንዱ ዋጋ) — የአንዱ ዋጋ የሚመዘገበው እቃዎች በጸደቀ የግዢ ትዕዛዝ (PO) ሲገቡ ነው።'
                : 'Total = Quantity on Hand × Unit Cost. Unit cost populates when goods are received via approved Purchase Orders.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="?export=csv" class="btn secondary">
            ⬇️ <?= current_locale() === 'am' ? 'በ CSV አውርድ (Export)' : 'Export CSV' ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/warehouse/dashboard.php" class="btn secondary">
            🏬 <?= current_locale() === 'am' ? 'የመጋዘን ማዕከል' : 'Warehouse Dashboard' ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">
            &larr; <?= current_locale() === 'am' ? 'ወደ ፋይናንስ ማዕከል' : 'Finance Dashboard' ?>
        </a>
    </div>
</div>

<!-- KPI Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:22px">
    <div class="card" style="border-left:4px solid #1565c0;padding:16px">
        <div style="font-size:12px;color:var(--text-muted);font-weight:700">
            💰 <?= current_locale() === 'am' ? 'አጠቃላይ የክምችት ዋጋ' : 'Total Inventory Value' ?>
        </div>
        <div style="font-size:24px;font-weight:800;color:#1565c0;margin-top:6px">
            <?= number_format((float)$data['total_inventory_value'], 2) ?> ETB
        </div>
        <small class="text-muted">
            <?= current_locale() === 'am' ? 'በመጋዘን ውስጥ ያለው ጠቅላላ ንብረት' : 'Total current warehouse asset' ?>
        </small>
    </div>

    <div class="card" style="border-left:4px solid #2e7d32;padding:16px">
        <div style="font-size:12px;color:var(--text-muted);font-weight:700">
            📦 <?= current_locale() === 'am' ? 'በክምችት ላይ ያሉ የዕቃዎች ብዛት' : 'Total Stocked Items' ?>
        </div>
        <div style="font-size:24px;font-weight:800;color:#2e7d32;margin-top:6px">
            <?= number_format($totalItemsCount) ?> <?= current_locale() === 'am' ? 'ዕቃዎች' : 'Items' ?>
        </div>
        <small class="text-muted">
            <?= current_locale() === 'am' ? 'መጠን ያላቸው የዕቃ አይነቶች' : 'Distinct item codes on hand' ?>
        </small>
    </div>

    <div class="card" style="border-left:4px solid #e65100;padding:16px">
        <div style="font-size:12px;color:var(--text-muted);font-weight:700">
            🏷️ <?= current_locale() === 'am' ? 'የአንዱ ዋጋ የተመዘገበላቸው' : 'Valued Items with Unit Cost' ?>
        </div>
        <div style="font-size:24px;font-weight:800;color:#e65100;margin-top:6px">
            <?= number_format($itemsWithValue) ?> / <?= number_format($totalItemsCount) ?>
        </div>
        <small class="text-muted">
            <?= current_locale() === 'am' ? 'ከግዢ ትዕዛዞች ዋጋ የተገኘላቸው' : 'Items with tracked PO unit costs' ?>
        </small>
    </div>
</div>

<!-- Stock Items Table with Instant Search & Filter -->
<div class="card" style="padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
        <div>
            <h3 style="margin:0;font-size:17px">
                📊 <?= current_locale() === 'am' ? 'በክምችት እቃዎች ዝርዝር ዋጋ' : 'Stock Valuation by Item' ?>
            </h3>
            <small class="text-muted">
                <?= current_locale() === 'am' ? 'እያንዳንዱ ዕቃ በመጋዘን ያለው መጠን እና የተሰላለት ጠቅላላ ዋጋ' : 'Unit cost and total monetary value for all stocked batches' ?>
            </small>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input type="text" id="inventorySearch" placeholder="🔍 <?= current_locale() === 'am' ? 'በዕቃው ስም ፈልግ...' : 'Search by item name...' ?>" onkeyup="filterInventoryTable()" style="padding:6px 12px;font-size:13px;border:1px solid var(--border);border-radius:6px;width:220px">
            <select id="partitionFilter" onchange="filterInventoryTable()" style="padding:6px 12px;font-size:13px;border:1px solid var(--border);border-radius:6px">
                <option value=""><?= current_locale() === 'am' ? '📁 ሁሉም ክፍልፍሎች (Partitions)' : 'All Partitions' ?></option>
                <?php
                $partitions = array_values(array_unique(array_filter(array_column($data['by_item'], 'partition_code'))));
                sort($partitions);
                foreach ($partitions as $pCode):
                ?>
                    <option value="<?= h($pCode) ?>"><?= h(stock_partition_label($pCode)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (empty($data['by_item'])): ?>
        <div style="padding:30px;text-align:center;color:var(--text-muted)">
            <p style="font-size:15px;margin:0">
                <?= current_locale() === 'am' ? '📦 በመጋዘን ውስጥ ምንም ዓይነት ክምችት አልተገኘም።' : 'No active inventory on hand.' ?>
            </p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table id="inventoryTable">
                <thead>
                    <tr>
                        <th><?= current_locale() === 'am' ? 'የዕቃው ስም' : 'Item Name' ?></th>
                        <th><?= current_locale() === 'am' ? 'የክምችት ክፍልፍል' : 'Partition' ?></th>
                        <th style="text-align:right"><?= current_locale() === 'am' ? 'በክምችት ያለ መጠን' : 'Qty on Hand' ?></th>
                        <th style="text-align:right"><?= current_locale() === 'am' ? 'አማካይ የአንዱ ዋጋ' : 'Avg Unit Cost' ?></th>
                        <th style="text-align:right"><?= current_locale() === 'am' ? 'ጠቅላላ የክምችት ዋጋ' : 'Total Valuation' ?></th>
                        <th style="text-align:center"><?= current_locale() === 'am' ? 'የዋጋ ሁኔታ' : 'Status' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['by_item'] as $row): 
                        $hasCost = (float)$row['avg_cost'] > 0;
                        $totalVal = (float)$row['total_value'];
                    ?>
                        <tr data-name="<?= h(mb_strtolower($row['name'])) ?>" data-partition="<?= h($row['partition_code'] ?? '') ?>">
                            <td>
                                <strong><?= h($row['name']) ?></strong>
                            </td>
                            <td>
                                <span class="badge" style="background:#e8eaf6;color:#283593;font-size:11.5px">
                                    <?= h(stock_partition_label($row['partition_code'] ?? '')) ?>
                                </span>
                            </td>
                            <td style="text-align:right;font-weight:700">
                                <?= number_format((float)$row['qty'], 2) ?> <span style="font-size:11.5px;color:var(--text-muted);font-weight:normal"><?= h($row['unit'] ?? '') ?></span>
                            </td>
                            <td style="text-align:right">
                                <?php if ($hasCost): ?>
                                    <span style="font-weight:600"><?= number_format((float)$row['avg_cost'], 2) ?> ETB</span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:12px" title="በግዢ ትዕዛዝ ሲገባ የአንዱ ዋጋ ይሞላል">— ዋጋ አልተመዘገበም</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;font-weight:800;color:<?= $totalVal > 0 ? '#1565c0' : 'var(--text-muted)' ?>;font-size:14px">
                                <?= number_format($totalVal, 2) ?> ETB
                            </td>
                            <td style="text-align:center">
                                <?php if ($totalVal > 0): ?>
                                    <span class="badge milking" style="font-size:11px">✓ ዋጋ ያለው</span>
                                <?php else: ?>
                                    <span class="badge dry" style="font-size:11px">⏳ ዋጋ ይጠብቃል</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Valuation Help Box -->
<div class="card" style="margin-top:20px;background:var(--hover);border-left:4px solid #1565c0;padding:14px 18px">
    <h4 style="margin-top:0;margin-bottom:6px;color:#1565c0">
        💡 <?= current_locale() === 'am' ? 'የክምችት ዋጋ አሰራር ደንብና ስሌት' : 'Inventory Valuation Rules & Methods' ?>
    </h4>
    <ul style="margin:0;padding-left:20px;font-size:12.5px;color:var(--text-muted);line-height:1.6">
        <li><strong>የስሌት ቀመር፦</strong> የክምችት ዋጋ የሚሰላው በመጋዘን ውስጥ ባለው ትክክለኛ የዕቃ መጠን እና እቃው በግዢ ትዕዛዝ (PO) በኩል ሲገባ በተመዘገበው የአንዱ ዋጋ (Unit Cost) ተባዝቶ ነው።</li>
        <li><strong>የአንዱ ዋጋ (Unit Cost)፦</strong> እቃዎች በግዢ ትዕዛዝ በኩል ወደ መጋዘን ገቢ (Store Receiving) ሲደረጉ በግዢ ደረሰኙ ላይ ባለው ትክክለኛ ዋጋ መሠረት በሲስተሙ ይመዘገባል።</li>
        <li><strong>የእርሻ ምርቶች፦</strong> በእርሻው የተመረቱ ምርቶች (ለምሳሌ፡ የተፈበረከ መኖ) ከመጋዘን ጥሬ ዕቃ ወጪ ተደርጎባቸው የሚመረቱ በመሆናቸው በምርት ወጪያቸው መሠረት ይተመናሉ።</li>
    </ul>
</div>

<script>
function filterInventoryTable() {
    const q = document.getElementById('inventorySearch').value.toLowerCase().trim();
    const partition = document.getElementById('partitionFilter').value;
    const rows = document.querySelectorAll('#inventoryTable tbody tr');

    rows.forEach(r => {
        const name = (r.getAttribute('data-name') || '').toLowerCase();
        const pCode = r.getAttribute('data-partition') || '';

        const matchesQuery = !q || name.includes(q);
        const matchesPartition = !partition || pCode === partition;

        if (matchesQuery && matchesPartition) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
