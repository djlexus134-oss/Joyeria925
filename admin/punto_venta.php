<?php
require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/ventas.php';
require_once __DIR__ . '/models/configuracion_general.php';
require_once __DIR__ . '/models/devoluciones.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ImpresionTicketHelper.php';
require_once __DIR__ . '/includes/pos_pausadas_helpers.php';
require_once __DIR__ . '/includes/PosReservaStockService.php';

const POS_SESSION_KEY = 'joyeria_pos_carrito';
const POS_RESERVA_TOKEN_KEY = 'joyeria_pos_reserva_token';

/**
 * Excepcion AJAX del POS con codigo_error estructurado para el cliente.
 */
class PosAjaxException extends InvalidArgumentException
{
    public string $codigoError;

    public function __construct(string $codigoError, string $mensaje)
    {
        $this->codigoError = $codigoError;
        parent::__construct($mensaje);
    }
}

function pos_json_error(Throwable $e): void
{
    $payload = ['ok' => false, 'mensaje' => $e->getMessage()];
    if ($e instanceof PosAjaxException) {
        $payload['codigo_error'] = $e->codigoError;
    } elseif ($e instanceof InvalidArgumentException) {
        $prefijo = Ventas::PREFIJO_ERROR_INVENTARIO_POS;
        if (strncmp($e->getMessage(), $prefijo, strlen($prefijo)) === 0) {
            $payload['codigo_error'] = 'inventario_no_disponible';
            $payload['mensaje'] = substr($e->getMessage(), strlen($prefijo));
        }
    }
    pos_json($payload, 422);
}

function pos_json(array $payload, int $status = 200): void
{
    require_once __DIR__ . '/../includes/joyeria_json_guard.php';
    joyeria_json_clean_buffer();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function pos_normalizar_entero_nullable($value): ?int
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }
    $intVal = (int) $value;
    return $intVal > 0 ? $intVal : null;
}

function pos_estado_inicial(Ventas $app): array
{
    $idImpuesto = $app->obtenerIdImpuestoDefault();
    $cfg        = new ConfiguracionGeneral();
    $mapCfg     = $cfg->leerPorClaves(['impresion_id_tienda_caja']);
    $idTienda   = (int) ($mapCfg['impresion_id_tienda_caja'] ?? 0);

    return [
        'id_cliente' => null,
        'id_impuesto' => $idImpuesto !== null && $idImpuesto > 0 ? $idImpuesto : null,
        'id_tienda' => $idTienda > 0 ? $idTienda : null,
        'detalles' => [],
        'creditos_canje' => [],
    ];
}

/**
 * Alinea id_impuesto del ticket POS con configuracion_general.id_impuesto_default.
 */
function pos_sincronizar_impuesto_desde_config(array $estado, Ventas $app): array
{
    $idCfg = $app->obtenerIdImpuestoDefault();
    if ($idCfg === null || $idCfg <= 0) {
        return $estado;
    }

    $idActual = pos_normalizar_entero_nullable($estado['id_impuesto'] ?? null);
    if ($idActual === null || $app->obtenerImpuestoPorId($idActual) === null) {
        $estado['id_impuesto'] = $idCfg;
    }

    return $estado;
}

function pos_obtener_estado(Ventas $app): array
{
    if (!isset($_SESSION[POS_SESSION_KEY]) || !is_array($_SESSION[POS_SESSION_KEY])) {
        $_SESSION[POS_SESSION_KEY] = pos_estado_inicial($app);
    }
    $st = $_SESSION[POS_SESSION_KEY];
    if (!isset($st['creditos_canje']) || !is_array($st['creditos_canje'])) {
        $st['creditos_canje'] = [];
    }
    $st = pos_sincronizar_impuesto_desde_config($st, $app);
    $_SESSION[POS_SESSION_KEY] = $st;

    return $_SESSION[POS_SESSION_KEY];
}

function pos_guardar_estado(array $estado): void
{
    $_SESSION[POS_SESSION_KEY] = $estado;
}

function pos_obtener_token_reserva(): string
{
    $token = isset($_SESSION[POS_RESERVA_TOKEN_KEY]) ? trim((string) $_SESSION[POS_RESERVA_TOKEN_KEY]) : '';
    if ($token === '') {
        $token = PosReservaStockService::generarToken();
        $_SESSION[POS_RESERVA_TOKEN_KEY] = $token;
    }

    return $token;
}

function pos_nuevo_token_reserva(): string
{
    $token = PosReservaStockService::generarToken();
    $_SESSION[POS_RESERVA_TOKEN_KEY] = $token;

    return $token;
}

function pos_establecer_token_reserva(string $token): void
{
    $token = trim($token);
    if ($token === '') {
        pos_nuevo_token_reserva();
        return;
    }
    $_SESSION[POS_RESERVA_TOKEN_KEY] = $token;
}

function pos_token_reserva_desde_estado(array $estado): string
{
    $token = trim((string) ($estado['pos_reserva_token'] ?? ''));
    if ($token !== '') {
        return $token;
    }

    return pos_obtener_token_reserva();
}

function pos_preparar_reservas_pos(Ventas $app): PosReservaStockService
{
    $reserva = new PosReservaStockService($app->getDb());
    $reserva->liberarExpiradasPos();
    $token = pos_obtener_token_reserva();
    $reserva->extenderToken($token);

    return $reserva;
}

function pos_credito_canje_ya_en_ticket(array $estado, int $idPiezaStock): bool
{
    if ($idPiezaStock <= 0) {
        return false;
    }
    foreach ($estado['creditos_canje'] ?? [] as $ex) {
        if (!is_array($ex)) {
            continue;
        }
        if ((int) ($ex['id_pieza_stock_FK'] ?? 0) === $idPiezaStock) {
            return true;
        }
    }

    return false;
}

$app = new Ventas();
$accion = isset($_GET['accion']) ? (string) $_GET['accion'] : 'leer';
$auth = auth_user();
$idUsuarioSesion = isset($auth['id_usuario']) ? (int) $auth['id_usuario'] : 0;

if ($idUsuarioSesion <= 0) {
    auth_set_flash('No fue posible identificar el usuario autenticado.', 'error');
    header('Location: login.php');
    exit;
}

$idEmpleadoSesion = $app->obtenerEmpleadoIdPorUsuario($idUsuarioSesion);
if ($idEmpleadoSesion === null) {
    auth_set_flash('Tu usuario no esta vinculado a un empleado activo para registrar ventas.', 'error');
    header('Location: index.php');
    exit;
}

$estado = pos_obtener_estado($app);

if (in_array($accion, [
    'estado',
    'agregar_item',
    'quitar_item',
    'actualizar_meta',
    'confirmar',
    'limpiar',
    'pausar_y_nueva',
    'listar_pausadas',
    'retomar_pausada',
    'eliminar_pausada',
    'agregar_credito_canje',
    'preparar_credito_canje',
    'aplicar_creditos_canje_lote',
    'quitar_credito_canje',
], true)) {
    require_once __DIR__ . '/../includes/joyeria_json_guard.php';
    joyeria_json_guard_begin();
    $guardPos = auth_current_access_guard();
    if (!$guardPos['allowed']) {
        pos_json(['ok' => false, 'mensaje' => (string) $guardPos['message']], 403);
    }
    if (strcasecmp((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'POST') !== 0) {
        auth_set_flash('Usa los botones del punto de venta (no abras enlaces con ?accion= en el navegador).', 'error');
        header('Location: punto_venta.php');
        exit;
    }
    if (!joyeria_admin_csrf_verify()) {
        pos_json(['ok' => false, 'mensaje' => 'Token de seguridad invalido. Recarga la pagina.'], 403);
    }
    try {
        $catalogosAjax = $app->obtenerCatalogos();
        $mapClientesPos = pos_mapa_nombres_clientes($catalogosAjax);
        $reservaPos = pos_preparar_reservas_pos($app);

        if ($accion === 'limpiar') {
            $reservaPos->liberarPorToken(pos_obtener_token_reserva());
            $estado = pos_estado_inicial($app);
            pos_nuevo_token_reserva();
            pos_guardar_estado($estado);
        }

        if ($accion === 'pausar_y_nueva') {
            $estado['id_cliente'] = pos_normalizar_entero_nullable($_POST['id_cliente_FK'] ?? ($estado['id_cliente'] ?? null));
            $estado['id_impuesto'] = pos_normalizar_entero_nullable($_POST['id_impuesto_FK'] ?? ($estado['id_impuesto'] ?? null));
            $estado['id_tienda'] = pos_normalizar_entero_nullable($_POST['id_tienda_FK'] ?? ($estado['id_tienda'] ?? null));
            $estado = pos_sincronizar_impuesto_desde_config($estado, $app);
            if (!pos_estado_es_pausable($estado)) {
                throw new InvalidArgumentException(
                    'Agrega productos, un cliente o creditos de canje al ticket antes de guardarlo en espera.'
                );
            }
            $pagosBorrador = pos_normalizar_pagos_borrador($_POST['pagos'] ?? '[]');
            $etiqueta = trim((string) ($_POST['etiqueta'] ?? ''));
            $estado['pos_reserva_token'] = pos_obtener_token_reserva();
            $idPausada = pos_agregar_pausada($estado, $etiqueta, $pagosBorrador, $mapClientesPos);
            $estado = pos_estado_inicial($app);
            pos_nuevo_token_reserva();
            pos_guardar_estado($estado);
            $idImpuestoActual = pos_normalizar_entero_nullable($estado['id_impuesto'] ?? null);
            $totales = $app->calcularTotalesPuntoVenta($estado['detalles'], null, $idImpuestoActual, 0.0);
            pos_json(pos_respuesta_pos_con_pausadas($app, $catalogosAjax, [
                'ok' => true,
                'mensaje' => 'Venta guardada en espera. Puedes atender otra venta y retomarla cuando quieras.',
                'id_pausada' => $idPausada,
                'estado' => $estado,
                'totales' => $totales,
                'pagos_borrador' => [],
                'id_empleado' => $idEmpleadoSesion,
            ]));
        }

        if ($accion === 'listar_pausadas') {
            pos_json(pos_respuesta_pos_con_pausadas($app, $catalogosAjax, [
                'ok' => true,
                'estado' => $estado,
            ]));
        }

        if ($accion === 'retomar_pausada') {
            $idPausada = trim((string) ($_POST['id_pausada'] ?? ''));
            $item = pos_buscar_pausada($idPausada);
            if ($item === null) {
                throw new InvalidArgumentException('La venta en espera ya no existe o fue retomada por otro usuario.');
            }
            $mensajeExtra = '';
            $advertenciasRetomar = [];
            if (pos_estado_es_pausable($estado)) {
                $pagosActuales = pos_normalizar_pagos_borrador($_POST['pagos'] ?? '[]');
                $estado['id_cliente'] = pos_normalizar_entero_nullable($_POST['id_cliente_FK'] ?? ($estado['id_cliente'] ?? null));
                $estado['id_impuesto'] = pos_normalizar_entero_nullable($_POST['id_impuesto_FK'] ?? ($estado['id_impuesto'] ?? null));
                $estado['id_tienda'] = pos_normalizar_entero_nullable($_POST['id_tienda_FK'] ?? ($estado['id_tienda'] ?? null));
                $estado['pos_reserva_token'] = pos_obtener_token_reserva();
                pos_agregar_pausada(
                    $estado,
                    'Auto-guardado al retomar otra venta',
                    $pagosActuales,
                    $mapClientesPos
                );
                pos_nuevo_token_reserva();
                $mensajeExtra = ' El ticket que tenias abierto tambien quedo guardado en espera.';
            }
            $estadoRetomado = $item['estado'];
            if (!is_array($estadoRetomado)) {
                throw new RuntimeException('La venta en espera no tiene datos validos.');
            }
            if (!isset($estadoRetomado['creditos_canje']) || !is_array($estadoRetomado['creditos_canje'])) {
                $estadoRetomado['creditos_canje'] = [];
            }
            $tokenRetomado = pos_token_reserva_desde_estado($estadoRetomado);
            $filtrado = $reservaPos->filtrarDetallesConReservaValida(
                is_array($estadoRetomado['detalles'] ?? null) ? $estadoRetomado['detalles'] : [],
                $tokenRetomado
            );
            $estadoRetomado['detalles'] = $filtrado['detalles'];
            $advertenciasRetomar = $filtrado['advertencias'];
            pos_establecer_token_reserva($tokenRetomado);
            $reservaPos->extenderToken($tokenRetomado);
            $estado = pos_sincronizar_impuesto_desde_config($estadoRetomado, $app);
            pos_eliminar_pausada($idPausada);
            pos_guardar_estado($estado);
            $idImpuestoActual = pos_normalizar_entero_nullable($estado['id_impuesto'] ?? null);
            if ($idImpuestoActual === null) {
                throw new InvalidArgumentException('Selecciona un impuesto para calcular los totales.');
            }
            $idClienteActual = pos_normalizar_entero_nullable($estado['id_cliente'] ?? null);
            $mCanjeAct = 0.0;
            foreach ($estado['creditos_canje'] as $cr) {
                if (is_array($cr)) {
                    $mCanjeAct += (float) ($cr['monto_credito'] ?? 0);
                }
            }
            $totales = $app->calcularTotalesPuntoVenta($estado['detalles'], $idClienteActual, $idImpuestoActual, $mCanjeAct);
            $mensajeRetomar = 'Venta retomada.' . $mensajeExtra;
            if ($advertenciasRetomar !== []) {
                $mensajeRetomar .= ' ' . implode(' ', $advertenciasRetomar);
            }
            pos_json(pos_respuesta_pos_con_pausadas($app, $catalogosAjax, [
                'ok' => true,
                'mensaje' => $mensajeRetomar,
                'estado' => $estado,
                'totales' => $totales,
                'pagos_borrador' => $item['pagos_borrador'] ?? [],
                'id_empleado' => $idEmpleadoSesion,
            ]));
        }

        if ($accion === 'eliminar_pausada') {
            $idPausada = trim((string) ($_POST['id_pausada'] ?? ''));
            $itemPausada = pos_buscar_pausada($idPausada);
            if ($itemPausada === null) {
                throw new InvalidArgumentException('No se encontro esa venta en espera.');
            }
            $tokenPausada = pos_token_reserva_desde_estado(
                is_array($itemPausada['estado'] ?? null) ? $itemPausada['estado'] : []
            );
            $reservaPos->liberarPorToken($tokenPausada);
            if (!pos_eliminar_pausada($idPausada)) {
                throw new InvalidArgumentException('No se encontro esa venta en espera.');
            }
            pos_json(pos_respuesta_pos_con_pausadas($app, $catalogosAjax, [
                'ok' => true,
                'mensaje' => 'Venta en espera eliminada.',
                'estado' => $estado,
            ]));
        }

        if ($accion === 'actualizar_meta') {
            $estado['id_cliente'] = pos_normalizar_entero_nullable($_POST['id_cliente_FK'] ?? null);
            $estado['id_impuesto'] = pos_normalizar_entero_nullable($_POST['id_impuesto_FK'] ?? null);
            $estado['id_tienda'] = pos_normalizar_entero_nullable($_POST['id_tienda_FK'] ?? null);
            pos_guardar_estado($estado);
        }

        if ($accion === 'agregar_item') {
            $codigo = trim((string) ($_POST['codigo'] ?? ''));
            if ($codigo === '') {
                throw new InvalidArgumentException('Ingresa un codigo para agregar un producto.');
            }
            $idTienda = pos_normalizar_entero_nullable($_POST['id_tienda_FK'] ?? ($estado['id_tienda'] ?? null));
            $resItem = $app->resolverItemPuntoVenta($codigo, $idTienda);
            if (!($resItem['ok'] ?? false)) {
                throw new PosAjaxException(
                    (string) ($resItem['codigo_error'] ?? 'codigo_no_encontrado'),
                    (string) ($resItem['mensaje'] ?? 'No se pudo agregar el producto.')
                );
            }
            $item = $resItem['item'] ?? null;
            if (!is_array($item)) {
                throw new PosAjaxException('codigo_no_encontrado', 'No se pudo agregar el producto.');
            }

            $indiceInsumoExistente = null;
            foreach ($estado['detalles'] as $idxLinea => $linea) {
                $tipo = (string) ($linea['tipo_linea'] ?? '');
                if ($tipo === 'joya' && ($item['tipo_linea'] ?? '') === 'joya'
                    && (int) ($linea['id_pieza_stock_FK'] ?? 0) === (int) ($item['id_pieza_stock_FK'] ?? -1)) {
                    throw new InvalidArgumentException('La pieza ya fue agregada al ticket.');
                }
                if ($tipo === 'insumo' && ($item['tipo_linea'] ?? '') === 'insumo'
                    && (int) ($linea['id_insumo_FK'] ?? 0) === (int) ($item['id_insumo_FK'] ?? -1)
                    && (int) ($linea['id_tienda_FK'] ?? 0) === (int) ($item['id_tienda_FK'] ?? -2)) {
                    $indiceInsumoExistente = (int) $idxLinea;
                }
            }

            if (($item['tipo_linea'] ?? '') === 'insumo' && $indiceInsumoExistente !== null) {
                $cantActual = isset($estado['detalles'][$indiceInsumoExistente]['cantidad'])
                    ? (float) $estado['detalles'][$indiceInsumoExistente]['cantidad']
                    : 1.0;
                $nuevaCant = $cantActual + 1.0;
                $existencia = isset($estado['detalles'][$indiceInsumoExistente]['existencia_tienda'])
                    ? (float) $estado['detalles'][$indiceInsumoExistente]['existencia_tienda']
                    : (float) ($item['existencia_tienda'] ?? 0);
                if ($existencia > 0 && $nuevaCant - $existencia > 0.0001) {
                    throw new InvalidArgumentException(
                        'Stock insuficiente del insumo (disponible: '
                        . number_format($existencia, 3, '.', '') . ').'
                    );
                }
                $estado['detalles'][$indiceInsumoExistente]['cantidad'] = number_format($nuevaCant, 3, '.', '');
                pos_guardar_estado($estado);
            } else {
                if (($item['tipo_linea'] ?? '') === 'joya') {
                    $idStock = (int) ($item['id_pieza_stock_FK'] ?? 0);
                    $res = $reservaPos->reservar($idStock, pos_obtener_token_reserva());
                    if (!($res['ok'] ?? false)) {
                        throw new PosAjaxException(
                            'inventario_no_disponible',
                            (string) ($res['error'] ?? 'La pieza ya no esta disponible.')
                        );
                    }
                }

                $estado['detalles'][] = $item;
                pos_guardar_estado($estado);
            }
        }

        if ($accion === 'actualizar_cantidad_linea') {
            $index = isset($_POST['index']) ? (int) $_POST['index'] : -1;
            $cantidadRaw = $_POST['cantidad'] ?? null;
            if ($index < 0 || !isset($estado['detalles'][$index]) || !is_array($estado['detalles'][$index])) {
                throw new InvalidArgumentException('Linea de ticket no valida.');
            }
            $linea = $estado['detalles'][$index];
            if ((string) ($linea['tipo_linea'] ?? '') !== 'insumo') {
                throw new InvalidArgumentException('Solo se puede modificar la cantidad de insumos.');
            }
            if ($cantidadRaw === null || trim((string) $cantidadRaw) === '' || !is_numeric($cantidadRaw)) {
                throw new InvalidArgumentException('Ingresa una cantidad valida.');
            }
            $cantidad = round((float) $cantidadRaw, 3);
            if ($cantidad <= 0) {
                array_splice($estado['detalles'], $index, 1);
                pos_guardar_estado($estado);
            } else {
                $existencia = isset($linea['existencia_tienda']) ? (float) $linea['existencia_tienda'] : 0.0;
                if ($existencia > 0 && $cantidad - $existencia > 0.0001) {
                    throw new InvalidArgumentException(
                        'Stock insuficiente del insumo (disponible: '
                        . number_format($existencia, 3, '.', '') . ').'
                    );
                }
                $estado['detalles'][$index]['cantidad'] = number_format($cantidad, 3, '.', '');
                pos_guardar_estado($estado);
            }
        }

        if ($accion === 'quitar_item') {
            $index = isset($_POST['index']) ? (int) $_POST['index'] : -1;
            if ($index >= 0 && isset($estado['detalles'][$index])) {
                $lineaQuitar = $estado['detalles'][$index];
                if (is_array($lineaQuitar) && ($lineaQuitar['tipo_linea'] ?? '') === 'joya') {
                    $idStockQuitar = (int) ($lineaQuitar['id_pieza_stock_FK'] ?? 0);
                    if ($idStockQuitar > 0
                        && !$reservaPos->liberar($idStockQuitar, pos_obtener_token_reserva())) {
                        throw new InvalidArgumentException(
                            'No se pudo liberar la pieza del inventario. Intenta de nuevo o descarta el ticket.'
                        );
                    }
                }
                array_splice($estado['detalles'], $index, 1);
                pos_guardar_estado($estado);
            }
        }

        if ($accion === 'preparar_credito_canje' || $accion === 'agregar_credito_canje') {
            if (!auth_has_permission('DEVOLUCION_CREAR')) {
                throw new InvalidArgumentException('No tienes permiso para agregar credito de canje (DEVOLUCION_CREAR).');
            }
            $idVenta = isset($_POST['id_venta']) ? (int) $_POST['id_venta'] : 0;
            $motivo = trim((string) ($_POST['motivo'] ?? ''));
            $idPs = isset($_POST['id_pieza_stock_FK']) ? (int) $_POST['id_pieza_stock_FK'] : 0;
            $codigo = trim((string) ($_POST['codigo'] ?? ''));
            $dev = new Devoluciones();
            $cred = $dev->prepararCreditoCanjeParaPos($idVenta, $codigo, $motivo, $idEmpleadoSesion, $idPs > 0 ? $idPs : null);
            if ($accion === 'preparar_credito_canje') {
                if (pos_credito_canje_ya_en_ticket($estado, (int) $cred['id_pieza_stock_FK'])) {
                    throw new InvalidArgumentException('Esa pieza ya esta aplicada como credito en el ticket.');
                }
                pos_json([
                    'ok' => true,
                    'credito' => $cred,
                    'estado' => $estado,
                ]);
            }
            if (pos_credito_canje_ya_en_ticket($estado, (int) $cred['id_pieza_stock_FK'])) {
                throw new InvalidArgumentException('Esa pieza ya esta en el ticket como credito de canje.');
            }
            $estado['creditos_canje'][] = $cred;
            pos_guardar_estado($estado);
        }

        if ($accion === 'aplicar_creditos_canje_lote') {
            if (!auth_has_permission('DEVOLUCION_CREAR')) {
                throw new InvalidArgumentException('No tienes permiso para aplicar creditos de canje (DEVOLUCION_CREAR).');
            }
            $raw = $_POST['creditos'] ?? '[]';
            $lista = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($lista) || $lista === []) {
                throw new InvalidArgumentException('Agrega al menos una pieza a la lista antes de aplicar los creditos.');
            }
            $motivoGlobal = trim((string) ($_POST['motivo'] ?? ''));
            $dev = new Devoluciones();
            $agregados = 0;
            $idsEnLote = [];

            foreach ($lista as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $idVenta = (int) ($item['id_venta_origen'] ?? $item['id_venta'] ?? 0);
                $idPs = (int) ($item['id_pieza_stock_FK'] ?? 0);
                $codigo = trim((string) ($item['codigo'] ?? ''));
                $motivo = trim((string) ($item['motivo'] ?? ''));
                if ($motivo === '') {
                    $motivo = $motivoGlobal;
                }

                if ($idPs > 0) {
                    $cred = $dev->prepararCreditoCanjeParaPos($idVenta, '', $motivo, $idEmpleadoSesion, $idPs);
                } elseif ($codigo !== '') {
                    $cred = $dev->prepararCreditoCanjeParaPos($idVenta, $codigo, $motivo, $idEmpleadoSesion, null);
                } else {
                    throw new InvalidArgumentException('Cada pieza de la lista debe incluir codigo o id de pieza.');
                }

                $idPiezaCred = (int) $cred['id_pieza_stock_FK'];
                if (isset($idsEnLote[$idPiezaCred])) {
                    throw new InvalidArgumentException('La pieza #' . $idPiezaCred . ' esta repetida en la lista.');
                }
                if (pos_credito_canje_ya_en_ticket($estado, $idPiezaCred)) {
                    throw new InvalidArgumentException(
                        'La pieza #' . $idPiezaCred . ' ya esta como credito en el ticket.'
                    );
                }
                $idsEnLote[$idPiezaCred] = true;
                $estado['creditos_canje'][] = $cred;
                $agregados++;
            }

            if ($agregados === 0) {
                throw new InvalidArgumentException('No se pudo aplicar ningun credito de la lista.');
            }
            pos_guardar_estado($estado);
        }

        if ($accion === 'quitar_credito_canje') {
            if (!auth_has_permission('DEVOLUCION_CREAR')) {
                throw new InvalidArgumentException('No tienes permiso para quitar credito de canje.');
            }
            $idx = isset($_POST['index']) ? (int) $_POST['index'] : -1;
            if ($idx >= 0 && isset($estado['creditos_canje'][$idx])) {
                array_splice($estado['creditos_canje'], $idx, 1);
                pos_guardar_estado($estado);
            }
        }

        if ($accion === 'confirmar') {
            if (empty($estado['detalles'])) {
                throw new InvalidArgumentException('Debes agregar al menos un producto al ticket.');
            }
            $idImpuesto = pos_normalizar_entero_nullable($estado['id_impuesto'] ?? null);
            if ($idImpuesto === null) {
                throw new InvalidArgumentException('Selecciona un impuesto para continuar.');
            }

            $idCliente = pos_normalizar_entero_nullable($estado['id_cliente'] ?? null);
            $creditosSesion = isset($estado['creditos_canje']) && is_array($estado['creditos_canje']) ? $estado['creditos_canje'] : [];
            $mCanje = 0.0;
            foreach ($creditosSesion as $cr) {
                if (is_array($cr)) {
                    $mCanje += (float) ($cr['monto_credito'] ?? 0);
                }
            }
            if ($mCanje > 0) {
                $app->validarCreditosCanjeContraDetalles($estado['detalles'], $idCliente, $idImpuesto, $mCanje);
            }
            $totales = $app->calcularTotalesPuntoVenta($estado['detalles'], $idCliente, $idImpuesto, $mCanje);
            $totalNum = (float) ($totales['total'] ?? 0);
            $pagosRaw = $_POST['pagos'] ?? '[]';
            $pagos = is_string($pagosRaw) ? json_decode($pagosRaw, true) : $pagosRaw;
            if (!is_array($pagos)) {
                $pagos = [];
            }
            $permiteSinPago = abs($totalNum) < 0.02;
            if (!$permiteSinPago && $pagos === []) {
                throw new InvalidArgumentException('Debes registrar al menos una forma de pago (el total a cobrar es mayor a cero).');
            }
            if ($permiteSinPago) {
                $pagos = [];
            }

            $payload = [
                'id_cliente_FK' => $idCliente,
                'id_empleado_FK' => $idEmpleadoSesion,
                'id_impuesto_FK' => $idImpuesto,
                'id_apartado_FK' => null,
                'id_usuario_FK' => $idUsuarioSesion,
                'pos_reserva_token' => pos_obtener_token_reserva(),
                'total' => $totales['total'],
                'estado' => 'completada',
                'impuesto_porcentaje' => $totales['impuesto_porcentaje'],
                'impuesto_monto' => $totales['impuesto_monto'],
                'descuento_porcentaje_aplicado' => $totales['descuento_porcentaje'],
                'detalles' => $estado['detalles'],
                'pagos' => $pagos,
                'creditos_canje' => $creditosSesion,
            ];
            $idVenta = $app->crear($payload);
            $idTiendaVenta = pos_normalizar_entero_nullable($estado['id_tienda'] ?? null);
            $idColaImpresion = joyeria_encolar_ticket_venta($idVenta, $idTiendaVenta, 'venta');
            $estado = pos_estado_inicial($app);
            pos_nuevo_token_reserva();
            pos_guardar_estado($estado);
            $idImpuestoNuevo = pos_normalizar_entero_nullable($estado['id_impuesto'] ?? null);
            $totalesNuevoTicket = $app->calcularTotalesPuntoVenta($estado['detalles'], null, $idImpuestoNuevo, 0.0);
            pos_json(pos_respuesta_pos_con_pausadas($app, $catalogosAjax, [
                'ok' => true,
                'mensaje' => 'Venta registrada correctamente.',
                'id_venta' => $idVenta,
                'id_cola_impresion' => $idColaImpresion,
                'impresion_encolada' => $idColaImpresion !== null,
                'estado' => $estado,
                'totales' => $totalesNuevoTicket,
                'pagos_borrador' => [],
            ]));
        }

        $idImpuestoActual = pos_normalizar_entero_nullable($estado['id_impuesto'] ?? null);
        if ($idImpuestoActual === null) {
            throw new InvalidArgumentException('Selecciona un impuesto para calcular los totales.');
        }
        $idClienteActual = pos_normalizar_entero_nullable($estado['id_cliente'] ?? null);
        $mCanjeAct = 0.0;
        if (!empty($estado['creditos_canje']) && is_array($estado['creditos_canje'])) {
            foreach ($estado['creditos_canje'] as $cr) {
                if (is_array($cr)) {
                    $mCanjeAct += (float) ($cr['monto_credito'] ?? 0);
                }
            }
        }
        $totales = $app->calcularTotalesPuntoVenta($estado['detalles'], $idClienteActual, $idImpuestoActual, $mCanjeAct);
        pos_json(pos_respuesta_pos_con_pausadas($app, $catalogosAjax, [
            'ok' => true,
            'estado' => $estado,
            'totales' => $totales,
            'id_empleado' => $idEmpleadoSesion,
        ]));
    } catch (Throwable $e) {
        pos_json_error($e);
    }
}

$catalogos = $app->obtenerCatalogos();
$db = $app->getDb();
$tiendas = $db->query("SELECT id_tienda, nom_tienda FROM tiendas WHERE COALESCE(activo, 1) = 1 ORDER BY nom_tienda ASC")->fetchAll(PDO::FETCH_ASSOC);
$formasPago = $db->query("SELECT id_forma_pago, forma_pago FROM forma_pago WHERE activo = 1 ORDER BY forma_pago ASC")->fetchAll(PDO::FETCH_ASSOC);
$configGeneral = new ConfiguracionGeneral();
$datosDepositoSpei = $configGeneral->leerDatosDepositoSpei();
$idsFormaTransferencia = [];
foreach ($formasPago as $fpRow) {
    if (stripos((string) ($fpRow['forma_pago'] ?? ''), 'transfer') !== false) {
        $idsFormaTransferencia[] = (int) ($fpRow['id_forma_pago'] ?? 0);
    }
}
$datosDepositoSpei['ids_forma_transferencia'] = array_values(array_filter($idsFormaTransferencia));
$idFormaPagoDefault = $configGeneral->resolverIdFormaPagoDefault();
$idImpuestoDefault = $app->obtenerIdImpuestoDefault();
$mostrarPanelDevoluciones = auth_has_permission('DEVOLUCION_CREAR') || auth_has_permission('DEVOLUCION_LEER');
$puedeDevolucionCrear = auth_has_permission('DEVOLUCION_CREAR');
$puedeDevolucionLeer = auth_has_permission('DEVOLUCION_LEER');
$puedeDevolucionMonedero = auth_has_permission('DEVOLUCION_CREDITO_MONEDERO');
$idImpuestoActual = pos_normalizar_entero_nullable($estado['id_impuesto'] ?? null);
$totalesIniciales = [
    'subtotal' => '0.00',
    'descuento_porcentaje' => '0.00',
    'descuento_monto' => '0.00',
    'monto_credito_canje' => '0.00',
    'base_gravable' => '0.00',
    'impuesto_porcentaje' => '0.00',
    'impuesto_monto' => '0.00',
    'total' => '0.00',
    'conteo_piezas' => 0,
];
if ($idImpuestoActual !== null) {
    $mIni = 0.0;
    if (!empty($estado['creditos_canje']) && is_array($estado['creditos_canje'])) {
        foreach ($estado['creditos_canje'] as $cr) {
            if (is_array($cr)) {
                $mIni += (float) ($cr['monto_credito'] ?? 0);
            }
        }
    }
    $totalesIniciales = $app->calcularTotalesPuntoVenta(
        $estado['detalles'],
        pos_normalizar_entero_nullable($estado['id_cliente'] ?? null),
        $idImpuestoActual,
        $mIni
    );
}

$modoEscaner = isset($_GET['modo']) && (string) $_GET['modo'] === 'escaner';

require_once __DIR__ . '/views/header.php';
?>
<?php if (!$modoEscaner): ?>
<header class="admin-header">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <h2 style="margin:0;">Punto de Venta en Tienda</h2>
        <a href="punto_venta.php?modo=escaner" class="btn-action-secondary pos-modo-escaner-link" id="btn_modo_escaner">
            <i class="bi bi-phone"></i> Modo escaner
        </a>
    </div>
</header>
<?php endif; ?>

<div class="admin-main">
    <?php
    if ($modoEscaner) {
        require __DIR__ . '/views/punto_venta/escaner.php';
    } else {
        require __DIR__ . '/views/punto_venta/index.php';
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
