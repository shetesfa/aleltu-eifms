<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'fish_manager', 'super_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['record_feeding'])) {
            $pondId = (int) $_POST['pond_id'];
            $feedType = trim($_POST['feed_type'] ?? '');
            $quantityKg = (float) $_POST['quantity_kg'];
            $feedDate = $_POST['feed_date'] ?? date('Y-m-d');

            if ($quantityKg <= 0) {
                throw new InvalidArgumentException('Quantity must be greater than zero.');
            }
            if (empty($feedType)) {
                throw new InvalidArgumentException('Feed type is required.');
            }

            $stockId = (int) db()->query("SELECT id FROM fish_stocks WHERE pond_id = {$pondId} AND status = 'active' LIMIT 1")->fetchColumn();
            if ($stockId > 0) {
                fish_log_feeding($stockId, $quantityKg, $feedType);
            } else {
                log_activity('fish', 'feeding', 'fish_ponds', $pondId, "Fed {$quantityKg}kg of {$feedType} on {$feedDate}");
            }
            flash(current_locale() === 'am' ? '✓ የዓሳ መመገቢያ ተመዝግቧል።' : 'Fish feeding recorded successfully.');
        }
        header("Location: " . BASE_URL . "/modules/fish/feeding.php");
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

// List all active ponds with their stocks
$ponds = db()->query(
    "SELECT p.*, s.id AS stock_id, s.species, s.stocking_quantity
     FROM fish_ponds p
     LEFT JOIN fish_stocks s ON s.pond_id = p.id AND s.status = 'active'
     WHERE p.status != 'empty'
     ORDER BY p.name"
)->fetchAll();

// Get recent feeding records from audit_log
$recentFeedings = db()->query(
    "SELECT al.*, p.name AS pond_name
     FROM audit_log al
     LEFT JOIN fish_ponds p ON p.id = al.record_id
     WHERE al.module = 'fish' AND al.action = 'feeding'
     ORDER BY al.created_at DESC LIMIT 20"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<h2 class="mt-0">🐟 <?= current_locale() === 'am' ? 'የዓሳ መመገቢያ መመዝገቢያ' : 'Fish Daily Feeding Entry' ?></h2>
<p class="text-muted"><?= current_locale() === 'am' ? 'ለእያንዳንዱ ገንዳ የተሰጠውን የመኖ ዓይነትና መጠን መዝግብ' : 'Record daily feeding amount and feed type for each active pond.' ?></p>

<div class="card" style="max-width:600px;margin-bottom:24px">
    <h3><?= current_locale() === 'am' ? 'ዕለታዊ መመገቢያ መዝግብ' : 'Record Feeding' ?></h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="record_feeding" value="1">

        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'የዓሳ ገንዳ (Pond) *' : 'Fish Pond *' ?></label>
            <select name="pond_id" required style="width:100%">
                <option value="">— ገንዳ ምረጥ —</option>
                <?php foreach ($ponds as $p): ?>
                    <option value="<?= (int)$p['id'] ?>">
                        <?= h($p['name']) ?> (<?= h($p['species'] ?? 'ዓሳ ዝርያ') ?> — <?= number_format((int)$p['quantity_stocked']) ?> ዓሦች)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የመኖ ዓይነት *' : 'Feed Type *' ?></label>
                <input type="text" name="feed_type" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የዓሳ ፔሌት 2ሚሜ' : 'e.g. Floating Pellets 2mm' ?>">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'መጠን (በኪሎግራም) *' : 'Quantity (kg) *' ?></label>
                <input type="number" step="0.01" min="0.01" name="quantity_kg" required placeholder="5.0">
            </div>
        </div>

        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'የተመገበበት ቀን' : 'Feeding Date' ?></label>
            <input type="date" name="feed_date" value="<?= date('Y-m-d') ?>">
        </div>

        <button class="btn" type="submit"><?= current_locale() === 'am' ? 'መመገቢያውን መዝግብ' : 'Save Feeding Record' ?></button>
    </form>
</div>

<div class="card">
    <h3>📜 <?= current_locale() === 'am' ? 'የቅርብ ጊዜ የመመገቢያ መዝገቦች' : 'Recent Feedings' ?></h3>
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'ቀንና ሰዓት' : 'Date & Time' ?></th>
                <th><?= current_locale() === 'am' ? 'ገንዳ' : 'Pond' ?></th>
                <th><?= current_locale() === 'am' ? 'ዝርዝር መረጃ' : 'Details' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentFeedings as $rf): ?>
                <tr>
                    <td><?= eth_datetime($rf['created_at']) ?></td>
                    <td><strong><?= h($rf['pond_name'] ?? 'ገንዳ #' . $rf['record_id']) ?></strong></td>
                    <td><?= h($rf['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentFeedings)): ?>
                <tr><td colspan="3" class="text-muted" style="text-align:center"><?= current_locale() === 'am' ? 'ምንም የመመገቢያ መዝገብ የለም።' : 'No feeding records logged yet.' ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
