<?php

require_once __DIR__ . '/../models/cola_impresion.php';

require_once __DIR__ . '/../models/configuracion_general.php';

require_once __DIR__ . '/../models/ventas.php';

require_once __DIR__ . '/../models/apartado_gestion.php';

require_once __DIR__ . '/../models/ordenes_taller.php';

require_once __DIR__ . '/../includes/TicketService.php';

require_once __DIR__ . '/../services/EtiquetaZplService.php';

require_once __DIR__ . '/../includes/ImpresionEtiquetaHelper.php';

require_once __DIR__ . '/../includes/ImpresionTicketHelper.php';



header('Content-Type: application/json; charset=utf-8');



function impresion_json_ok(array $data = [], int $status = 200): void

{

    http_response_code($status);

    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);

    exit;

}



function impresion_json_fail(string $message, int $status = 400): void

{

    http_response_code($status);

    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);

    exit;

}



function impresion_json_body(): array

{

    $raw = joyeria_request_raw_body();

    if ($raw === '' || trim($raw) === '') {

        return [];

    }

    $decoded = json_decode($raw, true);



    return is_array($decoded) ? $decoded : [];

}



function impresion_require_session(): void

{

    require_once __DIR__ . '/../includes/auth.php';

    if (!auth_is_logged_in()) {

        impresion_json_fail('Sesion no valida.', 401);

    }

    $csrfMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if (in_array($csrfMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !joyeria_admin_csrf_verify()) {

        impresion_json_fail('Token de seguridad invalido. Recarga la pagina.', 403);

    }

}



function impresion_token_header(): string

{

    $headers = function_exists('getallheaders') ? getallheaders() : [];

    $tokenHeader = '';

    if (is_array($headers)) {

        foreach ($headers as $key => $value) {

            if (strcasecmp((string) $key, 'X-Caja-Token') === 0) {

                $tokenHeader = trim((string) $value);

                break;

            }

        }

    }

    if ($tokenHeader === '' && isset($_SERVER['HTTP_X_CAJA_TOKEN'])) {

        $tokenHeader = trim((string) $_SERVER['HTTP_X_CAJA_TOKEN']);

    }



    return $tokenHeader;

}



function impresion_token_etiqueta_es_placeholder(string $token): bool

{

    $token = trim($token);

    if ($token === '') {

        return true;

    }

    foreach (['usar impresion_caja_token', 'cambiar_token_seguro'] as $placeholder) {

        if (strcasecmp($token, $placeholder) === 0) {

            return true;

        }

    }



    return false;

}



function impresion_token_esperado(ConfiguracionGeneral $config, string $destino = 'ticket'): string

{

    if ($destino === 'etiqueta') {

        $map = $config->leerPorClaves(['etiqueta_impresion_token', 'impresion_caja_token']);

        $tokenEtiqueta = trim((string) ($map['etiqueta_impresion_token'] ?? ''));

        if ($tokenEtiqueta !== '' && !impresion_token_etiqueta_es_placeholder($tokenEtiqueta)) {

            return $tokenEtiqueta;

        }



        return trim((string) ($map['impresion_caja_token'] ?? ''));

    }



    $map = $config->leerPorClaves(['impresion_caja_token']);



    return trim((string) ($map['impresion_caja_token'] ?? ''));

}



function impresion_token_valido(ConfiguracionGeneral $config, string $destino = 'ticket'): bool

{

    $esperado = impresion_token_esperado($config, $destino);

    if ($esperado === '') {

        return false;

    }



    $tokenHeader = impresion_token_header();



    return $tokenHeader !== '' && hash_equals($esperado, $tokenHeader);

}



function impresion_id_tienda_caja(ConfiguracionGeneral $config): ?int

{

    $map = $config->leerPorClaves(['impresion_id_tienda_caja']);

    $id = (int) ($map['impresion_id_tienda_caja'] ?? 0);



    return $id > 0 ? $id : null;

}



function impresion_destino_solicitado(): string

{

    $destino = isset($_GET['destino']) ? strtolower(trim((string) $_GET['destino'])) : 'ticket';



    return $destino === 'etiqueta' ? 'etiqueta' : 'ticket';

}



$accion = isset($_GET['accion']) ? trim((string) $_GET['accion']) : '';

$cola = new ColaImpresion();

$ticketService = new TicketService();

$etiquetaService = new EtiquetaZplService();

$config = new ConfiguracionGeneral();



try {

    switch ($accion) {

        case 'pendientes':

            $destino = impresion_destino_solicitado();

            if (!impresion_token_valido($config, $destino)) {

                impresion_json_fail('Token de caja invalido.', 401);

            }

            $idTiendaCaja = impresion_id_tienda_caja($config);

            $tipos = $destino === 'etiqueta' ? ColaImpresion::TIPOS_ETIQUETA : ColaImpresion::TIPOS_TICKET;

            $trabajo = $cola->obtenerSiguientePendiente($idTiendaCaja, $tipos);

            if (!$trabajo) {

                impresion_json_ok(['data' => null]);

            }



            $idCola = (int) ($trabajo['id_cola_impresion'] ?? 0);

            $tipoTrabajo = (string) ($trabajo['tipo'] ?? '');



            if (in_array($tipoTrabajo, ColaImpresion::TIPOS_ETIQUETA, true)) {

                $payload = json_decode((string) ($trabajo['payload_json'] ?? ''), true);

                if (!is_array($payload)) {

                    $cola->marcarError($idCola, 'Payload de etiquetas invalido.');

                    impresion_json_fail('Payload de etiquetas invalido.', 500);

                }

                $esInsumo = $etiquetaService->payloadEsEtiquetaInsumo($payload);

                if ($esInsumo) {
                    $ids = $etiquetaService->resolverIdsInsumoDesdePayload($payload);
                } else {
                    $ids = $etiquetaService->resolverIdsDesdePayload($payload);
                }

                if ($ids === []) {

                    $cola->marcarError($idCola, 'Sin IDs en el trabajo de etiquetas.');

                    impresion_json_fail('Sin IDs en el trabajo de etiquetas.', 500);

                }

                try {

                    $zpl = $esInsumo
                        ? $etiquetaService->generarZplLoteInsumos($ids)
                        : $etiquetaService->generarZplLote($ids);

                } catch (Throwable $e) {

                    $cola->marcarError($idCola, $e->getMessage());

                    impresion_json_fail('No se pudo generar el ZPL del lote de etiquetas.', 500);

                }

                if ($zpl === '') {

                    $cola->marcarError($idCola, 'ZPL vacio.');

                    impresion_json_fail('ZPL vacio.', 500);

                }



                impresion_json_ok([

                    'data' => [

                        'id_cola_impresion' => $idCola,

                        'tipo' => $tipoTrabajo,

                        'destino' => 'etiqueta',

                        'cantidad_etiquetas' => count($ids),

                        'zpl_base64' => base64_encode($zpl),

                    ],

                ]);

            }



            if (in_array($tipoTrabajo, ['apartado_alta', 'apartado_abono', 'apartado_liquidacion'], true)) {

                $payloadApart = json_decode((string) ($trabajo['payload_json'] ?? ''), true);

                if (!is_array($payloadApart)) {

                    $cola->marcarError($idCola, 'Payload de apartado invalido.');

                    impresion_json_fail('Payload de apartado invalido.', 500);

                }

                $idApartadoCola = (int) ($payloadApart['id_apartado'] ?? 0);

                $modoApart = (string) ($payloadApart['modo'] ?? 'abono');

                if ($idApartadoCola <= 0) {

                    $cola->marcarError($idCola, 'id_apartado invalido en cola.');

                    impresion_json_fail('id_apartado invalido en cola.', 500);

                }



                try {

                    $ticket = $ticketService->construirDesdeApartado($idApartadoCola, $modoApart);

                    $payload = $ticketService->construirEscPosApartadoBase64($idApartadoCola, $modoApart);

                } catch (Throwable $e) {

                    $cola->marcarError($idCola, $e->getMessage());

                    impresion_json_fail('No se pudo generar el ticket de apartado.', 500);

                }



                impresion_json_ok([

                    'data' => [

                        'id_cola_impresion' => $idCola,

                        'id_apartado' => $idApartadoCola,

                        'tipo' => $tipoTrabajo,

                        'destino' => 'ticket',

                        'escpos_base64' => $payload,

                        'ticket' => $ticket,

                    ],

                ]);

            }



            if ($tipoTrabajo === 'orden_taller') {

                $payloadOt = json_decode((string) ($trabajo['payload_json'] ?? ''), true);

                if (!is_array($payloadOt)) {

                    $cola->marcarError($idCola, 'Payload de orden de taller invalido.');

                    impresion_json_fail('Payload de orden de taller invalido.', 500);

                }

                $idOrdenTaller = (int) ($payloadOt['id_orden_taller'] ?? 0);

                if ($idOrdenTaller <= 0) {

                    $cola->marcarError($idCola, 'id_orden_taller invalido en cola.');

                    impresion_json_fail('id_orden_taller invalido en cola.', 500);

                }



                try {

                    $ticket = $ticketService->construirDesdeOrdenTaller($idOrdenTaller);

                    $payload = $ticketService->construirEscPosOrdenTallerBase64($idOrdenTaller);

                } catch (Throwable $e) {

                    $cola->marcarError($idCola, $e->getMessage());

                    impresion_json_fail('No se pudo generar el ticket de orden de taller.', 500);

                }



                impresion_json_ok([

                    'data' => [

                        'id_cola_impresion' => $idCola,

                        'id_orden_taller' => $idOrdenTaller,

                        'tipo' => $tipoTrabajo,

                        'destino' => 'ticket',

                        'escpos_base64' => $payload,

                        'ticket' => $ticket,

                    ],

                ]);

            }



            $idVenta = (int) ($trabajo['id_venta_FK'] ?? 0);

            try {

                $ticket = $ticketService->construirDesdeVenta($idVenta);

                $payload = $ticketService->construirEscPosBase64($idVenta);

            } catch (Throwable $e) {

                $cola->marcarError($idCola, $e->getMessage());

                impresion_json_fail('No se pudo generar el ticket.', 500);

            }



            impresion_json_ok([

                'data' => [

                    'id_cola_impresion' => $idCola,

                    'id_venta' => $idVenta,

                    'tipo' => $tipoTrabajo,

                    'destino' => 'ticket',

                    'escpos_base64' => $payload,

                    'ticket' => $ticket,

                ],

            ]);



        case 'confirmar':

            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {

                impresion_json_fail('Metodo no permitido.', 405);

            }

            $destino = impresion_destino_solicitado();

            if (!impresion_token_valido($config, $destino)) {

                impresion_json_fail('Token de caja invalido.', 401);

            }

            $body = impresion_json_body();

            $idCola = isset($body['id_cola_impresion']) ? (int) $body['id_cola_impresion'] : 0;

            if ($idCola <= 0) {

                impresion_json_fail('id_cola_impresion requerido.', 422);

            }

            if (!empty($body['ok'])) {

                $cola->marcarImpreso($idCola);

                impresion_json_ok(['message' => 'Trabajo marcado como impreso.']);

            }



            $mensaje = isset($body['mensaje']) ? trim((string) $body['mensaje']) : 'Error de impresion en agente.';

            $cola->incrementarIntento($idCola, $mensaje !== '' ? $mensaje : 'Error de impresion en agente.');

            impresion_json_ok(['message' => 'Error registrado.']);



        case 'encolar':

            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {

                impresion_json_fail('Metodo no permitido.', 405);

            }

            impresion_require_session();

            $body = impresion_json_body();

            $idVenta = isset($body['id_venta']) ? (int) $body['id_venta'] : 0;

            if ($idVenta <= 0) {

                impresion_json_fail('id_venta requerido.', 422);

            }

            if (!$ticketService->impresionHabilitada()) {

                impresion_json_fail('La impresion de tickets esta deshabilitada.', 422);

            }

            $venta = (new Ventas())->leerUno($idVenta);

            if (!$venta) {

                impresion_json_fail('Venta no encontrada.', 404);

            }

            $idApartadoFk = isset($venta['id_apartado_FK']) ? (int) $venta['id_apartado_FK'] : 0;

            $tipoTicket = isset($body['tipo_ticket']) ? strtolower(trim((string) $body['tipo_ticket'])) : 'auto';

            if ($tipoTicket !== 'venta' && $tipoTicket !== 'apartado_liquidacion') {

                $tipoTicket = 'auto';

            }

            if ($tipoTicket === 'auto') {

                $tipoTicket = $idApartadoFk > 0 ? 'apartado_liquidacion' : 'venta';

            }

            if ($tipoTicket === 'apartado_liquidacion') {

                if ($idApartadoFk <= 0) {

                    impresion_json_fail('Esta venta no esta ligada a un apartado; usa tipo_ticket=venta.', 422);

                }

                $agTk = new ApartadoGestion();

                $idTiendaTk = $agTk->obtenerIdTiendaPorApartado($idApartadoFk);

                $idCola = $cola->encolarTicketApartado($idApartadoFk, 'liquidacion', $idTiendaTk);

                impresion_json_ok([

                    'message' => 'Ticket de liquidacion de apartado encolado para reimpresion.',

                    'id_cola_impresion' => $idCola,

                    'tipo' => 'apartado_liquidacion',

                    'id_apartado' => $idApartadoFk,

                ], 201);

            }

            $idCola = $cola->encolar($idVenta, 'reimpresion');

            impresion_json_ok(['message' => 'Ticket de venta encolado para reimpresion.', 'id_cola_impresion' => $idCola, 'tipo' => 'reimpresion'], 201);



        case 'encolar_orden_taller':

            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {

                impresion_json_fail('Metodo no permitido.', 405);

            }

            impresion_require_session();

            $body = impresion_json_body();

            $idOrdenTaller = isset($body['id_orden_taller']) ? (int) $body['id_orden_taller'] : 0;

            if ($idOrdenTaller <= 0) {

                impresion_json_fail('id_orden_taller requerido.', 422);

            }

            if (!$ticketService->impresionHabilitada()) {

                impresion_json_fail('La impresion de tickets esta deshabilitada.', 422);

            }

            $ordenTk = (new OrdenesTaller())->leerUno($idOrdenTaller);

            if (!$ordenTk) {

                impresion_json_fail('Orden de taller no encontrada.', 404);

            }

            $idCola = $cola->encolarTicketOrdenTaller($idOrdenTaller, joyeria_id_tienda_cola_impresion(null));

            impresion_json_ok([

                'message' => 'Ticket de orden de taller encolado para impresion.',

                'id_cola_impresion' => $idCola,

                'tipo' => 'orden_taller',

                'id_orden_taller' => $idOrdenTaller,

            ], 201);



        case 'encolar_etiquetas':

            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {

                impresion_json_fail('Metodo no permitido.', 405);

            }

            impresion_require_session();

            if (!$etiquetaService->impresionHabilitada()) {

                impresion_json_fail('La impresion de etiquetas esta deshabilitada.', 422);

            }

            $body = impresion_json_body();

            $idTienda = isset($body['id_tienda']) ? (int) $body['id_tienda'] : null;

            if ($idTienda !== null && $idTienda <= 0) {

                $idTienda = null;

            }



            $idsInsumoExpandidos = [];
            $copiasPorIdInsumo = null;

            if (!empty($body['items']) && is_array($body['items'])) {
                $expInsumo = joyeria_expandir_ids_insumo_etiquetas($body['items']);
                $idsInsumoExpandidos = $expInsumo['ids'];
                $copiasPorIdInsumo = $expInsumo['copias_por_id'];
            } elseif (!empty($body['ids_insumo']) && is_array($body['ids_insumo'])) {
                $expInsumo = joyeria_expandir_ids_insumo_etiquetas($body['ids_insumo']);
                $idsInsumoExpandidos = $expInsumo['ids'];
                $copiasPorIdInsumo = $expInsumo['copias_por_id'];
            }

            $idsStock = [];

            if (!empty($body['ids']) && is_array($body['ids'])) {

                foreach ($body['ids'] as $id) {

                    $n = (int) $id;

                    if ($n > 0) {

                        $idsStock[] = $n;

                    }

                }

            } elseif (!empty($body['ids_pieza_stock']) && is_array($body['ids_pieza_stock'])) {

                foreach ($body['ids_pieza_stock'] as $id) {

                    $n = (int) $id;

                    if ($n > 0) {

                        $idsStock[] = $n;

                    }

                }

            }

            if ($idsInsumoExpandidos !== [] && $idsStock !== []) {
                impresion_json_fail('No mezcles etiquetas de insumos y de piezas en el mismo encolado.', 422);
            }

            if ($idsInsumoExpandidos !== []) {
                try {
                    $idCola = joyeria_encolar_etiquetas_insumo($idsInsumoExpandidos, $idTienda, $copiasPorIdInsumo);
                } catch (RuntimeException $e) {
                    impresion_json_fail($e->getMessage(), 500);
                } catch (InvalidArgumentException $e) {
                    impresion_json_fail($e->getMessage(), 422);
                }

                impresion_json_ok([
                    'message' => 'Etiquetas de insumos encoladas para impresion.',
                    'id_cola_impresion' => $idCola,
                    'cantidad' => count($idsInsumoExpandidos),
                ], 201);
            }

            $ids = $idsStock;

            if ($ids === []) {

                $idPieza = (int) ($body['id_pieza'] ?? 0);

                $desde = (int) ($body['desde'] ?? 0);

                $hasta = (int) ($body['hasta'] ?? 0);

                if ($idPieza > 0 && $desde > 0 && $hasta > 0) {
                    $soloDisponibles = !empty($body['solo_disponibles']);
                    try {
                        $idCola = joyeria_encolar_etiquetas_rango($idPieza, $desde, $hasta, $idTienda, $soloDisponibles);
                    } catch (InvalidArgumentException $e) {
                        impresion_json_fail($e->getMessage(), 422);
                    } catch (RuntimeException $e) {
                        impresion_json_fail($e->getMessage(), 500);
                    }

                    $estado = $cola->estadoPorId($idCola);

                    $cantidad = $cola->contarEtiquetasEnPayload($estado['payload_json'] ?? null);

                    impresion_json_ok([

                        'message' => 'Etiquetas encoladas para impresion.',

                        'id_cola_impresion' => $idCola,

                        'cantidad' => $cantidad,

                    ], 201);

                }

                impresion_json_fail('Indica ids de stock, id_pieza con desde/hasta, o ids_insumo/items de insumo.', 422);

            }



            try {
                $idCola = joyeria_encolar_etiquetas_stock($ids, $idTienda);
            } catch (RuntimeException $e) {
                impresion_json_fail($e->getMessage(), 500);
            } catch (InvalidArgumentException $e) {
                impresion_json_fail($e->getMessage(), 422);
            }

            impresion_json_ok([

                'message' => 'Etiquetas encoladas para impresion.',

                'id_cola_impresion' => $idCola,

                'cantidad' => count($ids),

            ], 201);



        case 'estado':

            impresion_require_session();

            $idCola = isset($_GET['id_cola_impresion']) ? (int) $_GET['id_cola_impresion'] : 0;

            if ($idCola > 0) {

                $estado = $cola->estadoPorId($idCola);

                if (!$estado) {

                    impresion_json_fail('Trabajo de cola no encontrado.', 404);

                }

                $estado['cantidad_etiquetas'] = $cola->contarEtiquetasEnPayload($estado['payload_json'] ?? null);

                impresion_json_ok(['data' => $estado]);

            }

            $idVenta = isset($_GET['id_venta']) ? (int) $_GET['id_venta'] : 0;

            if ($idVenta <= 0) {

                impresion_json_fail('Debes indicar id_venta o id_cola_impresion.', 422);

            }

            $estado = $cola->estadoPorVenta($idVenta);

            if (!$estado) {

                impresion_json_ok(['data' => ['estado' => 'sin_cola']]);

            }

            impresion_json_ok(['data' => $estado]);



        default:

            impresion_json_fail('Accion no soportada.', 404);

    }

} catch (Throwable $e) {

    error_log('impresion.php: ' . $e->getMessage());

    impresion_json_fail('Error interno del servidor.', 500);

}

