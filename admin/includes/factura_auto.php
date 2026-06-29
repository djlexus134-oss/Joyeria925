<?php
declare(strict_types=1);

/**
 * Emite y envia factura CFDI tras una venta (no revierte la venta si falla).
 */
function joyeria_emitir_factura_tras_venta(int $idVenta): void
{
    if ($idVenta <= 0) {
        return;
    }
    try {
        require_once __DIR__ . '/../models/factura.php';
        $factura = new Factura();
        if (!$factura->facturacionHabilitada()) {
            return;
        }
        $factura->emitirYEnviarParaVenta($idVenta, true);
    } catch (Throwable $e) {
        error_log('joyeria_emitir_factura_tras_venta venta #' . $idVenta . ': ' . $e->getMessage());
    }
}
