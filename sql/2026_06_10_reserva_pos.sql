-- =====================================================================
-- Migracion: reserva de piezas en ticket POS (mostrador)
-- Fecha: 2026-06-10
-- Bloquea unidades en ticket POS sin cobrar; libera a disponible al cancelar.
-- =====================================================================

ALTER TABLE piezas_stock
    MODIFY COLUMN estado ENUM(
        'disponible',
        'vendida',
        'apartada',
        'defectuosa',
        'reparacion',
        'reservada_online',
        'reservada_pos'
    ) NOT NULL DEFAULT 'disponible';

ALTER TABLE piezas_stock
    ADD COLUMN IF NOT EXISTS pos_reserva_token VARCHAR(32) NULL AFTER id_carrito_owner;

CREATE INDEX IF NOT EXISTS idx_piezas_stock_pos_reserva_token ON piezas_stock (pos_reserva_token);
