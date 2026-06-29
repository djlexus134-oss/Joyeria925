-- Pantalla unificada de devoluciones: permiso adicional para reembolso real (afecta caja).
-- Los modos Monedero y Solo inventario siguen gobernados por DEVOLUCION_CREAR + DEVOLUCION_CREDITO_MONEDERO.
-- Ejecutar en la base de datos de Joyeria.

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('DEVOLUCION_REEMBOLSO_EFECTIVO',
     'Registrar devoluciones con reembolso real al cliente (efectivo u otra forma; afecta caja)',
     1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre_rol = 'ADMINISTRADOR'
  AND p.nombre_permiso = 'DEVOLUCION_REEMBOLSO_EFECTIVO';
