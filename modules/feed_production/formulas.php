<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'feed_production_manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['create_formula'])) {
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'code' => trim($_POST['code'] ?? ''),
                'target_animal' => $_POST['target_animal'] ?? 'general',
                'description' => trim($_POST['description'] ?? '')
            ];

            $itemIds = $_POST['stock_item_id'] ?? [];
            $percentages = $_POST['percentage'] ?? [];
            $notes = $_POST['notes'] ?? [];

            $ingredients = [];
            for ($i = 0; $i < count($itemIds); $i++) {
                $sId = (int)($itemIds[$i] ?? 0);
                $pct = (float)($percentages[$i] ?? 0);
                if ($sId > 0 && $pct > 0) {
                    $ingredients[] = [
                        'stock_item_id' => $sId,
                        'percentage' => $pct,
                        'notes' => trim($notes[$i] ?? '')
                    ];
                }
            }

            if (empty($data['name']) || empty($data['code'])) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ የቀመሩን ስም እና ኮድ ያስገቡ።' : 'Please provide formula name and code.');
            }
            if (empty($ingredients)) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'ቢያንስ አንድ የመኖ ግብዓት መመረጥ አለበት።' : 'At least one ingredient is required.');
            }

            $formulaId = feed_formula_create($data, $ingredients);
            flash(current_locale() === 'am' ? "✓ የመኖ ቀመር '{$data['name']}' በተሳካ ሁኔታ ተመዝግቧል።" : "Feed formula '{$data['name']}' created successfully.");
            header('Location: ' . BASE_URL . '/modules/feed_production/formulas.php');
            exit;
        }
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

// Fetch all formulas with ingredient details
$formulas = db()->query("
    SELECT f.*, u.name AS creator_name,
           (SELECT COUNT(*) FROM feed_formula_ingredients WHERE formula_id = f.id) AS ingredient_count
    FROM feed_formulas f
    LEFT JOIN users u ON u.id = f.created_by
    ORDER BY f.id DESC
")->fetchAll();

// Available raw stock items for ingredients
$stockItems = db()->query("
    SELECT id, sku, name, unit, partition_code
    FROM wh_stock_items
    ORDER BY name ASC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🧪 <?= current_locale() === 'am' ? 'የመኖ ማዘጋጃ ቀመሮች (Feed Formulas)' : 'Feed Formulation Management' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'ለከብት፣ ለዶሮ እና ለዓሳ የሚዘጋጁ የመኖ ግብዓቶች ውህደት እና የ100% ምጣኔ አስተዳደር' : 'Manage animal feed nutritional formulas, raw material percentages, and standard costs.' ?></p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/feed_production/batches.php" class="btn secondary">⚙️ <?= current_locale() === 'am' ? 'የመኖ ማምረቻ ባች' : 'Production Batches' ?></a>
        <a href="<?= BASE_URL ?>/modules/feed_production/dashboard.php" class="btn secondary">🌾 <?= current_locale() === 'am' ? 'ወደ መኖ ዳሽቦርድ' : 'Feed Dashboard' ?></a>
    </div>
</div>

<div class="metrics-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:20px">
    <div class="card" style="margin:0;border-left:4px solid #1976d2">
        <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'ጠቅላላ የመኖ ቀመሮች' : 'Total Formulas' ?></div>
        <div style="font-size:24px;font-weight:700;color:#1976d2"><?= count($formulas) ?></div>
    </div>
    <div class="card" style="margin:0;border-left:4px solid #2e7d32">
        <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'ንቁ ቀመሮች' : 'Active Formulas' ?></div>
        <div style="font-size:24px;font-weight:700;color:#2e7d32">
            <?= count(array_filter($formulas, fn($f) => (int)$f['is_active'] === 1)) ?>
        </div>
    </div>
</div>

<!-- FORMULA CREATION CARD -->
<div class="card" style="margin-bottom:24px">
    <h3 style="margin-top:0">➕ <?= current_locale() === 'am' ? 'አዲስ የመኖ ቀመር መዝግብ (100% የግብዓቶች ድምር)' : 'Create New Feed Formula (100% Total)' ?></h3>
    <form method="post" id="formulaForm"><?= csrf_field() ?>
        <input type="hidden" name="create_formula" value="1">
        
        <div class="form-row" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;margin-bottom:14px">
            <div class="form-group" style="margin:0">
                <label><?= current_locale() === 'am' ? 'የቀመር ስም *' : 'Formula Name *' ?></label>
                <input type="text" name="name" required placeholder="<?= current_locale() === 'am' ? 'ምሳሌ፡ የላም ወተት ማሳደጊያ ደረጃ 1' : 'e.g. Standard Dairy Lactation Mix' ?>">
            </div>
            <div class="form-group" style="margin:0">
                <label><?= current_locale() === 'am' ? 'የቀመር ኮድ *' : 'Formula Code *' ?></label>
                <input type="text" name="code" required placeholder="e.g. FORM-DAIRY-01" style="text-transform:uppercase">
            </div>
            <div class="form-group" style="margin:0">
                <label><?= current_locale() === 'am' ? 'የታለመ እንስሳ *' : 'Target Animal *' ?></label>
                <select name="target_animal" required>
                    <option value="dairy"><?= current_locale() === 'am' ? 'ላም / ከብት (Dairy)' : 'Dairy' ?></option>
                    <option value="poultry"><?= current_locale() === 'am' ? 'ዶሮ (Poultry)' : 'Poultry' ?></option>
                    <option value="calf"><?= current_locale() === 'am' ? 'ጥጃ (Calf)' : 'Calf' ?></option>
                    <option value="fish"><?= current_locale() === 'am' ? 'ዓሳ (Fish)' : 'Fish' ?></option>
                    <option value="general"><?= current_locale() === 'am' ? 'አጠቃላይ (General)' : 'General' ?></option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:14px">
            <label><?= current_locale() === 'am' ? 'ዝርዝር መግለጫ / የአመጋገብ መመሪያ' : 'Description / Nutritional Purpose' ?></label>
            <textarea name="description" rows="2" placeholder="<?= current_locale() === 'am' ? 'የቀመሩ ፕሮቲን፣ ጉልበትና የአጠቃቀም መመሪያ...' : 'Notes on CP%, energy, or feeding instructions...' ?>"></textarea>
        </div>

        <h4 style="margin:16px 0 8px">🌾 <?= current_locale() === 'am' ? 'የቀመሩ ጥሬ ዕቃዎች እና የመቶኛ ምጣኔ' : 'Ingredients and Composition Percentages' ?></h4>
        <div style="background:var(--hover);padding:14px;border-radius:8px;margin-bottom:14px">
            <div id="ingredientRows">
                <!-- Row 1 -->
                <div class="ing-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
                    <div style="flex:2;min-width:200px">
                        <select name="stock_item_id[]" required onchange="calculateTotalPct()">
                            <option value=""><?= current_locale() === 'am' ? '-- ጥሬ ዕቃ ምረጥ --' : '-- Select Stock Item --' ?></option>
                            <?php foreach ($stockItems as $item): ?>
                                <option value="<?= $item['id'] ?>"><?= h($item['name']) ?> (<?= h($item['sku'] ?? '') ?> - <?= h($item['unit'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1;min-width:120px">
                        <input type="number" step="0.01" min="0.01" max="100" name="percentage[]" class="pct-input" required placeholder="<?= current_locale() === 'am' ? 'መቶኛ (%)' : 'Percentage (%)' ?>" oninput="calculateTotalPct()">
                    </div>
                    <div style="flex:2;min-width:150px">
                        <input type="text" name="notes[]" placeholder="<?= current_locale() === 'am' ? 'ማስታወሻ (አማራጭ)' : 'Notes (optional)' ?>">
                    </div>
                    <div>
                        <button type="button" class="btn danger" style="padding:6px 10px;display:none" onclick="removeIngRow(this)">✕</button>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:10px">
                <button type="button" class="btn secondary" onclick="addIngRow()">➕ <?= current_locale() === 'am' ? 'ተጨማሪ ጥሬ ዕቃ አክል' : 'Add Another Ingredient' ?></button>
                <div style="font-size:15px;font-weight:700">
                    <?= current_locale() === 'am' ? 'የመቶኛ ድምር፦' : 'Total Percentage:' ?> 
                    <span id="totalPctBadge" style="padding:4px 10px;border-radius:6px;background:#ffebee;color:#c62828">0.00%</span>
                </div>
            </div>
        </div>

        <button class="btn" type="submit" id="submitBtn" style="padding:8px 18px">💾 <?= current_locale() === 'am' ? 'ቀመሩን መዝግብ' : 'Save Formula' ?></button>
    </form>
</div>

<!-- FORMULAS LIST -->
<div class="card">
    <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'የተመዘገቡ የመኖ ቀመሮች ዝርዝር' : 'Registered Feed Formulas' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'የቀመር ስም' : 'Formula Name' ?></th>
                    <th><?= current_locale() === 'am' ? 'ኮድ' : 'Code' ?></th>
                    <th><?= current_locale() === 'am' ? 'የታለመ እንስሳ' : 'Target Animal' ?></th>
                    <th><?= current_locale() === 'am' ? 'የግብዓቶች ብዛት' : 'Ingredients' ?></th>
                    <th><?= current_locale() === 'am' ? 'የ1 ኪ.ግ ግምታዊ ዋጋ' : 'Est. Cost/kg' ?></th>
                    <th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተመዘገበበት ቀን' : 'Created At' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($formulas as $f): ?>
                    <?php
                    $ingList = db()->prepare("
                        SELECT fi.*, si.name AS item_name, si.unit
                        FROM feed_formula_ingredients fi
                        JOIN wh_stock_items si ON si.id = fi.stock_item_id
                        WHERE fi.formula_id = ?
                        ORDER BY fi.percentage DESC
                    ");
                    $ingList->execute([$f['id']]);
                    $ings = $ingList->fetchAll();
                    ?>
                    <tr>
                        <td>
                            <strong><?= h($f['name']) ?></strong>
                            <?php if ($f['description']): ?>
                                <div style="font-size:12px;color:var(--text-muted)"><?= h($f['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><code><?= h($f['code']) ?></code></td>
                        <td>
                            <span class="badge" style="background:#e3f2fd;color:#1565c0">
                                <?= ucfirst(h($f['target_animal'])) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= count($ings) ?> <?= current_locale() === 'am' ? 'ግብዓቶች' : 'items' ?></strong>
                            <div style="font-size:11px;color:#555;margin-top:4px">
                                <?php foreach ($ings as $ing): ?>
                                    <div>• <?= h($ing['item_name']) ?>: <strong><?= (float)$ing['percentage'] ?>%</strong></div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td style="font-weight:700;color:#2e7d32">
                            <?= number_format((float)$f['cost_per_kg'], 2) ?> ETB
                        </td>
                        <td>
                            <span class="badge <?= (int)$f['is_active'] === 1 ? 'active' : 'dry' ?>">
                                <?= (int)$f['is_active'] === 1 ? (current_locale() === 'am' ? 'ንቁ' : 'Active') : (current_locale() === 'am' ? 'የቦዘነ' : 'Inactive') ?>
                            </span>
                        </td>
                        <td><?= eth_date(substr($f['created_at'], 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($formulas)): ?>
                    <tr>
                        <td colspan="7" class="text-muted" style="text-align:center">
                            <?= current_locale() === 'am' ? 'ምንም የተመዘገበ የመኖ ቀመር የለም። ከላይ ባለው ቅጽ የመጀመሪያውን ቀመር ይመዝግቡ።' : 'No feed formulas registered yet. Create one using the form above.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function calculateTotalPct() {
    let total = 0.0;
    const inputs = document.querySelectorAll('.pct-input');
    inputs.forEach(input => {
        const val = parseFloat(input.value) || 0;
        total += val;
    });

    const badge = document.getElementById('totalPctBadge');
    badge.innerText = total.toFixed(2) + '%';

    if (Math.abs(total - 100.0) <= 0.01) {
        badge.style.background = '#e8f5e9';
        badge.style.color = '#2e7d32';
    } else {
        badge.style.background = '#ffebee';
        badge.style.color = '#c62828';
    }
}

function addIngRow() {
    const container = document.getElementById('ingredientRows');
    const firstRow = container.querySelector('.ing-row');
    const newRow = firstRow.cloneNode(true);

    newRow.querySelectorAll('input').forEach(input => input.value = '');
    newRow.querySelector('select').selectedIndex = 0;
    const removeBtn = newRow.querySelector('.danger');
    removeBtn.style.display = 'inline-block';

    container.appendChild(newRow);
    calculateTotalPct();
}

function removeIngRow(btn) {
    const row = btn.closest('.ing-row');
    const container = document.getElementById('ingredientRows');
    if (container.querySelectorAll('.ing-row').length > 1) {
        row.remove();
        calculateTotalPct();
    }
}

document.getElementById('formulaForm').addEventListener('submit', function(e) {
    let total = 0.0;
    document.querySelectorAll('.pct-input').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    if (Math.abs(total - 100.0) > 0.01) {
        e.preventDefault();
        alert('<?= current_locale() === 'am' ? 'የመኖ ግብዓቶች መቶኛ ድምር በትክክል 100% መሆን አለበት! የአሁኑ ድምር፦ ' : 'Formula ingredient percentages must sum to exactly 100%! Current sum: ' ?>' + total.toFixed(2) + '%');
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>