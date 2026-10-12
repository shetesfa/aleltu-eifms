<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'fish_manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        pond_register($_POST['name'], $_POST['area_sq_m'] !== '' ? (float) $_POST['area_sq_m'] : null);
        flash(current_locale() === 'am' ? '✓ አዲስ ኩሬ በተሳካ ሁኔታ ተመዝግቧል።' : 'Pond registered.');
        header('Location: /aleltu-eifms/modules/fish/ponds.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$ponds = db()->query(
    "SELECT p.*,
        (SELECT COUNT(*) FROM fish_stocks s WHERE s.pond_id = p.id AND s.status = 'active') AS active_stocks
     FROM fish_ponds p ORDER BY p.name"
)->fetchAll();
?>
<h2 class="mt-0">🐟 <?= current_locale() === 'am' ? 'የዓሣ እርባታ — የኩሬዎች ዝርዝርና ቁጥጥር' : 'Fish Farm — Ponds' ?></h2>

<div class="card" style="max-width:500px">
    <h3><?= h(t('fish.new_pond', 'Register Pond')) ?></h3>
    <form method="post"><?= csrf_field() ?>
        <div class="form-group"><label><?= h(t('common.name', 'Pond Name')) ?> *</label><input type="text" name="name" required></div>
        <div class="form-group"><label><?= h(t('fish.area', 'Area (sq. meters)')) ?></label><input type="number" step="0.01" name="area_sq_m"></div>
        <button class="btn" type="submit"><?= h(t('common.save', 'Register')) ?></button>
    </form>
</div>

<div class="card">
    <table>
        <tr>
            <th><?= h(t('common.name', 'Name')) ?></th>
            <th><?= h(t('fish.area', 'Area (m²)')) ?></th>
            <th><?= h(t('common.status', 'Status')) ?></th>
            <th><?= h(t('common.actions', 'Actions')) ?></th>
        </tr>
        <?php foreach ($ponds as $p): ?>
            <tr>
                <td><?= h($p['name']) ?></td>
                <td><?= $p['area_sq_m'] ? number_format($p['area_sq_m'], 1) : '—' ?></td>
                <td><span class="badge <?= $p['status']==='stocked'?'active':($p['status']==='empty'?'dry':'pregnant') ?>"><?= h($p['status']) ?></span></td>
                <td><a href="/aleltu-eifms/modules/fish/pond_view.php?id=<?= (int) $p['id'] ?>"><?= h(t('common.view_all', 'View')) ?></a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($ponds)): ?><tr><td colspan="4" class="text-muted"><?= h(t('common.no_records', 'No ponds yet.')) ?></td></tr><?php endif; ?>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
