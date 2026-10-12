<?php
/**
 * POST /api/v1/employees/attendance
 * Body: { "employee_id": 1, "attendance_date": "2024-01-15", "status": "present", "notes": "" }
 */
require_once __DIR__ . '/../../../includes/functions.php';
$user = api_require_auth();
if (!has_permission('attendance','create')) api_error('Forbidden',403);

$body   = json_decode(file_get_contents('php://input'),true) ?? [];
$empId  = (int)($body['employee_id'] ?? 0);
$date   = $body['attendance_date'] ?? date('Y-m-d');
$status = $body['status'] ?? 'present';
$notes  = trim($body['notes'] ?? '');

if (!$empId) api_error('employee_id required');
if (!in_array($status,['present','absent','half_day','leave'])) api_error('Invalid status');

$emp = db()->query("SELECT id FROM employees WHERE id={$empId} AND status='active'")->fetch();
if (!$emp) api_error('Employee not found or inactive',404);

// Check duplicate
$dup = db()->prepare("SELECT id FROM attendance WHERE employee_id=? AND attendance_date=?");
$dup->execute([$empId,$date]);
if ($dup->fetch()) api_error("Attendance already recorded for employee #{$empId} on {$date}",409);

$ins = db()->prepare("INSERT INTO attendance (employee_id,attendance_date,status,notes,recorded_by) VALUES (?,?,?,?,?)");
$ins->execute([$empId,$date,$status,$notes,$user['id']]);
api_json(['message'=>'Attendance recorded','id'=>(int)db()->lastInsertId()],201);
