-- Indices opcionales para busqueda por prefijo en colonias y calles (ajusta nombres si tu motor usa otro esquema).
-- Ejecutar manualmente si la tabla ya existe y los indices no estan creados.

-- MariaDB / MySQL 8+: prefijo de texto con LIKE 'abc%'
CREATE INDEX idx_colonias_cp_nom ON colonias (id_codigo_postal_FK, nom_colonia(80));
CREATE INDEX idx_calles_colonia_nom ON calles (id_colonia_FK, nom_calle(120));
