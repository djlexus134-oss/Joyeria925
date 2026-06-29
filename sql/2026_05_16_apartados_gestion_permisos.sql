-- Modulo Apartados (alta y abonos) - permisos panel admin
-- Ejecutar en la base de datos de Joyeria.

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('APARTADO_GESTION_LEER', 'Consultar apartados y catalogos del modulo', 1),
    ('APARTADO_GESTION_CREAR', 'Registrar nuevos apartados (apartar pieza)', 1),
    ('APARTADO_GESTION_ACTUALIZAR', 'Registrar abonos a apartados activos', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);
