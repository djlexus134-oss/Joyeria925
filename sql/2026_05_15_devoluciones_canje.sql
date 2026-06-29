-- Canje por devolucion: credito aplicado a una venta nueva (POS), sin reembolso en efectivo al cliente
ALTER TABLE devoluciones
    ADD COLUMN id_venta_destino_canje_FK int(11) NULL COMMENT 'Venta nueva donde se aplico el credito como descuento' AFTER id_venta_detalle_FK;

ALTER TABLE devoluciones
    ADD CONSTRAINT fk_devolucion_venta_destino_canje
        FOREIGN KEY (id_venta_destino_canje_FK) REFERENCES ventas (id_venta)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Forma de pago interna para cuadrar la venta origen (no es salida de caja)
INSERT INTO forma_pago (forma_pago, activo)
SELECT 'Canje interno (sin efectivo)', 1
FROM (SELECT 1 AS _x) t
WHERE NOT EXISTS (SELECT 1 FROM forma_pago WHERE forma_pago = 'Canje interno (sin efectivo)' LIMIT 1);
