-- Tickets termicos de apartado (alta, abono, liquidacion) en cola_impresion
-- Idempotente: verifica ENUM actual antes de alterar si hace falta.

ALTER TABLE cola_impresion
    MODIFY COLUMN tipo ENUM(
        'venta',
        'reimpresion',
        'etiqueta_stock',
        'etiqueta_lote',
        'apartado_alta',
        'apartado_abono',
        'apartado_liquidacion'
    ) NOT NULL DEFAULT 'venta';
