<?php
require_once __DIR__ . '/TicketService.php';
require_once __DIR__ . '/../models/cola_impresion.php';
require_once __DIR__ . '/../models/configuracion_general.php';

/**
 * id_tienda_FK que debe guardarse en cola_impresion para que el agente reciba el trabajo.
 * impresion.php filtra pendientes con (id_tienda_FK IS NULL OR id_tienda_FK = impresion_id_tienda_caja).
 * Si configuraste tienda de caja, hay que usar ese id al encolar; si no, la tienda del negocio o null.
 */
function joyeria_id_tienda_cola_impresion(?int $idTiendaNegocio = null): ?int
{
    $cfg = new ConfiguracionGeneral();
    $map = $cfg->leerPorClaves(['impresion_id_tienda_caja']);
    $idCajaCfg = (int) ($map['impresion_id_tienda_caja'] ?? 0);
    if ($idCajaCfg > 0) {
        return $idCajaCfg;
    }
    if ($idTiendaNegocio !== null && $idTiendaNegocio > 0) {
        return $idTiendaNegocio;
    }

    return null;
}

function joyeria_encolar_ticket_venta(int $idVenta, ?int $idTienda = null, string $tipo = 'venta'): ?int
{
    try {
        $ticketService = new TicketService();
        if (!$ticketService->impresionHabilitada()) {
            return null;
        }

        $cola = new ColaImpresion();
        $idTiendaCola = joyeria_id_tienda_cola_impresion($idTienda);

        return $cola->encolar($idVenta, $tipo, $idTiendaCola);
    } catch (Throwable $e) {
        error_log('joyeria_encolar_ticket_venta: ' . $e->getMessage());

        return null;
    }
}

/**
 * Encola ticket termico de apartado (alta, abono o liquidacion). Requiere ENUM en cola_impresion.
 *
 * @param 'alta'|'abono'|'liquidacion' $modo
 */
function joyeria_encolar_ticket_apartado(int $idApartado, string $modo, ?int $idTienda = null): ?int
{
    try {
        $ticketService = new TicketService();
        if (!$ticketService->impresionHabilitada()) {
            return null;
        }

        $cola = new ColaImpresion();
        $idTiendaCola = joyeria_id_tienda_cola_impresion($idTienda);

        return $cola->encolarTicketApartado($idApartado, $modo, $idTiendaCola);
    } catch (Throwable $e) {
        error_log('joyeria_encolar_ticket_apartado: ' . $e->getMessage());

        return null;
    }
}

/**
 * Encola ticket termico de orden de taller para el print-agent de caja.
 */
function joyeria_encolar_ticket_orden_taller(int $idOrdenTaller, ?int $idTienda = null): ?int
{
    try {
        $ticketService = new TicketService();
        if (!$ticketService->impresionHabilitada()) {
            return null;
        }

        $cola = new ColaImpresion();
        $idTiendaCola = joyeria_id_tienda_cola_impresion($idTienda);

        return $cola->encolarTicketOrdenTaller($idOrdenTaller, $idTiendaCola);
    } catch (Throwable $e) {
        error_log('joyeria_encolar_ticket_orden_taller: ' . $e->getMessage());

        return null;
    }
}
