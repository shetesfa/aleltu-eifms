<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user    = current_user();
$userId  = (int) $user['id'];
$roleId  = (int) ($user['role_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['mark_read_id'])) {
            // Per-user read — inserts into junction table, does NOT touch is_read globally
            $notifId = (int) $_POST['mark_read_id'];
            db()->prepare(
                "INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)"
            )->execute([$notifId, $userId]);
        } elseif (isset($_POST['mark_all_read'])) {
            notifications_mark_all_read_for_user($userId, $roleId);
            flash(current_locale() === 'am' ? 'ሁሉም ማሳወቂያዎች ተነቧል።' : 'All notifications marked as read.');
        }
        header('Location: ' . BASE_URL . '/modules/notifications.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../includes/header.php';

$filter = $_GET['filter'] ?? 'all';

// Scoped notifications for this user/role
$isBroadcastRole = in_array($user['role'] ?? '', ['super_admin', 'general_manager'], true);

if ($isBroadcastRole) {
    $scopeClause = "1=1";
    $scopeParams = [];
} else {
    $scopeClause = "(
        n.target_user_id = :uid
        OR n.target_role_id = :rid
        OR (n.target_user_id IS NULL AND n.target_role_id IS NULL AND n.target_department_id IS NULL)
    )";
    $scopeParams = [':uid' => $userId, ':rid' => $roleId];
}

$filterClause = "";
$filterParams = [];
if ($filter === 'unread') {
    $filterClause = " AND n.id NOT IN (SELECT notification_id FROM notification_reads WHERE user_id = {$userId})";
} elseif (in_array($filter, ['low', 'medium', 'high'], true)) {
    $filterClause = " AND priority = :priority";
    $filterParams[':priority'] = $filter;
}

$sql = "SELECT n.*, (SELECT 1 FROM notification_reads nr WHERE nr.notification_id = n.id AND nr.user_id = {$userId} LIMIT 1) AS is_read
        FROM notifications n WHERE {$scopeClause}{$filterClause} ORDER BY n.created_at DESC LIMIT 100";
$params = array_merge($scopeParams, $filterParams);
$stmt = db()->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Per-user unread count (not global is_read)
$unreadSql = "SELECT COUNT(*) FROM notifications n
              WHERE {$scopeClause}
              AND n.id NOT IN (SELECT notification_id FROM notification_reads WHERE user_id = {$userId})";
$cntStmt = db()->prepare($unreadSql);
$cntStmt->execute($scopeParams ?: []);
$unreadCount = (int) $cntStmt->fetchColumn();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🔔 <?= h(t('nav.notifications', 'Notifications Center')) ?>
            <?php if ($unreadCount > 0): ?>
                <span style="background:#b3261e;color:#fff;border-radius:10px;padding:2px 10px;font-size:13px;margin-left:8px"><?= $unreadCount ?> <?= current_locale() === 'am' ? 'ያልተነበቡ' : 'unread' ?></span>
            <?php endif; ?>
        </h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የስራ ሂደቶች ክስተቶች፣ የቁሳቁስ ጥያቄዎች፣ የክምችት እጥረት ማስጠንቀቂያዎች እና የርክክብ ማረጋገጫዎች' : 'Real workflow events, material request updates, shortage alerts, and shipment confirmations.' ?></p>
    </div>
</div>

<div class="card" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="?filter=all" class="btn <?= $filter==='all' ? '' : 'secondary' ?>" style="padding:6px 14px"><?= current_locale() === 'am' ? 'ሁሉም' : 'All' ?></a>
            <a href="?filter=unread" class="btn <?= $filter==='unread' ? '' : 'secondary' ?>" style="padding:6px 14px"><?= current_locale() === 'am' ? 'ያልተነበቡ' : 'Unread' ?></a>
            <a href="?filter=high" class="btn <?= $filter==='high' ? '' : 'secondary' ?>" style="padding:6px 14px">🔴 <?= current_locale() === 'am' ? 'አስቸኳይ / ከፍተኛ' : 'High Priority' ?></a>
            <a href="?filter=medium" class="btn <?= $filter==='medium' ? '' : 'secondary' ?>" style="padding:6px 14px">🟡 <?= current_locale() === 'am' ? 'መካከለኛ' : 'Medium' ?></a>
            <a href="?filter=low" class="btn <?= $filter==='low' ? '' : 'secondary' ?>" style="padding:6px 14px">🟢 <?= current_locale() === 'am' ? 'መደበኛ' : 'Low' ?></a>
        </div>
        <?php if ($unreadCount > 0): ?>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="mark_all_read" value="1">
                <button class="btn secondary" type="submit">✓ <?= current_locale() === 'am' ? 'ሁሉንም እንደተነበበ ምልክት አድርግ' : 'Mark All as Read' ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'ቅድሚያ' : 'Priority' ?></th>
                <th><?= current_locale() === 'am' ? 'የስራ ክፍል' : 'Module' ?></th>
                <th><?= current_locale() === 'am' ? 'የማሳወቂያ መልእክት' : 'Notification Message' ?></th>
                <th><?= current_locale() === 'am' ? 'የተላከበት ሰዓት' : 'Timestamp' ?></th>
                <th><?= current_locale() === 'am' ? 'ተግባር / ማስፈንጠሪያ' : 'Action / Link' ?></th>
                <th><?= current_locale() === 'am' ? 'የንባብ ሁኔታ' : 'Read Status' ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($notifications as $n): ?>
            <tr style="<?= $n['is_read'] ? 'opacity:0.65' : 'background:rgba(46,125,50,0.04)' ?>">
                <td>
                    <span class="badge <?= $n['priority']==='high' ? 'sold' : ($n['priority']==='medium' ? 'dry' : 'active') ?>">
                        <?= h(ucfirst($n['priority'])) ?>
                    </span>
                </td>
                <td><strong><?= h(ucwords(str_replace('_', ' ', $n['module']))) ?></strong></td>
                <td style="font-size:12px;white-space:nowrap">
                    <div><?= eth_datetime($n['created_at']) ?></div>
                    <small class="text-muted" style="font-size:10px"><?= h($n['created_at']) ?></small>
                </td>
                <td>
                    <?php if (!empty($n['link_url'])): ?>
                        <a href="<?= h($n['link_url']) ?>" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= h(t('common.view', 'View Record')) ?> &rarr;</a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$n['is_read']): ?>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="mark_read_id" value="<?= (int) $n['id'] ?>">
                            <button class="btn secondary" type="submit" style="padding:2px 8px;font-size:11px"><?= current_locale() === 'am' ? 'ተነቧል' : 'Mark Read' ?></button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:12px">✓ <?= current_locale() === 'am' ? 'የተነበበ' : 'read' ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($notifications)): ?>
            <tr><td colspan="6" class="text-muted" style="text-align:center;padding:20px"><?= h(t('common.no_records', 'No notifications found for this filter.')) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
