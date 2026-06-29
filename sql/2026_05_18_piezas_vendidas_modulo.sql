-- Permiso del modulo Piezas vendidas (panel admin piezas_vendidas.php).
-- Ejecutar en la base de datos de Joyeria.
-- Los usuarios con rol ADMINISTRADOR no requieren permisos explicitos (auth).
-- Para otros roles, asigna el permiso desde Rol permiso en el panel.

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('PIEZAS_VENDIDAS_LEER', 'Consultar piezas vendidas con baja rotacion', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);