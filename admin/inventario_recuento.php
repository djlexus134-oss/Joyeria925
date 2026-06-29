<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/models/inventario_recuento.php';
require_once __DIR__ . '/models/piezas_stock.php';

$accionPreCsrf = isset($_GET['accion']) ? mb_strtolower(trim((string) $_GET['accion'])) : 'leer';
$isAjaxRecuentoPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
    && $accionPreCsrf === 'actualizar'
    && !empty($_POST['ajax_recuento']);

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    if (!joyeria_admin_csrf_verify()) {
        if ($isAjaxRecuentoPost) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(
                ['ok' => false, 'error' => 'Token de seguridad invalido. Recarga la pagina.'],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }
        joyeria_admin_csrf_send_denied_response();
    }
}

const JOYERIA_SESSION_RECUENTO = 'joyeria_inventario_recuento';

$model = new InventarioRecuento();
$stockModel = new PiezasStock();

$accion = isset($_GET['accion']) ? mb_strtolower(trim((string) $_GET['accion'])) : 'leer';

$guardRecuento = auth_current_access_guard();
if (!$guardRecuento['allowed']) {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
        && $accion === 'actualizar'
        && !empty($_POST['ajax_recuento'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => (string) $guardRecuento['message']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    auth_set_flash((string) $guardRecuento['message'], 'error');
    if (!empty($guardRecuento['redirect'])) {
        header('Location: ' . $guardRecuento['redirect']);
        exit;
    }
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

$mensaje = null;
$error = null;

$idUsuario = 0;
if (isset($_SESSION[JOYERIA_AUTH_SESSION_KEY]['id_usuario'])) {
    $idUsuario = (int) $_SESSION[JOYERIA_AUTH_SESSION_KEY]['id_usuario'];
}

$idEmpleado = $model->obtenerIdEmpleadoPorUsuario($idUsuario);
$tiendasActivas = [];
$familiasActivas = [];
try {
    $tiendasActivas = $model->listarTiendasActivas();
    $familiasActivas = $model->listarFamiliasActivas();
} catch (Throwable $e) {
    $error = 'No se pudieron cargar las tiendas o familias.';
}

$ctx = isset($_SESSION[JOYERIA_SESSION_RECUENTO]) && is_array($_SESSION[JOYERIA_SESSION_RECUENTO])
    ? $_SESSION[JOYERIA_SESSION_RECUENTO]
    : null;

$idAuditoriaGet = isset($_GET['id_auditoria']) ? (int) $_GET['id_auditoria'] : 0;

/**
 * @param array<string, mixed>|null $ctx
 */
function joyeria_recuento_ctx_auditoria(?array $ctx): int
{
    if ($ctx === null || !isset($ctx['id_auditoria'])) {
        return 0;
    }
    return (int) $ctx['id_auditoria'];
}

/**
 * @param array<string, mixed>|null $ctx
 */
function joyeria_recuento_ctx_paso(?array $ctx): string
{
    if ($ctx === null || !isset($ctx['paso'])) {
        return '';
    }
    return (string) $ctx['paso'];
}

/**
 * Fecha Y-m-d para filtros GET o null si vacía / inválida.
 */
function joyeria_recuento_parse_fecha_filtro(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $t = trim($raw);
    if ($t === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $t);
    if ($dt === false || $dt->format('Y-m-d') !== $t) {
        return null;
    }
    return $t;
}

/**
 * Normaliza lectura de pistola (trim + quita caracteres de control).
 */
function joyeria_recuento_normalizar_codigo_escaneo(string $raw): string
{
    $t = trim($raw);
    if ($t === '') {
        return '';
    }
    return preg_replace('/[\x00-\x1F\x7F]+/u', '', $t) ?? $t;
}

/**
 * @return array{ok: bool, error?: string, mensaje?: string, contados?: int, esperados?: int, codigo_auxiliar?: string, desc_pieza?: string}
 */
function joyeria_recuento_intentar_agregar_codigo(
    InventarioRecuento $model,
    ?array $ctx,
    ?int $idEmpleado,
    int $idUsuario,
    string $codigoRaw
): array {
    if ($idEmpleado === null) {
        return ['ok' => false, 'error' => 'Tu usuario debe estar vinculado a un empleado activo.'];
    }
    $idAud = joyeria_recuento_ctx_auditoria($ctx);
    if ($idAud <= 0 || joyeria_recuento_ctx_paso($ctx) !== 'captura') {
        return ['ok' => false, 'error' => 'No hay un recuento en captura. Inicia uno nuevo.'];
    }
    $codigo = joyeria_recuento_normalizar_codigo_escaneo($codigoRaw);
    if ($codigo === '') {
        return ['ok' => false, 'error' => 'Ingresa o escanea un código.'];
    }
    $cab = $model->obtenerCabecera($idAud);
    if ($cab === null || ($cab['estado'] ?? '') !== 'abierta') {
        return ['ok' => false, 'error' => 'El recuento ya no está abierto.'];
    }
    $meta = $model->parsearMetaCabecera((string) ($cab['observaciones'] ?? ''));
    if ($meta === null || $meta['id_usuario'] !== $idUsuario || (int) ($cab['id_empleado_FK'] ?? 0) !== $idEmpleado) {
        return ['ok' => false, 'error' => 'No tienes permiso para modificar esta auditoría.'];
    }
    $idTienda = $meta['id_tienda'];
    $idFamilia = (int) ($meta['id_familia'] ?? 0);
    $row = $model->resolverCodigoEnTienda($codigo, $idTienda, $idFamilia);
    if ($row === null) {
        $msgErr = 'Código no encontrado como stock disponible en la tienda de este recuento.';
        if ($idFamilia > 0) {
            $msgErr .= ' Verifica que la pieza pertenezca a la familia seleccionada.';
        }
        return ['ok' => false, 'error' => $msgErr];
    }
    $idStock = (int) $row['id_pieza_stock'];
    try {
        $model->agregarEscaneo($idAud, $idStock, $codigo);
    } catch (RuntimeException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'No se pudo registrar el código: ' . $e->getMessage()];
    }
    $esperados = $model->contarEsperadosPorTienda($idTienda, $idFamilia);
    $contados = $model->contarDetalle($idAud);
    $desc = (string) ($row['desc_pieza'] ?? '');
    $aux = (string) ($row['codigo_auxiliar'] ?? '');
    return [
        'ok' => true,
        'mensaje' => 'Pieza agregada: ' . $desc . ' (' . $aux . ')',
        'contados' => $contados,
        'esperados' => $esperados,
        'desc_pieza' => $desc,
        'codigo_auxiliar' => $aux,
    ];
}

switch ($accion) {
    case 'crear':
        if ($idEmpleado === null) {
            $error = 'Tu usuario debe estar vinculado a un empleado activo para realizar recuentos.';
            break;
        }
        $idTiendaPost = isset($_POST['id_tienda']) ? (int) $_POST['id_tienda'] : 0;
        $idFamiliaPost = isset($_POST['id_familia']) ? (int) $_POST['id_familia'] : 0;
        if ($idTiendaPost <= 0) {
            $error = 'Selecciona una tienda válida.';
            break;
        }
        if ($idFamiliaPost < 0) {
            $idFamiliaPost = 0;
        }
        $existeTienda = false;
        foreach ($tiendasActivas as $t) {
            if ((int) ($t['id_tienda'] ?? 0) === $idTiendaPost) {
                $existeTienda = true;
                break;
            }
        }
        if (!$existeTienda) {
            $error = 'La tienda seleccionada no es válida.';
            break;
        }
        if ($idFamiliaPost > 0 && !$model->existeFamiliaActiva($idFamiliaPost)) {
            $error = 'La familia seleccionada no es válida.';
            break;
        }

        $abierta = $model->buscarAuditoriaAbiertaPorEmpleado($idEmpleado);
        if ($abierta !== null) {
            $meta = $model->parsearMetaCabecera((string) ($abierta['observaciones'] ?? ''));
            if ($meta === null) {
                $error = 'Existe una auditoría abierta sin el formato esperado. Contacta a sistemas o cancela desde la base de datos.';
                break;
            }
            if ($meta['id_usuario'] !== $idUsuario) {
                $error = 'Hay una auditoría abierta asociada a otro usuario en tu mismo empleado. No se puede iniciar otra.';
                break;
            }
            if ($meta['id_tienda'] !== $idTiendaPost) {
                $error = 'Ya tienes un recuento abierto en otra tienda. Cancélalo o finalízalo antes de iniciar uno nuevo.';
                break;
            }
            $famAbierta = (int) ($meta['id_familia'] ?? 0);
            if ($famAbierta !== $idFamiliaPost) {
                $error = 'Ya tienes un recuento abierto con otro filtro de familia. Cancélalo o finalízalo antes de iniciar uno nuevo.';
                break;
            }
            $idAud = (int) $abierta['id_auditoria'];
            $_SESSION[JOYERIA_SESSION_RECUENTO] = [
                'id_auditoria' => $idAud,
                'paso' => 'captura',
            ];
            $mensaje = 'Se reanudó el recuento abierto para esta tienda.';
        } else {
            $idAud = $model->crearCabecera($idEmpleado, $idTiendaPost, $idUsuario, $idFamiliaPost);
            $_SESSION[JOYERIA_SESSION_RECUENTO] = [
                'id_auditoria' => $idAud,
                'paso' => 'captura',
            ];
            if ($idFamiliaPost > 0) {
                $mensaje = 'Recuento iniciado por familia. Captura los códigos de las piezas encontradas.';
            } else {
                $mensaje = 'Recuento iniciado. Captura los códigos de las piezas encontradas.';
            }
        }
        $ctx = $_SESSION[JOYERIA_SESSION_RECUENTO];
        break;

    case 'cancelar':
        if ($idEmpleado === null) {
            $error = 'No se puede cancelar: usuario sin empleado activo.';
            break;
        }
        $idAud = joyeria_recuento_ctx_auditoria($ctx);
        if ($idAud <= 0) {
            $error = 'No hay un recuento activo en sesión.';
            break;
        }
        $cab = $model->obtenerCabecera($idAud);
        if ($cab === null || ($cab['estado'] ?? '') !== 'abierta') {
            unset($_SESSION[JOYERIA_SESSION_RECUENTO]);
            $error = 'El recuento ya no está abierto.';
            break;
        }
        $meta = $model->parsearMetaCabecera((string) ($cab['observaciones'] ?? ''));
        if ($meta === null || $meta['id_usuario'] !== $idUsuario || (int) ($cab['id_empleado_FK'] ?? 0) !== $idEmpleado) {
            $error = 'No tienes permiso para cancelar esta auditoría.';
            break;
        }
        $model->cerrarAuditoria($idAud);
        unset($_SESSION[JOYERIA_SESSION_RECUENTO]);
        $mensaje = 'Recuento cancelado (auditoría cerrada).';
        $ctx = null;
        break;

    case 'actualizar':
        $codigoPost = isset($_POST['codigo']) ? (string) $_POST['codigo'] : '';
        $ajaxRecuento = !empty($_POST['ajax_recuento']);
        $resAgregar = joyeria_recuento_intentar_agregar_codigo(
            $model,
            $ctx,
            $idEmpleado,
            $idUsuario,
            $codigoPost
        );
        if ($resAgregar['ok'] !== true && ($resAgregar['error'] ?? '') === 'El recuento ya no está abierto.') {
            unset($_SESSION[JOYERIA_SESSION_RECUENTO]);
        }
        if ($ajaxRecuento) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resAgregar, JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!$resAgregar['ok']) {
            $error = (string) ($resAgregar['error'] ?? 'Error al agregar código.');
        } else {
            $mensaje = (string) ($resAgregar['mensaje'] ?? '');
        }
        break;

    case 'finalizar':
        if ($idEmpleado === null) {
            $error = 'Tu usuario debe estar vinculado a un empleado activo.';
            break;
        }
        $idAud = joyeria_recuento_ctx_auditoria($ctx);
        if ($idAud <= 0 || joyeria_recuento_ctx_paso($ctx) !== 'captura') {
            $error = 'No hay un recuento en captura para finalizar.';
            break;
        }
        $cab = $model->obtenerCabecera($idAud);
        if ($cab === null || ($cab['estado'] ?? '') !== 'abierta') {
            unset($_SESSION[JOYERIA_SESSION_RECUENTO]);
            $error = 'El recuento ya no está abierto.';
            break;
        }
        $meta = $model->parsearMetaCabecera((string) ($cab['observaciones'] ?? ''));
        if ($meta === null || $meta['id_usuario'] !== $idUsuario || (int) ($cab['id_empleado_FK'] ?? 0) !== $idEmpleado) {
            $error = 'No tienes permiso para cerrar esta auditoría.';
            break;
        }
        $model->cerrarAuditoria($idAud);
        $_SESSION[JOYERIA_SESSION_RECUENTO] = [
            'id_auditoria' => $idAud,
            'paso' => 'resultado',
        ];
        $ctx = $_SESSION[JOYERIA_SESSION_RECUENTO];
        $mensaje = 'Recuento finalizado. Revisa las piezas faltantes.';
        break;

    case 'borrar':
        // El guard de header.php corre al final de este archivo, despues de
        // procesar la accion. Verificamos aqui para que dar de baja faltantes
        // sea exclusivo de quien tenga INVENTARIO_RECUENTO_BORRAR (admins incluidos).
        if (!auth_can_module_action('inventario_recuento', 'BORRAR')) {
            $error = 'Solo un administrador puede dar de baja piezas faltantes.';
            break;
        }
        if ($idEmpleado === null) {
            $error = 'Tu usuario debe estar vinculado a un empleado activo.';
            break;
        }
        $idAud = joyeria_recuento_ctx_auditoria($ctx);
        if ($idAud <= 0) {
            $error = 'Sesión de recuento no válida.';
            break;
        }
        $cab = $model->obtenerCabecera($idAud);
        if ($cab === null || ($cab['estado'] ?? '') !== 'cerrada') {
            $error = 'Solo se pueden dar de baja faltantes tras finalizar el recuento.';
            break;
        }
        $meta = $model->parsearMetaCabecera((string) ($cab['observaciones'] ?? ''));
        if ($meta === null || $meta['id_usuario'] !== $idUsuario || (int) ($cab['id_empleado_FK'] ?? 0) !== $idEmpleado) {
            $error = 'No tienes permiso para esta operación sobre la auditoría.';
            break;
        }
        $idsPost = isset($_POST['ids_pieza_stock']) && is_array($_POST['ids_pieza_stock'])
            ? $_POST['ids_pieza_stock']
            : [];
        $idsOk = $model->filtrarIdsBorrables(
            $meta['id_tienda'],
            $idsPost,
            (int) ($meta['id_familia'] ?? 0)
        );
        if ($idsOk === []) {
            $error = 'No se seleccionaron piezas válidas para dar de baja.';
            break;
        }
        $ok = 0;
        foreach ($idsOk as $idPs) {
            try {
                $stockModel->eliminar($idPs, $idUsuario);
                $ok++;
            } catch (Throwable $e) {
                $error = ($error !== null ? $error . ' ' : '') . 'ID ' . $idPs . ': ' . $e->getMessage();
            }
        }
        if ($ok > 0) {
            $mensaje = ($mensaje !== null ? $mensaje . ' ' : '') . 'Se dieron de baja ' . $ok . ' registro(s) de stock.';
        }
        $_SESSION[JOYERIA_SESSION_RECUENTO] = [
            'id_auditoria' => $idAud,
            'paso' => 'resultado',
        ];
        $ctx = $_SESSION[JOYERIA_SESSION_RECUENTO];
        break;

    case 'nuevo':
        $idAudNuevo = joyeria_recuento_ctx_auditoria($ctx);
        if ($idAudNuevo > 0 && $idEmpleado !== null) {
            $cabN = $model->obtenerCabecera($idAudNuevo);
            if ($cabN !== null && ($cabN['estado'] ?? '') === 'abierta') {
                $metaN = $model->parsearMetaCabecera((string) ($cabN['observaciones'] ?? ''));
                if ($metaN !== null && $metaN['id_usuario'] === $idUsuario
                    && (int) ($cabN['id_empleado_FK'] ?? 0) === $idEmpleado) {
                    $model->cerrarAuditoria($idAudNuevo);
                }
            }
        }
        unset($_SESSION[JOYERIA_SESSION_RECUENTO]);
        $ctx = null;
        $mensaje = 'Listo para iniciar un nuevo recuento.';
        break;

    case 'leer':
    default:
        if (isset($_GET['lista']) && (string) $_GET['lista'] === '1') {
            unset($_SESSION[JOYERIA_SESSION_RECUENTO]);
            $ctx = null;
        }
        if ($idEmpleado !== null && $ctx === null) {
            $abiertaSync = $model->buscarAuditoriaAbiertaPorEmpleado($idEmpleado);
            if ($abiertaSync !== null) {
                $metaSync = $model->parsearMetaCabecera((string) ($abiertaSync['observaciones'] ?? ''));
                if ($metaSync !== null && $metaSync['id_usuario'] === $idUsuario) {
                    $_SESSION[JOYERIA_SESSION_RECUENTO] = [
                        'id_auditoria' => (int) $abiertaSync['id_auditoria'],
                        'paso' => 'captura',
                    ];
                    $ctx = $_SESSION[JOYERIA_SESSION_RECUENTO];
                }
            }
        }
        if ($idAuditoriaGet > 0 && ($ctx === null || joyeria_recuento_ctx_paso($ctx) !== 'resultado')) {
            $cabGet = $model->obtenerCabecera($idAuditoriaGet);
            if ($cabGet !== null && ($cabGet['estado'] ?? '') === 'cerrada') {
                $metaGet = $model->parsearMetaCabecera((string) ($cabGet['observaciones'] ?? ''));
                if ($metaGet !== null) {
                    $_SESSION[JOYERIA_SESSION_RECUENTO] = [
                        'id_auditoria' => $idAuditoriaGet,
                        'paso' => 'resultado',
                    ];
                    $ctx = $_SESSION[JOYERIA_SESSION_RECUENTO];
                }
            }
        }
        break;
}

$filtrosHistorialGet = array_key_exists('fecha_desde', $_GET) || array_key_exists('fecha_hasta', $_GET)
    || array_key_exists('id_familia', $_GET);
$filtroHistFechaDesde = joyeria_recuento_parse_fecha_filtro(
    isset($_GET['fecha_desde']) ? (string) $_GET['fecha_desde'] : null
);
$filtroHistFechaHasta = joyeria_recuento_parse_fecha_filtro(
    isset($_GET['fecha_hasta']) ? (string) $_GET['fecha_hasta'] : null
);
if (!$filtrosHistorialGet) {
    $hoyRecuento = joyeria_today_ymd();
    $filtroHistFechaDesde = $hoyRecuento;
    $filtroHistFechaHasta = $hoyRecuento;
}
$filtroHistIdFamilia = isset($_GET['id_familia']) ? max(0, (int) $_GET['id_familia']) : 0;
if ($filtroHistIdFamilia > 0 && !$model->existeFamiliaActiva($filtroHistIdFamilia)) {
    $filtroHistIdFamilia = 0;
}

$historialRecuentos = [];
$mapTiendasPorId = [];
$mapFamiliasPorId = [];
foreach ($tiendasActivas as $t) {
    $mapTiendasPorId[(int) ($t['id_tienda'] ?? 0)] = (string) ($t['nom_tienda'] ?? '');
}
foreach ($familiasActivas as $f) {
    $mapFamiliasPorId[(int) ($f['id_familia'] ?? 0)] = (string) ($f['nom_familia'] ?? '');
}
if ($idEmpleado !== null) {
    try {
        $rowsHist = $model->listarRecuentosRealizados(
            $filtroHistFechaDesde,
            $filtroHistFechaHasta,
            $filtroHistIdFamilia
        );
        foreach ($rowsHist as $rh) {
            $metaH = $model->parsearMetaCabecera((string) ($rh['observaciones'] ?? ''));
            if ($metaH === null) {
                continue;
            }
            $idTiendaH = $metaH['id_tienda'];
            $idFamH = (int) ($metaH['id_familia'] ?? 0);
            $esperadosH = $model->contarEsperadosPorTienda($idTiendaH, $idFamH);
            $contadosH = (int) ($rh['contados'] ?? 0);
            $historialRecuentos[] = [
                'id_auditoria' => (int) ($rh['id_auditoria'] ?? 0),
                'fecha_inicio' => (string) ($rh['fecha_inicio'] ?? ''),
                'fecha_cierre' => (string) ($rh['fecha_cierre'] ?? ''),
                'empleado_nombre' => trim((string) ($rh['empleado_nombre'] ?? '')),
                'id_tienda' => $idTiendaH,
                'nom_tienda' => $mapTiendasPorId[$idTiendaH] ?? ('Tienda #' . (string) $idTiendaH),
                'id_familia' => $idFamH,
                'nom_familia' => $idFamH > 0
                    ? ($mapFamiliasPorId[$idFamH] ?? ('Familia #' . (string) $idFamH))
                    : 'Todas',
                'contados' => $contadosH,
                'esperados' => $esperadosH,
                'faltantes' => max(0, $esperadosH - $contadosH),
            ];
        }
    } catch (Throwable $e) {
        if ($error === null) {
            $error = 'No se pudo cargar el historial de recuentos.';
        }
    }
}

/**
 * @param array<string, string|int> $paramsExtra
 */
function joyeria_recuento_url_historial(array $paramsExtra = []): string
{
    global $filtroHistFechaDesde, $filtroHistFechaHasta, $filtroHistIdFamilia;
    $params = ['accion' => 'leer', 'lista' => '1'];
    if ($filtroHistFechaDesde !== null && $filtroHistFechaDesde !== '') {
        $params['fecha_desde'] = $filtroHistFechaDesde;
    }
    if ($filtroHistFechaHasta !== null && $filtroHistFechaHasta !== '') {
        $params['fecha_hasta'] = $filtroHistFechaHasta;
    }
    if ($filtroHistIdFamilia > 0) {
        $params['id_familia'] = (string) $filtroHistIdFamilia;
    }
    foreach ($paramsExtra as $k => $v) {
        $params[$k] = $v;
    }
    return 'inventario_recuento.php?' . http_build_query($params);
}

// --- Datos para la vista ---
$idAuditoriaVista = joyeria_recuento_ctx_auditoria($ctx);
$pasoVista = joyeria_recuento_ctx_paso($ctx);
$auditoriaVista = $idAuditoriaVista > 0 ? $model->obtenerCabecera($idAuditoriaVista) : null;
$metaVista = null;
$idTiendaVista = 0;
$nomTiendaVista = '';
$idFamiliaVista = 0;
$nomFamiliaVista = '';
$esperadosVista = 0;
$contadosVista = 0;
$faltantesVista = [];

if ($auditoriaVista !== null) {
    $metaVista = $model->parsearMetaCabecera((string) ($auditoriaVista['observaciones'] ?? ''));
    if ($metaVista !== null) {
        $idTiendaVista = $metaVista['id_tienda'];
        $idFamiliaVista = (int) ($metaVista['id_familia'] ?? 0);
        foreach ($tiendasActivas as $t) {
            if ((int) ($t['id_tienda'] ?? 0) === $idTiendaVista) {
                $nomTiendaVista = (string) ($t['nom_tienda'] ?? '');
                break;
            }
        }
        if ($idFamiliaVista > 0) {
            foreach ($familiasActivas as $f) {
                if ((int) ($f['id_familia'] ?? 0) === $idFamiliaVista) {
                    $nomFamiliaVista = (string) ($f['nom_familia'] ?? '');
                    break;
                }
            }
            if ($nomFamiliaVista === '') {
                $nomFamiliaVista = 'Familia #' . (string) $idFamiliaVista;
            }
        }
        $esperadosVista = $model->contarEsperadosPorTienda($idTiendaVista, $idFamiliaVista);
        $contadosVista = $model->contarDetalle($idAuditoriaVista);
        if (($auditoriaVista['estado'] ?? '') === 'cerrada' && $pasoVista === 'resultado') {
            $faltantesVista = $model->listarFaltantes($idTiendaVista, $idAuditoriaVista, $idFamiliaVista);
        }
    }
}

$authCaps = auth_current_capabilities();

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2>Recuento de inventario (joyas)</h2>
</header>

<div class="admin-main">
    <?php
    require __DIR__ . '/views/inventario_recuento/index.php';
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php';
