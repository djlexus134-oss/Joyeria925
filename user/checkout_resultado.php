<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/joyeria_branding.php';
require_once __DIR__ . '/../admin/includes/tienda_auth.php';
require_once __DIR__ . '/../admin/models/venta_online.php';
require_once __DIR__ . '/../admin/includes/MercadoPagoService.php';
require_once __DIR__ . '/../admin/includes/NotificacionService.php';

if (!tienda_is_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$tiendaUser = tienda_auth_user();
$idCliente = (int) ($tiendaUser['id_cliente'] ?? 0);

$idVenta = isset($_GET['venta']) ? (int) $_GET['venta'] : 0;
$estadoGet = isset($_GET['estado']) ? (string) $_GET['estado'] : 'success';
$estadoGet = in_array($estadoGet, ['success', 'failure', 'pending'], true) ? $estadoGet : 'success';

$mpStatus = (string) ($_GET['status'] ?? $_GET['collection_status'] ?? '');
$mpStatusDetail = (string) ($_GET['status_detail'] ?? '');
$mpPaymentId = (string) ($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');

$venta = null;
if ($idVenta > 0) {
    $modelo = new VentaOnline();
    $venta = $modelo->leerUno($idVenta);
    if (!is_array($venta) || (int) ($venta['id_cliente_FK'] ?? 0) !== $idCliente) {
        $venta = null;
    }
}

// Fallback al webhook: si MP nos rebota con status=approved y un payment_id,
// consultamos directamente a la API y marcamos pagada la venta. Asi el cliente
// ve "Pagado" inmediatamente sin depender de que MP llame a tienda_pago_webhook.php.
if ($venta && $idVenta > 0
    && ($mpStatus === 'approved' || $estadoGet === 'success')
    && $mpPaymentId !== ''
    && ($venta['estado_pago'] ?? '') !== 'pagado'
) {
    try {
        $mpSvc = new MercadoPagoService();
        if (MercadoPagoService::isConfigured()) {
            $resp = $mpSvc->verificarPago($mpPaymentId);
            if (!empty($resp['ok']) && is_array($resp['data'] ?? null)) {
                $pago = $resp['data'];
                $estadoMp = (string) ($pago['status'] ?? '');
                $extRef = (string) ($pago['external_reference'] ?? '');
                $idVentaMp = 0;
                if ($extRef !== '' && preg_match('/^venta_(\d+)$/', $extRef, $mRef)) {
                    $idVentaMp = (int) $mRef[1];
                }
                if ($idVentaMp === $idVenta && $estadoMp === 'approved') {
                    $resMark = $modelo->marcarPagada(
                        $idVenta,
                        (string) ($pago['id'] ?? ''),
                        (string) ($pago['order']['id'] ?? '')
                    );
                    if (!empty($resMark['ok'])) {
                        try {
                            (new NotificacionService())->notificarVentaOnline($idVenta);
                        } catch (Throwable $e) {
                            error_log('checkout_resultado notif: ' . $e->getMessage());
                        }
                        // Recargar la venta con el estado actualizado.
                        $venta = $modelo->leerUno($idVenta);
                    } else {
                        error_log('checkout_resultado marcarPagada fallo: ' . ($resMark['error'] ?? ''));
                    }
                }
            } else {
                error_log('checkout_resultado verificarPago fallo: ' . ($resp['error'] ?? ''));
            }
        }
    } catch (Throwable $e) {
        error_log('checkout_resultado fallback MP: ' . $e->getMessage());
    }
}

if ($estadoGet === 'failure' || ($mpStatus !== '' && $mpStatus !== 'approved')) {
    error_log(sprintf(
        'checkout_resultado venta=%d status=%s status_detail=%s payment_id=%s',
        $idVenta,
        $mpStatus,
        $mpStatusDetail,
        $mpPaymentId
    ));
}

$motivosRechazoMP = [
    'cc_rejected_other_reason'              => 'El banco rechazo el pago. En sandbox suele ser que el comprador es la misma cuenta que el vendedor.',
    'cc_rejected_bad_filled_security_code'  => 'El código de seguridad de la tarjeta es incorrecto.',
    'cc_rejected_bad_filled_date'           => 'La fecha de vencimiento es incorrecta.',
    'cc_rejected_bad_filled_card_number'    => 'El número de tarjeta es incorrecto.',
    'cc_rejected_bad_filled_other'          => 'Revisa los datos de la tarjeta.',
    'cc_rejected_call_for_authorize'        => 'Debes autorizar el pago con tu banco.',
    'cc_rejected_card_disabled'             => 'La tarjeta está deshabilitada.',
    'cc_rejected_duplicated_payment'        => 'Ya hiciste un pago por ese monto.',
    'cc_rejected_high_risk'                 => 'Antifraude rechazo el pago.',
    'cc_rejected_insufficient_amount'       => 'La tarjeta no tiene fondos suficientes.',
    'cc_rejected_invalid_installments'      => 'La tarjeta no procesa pagos en esos meses.',
    'cc_rejected_max_attempts'              => 'Se alcanzo el limite de intentos permitidos.',
    'payer_unauthorized'                    => 'El comprador no está autorizado (probablemente la misma cuenta del vendedor).',
    'cannot_pay_yourself'                   => 'No puedes pagarte a ti mismo. Usa un usuario de prueba comprador distinto al vendedor.',
];
$mensajeMotivoMP = $mpStatusDetail !== '' ? ($motivosRechazoMP[$mpStatusDetail] ?? null) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(joyeria_marca_titulo('Resultado de tu compra'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
</head>
<body>
<div class="container py-5" style="max-width:760px;">

    <?php if (!$venta): ?>
        <div class="alert alert-warning">
            No encontramos el pedido. <a href="index.php" class="alert-link">Volver al catálogo</a>.
        </div>
    <?php else: ?>

        <?php
        $estadoPago = (string) ($venta['estado_pago'] ?? 'pendiente');
        $estadoEntrega = (string) ($venta['estado_entrega'] ?? 'pendiente');
        ?>

        <div class="text-center mb-4" id="resultadoBox">
            <?php if ($estadoPago === 'pagado'): ?>
                <i class="bi bi-check-circle-fill text-success" style="font-size:64px;" aria-hidden="true"></i>
                <h2 class="mt-3">¡Gracias por tu compra!</h2>
                <p class="text-muted">Número de orden: <strong>#<?php echo (int) $venta['id_venta']; ?></strong></p>
            <?php elseif ($estadoPago === 'rechazado' || $estadoGet === 'failure'): ?>
                <i class="bi bi-x-circle-fill text-danger" style="font-size:64px;" aria-hidden="true"></i>
                <h2 class="mt-3">Tu pago no se pudo procesar</h2>
                <p class="text-muted">Las piezas vuelven a estar disponibles. Puedes intentar de nuevo.</p>
                <?php if ($mensajeMotivoMP !== null): ?>
                    <div class="alert alert-danger text-start mx-auto" style="max-width:560px;">
                        <strong>Motivo:</strong> <?php echo htmlspecialchars($mensajeMotivoMP, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php elseif ($mpStatusDetail !== ''): ?>
                    <div class="alert alert-secondary text-start mx-auto small" style="max-width:560px;">
                        <strong>Código Mercado Pago:</strong> <code><?php echo htmlspecialchars($mpStatusDetail, ENT_QUOTES, 'UTF-8'); ?></code>
                    </div>
                <?php endif; ?>
                <?php if ($mpPaymentId !== ''): ?>
                    <p class="text-muted small">Referencia MP: <code><?php echo htmlspecialchars($mpPaymentId, ENT_QUOTES, 'UTF-8'); ?></code></p>
                <?php endif; ?>
            <?php else: ?>
                <i class="bi bi-hourglass-split text-warning" style="font-size:64px;" aria-hidden="true"></i>
                <h2 class="mt-3" id="tituloPendiente">Estamos confirmando tu pago</h2>
                <p class="text-muted">Mercado Pago aún no nos notifica el resultado. Esta pantalla se actualizara sola.</p>
                <p class="text-muted">Número de orden: <strong>#<?php echo (int) $venta['id_venta']; ?></strong></p>
            <?php endif; ?>
        </div>

        <?php if ($estadoPago === 'pagado'): ?>
        <div class="alert alert-warning border-warning" role="note">
            <h5 class="mb-2"><i class="bi bi-shop" aria-hidden="true"></i> Entrega exclusivamente en tienda</h5>
            <p class="mb-2">
                Tu pieza queda apartada en la sucursal
                <strong><?php echo htmlspecialchars((string) ($venta['nom_tienda'] ?? 'la sucursal asignada'), ENT_QUOTES, 'UTF-8'); ?></strong>.
            </p>
            <p class="mb-2">Para recogerla, presenta:</p>
            <ol class="mb-0">
                <li>Una <strong>identificación oficial</strong> con fotografia.</li>
                <li>Tu <strong>número de orden #<?php echo (int) $venta['id_venta']; ?></strong>.</li>
            </ol>
        </div>

        <div class="card">
            <div class="card-header bg-light"><strong>Detalle de tu pedido</strong></div>
            <ul class="list-group list-group-flush">
                <?php foreach (($venta['detalle'] ?? []) as $det): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($det['desc_pieza'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars((string) ($det['nom_metal'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                · Código: <code><?php echo htmlspecialchars((string) ($det['codigo_auxiliar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                            </div>
                        </div>
                        <div class="fw-bold">$<?php echo number_format((float) ($det['precio_unitario'] ?? 0), 2, '.', ','); ?></div>
                    </li>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between">
                    <strong>Total pagado</strong>
                    <strong>$<?php echo number_format((float) ($venta['total'] ?? 0), 2, '.', ','); ?> MXN</strong>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-outline-dark">Volver al catálogo</a>
            <a href="compras.php" class="btn btn-dark">Mis compras</a>
        </div>

        <?php if ($estadoPago === 'pendiente' && $estadoGet !== 'failure'): ?>
        <script>
        (function(){
            var idVenta = <?php echo (int) $venta['id_venta']; ?>;
            var intentos = 0;
            var maxIntentos = 20; // ~60 segundos a 3s por intento
            async function poll(){
                intentos++;
                try {
                    var res = await fetch('../tienda_checkout_api.php', {
                        method:'POST',
                        credentials:'same-origin',
                        headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({action:'estado_venta', id_venta: idVenta})
                    });
                    var data = await res.json();
                    if (data && data.ok && data.estado_pago === 'pagado') {
                        window.location.reload();
                        return;
                    }
                    if (data && data.ok && data.estado_pago === 'rechazado') {
                        window.location.reload();
                        return;
                    }
                } catch(e){}
                if (intentos < maxIntentos) {
                    setTimeout(poll, 3000);
                } else {
                    var t = document.getElementById('tituloPendiente');
                    if (t) t.textContent = 'Aún no recibimos confirmación. Revisa "Mis compras" más tarde.';
                }
            }
            setTimeout(poll, 3000);
        })();
        </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
