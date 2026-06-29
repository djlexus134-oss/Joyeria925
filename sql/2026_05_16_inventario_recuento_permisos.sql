-- Permisos modulo Recuento de inventario (panel admin inventario_recuento.php).
-- Ejecutar en la base de datos de Joyeria.
-- Los usuarios con rol ADMINISTRADOR no requieren permisos explicitos (auth).
-- Para otros roles, asigna los permisos desde Rol permiso en el panel.

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('INVENTARIO_RECUENTO_LEER', 'Ver e iniciar recuentos y resultados', 1),
    ('INVENTARIO_RECUENTO_CREAR', 'Iniciar cabecera de auditoria de recuento', 1),
    ('INVENTARIO_RECUENTO_ACTUALIZAR', 'Capturar codigos, finalizar o cancelar recuento', 1),
    ('INVENTARIO_RECUENTO_BORRAR', 'Dar de baja piezas faltantes tras recuento', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);
