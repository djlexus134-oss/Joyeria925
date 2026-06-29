<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!auth_is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Sesion no valida. Inicia sesion nuevamente.'
    ]);
    exit;
}
