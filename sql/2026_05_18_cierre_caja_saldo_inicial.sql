-- Saldo inicial en cierre de caja + permiso modulo arqueo (consulta descuadre sin guardar).
-- Ejecutar una sola vez en la BD de produccion.

ALTER TABLE cierre_caja
    ADD COLUMN saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0
    AFTER fecha_operacion;

INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('ARQUEO_CAJA_LEER', 'Consultar arqueo y descuadre de caja sin registrar cierre', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);
