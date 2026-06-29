<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/models/venta_online.php';
require_once __DIR__ . '/admin/includes/MercadoPagoService.php';
require_once __DIR__ . '/admin/includes/NotificacionService.php';

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');

/**
 * Log dedicado a este webhook para depurar entregas de MP sin depender
 * de donde apunte error_log() en el servidor.
 * Cada llamada (incluso firma invalida) deja una linea aqui.
 */
function joyeria_wh_log(string $msg): void
{
    // Fuera del document root: evita exposición HTTP de datos de pago (ver deploy/nginx).
    $candidatos = [
        '/var/log/joyeria/mp_webhook.log',
        sys_get_temp_dir() . '/joyeria_mp_webhook.log',
    ];
    foreach ($candidatos as $ruta) {
        $dir = dirname($ruta);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@file_put_contents($ruta, '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX) !== false) {
            return;
        }
    }
    error_log('joyeria_wh_log fallback: ' . $msg);
}

function joyeria_wh_ack(array $data = ['ok' => true]): void
{
    echo json_encode($data);
    exit;
}

joyeria_wh_log(sprintf(
    'HIT method=%s ip=%s qs=%s',
    (string) ($_SERVER['REQUEST_METHOD'] ?? '?'),
    (string) ($_SERVER['REMOTE_ADDR'] ?? '?'),
    (string) ($_SERVER['QUERY_STRING'] ?? '')
));

/** ID del recurso (pago/orden) para firma y consulta API. */
function joyeria_mp_webhook_data_id(): string
{
    if (isset($_GET['data.id']) && (string) $_GET['data.id'] !== '') {
        return (string) $_GET['data.id'];
    }
    if (isset($_GET['data_id']) && (string) $_GET['data_id'] !== '') {
        return (string) $_GET['data_id'];
    }
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($qs !== '' && preg_match('/(?:^|&)data\.id=([^&]*)/', $qs, $m)) {
        return urldecode($m[1]);
    }
    if (isset($_GET['id']) && (string) $_GET['id'] !== '') {
        return (string) $_GET['id'];
    }
    return '';
}

// MP envia data.id en query y JSON con {type,action,data:{id}}.
// PHP convierte "data.id" en $_GET['data_id']; leer ambas formas + QUERY_STRING.
$dataIdQuery = joyeria_mp_webhook_data_id();
$type = (string) ($_GET['type'] ?? $_GET['topic'] ?? '');
$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
if (!is_array($payload)) $payload = [];

if ($type === '' && isset($payload['type'])) $type = (string) $payload['type'];
if ($type === '' && isset($payload['topic'])) $type = (string) $payload['topic'];
if ($dataIdQuery === '' && isset($payload['data']['id'])) $dataIdQuery = (string) $payload['data']['id'];
if ($dataIdQuery === '' && isset($payload['resource'])) {
    // merchant_orders viene como resource URL
    $dataIdQuery = (string) basename((string) $payload['resource']);
}

$headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
$mp = new MercadoPagoService();

joyeria_wh_log(sprintf(
    'PARSED type=%s data.id=%s body=%s',
    $type,
    $dataIdQuery,
    substr($rawBody, 0, 500)
));

$diagFirma = $mp->diagnosticarFirmaWebhook(is_array($headers) ? $headers : [], $dataIdQuery);
if ($diagFirma['ok']) {
    joyeria_wh_log('FIRMA OK data.id=' . $dataIdQuery);
} elseif ($diagFirma['motivo'] !== 'sin_secret_configurado') {
    joyeria_wh_log(sprintf(
        'FIRMA RECHAZADA data.id=%s motivo=%s',
        $dataIdQuery,
        $diagFirma['motivo']
    ));
    http_response_code(401);
    joyeria_wh_ack(['ok' => false, 'error' => 'invalid_signature']);
}

try {
    $esEventoPago = in_array($type, ['payment', 'payment.created', 'payment.updated'], true);
    if ($esEventoPago) {
        $r = $mp->verificarPago($dataIdQuery);
        if (!$r['ok']) {
            joyeria_wh_log('verificarPago FALLO: ' . ($r['error'] ?? ''));
            error_log('webhook verificarPago: ' . ($r['error'] ?? ''));
            // No es un pago nuestro / API no devolvio datos. Responder 200 para
            // que MP no reintente eternamente.
            joyeria_wh_ack(['ok' => true, 'ignored' => 'no_payment']);
        }
        $pago = $r['data'];
        $estado = (string) ($pago['status'] ?? '');
        $extRef = (string) ($pago['external_reference'] ?? '');
        $idVenta = 0;
        if ($extRef !== '' && preg_match('/^venta_(\d+)$/', $extRef, $m)) {
            $idVenta = (int) $m[1];
        }
        if ($idVenta <= 0 && !empty($pago['metadata']['id_venta'])) {
            $idVenta = (int) $pago['metadata']['id_venta'];
        }
        joyeria_wh_log(sprintf(
            'PAGO id=%s status=%s ext_ref=%s id_venta=%d',
            (string) ($pago['id'] ?? ''),
            $estado,
            $extRef,
            $idVenta
        ));
        if ($idVenta <= 0) {
            joyeria_wh_ack(['ok' => true, 'ignored' => 'sin id_venta']);
        }

        $vo = new VentaOnline();
        if ($estado === 'approved') {
            $resMark = $vo->marcarPagada($idVenta, (string) ($pago['id'] ?? ''), (string) ($pago['order']['id'] ?? ''));
            joyeria_wh_log('marcarPagada id_venta=' . $idVenta . ' resultado=' . json_encode($resMark));
            try { (new NotificacionService())->notificarVentaOnline($idVenta); } catch (Throwable $e) { error_log('notif venta: ' . $e->getMessage()); }
            joyeria_wh_ack(['ok' => true, 'status' => 'approved']);
        }
        if (in_array($estado, ['rejected', 'cancelled', 'refunded', 'charged_back'], true)) {
            $resMark = $vo->marcarRechazada($idVenta);
            joyeria_wh_log('marcarRechazada id_venta=' . $idVenta . ' resultado=' . json_encode($resMark));
            joyeria_wh_ack(['ok' => true, 'status' => $estado]);
        }
        joyeria_wh_ack(['ok' => true, 'ignored' => 'status_' . $estado]);
    }

    $esEventoMerchantOrder = $type === 'merchant_order'
        || $type === 'topic_merchant_order_wh'
        || strpos((string) ($payload['topic'] ?? ''), 'merchant_order') !== false;
    if ($esEventoMerchantOrder) {
        $r = $mp->consultarMerchantOrder($dataIdQuery);
        if (!$r['ok']) {
            joyeria_wh_log('consultarMerchantOrder FALLO: ' . ($r['error'] ?? ''));
            joyeria_wh_ack(['ok' => true, 'ignored' => 'no_merchant_order']);
        }
        $mo = $r['data'];
        $extRef = (string) ($mo['external_reference'] ?? '');
        $idVenta = 0;
        if ($extRef !== '' && preg_match('/^venta_(\d+)$/', $extRef, $m)) {
            $idVenta = (int) $m[1];
        }
        joyeria_wh_log('MO ext_ref=' . $extRef . ' id_venta=' . $idVenta . ' pagos=' . count($mo['payments'] ?? []));
        if ($idVenta <= 0) joyeria_wh_ack(['ok' => true, 'ignored' => 'sin id_venta']);

        $pagos = $mo['payments'] ?? [];
        $algunoAprobado = false;
        $algunoRechazado = false;
        foreach ($pagos as $p) {
            $st = (string) ($p['status'] ?? '');
            if ($st === 'approved') $algunoAprobado = true;
            if (in_array($st, ['rejected','cancelled','refunded','charged_back'], true)) $algunoRechazado = true;
        }
        $vo = new VentaOnline();
        if ($algunoAprobado) {
            $vo->marcarPagada($idVenta, '', (string) ($mo['id'] ?? ''));
            try { (new NotificacionService())->notificarVentaOnline($idVenta); } catch (Throwable $e) { error_log('notif venta MO: ' . $e->getMessage()); }
        } elseif ($algunoRechazado) {
            $vo->marcarRechazada($idVenta);
        }
        joyeria_wh_ack(['ok' => true, 'status' => $algunoAprobado ? 'approved' : ($algunoRechazado ? 'rejected' : 'pending')]);
    }

    joyeria_wh_log('IGNORADO tipo=' . $type);
    joyeria_wh_ack(['ok' => true, 'ignored' => 'tipo_' . $type]);
} catch (Throwable $e) {
    error_log('tienda_pago_webhook: ' . $e->getMessage());
    http_response_code(500);
    joyeria_wh_ack(['ok' => false, 'error' => 'internal']);
}
