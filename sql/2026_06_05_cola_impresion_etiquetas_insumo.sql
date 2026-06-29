-- Etiquetas de insumos en cola_impresion (POS / SKU escaneable).
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
        'apartado_liquidacion'
    ) NOT NULL DEFAULT 'venta';
