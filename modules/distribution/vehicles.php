<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper', 'distribution_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['register_shop'])) {
            $shopName = trim($_POST['shop_name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $managerName = trim($_POST['manager_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($shopName)) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የሱቅ ወይም የቅርንጫፍ ስም ማስገባት ግዴታ ነው' : 'Shop / Branch name is required.');
            }
            if (empty($managerName)) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የተረካቢ ኃላፊ ወይም ስራ አስኪያጅ ስም ማስገባት ግዴታ ነው' : 'Branch manager name is required.');
            }

            $stmt = db()->prepare("INSERT INTO dist_shops (shop_name, manager_name, phone, address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$shopName, $managerName, $phone ?: null, $address ?: null]);
            $newId = db()->lastInsertId();

            log_activity('distribution', 'create', 'dist_shops', (int)$newId, "Registered shop: {$shopName} with manager: {$managerName}");
            flash(current_locale() === 'am' ? '✓ የመዳረሻ ሱቅ/ቅርንጫፉ እና የስራ አስኪያጁ መረጃ በተሳካ ሁኔታ ተመዝግቧል!' : 'Shop / Branch and manager registered successfully.');
        } elseif (isset($_POST['update_shop'])) {
            $shopId = (int)$_POST['shop_id'];
            $shopName = trim($_POST['shop_name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $managerName = trim($_POST['manager_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if ($shopId <= 0 || empty($shopName) || empty($managerName)) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ ትክክለኛ መረጃ ያስገቡ' : 'Valid details required.');
            }

            $stmt = db()->prepare("UPDATE dist_shops SET shop_name = ?, manager_name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$shopName, $managerName, $phone ?: null, $address ?: null, $shopId]);

            log_activity('distribution', 'update', 'dist_shops', $shopId, "Updated manager for shop #{$shopId}: {$managerName}");
            flash(current_locale() === 'am' ? '✓ የሱቁ እና የስራ አስኪያጁ መረጃ ተሻሽሏል! የቀድሞ የስርጭት መዝገቦች ሳይነኩ ተጠብቀዋል።' : 'Shop and manager updated successfully. Historical dispatch data preserved.');
        } elseif (isset($_POST['delete_shop'])) {
            require_role(['super_admin', 'general_manager']);
            $shopId = (int)$_POST['shop_id'];
            db()->prepare("DELETE FROM dist_shops WHERE id = ?")->execute([$shopId]);
            flash(current_locale() === 'am' ? '✓ ሱቁ ተሰርዟል' : 'Shop removed.');
        }
        header('Location: ' . BASE_URL . '/modules/distribution/vehicles.php');
        exit;
    } catch (Throwable $e) {
        flash(current_locale() === 'am' ? 'ስህተት፦ ' . $e->getMessage() : 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$shops = db()->query("SELECT * FROM dist_shops ORDER BY id DESC")->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🏢 <?= current_locale() === 'am' ? 'የመዳረሻ ሱቆች እና ቅርንጫፎች መመዝገቢያ' : 'Shops & Outlets Management' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' 
                ? 'ምርት የሚላክላቸውን የድርጅቱ መሸጫ ሱቆች፣ የቅርንጫፍ መዳረሻዎች እና ተረካቢ ስራ አስኪያጆችን መመዝገቢያ እና መቆጣጠሪያ።' 
                : 'Register and manage destination branch shops, retail outlets, and receiving branch managers.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/distribution/dispatches.php" class="btn">📦 <?= current_locale() === 'am' ? 'የምርት ስርጭትና ሽያጭ (Dispatches & Sales)' : 'Dispatches & Sales' ?> &rarr;</a>
    </div>
</div>

<div class="card" style="background:#e8f5e9;border-left:4px solid #2e7d32;color:#111111;margin-bottom:20px;padding:12px 16px">
    <strong style="color:#1b5e20">ℹ️ <?= current_locale() === 'am' ? 'የመረጃ ደህንነት ዋስትና (Historical Data Protection):' : 'Data Integrity Notice:' ?></strong> 
    <?= current_locale() === 'am' 
        ? 'የአንድ ሱቅ ስራ አስኪያጅ ወይም ተረካቢ ኃላፊ ቢቀየር እዚህ ገጽ ላይ ማስተካከል ይችላሉ። ስራ አስኪያጁ ቢቀየርም ከዚህ በፊት ወደ ሱቁ የተላኩ ምርቶች መዝገብ ላይ በወቅቱ የነበረው ተረካቢ ስም ሳይቀየር በታሪክነት ተጠብቆ ይቆያል።' 
        : 'If a branch manager changes, you can update them here anytime. Previous dispatch transaction records preserve the historical receiving manager name.' ?>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:20px;margin-bottom:24px">

    <!-- Register Shop Form -->
    <div class="card">
        <h3>➕ <?= current_locale() === 'am' ? 'አዲስ መዳረሻ ሱቅ / ቅርንጫፍ መዝግብ' : 'Register New Shop / Branch' ?></h3>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="register_shop" value="1">
            
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የሱቅ ወይም የቅርንጫፍ ስም *' : 'Shop / Branch Name *' ?></label>
                <input type="text" name="shop_name" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ቦሌ ፍሬሽ መሸጫ ሱቅ' : 'e.g. Bole Fresh Corner' ?>">
            </div>

            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'መገኛ ቦታ / አድራሻ' : 'Location / Address' ?></label>
                <input type="text" name="address" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ቦሌ መድኃኔዓለም፣ ኤድናሞል አጠገብ' : 'e.g. Bole Medhanialem, Addis Ababa' ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የተረካቢ ስራ አስኪያጅ / ኃላፊ ስም *' : 'Branch Manager / Receiver *' ?></label>
                    <input type="text" name="manager_name" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ አቶ ተስፋዬ በቀለ' : 'e.g. Tesfaye Bekele' ?>">
                </div>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የኃላፊው ስልክ ቁጥር' : 'Manager Phone' ?></label>
                    <input type="tel" name="phone" placeholder="<?= current_locale() === 'am' ? '09xxxxxxxx' : '09xxxxxxxx' ?>">
                </div>
            </div>

            <button class="btn" type="submit">✓ <?= current_locale() === 'am' ? 'ሱቁን መዝግብ (Register Shop)' : 'Register Shop' ?></button>
        </form>
    </div>

    <!-- Shops List -->
    <div class="card">
        <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'የተመዘገቡ ሱቆችና ቅርንጫፎች ዝርዝር' : 'Registered Shops & Outlets' ?></h3>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th><?= current_locale() === 'am' ? 'የሱቅ ስም' : 'Shop Name' ?></th>
                        <th><?= current_locale() === 'am' ? 'የስራ አስኪያጅ ስም' : 'Branch Manager' ?></th>
                        <th><?= current_locale() === 'am' ? 'ስልክ ቁጥር' : 'Phone' ?></th>
                        <th><?= current_locale() === 'am' ? 'አድራሻ' : 'Address' ?></th>
                        <th><?= current_locale() === 'am' ? 'ተግባር' : 'Action' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shops as $s): ?>
                        <tr>
                            <td><strong><?= h($s['shop_name']) ?></strong></td>
                            <td>👤 <?= h($s['manager_name']) ?></td>
                            <td><?= h($s['phone'] ?: '—') ?></td>
                            <td><small class="text-muted"><?= h($s['address'] ?: '—') ?></small></td>
                            <td>
                                <button type="button" class="btn secondary" style="padding:4px 8px;font-size:12px"
                                        onclick="openEditShopModal(<?= (int)$s['id'] ?>, '<?= addslashes(h($s['shop_name'])) ?>', '<?= addslashes(h($s['manager_name'])) ?>', '<?= addslashes(h($s['phone'] ?? '')) ?>', '<?= addslashes(h($s['address'] ?? '')) ?>')">
                                    ✏️ <?= current_locale() === 'am' ? 'አስተካክል' : 'Edit' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($shops)): ?>
                        <tr><td colspan="5" class="text-muted" style="text-align:center;padding:20px"><?= current_locale() === 'am' ? 'ምንም የተመዘገበ ሱቅ የለም' : 'No shops registered yet.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Edit Shop Modal -->
<div id="editShopModal" class="no-print" style="display:none;position:fixed;z-index:2000;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);overflow-y:auto;padding:20px 10px;">
    <div style="background:var(--card);max-width:500px;margin:50px auto;padding:24px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.2);border:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:10px">
            <h3 style="margin:0">✏️ <?= current_locale() === 'am' ? 'የሱቅና የስራ አስኪያጅ መረጃ ማሻሻያ' : 'Edit Shop & Manager' ?></h3>
            <button type="button" onclick="closeEditShopModal()" style="background:none;border:none;font-size:22px;cursor:pointer">&times;</button>
        </div>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="update_shop" value="1">
            <input type="hidden" name="shop_id" id="editShopId" value="">

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700"><?= current_locale() === 'am' ? 'የሱቅ ወይም ቅርንጫፍ ስም *' : 'Shop Name *' ?></label>
                <input type="text" name="shop_name" id="editShopName" required style="width:100%;padding:8px">
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700"><?= current_locale() === 'am' ? 'መገኛ ቦታ / አድራሻ' : 'Address' ?></label>
                <input type="text" name="address" id="editAddress" style="width:100%;padding:8px">
            </div>

            <div class="form-row" style="margin-bottom:14px">
                <div class="form-group" style="flex:1">
                    <label style="font-size:12px;font-weight:700"><?= current_locale() === 'am' ? 'የተረካቢ ስራ አስኪያጅ ስም *' : 'Manager Name *' ?></label>
                    <input type="text" name="manager_name" id="editManagerName" required style="width:100%;padding:8px">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-size:12px;font-weight:700"><?= current_locale() === 'am' ? 'ስልክ ቁጥር' : 'Phone' ?></label>
                    <input type="tel" name="phone" id="editPhone" style="width:100%;padding:8px">
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn secondary" onclick="closeEditShopModal()"><?= current_locale() === 'am' ? 'ይቅር' : 'Cancel' ?></button>
                <button type="submit" class="btn" style="background:#1e5631;border-color:#1e5631"><?= current_locale() === 'am' ? '✓ መረጃውን አዘምን' : 'Update Shop' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditShopModal(id, name, manager, phone, address) {
    document.getElementById('editShopId').value = id;
    document.getElementById('editShopName').value = name;
    document.getElementById('editManagerName').value = manager;
    document.getElementById('editPhone').value = phone;
    document.getElementById('editAddress').value = address;
    document.getElementById('editShopModal').style.display = 'block';
}

function closeEditShopModal() {
    document.getElementById('editShopModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
