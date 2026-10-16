<?php
/** Super Admin - analytics settings: shop-dispatch treatment and item -> business unit mapping. */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/analytics/analytics_ui.php';

analytics_guard();
$pdo = db();
$loc = current_locale() === 'am';
$missing = an_schema_status($pdo);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $uid = (int)(current_user()['id'] ?? 0);
    if (!$missing) {
        $treat = ($_POST['shop_treatment'] ?? '') === 'external' ? 'external' : 'internal';
        $old = an_shop_treatment($pdo);
        if ($old !== $treat) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'analytics_shop_dispatch_treatment'")->execute([$treat]);
            an_audit('setting_change', 'settings', 0, "analytics_shop_dispatch_treatment: $old -> $treat");
        }
        $map = $_POST['map'] ?? [];
        if (is_array($map)) {
            $ins = $pdo->prepare("INSERT INTO rpt_item_unit_map (stock_item_id, business_unit, source, updated_by) VALUES (?, ?, 'manual', ?)
                                  ON DUPLICATE KEY UPDATE business_unit = VALUES(business_unit), source = 'manual', updated_by = VALUES(updated_by)");
            $del = $pdo->prepare("DELETE FROM rpt_item_unit_map WHERE stock_item_id = ?");
            $changed = 0;
            foreach ($map as $itemId => $unit) {
                $itemId = (int)$itemId;
                if ($itemId <= 0) { continue; }
                if ($unit === '') { $del->execute([$itemId]); $changed++; }
                elseif (in_array($unit, AN_UNITS, true)) { $ins->execute([$itemId, $unit, $uid ?: null]); $changed++; }
            }
            an_audit('mapping_save', 'rpt_item_unit_map', 0, "rows=$changed");
        }
        $msg = $loc ? 'ተቀምጧል።' : 'Saved.';
    }
}

$missing = an_schema_status($pdo);
$treat = $missing ? 'internal' : an_shop_treatment($pdo);
$items = $missing ? [] : $pdo->query(
    "SELECT i.id, i.sku, i.name, i.unit, i.product_class, m.business_unit, m.source
       FROM wh_stock_items i LEFT JOIN rpt_item_unit_map m ON m.stock_item_id = i.id
      ORDER BY i.name"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="/aleltu-eifms/assets/css/analytics.css">
<div class="an-wrap">
    <div class="an-head"><h1>⚙ <?= h(an_t('title')) ?> — <?= $loc ? 'መቼቶች' : 'Settings' ?></h1>
        <a class="btn secondary" href="/aleltu-eifms/modules/admin/analytics.php"><?= h(an_t('back')) ?></a></div>
    <?php an_render_schema_notice($missing); ?>
    <?php if ($msg): ?><div class="an-note an-n-info"><?= h($msg) ?></div><?php endif; ?>
    <?php if (!$missing): ?>
    <form method="post">
        <?= csrf_field() ?>
        <h2><?= $loc ? 'ወደ ድርጅቱ ሱቆች የሚላክ ዕቃ' : 'Dispatches to company shops' ?></h2>
        <label><input type="radio" name="shop_treatment" value="internal" <?= $treat === 'internal' ? 'checked' : '' ?>>
            <?= $loc ? 'የውስጥ ዝውውር — ገቢ አይደለም (የሚመከር)' : 'Internal transfer - NOT income (recommended)' ?></label><br>
        <label><input type="radio" name="shop_treatment" value="external" <?= $treat === 'external' ? 'checked' : '' ?>>
            <?= $loc ? 'ሱቆቹ ራሳቸውን የቻሉ ደንበኞች ናቸው — ገቢ ነው' : 'Shops are independent customers - counts as income' ?></label>
        <p class="an-hint"><?= $loc ? 'ሱቆቹ የድርጅቱ አካል ከሆኑ ሁለተኛውን አይምረጡ፤ አለበለዚያ ገቢ ሁለት ጊዜ ይቆጠራል (ወደ ሱቅ ሲላክና ሱቁ ሲሸጥ)።' : 'If the shops belong to the company, do not pick the second option: income would be counted twice (once when sent to the shop, again when the shop sells).' ?></p>

        <h2><?= $loc ? 'ዕቃ → የሥራ ክፍል (ለሽያጭ ገቢ)' : 'Item → business unit (for sales income)' ?></h2>
        <div class="an-scroll"><table class="an-table">
            <thead><tr><th>SKU</th><th><?= $loc ? 'ስም' : 'Item' ?></th><th><?= h(an_t('unit')) ?></th><th>Source</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= h($it['sku']) ?></td><td><?= h($it['name']) ?> <small>(<?= h($it['unit']) ?>)</small></td>
                    <td><select name="map[<?= (int)$it['id'] ?>]">
                        <option value="">— <?= h(an_t('unattributed')) ?> —</option>
                        <?php foreach (AN_UNITS as $u): ?>
                            <option value="<?= h($u) ?>" <?= $it['business_unit'] === $u ? 'selected' : '' ?>><?= h(an_t($u)) ?></option>
                        <?php endforeach; ?>
                    </select></td>
                    <td><small><?= h((string)$it['source']) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <p><button class="btn" type="submit"><?= $loc ? 'አስቀምጥ' : 'Save' ?></button></p>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
