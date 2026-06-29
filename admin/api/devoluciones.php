<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/devoluciones.php';
require_once __DIR__ . '/../models/ventas.php';

header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$app = new Devoluciones();

try {
    if ($method === 'GET') {
        if (!auth_has_permission('DEVOLUCION_LEER')) {
            api_fail('No tienes permiso para consultar devoluciones.', 403);
        }
        $idVenta = isset($_GET['id_venta']) ? (int) $_GET['id_venta'] : 0;
        if ($idVenta > 0) {
            api_ok(['data' => $app->listarPorVenta($idVenta)]);
        }

        if (isset($_GET['preview']) && (string) $_GET['preview'] === '1') {
            if (!auth_has_permission('DEVOLUCION_CREAR')
                && !auth_has_permission('DEVOLUCION_CREDITO_MONEDERO')
                && !auth_has_permission('DEVOLUCION_REEMBOLSO_EFECTIVO')) {
                api_fail('No tienes permiso para previsualizar devoluciones.', 403);
            }
            $ventasApp = new Ventas();
            $usuarioSesion = auth_user();
            $idUsuario = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
            $idEmpleado = $idUsuario > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuario) : null;
            if ($idEmpleado === null) {
                api_fail('Tu usuario no esta vinculado a un empleado activo.', 422);
            }
            $codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
            $idCliente = isset($_GET['id_cliente']) ? (int) $_GET['id_cliente']
                : (isset($_GET['id_cliente_FK']) ? (int) $_GET['id_cliente_FK'] : 0);
            $idVentaPrev = isset($_GET['id_venta_FK']) ? (int) $_GET['id_venta_FK']
                : (isset($_GET['id_venta']) ? (int) $_GET['id_venta'] : 0);
            $idPs = isset($_GET['id_pieza_stock_FK']) ? (int) $_GET['id_pieza_stock_FK'] : 0;
            $preview = $app->prepararDevolucionUnificada(
                $codigo,
                $idCliente > 0 ? $idCliente : null,
                $idVentaPrev > 0 ? $idVentaPrev : null,
                $idEmpleado,
                $idPs > 0 ? $idPs : null
            );
            $preview['permisos'] = [
                'monedero' => auth_has_permission('DEVOLUCION_CREDITO_MONEDERO'),
                'reembolso' => auth_has_permission('DEVOLUCION_REEMBOLSO_EFECTIVO'),
                'crear' => auth_has_permission('DEVOLUCION_CREAR'),
            ];
            api_ok(['data' => $preview]);
        }

        if (isset($_GET['preview_monedero']) && (string) $_GET['preview_monedero'] === '1') {
            if (!auth_has_permission('DEVOLUCION_CREAR') && !auth_has_permission('DEVOLUCION_CREDITO_MONEDERO')) {
                api_fail('No tienes permiso para previsualizar devoluciones con monedero.', 403);
            }
            $idCliente = isset($_GET['id_cliente']) ? (int) $_GET['id_cliente'] : 0;
            $idVentaPrev = isset($_GET['id_venta_FK']) ? (int) $_GET['id_venta_FK'] : (isset($_GET['id_venta']) ? (int) $_GET['id_venta'] : 0);
            $codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
            $idPs = isset($_GET['id_pieza_stock_FK']) ? (int) $_GET['id_pieza_stock_FK'] : 0;
            $ventasApp = new Ventas();
            $usuarioSesion = auth_user();
            $idUsuario = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
            $idEmpleado = $idUsuario > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuario) : null;
            if ($idEmpleado === null) {
                api_fail('Tu usuario no esta vinculado a un empleado activo.', 422);
            }
            if ($idCliente <= 0) {
                api_fail('Falta id_cliente para la vista previa del monedero.', 422);
            }
            $preview = $app->prepararDevolucionMonedero(
                $idVentaPrev,
                $codigo,
                $idCliente,
                $idEmpleado,
                $idPs > 0 ? $idPs : null
            );
            api_ok(['data' => $preview]);
        }

        $lim = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
        api_ok(['data' => $app->listarRecientes($lim)]);
    }

    if ($method === 'POST') {
        $data = api_json_body();
        $tipoRaw = isset($data['tipo']) ? trim((string) $data['tipo']) : '';
        $tipo = $tipoRaw !== '' ? mb_strtolower($tipoRaw) : '';

        if ($tipo === '') {
            api_fail(
                'Falta el campo tipo. Para esta pantalla envia tipo: mostrador o monedero (JSON).',
                422
            );
        }

        if ($tipo === 'preview_monedero') {
            if (!auth_has_permission('DEVOLUCION_CREAR') && !auth_has_permission('DEVOLUCION_CREDITO_MONEDERO')) {
                api_fail('No tienes permiso para previsualizar devoluciones con monedero.', 403);
            }
            $ventasApp = new Ventas();
            $usuarioSesion = auth_user();
            $idUsuario = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
            $idEmpleado = $idUsuario > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuario) : null;
            if ($idEmpleado === null) {
                api_fail('Tu usuario no esta vinculado a un empleado activo.', 422);
            }
            $idCliente = (int) ($data['id_cliente_FK'] ?? 0);
            if ($idCliente <= 0) {
                api_fail('Falta id_cliente_FK.', 422);
            }
            $preview = $app->prepararDevolucionMonedero(
                (int) ($data['id_venta_FK'] ?? $data['id_venta'] ?? 0),
                trim((string) ($data['codigo'] ?? '')),
                $idCliente,
                $idEmpleado,
                isset($data['id_pieza_stock_FK']) && (int) $data['id_pieza_stock_FK'] > 0
                    ? (int) $data['id_pieza_stock_FK']
                    : null
            );
            api_ok(['data' => $preview]);
        }

        if ($tipo === 'monedero') {
            if (!auth_has_permission('DEVOLUCION_CREAR') || !auth_has_permission('DEVOLUCION_CREDITO_MONEDERO')) {
                api_fail('No tienes permiso para registrar devoluciones con credito al monedero.', 403);
            }
            $ventasApp = new Ventas();
            $usuarioSesion = auth_user();
            $idUsuario = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
            $idEmpleado = $idUsuario > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuario) : null;
            if ($idEmpleado === null) {
                api_fail('Tu usuario no esta vinculado a un empleado activo.', 422);
            }
            $data['id_empleado_FK'] = $idEmpleado;
            $data['id_usuario_FK'] = $idUsuario;
            $out = $app->registrarDevolucionConMonedero($data);
            api_ok([
                'message' => 'Devolucion registrada. Credito $' . ($out['monto_credito'] ?? '0')
                    . ' en monedero (saldo total $' . ($out['monedero_saldo_disponible'] ?? '0') . ').',
                'data' => $out,
            ], 201);
        }

        if ($tipo === 'mostrador') {
            if (!auth_has_permission('DEVOLUCION_CREAR')) {
                api_fail('No tienes permiso para registrar devoluciones.', 403);
            }
            if (!empty($data['acreditar_monedero']) && !auth_has_permission('DEVOLUCION_CREDITO_MONEDERO')) {
                api_fail('No tienes permiso para acreditar el monedero del cliente.', 403);
            }
            $ventasApp = new Ventas();
            $usuarioSesion = auth_user();
            $idUsuario = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
            if (!empty($data['acreditar_monedero'])) {
                $data['id_usuario_FK'] = $idUsuario;
            }
            if (empty($data['id_empleado_FK'])) {
                $idEmpleado = $idUsuario > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuario) : null;
                if ($idEmpleado !== null) {
                    $data['id_empleado_FK'] = $idEmpleado;
                }
            }
            $out = $app->registrarDevolucionMostrador($data);
            $msg = 'Devolucion de mostrador registrada.';
            if (!empty($out['id_credito'])) {
                $msg .= ' Credito al monedero: $' . ($out['monto_reembolso'] ?? '0')
                    . ' (saldo total $' . ($out['monedero_saldo_disponible'] ?? '0') . ').';
            }
            api_ok(['message' => $msg, 'data' => $out], 201);
        }

        if ($tipo === 'devolucion') {
            if (!auth_has_permission('DEVOLUCION_CREAR')) {
                api_fail('No tienes permiso para registrar devoluciones.', 403);
            }
            $modo = strtolower(trim((string) ($data['modo'] ?? '')));
            if ($modo === 'monedero' && !auth_has_permission('DEVOLUCION_CREDITO_MONEDERO')) {
                api_fail('No tienes permiso para acreditar el monedero del cliente.', 403);
            }
            if (($modo === 'efectivo' || $modo === 'otra_forma') && !auth_has_permission('DEVOLUCION_REEMBOLSO_EFECTIVO')) {
                api_fail('No tienes permiso para registrar reembolsos que afectan caja.', 403);
            }
            $ventasApp = new Ventas();
            $usuarioSesion = auth_user();
            $idUsuario = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
            $idEmpleado = $idUsuario > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuario) : null;
            if ($idEmpleado === null) {
                api_fail('Tu usuario no esta vinculado a un empleado activo.', 422);
            }
            $data['id_empleado_FK'] = $idEmpleado;
            $data['id_usuario_FK'] = $idUsuario;
            $out = $app->registrarDevolucionUnificada($data);

            $modoOut = (string) ($out['modo'] ?? $modo);
            switch ($modoOut) {
                case 'monedero':
                    $msg = 'Credito en monedero por $' . ($out['monto_credito'] ?? '0')
                        . ' (saldo total $' . ($out['monedero_saldo_disponible'] ?? '0') . ').';
                    break;
                case 'efectivo':
                    $msg = 'Reembolso en efectivo registrado por $' . ($out['monto_reembolso'] ?? '0') . '. Afecta caja del dia.';
                    break;
                case 'otra_forma':
                    $msg = 'Reembolso registrado por $' . ($out['monto_reembolso'] ?? '0') . ' en la forma seleccionada.';
                    break;
                case 'solo_inventario':
                    $msg = 'Pieza reingresada al inventario (sin reembolso ni credito).';
                    break;
                default:
                    $msg = 'Devolucion registrada.';
            }

            api_ok(['message' => $msg, 'data' => $out], 201);
        }

        if ($tipo === 'venta') {
            api_fail(
                'Las devoluciones con ticket ya no se registran con tipo "venta". Usa tipo "devolucion" con el modo correspondiente (efectivo, otra_forma, monedero, solo_inventario).',
                422
            );
        }
        api_fail('El valor de tipo no es valido. Usa: devolucion, mostrador, monedero o preview_monedero.', 422);
    }

    api_fail('Metodo no soportado.', 405);
} catch (InvalidArgumentException $e) {
    api_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
