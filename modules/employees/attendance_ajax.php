<?php
// Inline "click the cell" attendance marking used by the Monthly Timesheet
// Matrix (paper-register style). One click cycles a single day's status for
// one employee, without leaving/reloading the matrix page.
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'hr_officer', 'super_admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

if (!hash_equals(csrf_token(), $_POST['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'csrf', 'message' => 'Session expired, please reload the page.']);
    exit;
}

$empId  = (int)($_POST['employee_id'] ?? 0);
$date   = $_POST['attendance_date'] ?? '';
$status = $_POST['status'] ?? '';

$validStatuses = ['present', 'absent', 'half_day', 'on_leave', ''];
if ($empId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($status, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

try {
    if ($status === '') {
        db()->prepare("DELETE FROM attendance WHERE employee_id = ? AND attendance_date = ?")
            ->execute([$empId, $date]);
        log_activity('employee', 'delete', 'attendance', $empId, "Attendance cleared for {$date}");
    } else {
        attendance_mark($empId, $date, $status);
    }
    echo json_encode(['ok' => true, 'status' => $status]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
