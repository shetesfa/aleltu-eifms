<?php
require_once __DIR__ . '/../../../includes/functions.php';
$user = api_require_auth();
if (!has_permission('employees','view')) api_error('Forbidden',403);
$stmt = db()->query("SELECT id,employee_number,full_name,department,position,status,base_salary FROM employees WHERE status='active' ORDER BY full_name");
api_json(['employees'=>$stmt->fetchAll()]);
