<?php
/** GET /api/v1/auth/me — return current authenticated user profile */
require_once dirname(__DIR__, 3) . '/includes/functions.php';
$user = api_require_auth();
api_json([
    'id'        => (int)$user['id'],
    'name'      => $user['name'],
    'email'     => $user['email'],
    'role'      => $user['role'],
    'role_name' => $user['role_name'] ?? $user['role'],
]);
