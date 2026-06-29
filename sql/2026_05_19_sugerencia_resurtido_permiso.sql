-- Actualiza la descripcion del permiso del modulo de sugerencia de resurtido
-- (reemplazo logico del reporte de piezas vendidas con baja rotacion).
-- Ejecutar en la base de datos de Joyeria.

UPDATE permisos
SET descripcion = 'Consultar sugerencia de resurtido y exportar PDF de compra',
    activo = 1
WHERE nombre_permiso = 'PIEZAS_VENDIDAS_LEER';

INSERT INTO permisos (nombre_permiso, descripcion, activo)
SELECT 'PIEZAS_VENDIDAS_LEER', 'Consultar sugerencia de resurtido y exportar PDF de compra', 1
WHERE NOT EXISTS (
    SELECT 1 FROM permisos WHERE nombre_permiso = 'PIEZAS_VENDIDAS_LEER'
);
