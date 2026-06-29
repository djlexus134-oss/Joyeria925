-- =============================================================================
-- Elimina los datos insertados por bulk_seed_familias_y_piezas_demo.sql
-- (familias cuyo nombre empieza por DEMO_BULK_Fam)
-- =============================================================================

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

DELETE ps FROM piezas_stock ps
INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
INNER JOIN familias f ON f.id_familia = sf.id_familia_FK
WHERE f.nom_familia LIKE 'DEMO_BULK_Fam %';

DELETE ip FROM imagenes_piezas ip
INNER JOIN piezas p ON p.id_pieza = ip.id_pieza_FK
INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
INNER JOIN familias f ON f.id_familia = sf.id_familia_FK
WHERE f.nom_familia LIKE 'DEMO_BULK_Fam %';

DELETE p FROM piezas p
INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
INNER JOIN familias f ON f.id_familia = sf.id_familia_FK
WHERE f.nom_familia LIKE 'DEMO_BULK_Fam %';

DELETE sf FROM sub_familia sf
INNER JOIN familias f ON f.id_familia = sf.id_familia_FK
WHERE f.nom_familia LIKE 'DEMO_BULK_Fam %';

DELETE FROM familias
WHERE nom_familia LIKE 'DEMO_BULK_Fam %';

SET FOREIGN_KEY_CHECKS = 1;

DROP PROCEDURE IF EXISTS joyeria_bulk_seed_demo_familias_piezas;
