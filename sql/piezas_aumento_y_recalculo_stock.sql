-- Agrega columna aumento_pct a piezas y un indice de apoyo para el recalculo de precio_venta del stock.
-- aumento_pct se interpreta como porcentaje (80.00 => +80%). NULL/0 => precio_venta = costo.
-- Ajusta el schema (joyeria) si tu instancia usa otro nombre.

USE joyeria;

ALTER TABLE piezas
    ADD COLUMN aumento_pct DECIMAL(6,2) NULL DEFAULT NULL
        AFTER precio_por_gramo;

-- Indice de apoyo para acelerar el recalculo de precio_venta cuando se edita una pieza.
-- (Si tu motor se queja por duplicado del prefijo de id_pieza_FK, puedes omitir esta linea.)
CREATE INDEX idx_piezas_stock_pieza_estado_activo
    ON piezas_stock (id_pieza_FK, estado, activo);
