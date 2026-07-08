<?php

require_once __DIR__ . '/../../includes/joyeria_json_guard.php';

joyeria_json_guard_begin();

// auth.php inicia la sesion (joyeria_session_start).
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_is_logged_in()) {
    joyeria_json_clean_buffer();
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Sesion no valida. Inicia sesion nuevamente.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar token CSRF en cualquier metodo que modifique estado
$_csrfMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (in_array($_csrfMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    if (!joyeria_admin_csrf_verify()) {
        joyeria_json_clean_buffer();
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Token de seguridad invalido. Recarga la pagina.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
unset($_csrfMethod);
