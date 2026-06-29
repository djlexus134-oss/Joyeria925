-- Permisos del modulo Enviar notificaciones (enviar_notificaciones.php + api/enviar_notificaciones.php).
-- Idempotente: se puede ejecutar varias veces.

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('NOTIFICACION_LEER', 'Ver modulo enviar notificaciones', 1),
    ('NOTIFICACION_CREAR', 'Enviar notificaciones por correo, WhatsApp y panel', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE UPPER(TRIM(r.nombre_rol)) = 'ADMINISTRADOR'
  AND p.nombre_permiso IN ('NOTIFICACION_LEER', 'NOTIFICACION_CREAR');
