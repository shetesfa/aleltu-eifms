<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'poultry_manager', 'field_worker']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['create_house'])) {
            poultry_house_create(trim($_POST['house_name']), (int) $_POST['capacity']);
            flash(t('poultry.house_created', 'House created.'));
        } elseif (isset($_POST['create_flock'])) {
            poultry_flock_create((int) $_POST['poultry_house_id'], trim($_POST['breed']), $_POST['stocking_date'], (int) $_POST['initial_population']);
            flash(t('poultry.flock_created', 'Flock stocked.'));
        } elseif (isset($_POST['flock_id_mortality'])) {
            poultry_record_mortality((int) $_POST['flock_id_mortality'], $_POST['log_date'], (int) $_POST['count'], $_POST['cause_code']);
            flash('Mortality logged, live population updated (BR-P01).');
        }
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: /aleltu-eifms/modules/poultry/flocks.php');
    exit;
}
require_once __DIR__ . '/../../includes/header.php';

$houses = db()->query("SELECT * FROM poultry_houses ORDER BY name")->fetchAll();

$flocks = db()->query(
    "SELECT f.*, h.name AS house_name FROM poultry_flocks f JOIN poultry_houses h ON h.id = f.poultry_house_id WHERE f.status = 'active' ORDER BY f.stocking_date DESC"
)->fetchAll();

// Live bird count per house, for the "how full is it" hint on the flock form.
$houseLoad = [];
foreach ($flocks as $f) {
    $houseLoad[$f['poultry_house_id']] = ($houseLoad[$f['poultry_house_id']] ?? 0) + flock_live_population((int)$f['id']);
}
?>
<h2 class="mt-0"><?= h(t('poultry.mgmt', 'Poultry — Houses & Flocks')) ?></h2>
<p class="text-muted">BR-P01: Live Population recalculated on every entry.</p>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <!-- House creation -->
    <div class="card">
        <h3><?= h(t('poultry.houses', 'Houses / Coops')) ?></h3>
        <table style="margin-bottom:14px">
            <tr><th><?= h(t('poultry.house', 'House')) ?></th><th><?= h(t('poultry.capacity', 'Capacity')) ?></th><th><?= h(t('poultry.current_birds', 'Current Birds')) ?></th></tr>
            <?php foreach ($houses as $h): ?>
                <tr>
                    <td><?= h($h['name']) ?> <?php if ($h['quarantined']): ?><span class="badge sold"><?= h(t('poultry.quarantined', 'Quarantined')) ?></span><?php endif; ?></td>
                    <td><?= (int) $h['capacity'] ?></td>
                    <td><?= (int) ($houseLoad[$h['id']] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($houses)): ?><tr><td colspan="3" class="text-muted"><?= h(t('poultry.no_houses', 'No houses yet — add one below.')) ?></td></tr><?php endif; ?>
        </table>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="create_house" value="1">
            <div class="form-row">
                <div class="form-group"><label><?= h(t('poultry.house_name', 'House Name')) ?> *</label><input type="text" name="house_name" required placeholder="e.g. House 1"></div>
                <div class="form-group"><label><?= h(t('poultry.capacity', 'Capacity')) ?> *</label><input type="number" name="capacity" min="1" required></div>
            </div>
            <button class="btn secondary" type="submit"><?= h(t('poultry.add_house', 'Add House')) ?></button>
        </form>
    </div>

    <!-- Flock creation -->
    <div class="card">
        <h3><?= h(t('poultry.stock_new_flock', 'Stock a New Flock')) ?></h3>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="create_flock" value="1">
            <div class="form-group"><label><?= h(t('poultry.house', 'House')) ?> *</label>
                <select name="poultry_house_id" required>
                    <?php foreach ($houses as $h): ?><option value="<?= (int) $h['id'] ?>"><?= h($h['name']) ?> (<?= (int) $h['capacity'] ?> <?= h(t('poultry.capacity_short', 'cap')) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label><?= h(t('dairy.breed', 'Breed')) ?> *</label><input type="text" name="breed" required placeholder="e.g. Bovans Brown"></div>
            <div class="form-group"><label><?= h(t('poultry.stocking_date', 'Stocking Date')) ?> *</label><input type="date" name="stocking_date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group"><label><?= h(t('poultry.opening_pop', 'Initial Population')) ?> *</label><input type="number" name="initial_population" min="1" required></div>
            <button class="btn" type="submit"><?= h(t('poultry.add_flock', 'Stock Flock')) ?></button>
        </form>
        <?php if (empty($houses)): ?><p class="text-muted" style="margin-top:8px"><?= h(t('poultry.need_house_first', 'Add a house first before stocking a flock.')) ?></p><?php endif; ?>
    </div>
</div>

<div class="card">
    <h3><?= h(t('poultry.active_flocks', 'Active Flocks')) ?></h3>
    <table>
        <tr>
            <th><?= h(t('poultry.house', 'House')) ?></th>
            <th><?= h(t('dairy.breed', 'Breed')) ?></th>
            <th><?= h(t('poultry.stocking_date', 'Stocked')) ?></th>
            <th><?= h(t('poultry.opening_pop', 'Initial')) ?></th>
            <th><?= h(t('poultry.live_population', 'Live Population')) ?></th>
            <th><?= h(t('common.actions', 'Action')) ?></th>
        </tr>
        <?php foreach ($flocks as $f): ?>
            <tr>
                <td><?= h($f['house_name']) ?></td>
                <td><?= h($f['breed']) ?></td>
                <td><?= h($f['stocking_date']) ?></td>
                <td><?= (int) $f['initial_population'] ?></td>
                <td><strong><?= flock_live_population((int)$f['id']) ?></strong></td>
                <td><a href="/aleltu-eifms/modules/poultry/flock_view.php?id=<?= (int)$f['id'] ?>" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= h(t('common.view_all', 'View / Log Eggs')) ?> &rarr;</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($flocks)): ?><tr><td colspan="6" class="text-muted"><?= h(t('common.no_records', 'No active flocks.')) ?></td></tr><?php endif; ?>
    </table>
</div>

<div class="card" style="max-width:500px">
    <h3><?= h(t('poultry.mortality_log', 'Log Mortality')) ?></h3>
    <form method="post"><?= csrf_field() ?>
        <div class="form-group"><label><?= h(t('poultry.flock_code', 'Flock')) ?> *</label>
            <select name="flock_id_mortality" required>
                <?php foreach ($flocks as $f): ?><option value="<?= (int) $f['id'] ?>"><?= h($f['house_name']) ?> — <?= h($f['breed']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label><?= h(t('common.date', 'Date')) ?> *</label><input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label><?= h(t('common.quantity', 'Quantity (Number of Birds)')) ?> *</label><input type="number" min="1" name="count" required placeholder="e.g. 3"></div>
        <input type="hidden" name="cause_code" value="unknown">
        <button class="btn" type="submit"><?= h(t('common.save', 'Log Mortality')) ?></button>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
