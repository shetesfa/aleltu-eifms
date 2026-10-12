<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'feed_production_manager', 'field_worker']);

$plotId = (int) ($_GET['id'] ?? 0);
$plot = $plotId > 0 ? db()->query("SELECT * FROM feed_plots WHERE id = {$plotId}")->fetch() : null;
if (!$plot) {
    $firstId = db()->query("SELECT id FROM feed_plots ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstId) {
        $plotId = (int)$firstId;
        $plot = db()->query("SELECT * FROM feed_plots WHERE id = {$plotId}")->fetch();
    } else {
        flash('No plot records found.', 'error');
        header('Location: /aleltu-eifms/modules/feed_production/plots.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['plant'])) {
            feed_plant($plotId, $_POST['crop_type'], $_POST['planting_date'], null);
            flash(current_locale() === 'am' ? '✓ ሰብል በተሳካ ሁኔታ ተዘርቷል።' : 'Crop planted.');
        } elseif (isset($_POST['harvest_planting_id'])) {
            feed_harvest((int) $_POST['harvest_planting_id'], $_POST['harvest_date'], (float) $_POST['yield_kg']);
            flash(current_locale() === 'am' ? '✓ ምርት ተመዝግቧል፤ ወደ መጋዘን ለመላክ ወደ ስርጭት ገጽ ይሂዱ።' : 'Harvest recorded. Go to Distribution to send it to Store.');
        }
        header("Location: /aleltu-eifms/modules/feed_production/plot_view.php?id={$plotId}");
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$growing = db()->prepare("SELECT * FROM feed_planting_records WHERE plot_id = ? AND status = 'growing' ORDER BY planting_date DESC");
$growing->execute([$plotId]);
$growing = $growing->fetchAll();

$history = db()->prepare(
    "SELECT r.*, h.harvest_date, h.yield_kg FROM feed_planting_records r
     LEFT JOIN feed_harvest_records h ON h.planting_record_id = r.id
     WHERE r.plot_id = ? AND r.status = 'harvested' ORDER BY r.planting_date DESC LIMIT 10"
);
$history->execute([$plotId]);
$history = $history->fetchAll();
?>
<h2 class="mt-0">🌾 <?= current_locale() === 'am' ? 'የማሳ ዝርዝር መረጃ' : 'Plot' ?>: <?= h($plot['name']) ?></h2>

<div class="card" style="max-width:500px">
    <h3><?= current_locale() === 'am' ? 'አዲስ ሰብል ዝራ' : 'Plant a Crop' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="plant" value="1">
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የሰብል ዓይነት *' : 'Crop Type *' ?></label><input type="text" name="crop_type" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ በቆሎ፣ አልፋልፋ፣ ሣር' : 'e.g. Maize, Napier grass' ?>"></div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የተዘራበት ቀን *' : 'Planting Date *' ?></label><input type="date" name="planting_date" value="<?= date('Y-m-d') ?>" required></div>
        <button class="btn" type="submit"><?= current_locale() === 'am' ? 'ሰብል ዝራ' : 'Plant Crop' ?></button>
    </form>
</div>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'በማደግ ላይ ያሉ ሰብሎች' : 'Currently Growing' ?></h3>
    <?php if (empty($growing)): ?><p class="text-muted"><?= current_locale() === 'am' ? 'በዚህ ማሳ ላይ በአሁኑ ወቅት የተዘራ ሰብል የለም።' : 'Nothing currently growing on this plot.' ?></p><?php endif; ?>
    <?php foreach ($growing as $g): ?>
        <div style="border:1px solid #dfe5dd;border-radius:6px;padding:12px;margin-bottom:12px">
            <strong><?= h($g['crop_type']) ?></strong> — <?= current_locale() === 'am' ? 'የተዘራው' : 'planted' ?> <?= h(eth_date($g['planting_date'])) ?>
            <?php if ($g['expected_harvest_date']): ?> · <?= current_locale() === 'am' ? 'የሚጠበቅ ምርት አሰባሰብ' : 'expected harvest' ?> <?= h(eth_date($g['expected_harvest_date'])) ?><?php endif; ?>
            <form method="post" style="margin-top:10px;display:flex;gap:8px;align-items:end;flex-wrap:wrap"><?= csrf_field() ?>
                <input type="hidden" name="harvest_planting_id" value="<?= (int) $g['id'] ?>">
                <div class="form-group" style="margin:0"><label><?= current_locale() === 'am' ? 'የተሰበሰበበት ቀን' : 'Harvest Date' ?></label><input type="date" name="harvest_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group" style="margin:0"><label><?= current_locale() === 'am' ? 'የተገኘ ምርት (ኪ.ግ)' : 'Yield (kg)' ?></label><input type="number" step="0.01" name="yield_kg" required style="width:110px"></div>
                <button class="btn secondary" type="submit"><?= current_locale() === 'am' ? 'ምርት መዝግብ' : 'Record Harvest' ?></button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'ያለፉት ምርቶች ታሪክ' : 'Harvest History' ?></h3>
    <table>
        <tr>
            <th><?= current_locale() === 'am' ? 'ሰብል' : 'Crop' ?></th>
            <th><?= current_locale() === 'am' ? 'የተዘራበት' : 'Planted' ?></th>
            <th><?= current_locale() === 'am' ? 'የተሰበሰበበት' : 'Harvested' ?></th>
            <th><?= current_locale() === 'am' ? 'ምርት (ኪ.ግ)' : 'Yield (kg)' ?></th>
        </tr>
        <?php foreach ($history as $h): ?>
            <tr><td><strong><?= h($h['crop_type']) ?></strong></td><td><?= h(eth_date($h['planting_date'])) ?></td><td><?= h($h['harvest_date'] ? eth_date($h['harvest_date']) : '—') ?></td><td><?= $h['yield_kg'] ? number_format($h['yield_kg'], 2) . ' kg' : '—' ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($history)): ?><tr><td colspan="4" class="text-muted"><?= h(t('common.no_records', 'No harvest records yet.')) ?></td></tr><?php endif; ?>
    </table>
</div>
<a href="/aleltu-eifms/modules/feed_production/plots.php">&larr; <?= current_locale() === 'am' ? 'ወደ ማሳዎች ዝርዝር ተመለስ' : 'Back to Plots' ?></a>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
