<?php
/** GET /api/v1/auth/me — return current authenticated user profile */
require_once __DIR__ . '/../../../includes/functions.php';
$user = api_require_auth();
api_json(['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']]);
