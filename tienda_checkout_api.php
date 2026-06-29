<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/includes/tienda_auth.php';
require_once __DIR__ . '/admin/models/carrito.php';
require_once __DIR__ . '/admin/models/venta_online.php';
require_once __DIR__ . '/admin/includes/MercadoPagoService.php';

header('Content-Type: application/json; charset=utf-8');

function joyeria_tco_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function joyeria_public_base_url_checkout(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    return $scheme . '://' . $host . $scriptDir;
}

if (!tienda_is_logged_in()) {
    joyeria_tco_out(['ok' => false, 'login_required' => true, 'error' => 'Debes iniciar sesion.']);
}

$user = tienda_auth_user();
$idCliente = (int) ($user['id_cliente'] ?? 0);
$body = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($body)) $body = [];
$action = (string) ($body['action'] ?? $_GET['action'] ?? '');

try {
    if ($action === 'iniciar') {
        $aceptacion = !empty($body['aceptacion_entrega_tienda']);
        if (!$aceptacion) {
            joyeria_tco_out(['ok' => false, 'error' => 'Debes aceptar la entrega en tienda.']);
        }

        $carrito = new Carrito();
        // TTL extendido (90 min) durante checkout: cubre pagos lentos con 3DS/OTP.
        $carrito->refrescarReservas($idCliente, Carrito::RESERVA_TTL_CHECKOUT_MINUTOS);

        $items = $carrito->listar($idCliente);
        if ($items === []) {
            joyeria_tco_out(['ok' => false, 'error' => 'Tu carrito esta vacio.']);
        }

        // Antes de mandar a MP, validar que TODAS las piezas del carrito sigan
        // reservadas para este cliente. Si alguna se perdio (vendida en sucursal,
        // robada por otro cliente o reserva expirada y liberada), abortamos con
        // el detalle para que el frontend muestre que piezas remover.
        $validacion = $carrito->validarReservasIntactas($idCliente);
        if (!$validacion['ok']) {
            $perdidas = $validacion['perdidas'] ?? [];
            $detalles = [];
            foreach ($perdidas as $p) {
                $detalles[] = $p['desc'] . ' — ' . $p['motivo'];
            }
            joyeria_tco_out([
                'ok' => false,
                'error' => 'Algunas piezas de tu carrito ya no estan disponibles. Reviselo y vuelva a intentar.',
                'piezas_perdidas' => $perdidas,
                'detalle' => implode(' | ', $detalles),
            ]);
        }

        $creditoSolicitado = max(0.0, (float) ($body['credito_aplicado'] ?? 0));

        $ventaOnline = new VentaOnline();
        $r = $ventaOnline->crearPedidoPendiente($idCliente, true, $creditoSolicitado);
        if (!$r['ok']) {
            joyeria_tco_out(['ok' => false, 'error' => $r['error'] ?? 'No se pudo crear el pedido.']);
        }
        $idVenta = (int) $r['id_venta'];
        $creditoAplicado = (float) ($r['credito_aplicado'] ?? 0);
        $montoAPagar = (float) ($r['monto_a_pagar'] ?? ($r['total'] ?? 0));

        $base = trim((string) (defined('JOYERIA_MP_RETURN_BASE') ? JOYERIA_MP_RETURN_BASE : ''));
        if ($base === '') {
            $base = joyeria_public_base_url_checkout();
        }
        $base = rtrim($base, '/');

        // Credito cubre el total: marcar pagada sin pasar por Mercado Pago.
        if ($montoAPagar <= 0.009) {
            $resPago = $ventaOnline->marcarPagada($idVenta, 'credito_cliente', 'credito_cliente');
            if (!$resPago['ok']) {
                $ventaOnline->marcarRechazada($idVenta);
                joyeria_tco_out(['ok' => false, 'error' => $resPago['error'] ?? 'No se pudo confirmar el pago con credito.']);
            }
            try {
                require_once __DIR__ . '/admin/includes/NotificacionService.php';
                (new NotificacionService())->notificarVentaOnline($idVenta);
            } catch (Throwable $e) {
                error_log('tienda_checkout_api notif venta credito: ' . $e->getMessage());
            }
            joyeria_tco_out([
                'ok' => true,
                'id_venta' => $idVenta,
                'pagado_con_credito' => true,
                'credito_aplicado' => $creditoAplicado,
                'redirect' => $base . '/user/checkout_resultado.php?venta=' . $idVenta . '&estado=success',
            ]);
        }

        if (!MercadoPagoService::isConfigured()) {
            $ventaOnline->marcarRechazada($idVenta);
            joyeria_tco_out(['ok' => false, 'error' => 'Mercado Pago no esta configurado en el servidor.']);
        }

        // Construir items para MP
        $mpItems = [];
        if ($creditoAplicado > 0.009) {
            $mpItems[] = [
                'id' => (string) $idVenta,
                'title' => 'Pedido #' . $idVenta . ' (credito aplicado: $' . number_format($creditoAplicado, 2, '.', ',') . ')',
                'description' => 'Compra en linea con entrega en tienda',
                'quantity' => 1,
                'currency_id' => 'MXN',
                'unit_price' => round($montoAPagar, 2),
            ];
        } else {
            foreach ($items as $it) {
                $mpItems[] = [
                    'id' => (string) $it['id_pieza_stock_FK'],
                    'title' => (string) ($it['desc_pieza'] ?? 'Joya') . ' (' . (string) ($it['nom_metal'] ?? '') . ')',
                    'description' => 'Entrega en tienda: ' . (string) ($it['nom_tienda'] ?? ''),
                    'quantity' => 1,
                    'currency_id' => 'MXN',
                    'unit_price' => (float) $it['precio_unitario_snapshot'],
                ];
            }
        }

        // MP requiere payer.email para habilitar el boton Pagar. Sin email,
        // Checkout Pro deja el boton apagado aunque el TESTUSER este logueado.
        // En sandbox usamos un email generado para evitar el bloqueo de
        // "cannot_pay_yourself" cuando el correo del cliente coincide con la
        // cuenta vendedora. En produccion enviamos el correo real del cliente.
        $modoMpActual = defined('JOYERIA_MP_MODO')
            ? strtolower(trim((string) JOYERIA_MP_MODO))
            : 'sandbox';
        $payer = [
            'name' => (string) ($user['nombre'] ?? ''),
        ];
        if ($modoMpActual === 'sandbox') {
            $payer['email'] = 'comprador-prueba-' . $idVenta . '@testuser.com';
        } else {
            $correoCliente = trim((string) ($user['correo'] ?? ''));
            if ($correoCliente !== '') {
                $payer['email'] = $correoCliente;
            }
        }

        $backUrls = [
            'success' => $base . '/user/checkout_resultado.php?venta=' . $idVenta . '&estado=success',
            'failure' => $base . '/user/checkout_resultado.php?venta=' . $idVenta . '&estado=failure',
            'pending' => $base . '/user/checkout_resultado.php?venta=' . $idVenta . '&estado=pending',
        ];

        $notifUrl = trim((string) (defined('JOYERIA_MP_NOTIFICATION_URL') ? JOYERIA_MP_NOTIFICATION_URL : ''));
        if ($notifUrl === '') {
            $notifUrl = $base . '/tienda_pago_webhook.php';
        }

        $mp = new MercadoPagoService();
        $pref = $mp->crearPreference($idVenta, $mpItems, $payer, $backUrls, $notifUrl);
        if (!$pref['ok']) {
            $ventaOnline->marcarRechazada($idVenta);
            joyeria_tco_out(['ok' => false, 'error' => $pref['error'] ?? 'No se pudo iniciar el pago.']);
        }

        // Guardar referencia de la preferencia
        $ventaOnline->registrarReferenciaPago($idVenta, (string) ($pref['id'] ?? ''), 'preference');

        // MP recomienda usar siempre init_point con credenciales de prueba
        // (www.mercadopago.com.mx). El sandbox_init_point usa el subdominio
        // sandbox.mercadopago.com.mx, que sufre bucle de cookies cross-domain en
        // Chrome con bloqueo de terceros activado. init_point + credenciales TEST
        // = mismo comportamiento sandbox sin el loop.
        $redirect = !empty($pref['init_point'])
            ? $pref['init_point']
            : (string) ($pref['sandbox_init_point'] ?? '');

        joyeria_tco_out([
            'ok' => true,
            'id_venta' => $idVenta,
            'credito_aplicado' => $creditoAplicado,
            'monto_a_pagar' => $montoAPagar,
            'redirect' => $redirect,
        ]);
    }

    if ($action === 'estado_venta') {
        $idVenta = (int) ($body['id_venta'] ?? 0);
        if ($idVenta <= 0) joyeria_tco_out(['ok' => false, 'error' => 'id_venta invalido']);
        $vo = new VentaOnline();
        $venta = $vo->leerUno($idVenta);
        if (!$venta || (int) $venta['id_cliente_FK'] !== $idCliente) {
            joyeria_tco_out(['ok' => false, 'error' => 'No autorizado']);
        }
        joyeria_tco_out([
            'ok' => true,
            'estado_pago' => (string) ($venta['estado_pago'] ?? ''),
            'estado_entrega' => (string) ($venta['estado_entrega'] ?? ''),
        ]);
    }

    joyeria_tco_out(['ok' => false, 'error' => 'Accion no soportada.']);
} catch (Throwable $e) {
    error_log('tienda_checkout_api: ' . $e->getMessage());
    http_response_code(500);
    joyeria_tco_out(['ok' => false, 'error' => 'Error interno']);
}
