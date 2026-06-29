-- Barra inferior fija deslizable en catálogo (visitante / cliente).
-- Ejecutar en la base de datos de la joyería.

ALTER TABLE promociones_banner
    ADD COLUMN visible_barra_inferior TINYINT(1) NOT NULL DEFAULT 0 AFTER visible_ticker;
