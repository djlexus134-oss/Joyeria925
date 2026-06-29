-- Columnas auxiliares en familias y sub_familia para búsquedas normalizadas (minúsculas, sin espacios extremos).
-- Son columnas GENERATED STORED: MySQL las calcula al INSERT/UPDATE del nombre; no hace falta rellenarlas en la aplicación.
-- Ajusta el schema (joyeria) si tu instancia usa otro nombre de base.

USE joyeria;

ALTER TABLE familias
    ADD COLUMN nom_familia_key VARCHAR(50)
        AS (LOWER(TRIM(nom_familia))) STORED
        AFTER nom_familia,
    ADD INDEX idx_familias_activo_nom_key (activo, nom_familia_key);

ALTER TABLE sub_familia
    ADD COLUMN nom_sub_familia_key VARCHAR(50)
        AS (LOWER(TRIM(nom_sub_familia))) STORED
        AFTER nom_sub_familia,
    ADD INDEX idx_subfamilia_familia_activo_nom_key (id_familia_FK, activo, nom_sub_familia_key);
