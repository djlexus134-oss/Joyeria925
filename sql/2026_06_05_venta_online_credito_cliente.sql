-- =====================================================================
-- Migracion: Credito de tienda (monedero) en venta en linea
-- Fecha: 2026-06-05
-- Sistema: Joyeria Plateria El Angel
-- =====================================================================

-- Monto de monedero aplicado a la venta online (ventas.total conserva el valor completo).
ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS credito_aplicado DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER total;

-- Trazabilidad: distinguir consumos de monedero originados en venta en linea.
ALTER TABLE cliente_credito_consumos
    MODIFY COLUMN tipo_uso ENUM (
        'abono_apartado',
        'venta_pos',
        'alta_apartado',
        'ajuste',
        'venta_online'
    ) NOT NULL;
