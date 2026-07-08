<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/apartado_gestion.php';
require_once __DIR__ . '/../models/ventas.php';
require_once __DIR__ . '/../includes/ImpresionTicketHelper.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$app = new ApartadoGestion();
$ventas = new Ventas();

try {
    if ($method === 'GET') {
        if (!auth_has_permission('APARTADO_GESTION_LEER') && !auth_has_permission('APARTADO_CAMBIO_LEER')) {
            api_fail('No tienes permiso para consultar apartados.', 403);
        }

        $codigoPieza = isset($_GET['codigo_pieza']) ? trim((string) $_GET['codigo_pieza']) : '';
        if ($codigoPieza !== '') {
            api_ok(['data' => $app->vistaPreviaPiezaPorCodigo($codigoPieza)]);
        }

        $idApartado = isset($_GET['id_apartado']) ? (int) $_GET['id_apartado'] : 0;
        if ($idApartado > 0) {
            api_ok(['data' => $app->leerApartadoCompleto($idApartado)]);
        }

        $estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';
        $lim = isset($_GET['limit']) ? (int) $_GET['limit'] : 150;
        $idCliente = isset($_GET['id_cliente']) ? (int) $_GET['id_cliente'] : 0;
        $idClienteF = $idCliente > 0 ? $idCliente : null;
        api_ok(['data' => $app->listarApartados($lim, $estado !== '' ? $estado : null, $idClienteF)]);
    }

    if ($method === 'POST') {
        $data = api_json_body();
        $tipo = mb_strtolower(trim((string) ($data['tipo'] ?? 'crear')));

        $usuario = auth_user();
        $idUsuario = is_array($usuario) ? (int) ($usuario['id_usuario'] ?? 0) : 0;
        if ($idUsuario <= 0) {
            api_fail('Sesion invalida.', 401);
        }

        if ($tipo === 'preview_totales') {
            if (!auth_has_permission('APARTADO_GESTION_LEER') && !auth_has_permission('APARTADO_GESTION_CREAR')) {
                api_fail('No tienes permiso para calcular totales de apartado.', 403);
            }
            $idCliente = (int) ($data['id_cliente_FK'] ?? 0);
            $idImpuesto = (int) ($data['id_impuesto_FK'] ?? 0);
            if ($idImpuesto <= 0) {
                $idImpuesto = (int) ($ventas->obtenerIdImpuestoDefault() ?? 0);
            }
            $lineas = $data['lineas'] ?? [];
            if (!is_array($lineas)) {
                $lineas = [];
            }
            $out = $app->calcularTotalesApartado($idCliente, $idImpuesto, $lineas);
            api_ok(['data' => $out]);
        }

        if ($tipo === 'crear') {
            if (!auth_has_permission('APARTADO_GESTION_CREAR')) {
                api_fail('No tienes permiso para crear apartados.', 403);
            }
            $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
            if ($idEmpleado <= 0) {
                $idEmpleado = (int) ($ventas->obtenerEmpleadoIdPorUsuario($idUsuario) ?? 0);
            }
            if ($idEmpleado <= 0) {
                api_fail('Se requiere id_empleado_FK o empleado activo vinculado al usuario.', 422);
            }
            $payload = $data;
            $payload['id_empleado_FK'] = $idEmpleado;
            $payload['id_usuario_FK'] = $idUsuario;
            if (isset($payload['abono_credito_monto']) && (float) $payload['abono_credito_monto'] > 0
                && !auth_has_permission('CLIENTE_CREDITO_APLICAR')) {
                api_fail('No tienes permiso para aplicar credito a favor del cliente.', 403);
            }
            $out = $app->crearApartado($payload);
            $idApartadoCreado = (int) ($out['id_apartado'] ?? 0);
            $idTiendaTk = $idApartadoCreado > 0 ? $app->obtenerIdTiendaPorApartado($idApartadoCreado) : null;
            $idColaTk = $idApartadoCreado > 0
                ? joyeria_encolar_ticket_apartado($idApartadoCreado, 'alta', $idTiendaTk)
                : null;
            $out['id_cola_impresion'] = $idColaTk;
            $out['impresion_encolada'] = $idColaTk !== null;

            api_ok(['message' => 'Apartado registrado.', 'data' => $out], 201);
        }

        if ($tipo === 'abono') {
            if (!auth_has_permission('APARTADO_GESTION_ACTUALIZAR')) {
                api_fail('No tienes permiso para registrar abonos.', 403);
            }
            $usarCredito = !empty($data['usar_credito_cliente']);
            if ($usarCredito && !auth_has_permission('CLIENTE_CREDITO_APLICAR')) {
                api_fail('No tienes permiso para aplicar credito a favor del cliente.', 403);
            }
            $idEmpleadoAbono = (int) ($data['id_empleado_FK'] ?? 0);
            if ($idEmpleadoAbono <= 0) {
                $idEmpleadoAbono = (int) ($ventas->obtenerEmpleadoIdPorUsuario($idUsuario) ?? 0);
            }
            $payload = [
                'id_apartado_FK' => (int) ($data['id_apartado_FK'] ?? 0),
                'monto' => $data['monto'] ?? null,
                'id_forma_pago_FK' => (int) ($data['id_forma_pago_FK'] ?? 0),
                'id_usuario_FK' => $idUsuario,
                'id_empleado_FK' => $idEmpleadoAbono,
                'usar_credito_cliente' => $usarCredito,
            ];
            $out = $app->registrarAbono($payload);
            $idAp = (int) ($payload['id_apartado_FK'] ?? 0);
            $idTiendaTk = $idAp > 0 ? $app->obtenerIdTiendaPorApartado($idAp) : null;
            $idColaTk = $idAp > 0 ? joyeria_encolar_ticket_apartado($idAp, 'abono', $idTiendaTk) : null;
            $out['id_cola_impresion'] = $idColaTk;
            $out['impresion_encolada'] = $idColaTk !== null;
            $out['ticket_modo'] = 'abono';

            api_ok(['message' => 'Abono registrado.', 'data' => $out], 201);
        }

        if ($tipo === 'quitar_pieza') {
            if (!auth_has_permission('APARTADO_GESTION_QUITAR_PIEZA')) {
                api_fail('No tienes permiso para quitar piezas de apartados.', 403);
            }
            $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
            if ($idEmpleado <= 0) {
                $idEmpleado = (int) ($ventas->obtenerEmpleadoIdPorUsuario($idUsuario) ?? 0);
            }
            if ($idEmpleado <= 0) {
                api_fail('Se requiere id_empleado_FK o empleado activo vinculado al usuario.', 422);
            }

            $payload = [
                'id_apartado_FK' => (int) ($data['id_apartado_FK'] ?? 0),
                'id_apartado_detalle' => (int) ($data['id_apartado_detalle'] ?? 0),
                'id_usuario_FK' => $idUsuario,
                'id_empleado_FK' => $idEmpleado,
                'observaciones' => $data['observaciones'] ?? null,
            ];
            $out = $app->quitarPiezaDelApartado($payload);

            $msg = 'Pieza quitada del apartado. Saldo recalculado.';
            $estado = (string) ($out['estado'] ?? 'activo');
            if ($estado === 'cancelado') {
                $msg = 'Apartado cancelado (sin piezas). ';
                $msg .= ((float) ($out['excedente'] ?? 0) > 0)
                    ? 'Los abonos se acreditaron como credito a favor del cliente.'
                    : 'No habia abonos por reembolsar.';
            } elseif ($estado === 'liquidado' && (float) ($out['excedente'] ?? 0) > 0) {
                $msg = 'Apartado liquidado. Excedente $' . $out['excedente'] . ' acreditado como credito al cliente.';
            } elseif ($estado === 'liquidado') {
                $msg = 'Apartado liquidado tras quitar la pieza (saldo cubierto).';
            }

            api_ok(['message' => $msg, 'data' => $out], 200);
        }

        if ($tipo === 'agregar_pieza') {
            if (!auth_has_permission('APARTADO_GESTION_AGREGAR_PIEZA')) {
                api_fail('No tienes permiso para agregar piezas a apartados.', 403);
            }
            $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
            if ($idEmpleado <= 0) {
                $idEmpleado = (int) ($ventas->obtenerEmpleadoIdPorUsuario($idUsuario) ?? 0);
            }
            if ($idEmpleado <= 0) {
                api_fail('Se requiere id_empleado_FK o empleado activo vinculado al usuario.', 422);
            }

            $payload = [
                'id_apartado_FK' => (int) ($data['id_apartado_FK'] ?? 0),
                'id_pieza_stock_FK' => (int) ($data['id_pieza_stock_FK'] ?? 0),
                'codigo_pieza' => $data['codigo_pieza'] ?? '',
                'precio_apartado' => $data['precio_apartado'] ?? null,
                'id_usuario_FK' => $idUsuario,
                'id_empleado_FK' => $idEmpleado,
                'observaciones' => $data['observaciones'] ?? null,
            ];
            $out = $app->agregarPiezaAlApartado($payload);

            api_ok(['message' => 'Pieza agregada al apartado. Total y saldo recalculados.', 'data' => $out], 201);
        }

        api_fail('tipo debe ser preview_totales, crear, abono, quitar_pieza o agregar_pieza.', 422);
    }

    api_fail('Metodo no soportado.', 405);
} catch (InvalidArgumentException $e) {
    api_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
