<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'fish_manager', 'super_admin']);
require_once __DIR__ . '/../../includes/header.php';

// List all active ponds with their stocks
$ponds = db()->query(
    "SELECT p.*, s.id AS stock_id, s.species, s.quantity_stocked
     FROM fish_ponds p
     LEFT JOIN fish_stocks s ON s.pond_id = p.id AND s.status = 'active'
     WHERE p.status != 'empty'
     ORDER BY p.name"
)->fetchAll();

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

            // Record feeding (simplified - you may want to add a fish_feeding_records table)
            log_activity('fish', 'feeding', 'fish_ponds', $pondId, "Fed {$quantityKg}kg of {$feedType} on {$feedDate}");
            flash(current_locale() === 'am' ? '✓ የዓሳ መመገቢያ ተመዝግቧል።' : 'Fish feeding recorded successfully.');
        }
        header("Location: " . BASE_URL . "/modules/fish/feeding.php");
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

// Get recent feeding records (if you have a fish_feeding_records table)
// For now, we'll show activity log
$recentFeedings = db()->query(
    "SELECT al.*, p.name AS pond_name
     FROM activity_log al
     LEFT JOIN fish_ponds p ON p.id = al.subject_id
     WHERE al.module = 'fish' AND al.action = 'feeding'
     ORDER BY al.created_at DESC LIMIT 20"
)->fetchAll();
?>

<h2 class="mt-0">🐟 <?= current_locale() === 'am' ? 'ዕለታዊ የዓሳ መመገቢያ' : 'Daily Fish Feeding' ?></h2>
<p class="text-muted"><?= current_locale() === 'am' ? 'የአሁኑን የዓሳ መመገቢያ መዝግብ እና ታሪክ ይመልከቱ' : 'Record daily fish feeding and view history' ?></p>

<div class="card" style="max-width:600px;margin-bottom:24px">
    <h3><?= current_locale() === 'am' ? 'አዲስ መመገቢያ መዝግብ' : 'Record New Feeding' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="record_feeding" value="1">
        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'የዓሳ ማህደር *' : 'Fish Pond *' ?></label>
            <select name="pond_id" required>
                <option value="">— <?= current_locale() === 'am' ? 'ማህደር ይምረጡ' : 'Select Pond' ?> —</option>
                <?php foreach ($ponds as $p): ?>
                    <option value="<?= (int)$p['id'] ?>">
                        <?= h($p['name']) ?>
                        <?php if ($p['species']): ?>(<?= h($p['species']) ?>: <?= number_format($p['quantity_stocked']) ?>)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'የመኖ ዓይነት *' : 'Feed Type *' ?></label>
            <input type="text" name="feed_type" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ አልፋልፋ፣ ድርቆሽ' : 'e.g. Alfalfa, Pellets' ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'መጠን (ኪ.ግ) *' : 'Quantity (kg) *' ?></label>
                <input type="number" step="0.1" min="0.1" name="quantity_kg" required placeholder="50">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'ቀን *' : 'Date *' ?></label>
                <input type="date" name="feed_date" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <button class="btn" type="submit"><?= current_locale() === 'am' ? 'መመገቢያ መዝግብ' : 'Record Feeding' ?></button>
    </form>
</div>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'የቅርብ ጊዜ መመገቢያ ታሪክ' : 'Recent Feeding History' ?></h3>
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th>
                <th><?= current_locale() === 'am' ? 'ማህደር' : 'Pond' ?></th>
                <th><?= current_locale() === 'am' ? 'የመኖ ዓይነት' : 'Feed Type' ?></th>
                <th><?= current_locale() === 'am' ? 'መጠን' : 'Quantity' ?></th>
                <th><?= current_locale() === 'am' ? 'የመዘገበው' : 'Recorded By' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentFeedings as $f): ?>
                <tr>
                    <td><?= eth_date($f['created_at']) ?></td>
                    <td><?= h($f['pond_name'] ?: '—') ?></td>
                    <td><?= h($f['description'] ?: '—') ?></td>
                    <td><?= h($f['pond_name'] ?: '—') ?></td>
                    <td><?= h($f['user_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentFeedings)): ?>
                <tr><td colspan="5" class="text-muted"><?= current_locale() === 'am' ? 'ምንም መመገቢያ መዝገብ የለም።' : 'No feeding records yet.' ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<a href="<?= BASE_URL ?>/modules/fish/dashboard.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ዓሳ ዳሽቦርድ ተመለስ' : 'Back to Fish Dashboard' ?></a>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
