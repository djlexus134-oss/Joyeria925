-- Conserva solo 3 empleados activos; elimina el resto.
-- Sin referencias historicas: DELETE fisico (usuario_rol, empleados, usuarios).
-- Con referencias (ventas, apartados, etc.): baja logica (activo=0).
-- Suma referencias en cliente_creditos y apartado_cambios_pieza solo si esas tablas existen.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_emp_keep;
CREATE TEMPORARY TABLE tmp_emp_keep (
    nom_norm VARCHAR(200) NOT NULL PRIMARY KEY
);
INSERT INTO tmp_emp_keep (nom_norm) VALUES
    ('gael ricardo mendoza ballardo'),
    ('beatriz martha hernandez alvarado'),
    ('daniela melesio diaz');

DROP TEMPORARY TABLE IF EXISTS tmp_emp_remove;
CREATE TEMPORARY TABLE tmp_emp_remove AS
SELECT
    e.id_empleado,
    e.id_usuario_FK AS id_usuario,
    u.id_direccion_FK,
    LOWER(
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            TRIM(CONCAT_WS(' ', u.nombre, u.primer_apellido, COALESCE(u.segundo_apellido, ''))),
        'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u')
    ) AS nom_norm
FROM empleados e
INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
WHERE e.activo = 1
  AND LOWER(
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            TRIM(CONCAT_WS(' ', u.nombre, u.primer_apellido, COALESCE(u.segundo_apellido, ''))),
        'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u')
      ) NOT IN (SELECT nom_norm FROM tmp_emp_keep);

DROP TEMPORARY TABLE IF EXISTS tmp_emp_refs;
CREATE TEMPORARY TABLE tmp_emp_refs AS
SELECT
    r.id_empleado,
    r.id_usuario,
    r.id_direccion_FK,
    (
        (SELECT COUNT(*) FROM ventas v WHERE v.id_empleado_FK = r.id_empleado)
        + (SELECT COUNT(*) FROM apartados a WHERE a.id_empleado_FK = r.id_empleado)
        + (SELECT COUNT(*) FROM gastos g WHERE g.id_empleado_FK = r.id_empleado)
        + (SELECT COUNT(*) FROM devoluciones dv WHERE dv.id_empleado_FK = r.id_empleado)
        + (SELECT COUNT(*) FROM contratos_empleados ce WHERE ce.id_empleado_FK = r.id_empleado)
    ) AS total_refs
FROM tmp_emp_remove r;

SET @has_cliente_creditos := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'cliente_creditos'
);
SET @sql_refs_cc := IF(
    @has_cliente_creditos > 0,
    'UPDATE tmp_emp_refs t
     SET t.total_refs = t.total_refs + (
         SELECT COUNT(*) FROM cliente_creditos cc WHERE cc.id_empleado_FK = t.id_empleado
     )',
    'SELECT 1'
);
PREPARE stmt_refs_cc FROM @sql_refs_cc;
EXECUTE stmt_refs_cc;
DEALLOCATE PREPARE stmt_refs_cc;

SET @has_apartado_cambios := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'apartado_cambios_pieza'
);
SET @sql_refs_acp := IF(
    @has_apartado_cambios > 0,
    'UPDATE tmp_emp_refs t
     SET t.total_refs = t.total_refs + (
         SELECT COUNT(*) FROM apartado_cambios_pieza ac WHERE ac.id_empleado_FK = t.id_empleado
     )',
    'SELECT 1'
);
PREPARE stmt_refs_acp FROM @sql_refs_acp;
EXECUTE stmt_refs_acp;
DEALLOCATE PREPARE stmt_refs_acp;

-- Baja logica: empleados con historial operativo
UPDATE empleados e
INNER JOIN tmp_emp_refs t ON t.id_empleado = e.id_empleado
SET e.activo = 0,
    e.fecha_baja = NOW()
WHERE t.total_refs > 0;

UPDATE usuarios u
INNER JOIN tmp_emp_refs t ON t.id_usuario = u.id_usuario
SET u.activo = 0
WHERE t.total_refs > 0;

DELETE ur FROM usuario_rol ur
INNER JOIN tmp_emp_refs t ON t.id_usuario = ur.id_usuario_FK
WHERE t.total_refs > 0;

-- Eliminacion fisica: sin referencias
DELETE ur FROM usuario_rol ur
INNER JOIN tmp_emp_refs t ON t.id_usuario = ur.id_usuario_FK
WHERE t.total_refs = 0;

DELETE e FROM empleados e
INNER JOIN tmp_emp_refs t ON t.id_empleado = e.id_empleado
WHERE t.total_refs = 0;

DELETE u FROM usuarios u
INNER JOIN tmp_emp_refs t ON t.id_usuario = u.id_usuario
WHERE t.total_refs = 0;

-- Direcciones huerfanas de usuarios eliminados (solo si no las usa otro usuario)
DELETE d FROM direcciones d
INNER JOIN tmp_emp_refs t ON t.id_direccion_FK = d.id_direccion
WHERE t.total_refs = 0
  AND t.id_direccion_FK IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM usuarios u2 WHERE u2.id_direccion_FK = d.id_direccion
  );

COMMIT;

-- Verificacion: deben quedar solo los 3 empleados activos (o menos si alguno no existia).
-- SELECT e.id_empleado, u.nombre FROM empleados e JOIN usuarios u ON u.id_usuario = e.id_usuario_FK WHERE e.activo = 1;
