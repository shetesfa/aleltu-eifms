<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'feed_production_manager', 'field_worker']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        feed_plot_register($_POST['name'], $_POST['area_hectares'] !== '' ? (float) $_POST['area_hectares'] : null);
        flash(current_locale() === 'am' ? '✓ አዲስ የመኖ ማሳ ተመዝግቧል።' : 'Plot registered.');
        header('Location: /aleltu-eifms/modules/feed_production/plots.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$plots = db()->query(
    "SELECT p.*,
        (SELECT COUNT(*) FROM feed_planting_records r WHERE r.plot_id = p.id AND r.status = 'growing') AS growing_count
     FROM feed_plots p ORDER BY p.name"
)->fetchAll();
?>
<h2 class="mt-0">🌾 <?= current_locale() === 'am' ? 'የመኖ እርሻ ማሳዎችና የሰብል ልማት' : 'Feed Production & Crop Plots' ?></h2>
<p class="text-muted"><?= current_locale() === 'am' ? 'የመኖ እርሻ ማሳዎች ምዝገባ፣ ዘር መዝራት፣ አሰባሰብ እና ወደ ማዕከላዊ መጋዘን ርክክብ መቆጣጠሪያ' : 'Planting, harvest, and internal feed distribution.' ?></p>

<div class="card" style="margin-bottom:16px">
    <a href="/aleltu-eifms/modules/feed_production/distribution.php" class="btn secondary">🚚 <?= current_locale() === 'am' ? 'የመኖ ምርት ርክክብ ወደ መጋዘን' : 'Distribution Requests' ?></a>
</div>

<div class="card" style="max-width:500px">
    <h3><?= current_locale() === 'am' ? 'አዲስ የመኖ እርሻ ማሳ መዝግብ' : 'Register Plot' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የማሳው ስም *' : 'Plot Name *' ?></label><input type="text" name="name" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ማሳ 4 — በቆሎ' : 'e.g. Field Plot A' ?>"></div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'ስፋት (በሄክታር)' : 'Area (hectares)' ?></label><input type="number" step="0.01" name="area_hectares" placeholder="2.5"></div>
        <button class="btn" type="submit"><?= current_locale() === 'am' ? 'ማሳውን መዝግብ' : 'Register' ?></button>
    </form>
</div>

<div class="card">
    <table>
        <tr>
            <th><?= current_locale() === 'am' ? 'የማሳ ስም' : 'Plot' ?></th>
            <th><?= current_locale() === 'am' ? 'ስፋት (በሄክታር)' : 'Area (ha)' ?></th>
            <th><?= current_locale() === 'am' ? 'በማደግ ላይ ያለ ሰብል' : 'Currently Growing' ?></th>
            <th><?= h(t('common.actions', 'Action')) ?></th>
        </tr>
        <?php foreach ($plots as $p): ?>
            <tr>
                <td><strong><?= h($p['name']) ?></strong></td>
                <td><?= $p['area_hectares'] ? number_format($p['area_hectares'], 2) : '—' ?></td>
                <td><span class="badge active"><?= (int) $p['growing_count'] ?> <?= current_locale() === 'am' ? 'ሰብሎች' : 'crop(s)' ?></span></td>
                <td><a href="/aleltu-eifms/modules/feed_production/plot_view.php?id=<?= (int) $p['id'] ?>" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= h(t('common.view', 'View')) ?> &rarr;</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($plots)): ?><tr><td colspan="4" class="text-muted"><?= h(t('common.no_records', 'No plots yet.')) ?></td></tr><?php endif; ?>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
