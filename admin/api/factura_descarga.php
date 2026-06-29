<?php
require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/factura.php';

if (!function_exists('auth_user') || !auth_user()) {
    http_response_code(403);
    exit('Acceso denegado');
}
if (function_exists('auth_has_permission')
    && !auth_has_permission('VENTA_LEER')
    && !auth_has_permission('FACTURA_LEER')) {
    http_response_code(403);
    exit('Acceso denegado');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$tipo = strtolower(trim((string) ($_GET['tipo'] ?? 'pdf')));

if ($id <= 0) {
    http_response_code(400);
    exit('ID invalido');
}

$factura = (new Factura())->leerUno($id);
if (!$factura || ($factura['estado'] ?? '') !== 'emitida') {
    http_response_code(404);
    exit('Factura no disponible');
}

$serieFolio = trim((string) (($factura['serie'] ?? '') . '-' . ($factura['folio'] ?? 'factura')));

if ($tipo === 'xml') {
    $xml = (string) ($factura['xml'] ?? '');
    if ($xml === '') {
        http_response_code(404);
        exit('XML no disponible');
    }
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="factura-' . $serieFolio . '.xml"');
    echo $xml;
    exit;
}

$pdf = $factura['pdf'] ?? null;
if (!is_string($pdf) || $pdf === '') {
    http_response_code(404);
    exit('PDF no disponible');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="factura-' . $serieFolio . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
