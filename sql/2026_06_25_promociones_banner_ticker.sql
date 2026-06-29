-- Barra superior fija (ticker) en tienda pública / zona cliente.
-- Ejecutar en la base de datos de la joyería.

ALTER TABLE promociones_banner
    ADD COLUMN visible_ticker TINYINT(1) NOT NULL DEFAULT 0 AFTER visible_cliente,
    ADD COLUMN ticker_segmentos VARCHAR(1024) NULL DEFAULT NULL AFTER visible_ticker;
