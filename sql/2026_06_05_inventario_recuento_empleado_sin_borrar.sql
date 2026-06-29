-- Flujo Recuento de inventario para rol Empleado:
-- - Empleado puede iniciar, capturar piezas, finalizar y cancelar recuentos.
-- - Dar de baja piezas faltantes (INVENTARIO_RECUENTO_BORRAR) queda EXCLUSIVO de administradores.
-- Los usuarios con rol ADMINISTRADOR no requieren permisos explicitos (auth_is_admin()).
--
-- Idempotente: se puede ejecutar varias veces. Ejecutar en la base de datos de Joyeria.

-- 1) Reasegura que existan los permisos del modulo.
INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('INVENTARIO_RECUENTO_LEER', 'Ver e iniciar recuentos y resultados', 1),
    ('INVENTARIO_RECUENTO_CREAR', 'Iniciar cabecera de auditoria de recuento', 1),
    ('INVENTARIO_RECUENTO_ACTUALIZAR', 'Capturar codigos, finalizar o cancelar recuento', 1),
    ('INVENTARIO_RECUENTO_BORRAR', 'Dar de baja piezas faltantes tras recuento', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- 2) Asigna al rol Empleado los permisos del recuento EXCEPTO BORRAR.
INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE UPPER(TRIM(r.nombre_rol)) = 'EMPLEADO'
  AND p.nombre_permiso IN (
      'INVENTARIO_RECUENTO_LEER',
      'INVENTARIO_RECUENTO_CREAR',
      'INVENTARIO_RECUENTO_ACTUALIZAR'
  );

-- 3) Revoca al rol Empleado la baja de piezas faltantes (exclusivo de admin).
DELETE rp FROM rol_permiso rp
INNER JOIN roles r ON r.id_rol = rp.id_rol_FK
INNER JOIN permisos p ON p.id_permiso = rp.id_permiso_FK
WHERE UPPER(TRIM(r.nombre_rol)) = 'EMPLEADO'
  AND p.nombre_permiso = 'INVENTARIO_RECUENTO_BORRAR';
