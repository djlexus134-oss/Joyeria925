<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/joyeria_json_guard.php';
joyeria_json_guard_begin();
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$sessionKeys = session_status() === PHP_SESSION_ACTIVE ? array_keys($_SESSION) : [];

joyeria_json_clean_buffer();
echo json_encode([
    'success' => true,
    'logged_in' => auth_is_logged_in(),
    'session_active' => session_status() === PHP_SESSION_ACTIVE,
    'session_id_prefix' => substr((string) session_id(), 0, 8),
    'session_keys' => $sessionKeys,
    'has_admin_auth' => session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[JOYERIA_AUTH_SESSION_KEY]),
    'cookie_received' => isset($_COOKIE[session_name()]),
    'cookie_len' => isset($_COOKIE[session_name()]) ? strlen((string) $_COOKIE[session_name()]) : 0,
    'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
    'script' => (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
    'save_path' => session_save_path(),
], JSON_UNESCAPED_UNICODE);
