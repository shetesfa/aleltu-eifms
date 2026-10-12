<?php
require_once __DIR__ . '/../../../includes/functions.php';
$user   = api_require_auth();
$userId = (int)$user['id'];
$roleId = (int)($user['role_id'] ?? 0);
$isBroad = in_array($user['role'] ?? '', ['super_admin','general_manager']);

if ($isBroad) {
    $stmt = db()->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50");
    $stmt->execute();
} else {
    $stmt = db()->prepare("SELECT * FROM notifications WHERE target_user_id=? OR target_role_id=? OR (target_user_id IS NULL AND target_role_id IS NULL AND target_department_id IS NULL) ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$userId,$roleId]);
}
api_json(['notifications'=>$stmt->fetchAll()]);
