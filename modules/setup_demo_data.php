<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['super_admin']);

$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $results = seed_demo_data();
        flash('Demo data generated successfully.');
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../includes/header.php';

$demoAccounts = [
    ['manager@aleltufarm.com', 'General Manager', 'Company-wide view, Reports & Analytics, approvals'],
    ['dairy@aleltufarm.com', 'Dairy Manager', 'Cow management, milking'],
    ['poultry@aleltufarm.com', 'Poultry Manager', 'Flocks, mortality'],
    ['fish@aleltufarm.com', 'Fish Manager', 'Ponds, feeding, harvest'],
    ['feed@aleltufarm.com', 'Feed Production Manager', 'Plots, planting, harvest'],
    ['vet@aleltufarm.com', 'Veterinary Officer', 'Health, vaccinations, disease/quarantine'],
    ['warehouse@aleltufarm.com', 'Warehouse Keeper', 'Stock receive/issue'],
    ['distribution@aleltufarm.com', 'Distribution Officer', 'Vehicles, dispatch'],
    ['finance@aleltufarm.com', 'Finance Officer', 'Cash flow, expenses, purchasing, payroll'],
    ['hr@aleltufarm.com', 'HR Officer', 'Employees, attendance, leave'],
    ['milker@aleltufarm.com', 'Field Worker', 'Milking entry only — try this one to see role restrictions in action'],
];
?>
<h2 class="mt-0">🛠️ <?= current_locale() === 'am' ? 'የሙከራ መረጃዎች ማመንጫ (Demo Data Setup)' : 'Demo Data Setup' ?></h2>
<p class="text-muted"><?= current_locale() === 'am' ? 'በሁሉም የስራ ክፍሎች ውስጥ ተጨባጭ የሙከራ መረጃዎችን ያመነጫል እንዲሁም ለእያንዳንዱ የስራ ኃላፊነት የሙከራ መለያዎችን ያዘጋጃል።' : 'Populates realistic sample data across every module, and creates one test login per role.' ?></p>

<div class="card" style="max-width:600px">
    <form method="post"><?= csrf_field() ?>
        <button class="btn" type="submit"><?= current_locale() === 'am' ? 'የሙከራ መረጃዎችን አፍልቅ' : 'Generate Demo Data' ?></button>
    </form>
</div>

<?php if ($results): ?>
<div class="card">
    <h3><?= current_locale() === 'am' ? 'የተፈጠሩ መረጃዎች ዝርዝር' : 'What was created' ?></h3>
    <ul>
        <?php foreach ($results as $line): ?><li><?= h($line) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'የሙከራ መግቢያ መለያዎች (Test Logins)' : 'Test Logins' ?></h3>
    <p class="text-muted"><?= current_locale() === 'am' ? 'የሁሉም የሙከራ አካውንቶች የይለፍ ቃል: ' : 'All demo accounts share the same password: ' ?><strong>Demo@2026</strong></p>
    <table>
        <tr>
            <th><?= h(t('users.email', 'Email')) ?></th>
            <th><?= h(t('users.role', 'Role')) ?></th>
            <th><?= current_locale() === 'am' ? 'የስራ ድርሻ / የሚሞከሩ ተግባራት' : 'What to try' ?></th>
        </tr>
        <?php foreach ($demoAccounts as [$email, $role, $tip]): ?>
            <tr><td><code><?= h($email) ?></code></td><td><strong><?= h($role) ?></strong></td><td class="text-muted"><?= h($tip) ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
