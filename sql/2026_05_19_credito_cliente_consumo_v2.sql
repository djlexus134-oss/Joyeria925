-- v2 del monedero de creditos del cliente:
-- - Habilita su uso como pago en POS (venta_pagos) via forma_pago sintetica.
-- - Habilita su uso como abono en apartado_pagos via tipo_origen='credito_cliente'.
-- - Permiso para aplicar el monedero.
-- Ejecutar en la base de datos de Joyeria.

-- 1) Forma de pago sintetica para identificar pagos cubiertos con el monedero.
INSERT INTO forma_pago (forma_pago, activo)
SELECT 'Credito a favor cliente', 1
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM forma_pago WHERE forma_pago = 'Credito a favor cliente'
);

UPDATE forma_pago
SET activo = 1
WHERE forma_pago = 'Credito a favor cliente';

-- 2) Extender el enum tipo_origen de apartado_pagos para admitir uso del monedero.
ALTER TABLE apartado_pagos
    MODIFY COLUMN tipo_origen ENUM ('cobro_tienda', 'credito_por_cambio', 'credito_cliente') NOT NULL DEFAULT 'cobro_tienda';

-- 3) Nuevo permiso para aplicar el monedero (descontar credito).
INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('CLIENTE_CREDITO_APLICAR', 'Aplicar credito a favor del cliente como pago (POS y apartados)', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- 4) Asignar al rol ADMINISTRADOR (opcional, omitir si se asigna por panel).
INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre_rol = 'ADMINISTRADOR'
  AND p.nombre_permiso = 'CLIENTE_CREDITO_APLICAR';
