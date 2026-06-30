-- Asigna metal por descripcion de pieza (solo piezas activas):
--   contiene la palabra "oro" delimitada por espacios -> Oro de 10 k
--   resto -> Plata .925
-- No coincide "dorado", "color", etc. (usa espacios:  oro )
--
-- VPS:
--   source /etc/joyeria925/env && export MYSQL_PWD="$DB_PASSWORD"
--   mariadb -u"$DB_USER" "$DB_NAME" < sql/migracion_gema/actualizar_metales_oro_plata.sql

SET NAMES utf8mb4;

INSERT INTO metales (nom_metal, activo, precio_tienda, precio_mercado)
SELECT 'Oro de 10 k', 1, 0.00, 0.00
WHERE NOT EXISTS (SELECT 1 FROM metales WHERE nom_metal = 'Oro de 10 k');

INSERT INTO metales (nom_metal, activo, precio_tienda, precio_mercado)
SELECT 'Plata .925', 1, 0.00, 0.00
WHERE NOT EXISTS (SELECT 1 FROM metales WHERE nom_metal = 'Plata .925');

UPDATE metales
SET activo = 1, fecha_baja = NULL, id_usuario_baja = NULL
WHERE nom_metal IN ('Oro de 10 k', 'Plata .925');

SET @id_oro   = (SELECT id_metal FROM metales WHERE nom_metal = 'Oro de 10 k' LIMIT 1);
SET @id_plata = (SELECT id_metal FROM metales WHERE nom_metal = 'Plata .925' LIMIT 1);

SELECT @id_oro AS id_oro, @id_plata AS id_plata;

-- Vista previa
SELECT
  CASE
    WHEN CONCAT(' ', LOWER(IFNULL(p.desc_pieza, '')), ' ') LIKE '% oro %' THEN 'Oro de 10 k'
    ELSE 'Plata .925'
  END AS metal_asignado,
  COUNT(*) AS piezas
FROM piezas p
WHERE p.activo = 1
GROUP BY 1;

START TRANSACTION;

UPDATE piezas p
SET p.id_metal_FK = CASE
  WHEN CONCAT(' ', LOWER(IFNULL(p.desc_pieza, '')), ' ') LIKE '% oro %' THEN @id_oro
  ELSE @id_plata
END
WHERE p.activo = 1;

SELECT m.nom_metal, COUNT(*) AS piezas
FROM piezas p
INNER JOIN metales m ON m.id_metal = p.id_metal_FK
WHERE p.activo = 1
GROUP BY m.nom_metal;

COMMIT;
