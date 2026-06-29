-- =====================================================================
-- Migracion: Robustez de stock y atencion administrativa en ventas
-- Fecha: 2026-05-21
-- Sistema: Joyeria Plateria El Angel
-- =====================================================================
-- Esta migracion agrega capacidad de "notas administrativas" y marca de
-- "requiere atencion" en la tabla `ventas`, para soportar el caso en el
-- que un pago de Mercado Pago se aprueba pero la pieza ya no esta
-- disponible (porque expiro la reserva del carrito y se vendio en
-- sucursal entre tanto). En ese caso `tienda_pago_webhook.php` /
-- `VentaOnline::marcarPagada()` dejan la venta con:
--   estado_pago      = 'pagado'        (el cobro se hizo en MP)
--   estado_entrega   = 'cancelada'     (no se puede surtir)
--   requiere_atencion= 1               (bandera visible en panel admin)
--   observaciones_admin = 'Stock perdido tras pago. Reembolsar al cliente.'
-- y se dispara una notificacion para que el administrador haga el
-- reembolso manual desde MP.
-- ---------------------------------------------------------------------

-- 1) Notas internas para el administrador (libre, hasta 65535 chars)
ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS observaciones_admin TEXT NULL AFTER aceptacion_entrega_tienda;

-- 2) Bandera de "necesita revision/intervencion del administrador"
ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS requiere_atencion TINYINT(1) NOT NULL DEFAULT 0 AFTER observaciones_admin;

-- Indice util para filtrar pedidos que requieren accion en el panel
CREATE INDEX idx_ventas_requiere_atencion ON ventas (requiere_atencion, estado_entrega);
