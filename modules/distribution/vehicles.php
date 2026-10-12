<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper', 'distribution_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['register_vehicle'])) {
            $plate = trim($_POST['plate_number'] ?? '');
            $type = trim($_POST['vehicle_type'] ?? '');
            $driverName = trim($_POST['driver_name'] ?? '');
            $driverPhone = trim($_POST['driver_phone'] ?? '');
            $capacity = $_POST['capacity_kg'] !== '' ? (float) $_POST['capacity_kg'] : null;

            vehicle_register($plate, $type, $capacity, $driverName, $driverPhone);
            flash('✓ ተሽከርካሪው ከአሽከርካሪው ስም ጋር በተሳካ ሁኔታ ተመዝግቧል (Vehicle registered successfully).');
        } elseif (isset($_POST['change_status'])) {
            $vId = (int)$_POST['vehicle_id'];
            $st = $_POST['status'];
            db()->prepare("UPDATE dist_vehicles SET status = ? WHERE id = ?")->execute([$st, $vId]);
            flash('የተሽከርካሪው ሁኔታ ተቀይሯል (Vehicle status updated).');
        }
        header('Location: /aleltu-eifms/modules/distribution/vehicles.php');
        exit;
    } catch (Throwable $e) {
        flash('ስህተት፦ ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$vehicles = db()->query("SELECT * FROM dist_vehicles ORDER BY created_at DESC")->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🚚 የተሽከርካሪዎች መመዝገቢያ (Vehicles Registry)</h2>
        <p class="text-muted" style="margin:0">ለምርት ስርጭት እና ሽያጭ የሚያገለግሉ ተሽከርካሪዎችን ከአሽከርካሪዎቻቸው ጋር መመዝገቢያ።</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="/aleltu-eifms/modules/distribution/dispatches.php" class="btn">📦 የምርት ስርጭትና ሽያጭ (Dispatches &amp; Sales) &rarr;</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:20px;margin-bottom:24px">

    <!-- Register Vehicle Form -->
    <div class="card">
        <h3>➕ አዲስ ተሽከርካሪ መዝግብ (Register Vehicle)</h3>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="register_vehicle" value="1">
            
            <div class="form-group">
                <label>የሰሌዳ ቁጥር (Plate Number) *</label>
                <input type="text" name="plate_number" required placeholder="ለምሳሌ፡ 3-45678 አ.አ">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>የተሽከርካሪው ዓይነት (Vehicle Type)</label>
                    <input type="text" name="vehicle_type" placeholder="ለምሳሌ፡ አይሱዙ / ዲና / ፒክአፕ">
                </div>
                <div class="form-group">
                    <label>የመጫን አቅም በኪ.ግ (Capacity KG)</label>
                    <input type="number" step="0.1" name="capacity_kg" placeholder="ለምሳሌ፡ 3500">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>የአሽከርካሪ ስም በጽሑፍ (Driver Name)</label>
                    <input type="text" name="driver_name" placeholder="ለምሳሌ፡ አበበ ከበደ">
                </div>
                <div class="form-group">
                    <label>የአሽከርካሪ ስልክ ቁጥር (Driver Phone)</label>
                    <input type="text" name="driver_phone" placeholder="ለምሳሌ፡ 0911223344">
                </div>
            </div>

            <button class="btn" type="submit">✓ መዝግብ (Register Vehicle)</button>
        </form>
    </div>

    <!-- Vehicles List -->
    <div class="card">
        <h3 style="margin-top:0">📋 የተመዘገቡ ተሽከርካሪዎች ዝርዝር (Vehicles List)</h3>
        <table>
            <thead>
                <tr>
                    <th>ሰሌዳ</th>
                    <th>ዓይነት</th>
                    <th>አሽከርካሪና ስልክ</th>
                    <th>አቅም</th>
                    <th>ሁኔታ (Status)</th>
                    <th>እርምጃ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicles as $v): 
                    $badge = match($v['status']) {
                        'available' => 'badge milking',
                        'in_use' => 'badge dry',
                        default => 'badge sold'
                    };
                ?>
                    <tr>
                        <td><strong><?= h($v['plate_number']) ?></strong></td>
                        <td><?= h($v['vehicle_type'] ?: '—') ?></td>
                        <td>
                            <strong><?= h($v['driver_name'] ?: '—') ?></strong>
                            <?php if ($v['driver_phone']): ?>
                                <br><small class="text-muted">📞 <?= h($v['driver_phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $v['capacity_kg'] ? number_format($v['capacity_kg'], 1).' KG' : '—' ?></td>
                        <td>
                            <?php
                            $stName = match($v['status']) {
                                'available' => 'ዝግጁ / ክፍት',
                                'in_use' => 'በስራ ላይ',
                                'maintenance' => 'ጥገና ላይ',
                                default => ucfirst($v['status'])
                            };
                            ?>
                            <span class="<?= $badge ?>"><?= h($stName) ?></span>
                        </td>
                        <td>
                            <form method="post" style="display:inline"><?= csrf_field() ?>
                                <input type="hidden" name="change_status" value="1">
                                <input type="hidden" name="vehicle_id" value="<?= (int)$v['id'] ?>">
                                <select name="status" onchange="this.form.submit()" style="padding:2px 4px;font-size:11px">
                                    <option value="available" <?= $v['status']==='available'?'selected':'' ?>>ዝግጁ (Available)</option>
                                    <option value="in_use" <?= $v['status']==='in_use'?'selected':'' ?>>በስራ ላይ (In Use)</option>
                                    <option value="maintenance" <?= $v['status']==='maintenance'?'selected':'' ?>>ጥገና ላይ (Maintenance)</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($vehicles)): ?>
                    <tr><td colspan="6" class="text-muted">እስካሁን የተመዘገበ ተሽከርካሪ የለም።</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
