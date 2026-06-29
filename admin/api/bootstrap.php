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

// Verificar token CSRF en cualquier metodo que modifique estado
$_csrfMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (in_array($_csrfMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    if (!joyeria_admin_csrf_verify()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token de seguridad invalido. Recarga la pagina.']);
        exit;
    }
}
unset($_csrfMethod);
