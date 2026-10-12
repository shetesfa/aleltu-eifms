<?php
/** GET /api/v1/dashboard — role-scoped KPIs */
require_once __DIR__ . '/../../../includes/functions.php';
$user = api_require_auth();
$role = $user['role'] ?? '';

$data = ['role' => $role, 'generated_at' => date('c')];

// Always include unread notifications count
$userId = (int)$user['id'];
$roleId = (int)($user['role_id'] ?? 0);
$isBroad = in_array($role, ['super_admin','general_manager']);
if ($isBroad) {
    $data['unread_notifications'] = (int)db()->query("SELECT COUNT(*) FROM notifications WHERE is_read=0")->fetchColumn();
} else {
    $s = db()->prepare("SELECT COUNT(*) FROM notifications WHERE is_read=0 AND (target_user_id=? OR target_role_id=? OR (target_user_id IS NULL AND target_role_id IS NULL AND target_department_id IS NULL))");
    $s->execute([$userId,$roleId]); $data['unread_notifications'] = (int)$s->fetchColumn();
}

switch ($role) {
    case 'super_admin':
    case 'general_manager':
        $data['kpis'] = [
            'active_cows'    => (int)db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status='active'")->fetchColumn(),
            'todays_milk_L'  => (float)db()->query("SELECT COALESCE(SUM(e.yield_liters),0) FROM dairy_milk_yield_entries e JOIN dairy_milking_sessions s ON s.id=e.milking_session_id WHERE s.session_date=CURDATE()")->fetchColumn(),
            'active_flocks'  => (int)db()->query("SELECT COUNT(*) FROM poultry_flocks WHERE status='active'")->fetchColumn(),
            'active_ponds'   => (int)db()->query("SELECT COUNT(*) FROM fish_ponds WHERE status='active'")->fetchColumn(),
            'low_stock_items'=> (int)db()->query("SELECT COUNT(*) FROM wh_stock_items WHERE reorder_level>0")->fetchColumn(),
            'pending_po'     => (int)db()->query("SELECT COUNT(*) FROM fin_purchase_orders WHERE status='approved'")->fetchColumn(),
            'active_employees'=> (int)db()->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn(),
            'monthly_expenses'=> (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM fin_transactions WHERE direction='outflow' AND MONTH(transaction_date)=MONTH(CURDATE())")->fetchColumn(),
        ];
        break;
    case 'dairy_manager':
        $data['kpis'] = [
            'active_cows'   => (int)db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status='active'")->fetchColumn(),
            'todays_milk_L' => (float)db()->query("SELECT COALESCE(SUM(e.yield_liters),0) FROM dairy_milk_yield_entries e JOIN dairy_milking_sessions s ON s.id=e.milking_session_id WHERE s.session_date=CURDATE()")->fetchColumn(),
            'pregnant_cows' => (int)db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status='pregnant'")->fetchColumn(),
        ];
        break;
    case 'poultry_manager':
        $data['kpis'] = [
            'active_flocks'    => (int)db()->query("SELECT COUNT(*) FROM poultry_flocks WHERE status='active'")->fetchColumn(),
            'todays_eggs'      => (int)db()->query("SELECT COALESCE(SUM(whole_count),0) FROM poultry_egg_collections WHERE collection_date=CURDATE()")->fetchColumn(),
            'monthly_mortality'=> (int)db()->query("SELECT COALESCE(SUM(count),0) FROM poultry_mortality_logs WHERE MONTH(log_date)=MONTH(CURDATE())")->fetchColumn(),
        ];
        break;
    case 'fish_manager':
        $data['kpis'] = [
            'active_ponds'     => (int)db()->query("SELECT COUNT(*) FROM fish_ponds WHERE status='active'")->fetchColumn(),
            'total_fish'       => (int)db()->query("SELECT COALESCE(SUM(current_count),0) FROM fish_stocks WHERE status='active'")->fetchColumn(),
            'monthly_harvest_kg'=> (float)db()->query("SELECT COALESCE(SUM(quantity_kg),0) FROM fish_harvest_records WHERE MONTH(harvest_date)=MONTH(CURDATE())")->fetchColumn(),
        ];
        break;
    case 'warehouse_keeper':
        $data['kpis'] = [
            'pending_receipts' => (int)db()->query("SELECT COUNT(*) FROM wh_store_receiving WHERE status='pending_receipt'")->fetchColumn(),
            'total_stock_items'=> (int)db()->query("SELECT COUNT(*) FROM wh_stock_items")->fetchColumn(),
        ];
        break;
    case 'finance_officer':
        $data['kpis'] = [
            'monthly_expenses' => (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM fin_transactions WHERE direction='outflow' AND MONTH(transaction_date)=MONTH(CURDATE())")->fetchColumn(),
            'pending_po'       => (int)db()->query("SELECT COUNT(*) FROM fin_purchase_orders WHERE status='approved'")->fetchColumn(),
            'payroll_unpaid'   => (float)db()->query("SELECT COALESCE(SUM(net_pay-total_paid),0) FROM fin_payslips p JOIN fin_payroll_runs r ON r.id=p.payroll_run_id WHERE p.total_paid<p.net_pay AND r.status='finalized'")->fetchColumn(),
        ];
        break;
    default:
        $data['kpis'] = [];
}

api_json($data);
