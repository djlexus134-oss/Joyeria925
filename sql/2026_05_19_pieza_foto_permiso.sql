-- Permiso granular PIEZA_FOTO: permite gestionar fotos de piezas
-- (subir/reemplazar imagen principal, agregar a la galeria, marcar principal,
--  eliminar imagen) SIN poder modificar descripcion, costos, aumento ni borrar piezas.
--
-- Diseno:
-- - Subconjunto operativo de PIEZA_ACTUALIZAR.
-- - En auth.php, auth_can_module_action('pieza', 'FOTO') devuelve true si el usuario
--   tiene PIEZA_FOTO o PIEZA_ACTUALIZAR; asi quien ya tenga ACTUALIZAR no pierde nada.
--
-- Idempotente: se puede ejecutar varias veces.

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('PIEZA_FOTO', 'Permite gestionar fotos de piezas (subir principal, galeria, marcar principal, eliminar imagen) sin modificar el resto de campos', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE UPPER(TRIM(r.nombre_rol)) IN ('ADMINISTRADOR', 'ADMIN', 'EMPLEADO')
  AND p.nombre_permiso = 'PIEZA_FOTO';
