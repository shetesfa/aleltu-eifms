<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'dairy_manager', 'super_admin']);
require_once __DIR__ . '/../../includes/header.php';

$exits = db()->query(
    "SELECT e.*, c.tag_number, c.name, c.breed, c.sex
     FROM dairy_cow_exit_records e
     JOIN dairy_cows c ON c.id = e.cow_id
     ORDER BY e.exit_date DESC"
)->fetchAll();
?>

<h2 class="mt-0">📋 <?= current_locale() === 'am' ? 'የሽያጭና ሞት ታሪክ' : 'Sale & Death History' ?></h2>
<p class="text-muted"><?= current_locale() === 'am' ? 'ሁሉም የተሸጉሉን ወይም የሞቱ ላሞች ዝርዝር' : 'All cows that have been sold or have died' ?></p>

<div class="card">
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'የላም መለያ' : 'Cow Tag' ?></th>
                <th><?= current_locale() === 'am' ? 'ስም' : 'Name' ?></th>
                <th><?= current_locale() === 'am' ? 'ዝርያ' : 'Breed' ?></th>
                <th><?= current_locale() === 'am' ? 'ጾታ' : 'Sex' ?></th>
                <th><?= current_locale() === 'am' ? 'የወጣበት ቀን' : 'Exit Date' ?></th>
                <th><?= current_locale() === 'am' ? 'ምክንያት' : 'Reason' ?></th>
                <th><?= current_locale() === 'am' ? 'አስተያየሚው' : 'Recorded By' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($exits as $e): ?>
                <tr>
                    <td><strong><?= h($e['tag_number']) ?></strong></td>
                    <td><?= h($e['name'] ?: '—') ?></td>
                    <td><?= h($e['breed'] ?: '—') ?></td>
                    <td><?= h($e['sex']) ?></td>
                    <td><?= eth_date($e['exit_date']) ?></td>
                    <td>
                        <span class="badge <?= $e['exit_reason'] === 'sold' ? 'milking' : 'sold' ?>">
                            <?= h(ucfirst($e['exit_reason'])) ?>
                        </span>
                        <?php if ($e['notes']): ?>
                            <br><small class="text-muted"><?= h($e['notes']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= h($e['recorded_by'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($exits)): ?>
                <tr><td colspan="7" class="text-muted"><?= current_locale() === 'am' ? 'ምንም የሽያጭ ወይም ሞት መዝገብ የለም።' : 'No sale or death records found.' ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<a href="/aleltu-eifms/modules/dairy/cows.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ላሞች ዝርዝር ተመለስ' : 'Back to Cow List' ?></a>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
