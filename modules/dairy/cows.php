<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'dairy_manager']);
require_once __DIR__ . '/../../includes/header.php';

$search = trim($_GET['search'] ?? '');
$sql = "SELECT c.*, g.name AS group_name FROM dairy_cows c LEFT JOIN dairy_cow_groups g ON g.id = c.cow_group_id";
$params = [];
if ($search !== '') {
    $sql .= " WHERE c.tag_number LIKE ? OR c.name LIKE ?";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$sql .= " ORDER BY c.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$cows = $stmt->fetchAll();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🐄 <?= h(t('dairy.mgmt', 'Cow Management')) ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'እያንዳንዱ ላም ልዩ እና ቋሚ የሆነ የመለያ ቁጥር (Tag Number) እና ፎቶ አለው።' : 'Every cow has a unique, permanent tag number and photo record.' ?></p>
    </div>
    <a href="/aleltu-eifms/modules/dairy/cow_add.php" class="btn">+ <?= h(t('dairy.register_new', 'Register Cow')) ?></a>
</div>

<div class="card">
    <form method="get" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <input type="text" name="search" placeholder="<?= current_locale() === 'am' ? 'በመለያ ቁጥር ወይም ስም ፈልግ...' : 'Search by tag number or name...' ?>" value="<?= h($search) ?>" style="flex:1;min-width:200px;padding:8px;border:1px solid #dfe5dd;border-radius:6px">
        <button class="btn secondary" type="submit"><?= h(t('common.search', 'Search')) ?></button>
        <?php if ($search !== ''): ?><a href="/aleltu-eifms/modules/dairy/cows.php" class="btn secondary"><?= current_locale() === 'am' ? 'አጽዳ' : 'Clear' ?></a><?php endif; ?>
    </form>

    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'ፎቶ' : 'Photo' ?></th>
                <th><?= h(t('dairy.tag_num', 'Tag #')) ?></th>
                <th><?= h(t('common.name', 'Name')) ?></th>
                <th><?= h(t('dairy.sex', 'Sex')) ?></th>
                <th><?= h(t('dairy.breed', 'Breed')) ?></th>
                <th><?= h(t('dairy.group', 'Group')) ?></th>
                <th><?= h(t('common.status', 'Status')) ?></th>
                <th><?= h(t('common.actions', 'Actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cows as $cow): 
                $stText = t('dairy.lifecycle_' . $cow['lifecycle_status'], ucfirst($cow['lifecycle_status']));
            ?>
                <tr>
                    <td style="width:50px;text-align:center">
                        <?php if (!empty($cow['photo_path'])): ?>
                            <img src="<?= h($cow['photo_path']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:50%;border:1px solid #ccc" alt="Cow Photo">
                        <?php else: ?>
                            <span style="font-size:20px">🐄</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= h($cow['tag_number']) ?></strong></td>
                    <td><?= h($cow['name'] ?: '—') ?></td>
                    <td><?= h($cow['sex'] === 'female' ? t('dairy.female', 'Female') : t('dairy.male', 'Male')) ?></td>
                    <td><?= h($cow['breed'] ?: '—') ?></td>
                    <td><?= h($cow['group_name'] ?: '—') ?></td>
                    <td><span class="badge <?= h($cow['lifecycle_status']) ?>"><?= h($stText) ?></span></td>
                    <td><a href="/aleltu-eifms/modules/dairy/cow_view.php?id=<?= (int) $cow['id'] ?>" class="btn secondary" style="padding:2px 8px;font-size:12px"><?= h(t('common.view', 'View Profile')) ?> →</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($cows)): ?>
                <tr><td colspan="8" class="text-muted"><?= h(t('common.no_records', 'No cows found.')) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
