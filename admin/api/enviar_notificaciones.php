<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/MailService.php';
require_once __DIR__ . '/../includes/WhatsAppService.php';
require_once __DIR__ . '/../includes/NotificacionService.php';
require_once __DIR__ . '/../includes/cliente_correo.php';

header('Content-Type: application/json; charset=utf-8');

const GRUPOS_VALIDOS = ['clientes', 'empleados', 'usuarios'];

function envio_grupo_valido(?string $grupo): string
{
    $grupo = strtolower(trim((string) $grupo));
    if (!in_array($grupo, GRUPOS_VALIDOS, true)) {
        api_fail('Grupo de destinatarios no valido.', 422);
    }
    return $grupo;
}

/**
 * @return array<int, array{id_usuario:int, nombre:string, correo:string, telefono:string}>
 */
function envio_obtener_destinatarios(PDO $db, string $grupo, array $ids = []): array
{
    if ($grupo === 'clientes') {
        $sql = "SELECT u.id_usuario,
                       TRIM(CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), ''))) AS nombre,
                       u.correo, u.telefono
                FROM clientes c
                INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                WHERE c.activo = 1 AND COALESCE(u.activo, 1) = 1";
    } elseif ($grupo === 'empleados') {
        $sql = "SELECT u.id_usuario,
                       TRIM(CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), ''))) AS nombre,
                       u.correo, u.telefono
                FROM empleados e
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                WHERE e.activo = 1 AND COALESCE(u.activo, 1) = 1";
    } else {
        $sql = "SELECT u.id_usuario,
                       TRIM(CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), ''))) AS nombre,
                       u.correo, u.telefono
                FROM usuarios u
                WHERE COALESCE(u.activo, 1) = 1";
    }
    $sql .= ' ORDER BY u.primer_apellido ASC, u.segundo_apellido ASC, u.nombre ASC';

    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $idsFiltro = [];
    foreach ($ids as $id) {
        $idInt = (int) $id;
        if ($idInt > 0) {
            $idsFiltro[$idInt] = true;
        }
    }

    $out = [];
    foreach ($rows as $row) {
        $idUsuario = (int) ($row['id_usuario'] ?? 0);
        if ($idsFiltro !== [] && !isset($idsFiltro[$idUsuario])) {
            continue;
        }
        $out[] = [
            'id_usuario' => $idUsuario,
            'nombre' => (string) ($row['nombre'] ?? ''),
            'correo' => (string) ($row['correo'] ?? ''),
            'telefono' => (string) ($row['telefono'] ?? ''),
        ];
    }

    return $out;
}

function envio_correo_entregable(string $correo): bool
{
    $correo = trim($correo);
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return joyeria_cliente_correo_es_entregable($correo);
}

function envio_plantilla_correo(string $mensaje): string
{
    $cuerpo = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f6f4f1;font-family:Arial,sans-serif;color:#333;">
<div style="max-width:600px;margin:0 auto;padding:24px;">
    <div style="background:#1a1a1a;color:#f4d03f;padding:18px;text-align:center;border-radius:8px 8px 0 0;">
        <h2 style="margin:0;">Plateria El Angel</h2>
    </div>
    <div style="background:#fff;border:1px solid #e2e2e2;border-top:none;padding:24px;border-radius:0 0 8px 8px;">
        <p style="margin:0;line-height:1.6;">{$cuerpo}</p>
    </div>
    <p style="text-align:center;font-size:11px;color:#a39b8e;margin-top:16px;">&copy; {$year} Plateria El Angel</p>
</div>
</body></html>
HTML;
}

if (!auth_has_permission('NOTIFICACION_CREAR') && !auth_has_permission('NOTIFICACION_LEER')) {
    api_fail('No tienes permiso para enviar notificaciones.', 403);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $sistema = new Sistema();
    $db = $sistema->getDb();

    if ($method === 'GET') {
        $grupo = envio_grupo_valido($_GET['grupo'] ?? null);
        $destinatarios = envio_obtener_destinatarios($db, $grupo);
        api_ok([
            'grupo' => $grupo,
            'data' => $destinatarios,
            'total' => count($destinatarios),
        ]);
    }

    if ($method === 'POST') {
        if (!auth_has_permission('NOTIFICACION_CREAR') && !auth_has_permission('NOTIFICACION_LEER')) {
            api_fail('No tienes permiso para enviar notificaciones.', 403);
        }

        $data = api_json_body();
        $grupo = envio_grupo_valido($data['grupo'] ?? null);

        $mensaje = trim((string) ($data['mensaje'] ?? ''));
        if ($mensaje === '') {
            api_fail('El mensaje es obligatorio.', 422);
        }
        $asunto = trim((string) ($data['asunto'] ?? ''));
        if ($asunto === '') {
            $asunto = 'Notificacion - Plateria El Angel';
        }

        $canales = is_array($data['canales'] ?? null) ? $data['canales'] : [];
        $usarWhatsapp = !empty($canales['whatsapp']);
        $usarCorreo = !empty($canales['correo']);
        $usarInterna = !empty($canales['interna']);
        if (!$usarWhatsapp && !$usarCorreo && !$usarInterna) {
            api_fail('Selecciona al menos un canal de envio.', 422);
        }

        $ids = is_array($data['ids'] ?? null) ? $data['ids'] : [];
        $destinatarios = envio_obtener_destinatarios($db, $grupo, $ids);
        if ($destinatarios === []) {
            api_fail('No hay destinatarios para enviar.', 422);
        }

        $service = new NotificacionService();

        $resumen = [
            'destinatarios' => count($destinatarios),
            'whatsapp' => ['enviados' => 0, 'omitidos' => 0, 'errores' => 0],
            'correo' => ['enviados' => 0, 'omitidos' => 0, 'errores' => 0],
            'interna' => ['enviados' => 0, 'omitidos' => 0, 'errores' => 0],
        ];
        $errores = [];

        $esCliente = ($grupo === 'clientes');

        foreach ($destinatarios as $dest) {
            if ($usarWhatsapp) {
                if (trim($dest['telefono']) === '') {
                    $resumen['whatsapp']['omitidos']++;
                } else {
                    try {
                        $r = WhatsAppService::enviarNotificacionGenerica($dest['telefono'], $mensaje);
                        if (!empty($r['success'])) {
                            $resumen['whatsapp']['enviados']++;
                        } else {
                            $resumen['whatsapp']['errores']++;
                            if (isset($r['message']) && count($errores) < 5) {
                                $errores[] = 'WhatsApp ' . $dest['nombre'] . ': ' . $r['message'];
                            }
                        }
                    } catch (Throwable $e) {
                        $resumen['whatsapp']['errores']++;
                        error_log('envio whatsapp: ' . $e->getMessage());
                    }
                }
            }

            if ($usarCorreo) {
                if (!envio_correo_entregable($dest['correo'])) {
                    $resumen['correo']['omitidos']++;
                } else {
                    try {
                        $r = MailService::enviarNotificacion(
                            $dest['correo'],
                            $asunto,
                            envio_plantilla_correo($mensaje),
                            $mensaje
                        );
                        if (!empty($r['success'])) {
                            $resumen['correo']['enviados']++;
                        } else {
                            $resumen['correo']['errores']++;
                            if (isset($r['message']) && count($errores) < 5) {
                                $errores[] = 'Correo ' . $dest['nombre'] . ': ' . $r['message'];
                            }
                        }
                    } catch (Throwable $e) {
                        $resumen['correo']['errores']++;
                        error_log('envio correo: ' . $e->getMessage());
                    }
                }
            }

            if ($usarInterna) {
                // La campana interna solo aplica a usuarios del panel (empleados/usuarios).
                if ($esCliente) {
                    $resumen['interna']['omitidos']++;
                } else {
                    $ok = $service->crearNotificacionInterna($dest['id_usuario'], $mensaje, 'aviso');
                    if ($ok) {
                        $resumen['interna']['enviados']++;
                    } else {
                        $resumen['interna']['errores']++;
                    }
                }
            }
        }

        $payload = [
            'message' => 'Envio procesado.',
            'resumen' => $resumen,
        ];
        if ($errores !== []) {
            $payload['errores'] = $errores;
        }
        if ($usarInterna && $esCliente) {
            $payload['aviso'] = 'La notificacion interna (campana) no aplica para clientes; se omitio ese canal.';
        }

        api_ok($payload);
    }

    api_fail('Metodo no soportado.', 405);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
