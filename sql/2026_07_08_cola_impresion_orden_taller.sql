-- Ticket termico de orden de taller en cola_impresion (print-agent).
-- Idempotente: ejecutar una sola vez.

ALTER TABLE cola_impresion
    MODIFY COLUMN tipo ENUM(
        'venta',
        'reimpresion',
        'etiqueta_stock',
        'etiqueta_lote',
        'etiqueta_insumo',
        'etiqueta_insumo_lote',
        'apartado_alta',
        'apartado_abono',
        'apartado_liquidacion',
        'orden_taller'
    ) NOT NULL DEFAULT 'venta';
