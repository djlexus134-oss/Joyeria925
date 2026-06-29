-- Modulo reporte capital en tienda (costo de inventario disponible por familia y pieza).
-- Ejecutar una sola vez sobre la misma BD que usa la app.

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('CAPITAL_INVENTARIO_LEER', 'Consultar capital en tienda (costo de inventario por familia y pieza)', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);
