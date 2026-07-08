<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/joyeria_json_guard.php';
joyeria_json_guard_begin();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/NotificacionService.php';

header('Content-Type: application/json; charset=utf-8');

function joyeria_notif_out(array $data): void
{
    joyeria_json_clean_buffer();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!auth_is_logged_in()) {
    http_response_code(401);
    joyeria_notif_out(['ok' => false, 'error' => 'No autenticado']);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST' && !joyeria_admin_csrf_verify()) {
    http_response_code(403);
    joyeria_notif_out(['ok' => false, 'error' => 'Token de seguridad invalido. Recarga la pagina.']);
}

$user = auth_user();
$idUsuario = (int) ($user['id_usuario'] ?? 0);

$idTiendaUsuario = 0;
try {
    $sistema = new Sistema();
    $db = $sistema->getDb();
    $stmt = $db->prepare('SELECT id_tienda_FK FROM empleados WHERE id_usuario_FK = :id AND activo = 1 LIMIT 1');
    $stmt->bindValue(':id', $idUsuario, PDO::PARAM_INT);
    $stmt->execute();
    $idTiendaUsuario = (int) ($stmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    error_log('notificaciones_panel_api lookup tienda: ' . $e->getMessage());
    // Si el usuario admin no esta en empleados (caso ADMINISTRADOR puro),
    // o la columna no esta, seguimos sin filtrar por tienda.
    $idTiendaUsuario = 0;
}

$body = json_decode(joyeria_request_raw_body() ?: '[]', true);
if (!is_array($body)) $body = [];
$action = (string) ($body['action'] ?? $_GET['action'] ?? 'consultar');

$service = new NotificacionService();

try {
    if ($action === 'consultar') {
        $items = $service->listarParaUsuario($idUsuario, $idTiendaUsuario, 15);
        $noLeidas = $service->contarNoLeidas($idUsuario, $idTiendaUsuario);
        joyeria_notif_out(['ok' => true, 'items' => $items, 'no_leidas' => $noLeidas]);
    }
    if ($action === 'marcar_todas') {
        $service->marcarTodasLeidas($idUsuario, $idTiendaUsuario);
        joyeria_notif_out(['ok' => true]);
    }
    if ($action === 'marcar_una') {
        $idN = (int) ($body['id_notificacion'] ?? 0);
        if ($idN > 0) {
            $service->marcarUnaLeida($idN);
        }
        joyeria_notif_out(['ok' => true]);
    }
    joyeria_notif_out(['ok' => false, 'error' => 'Accion no soportada']);
} catch (Throwable $e) {
    $detalle = $e->getMessage();
    error_log('notificaciones_panel_api: ' . $detalle . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    // Como el endpoint requiere sesion admin, podemos devolver el error real
    // para diagnostico sin filtrar a usuarios anonimos.
    joyeria_notif_out([
        'ok' => false,
        'error' => 'Error interno: ' . $detalle,
    ]);
}
