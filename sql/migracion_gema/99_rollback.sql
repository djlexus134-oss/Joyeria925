-- Rollback migracion Gema (usa tablas mig_gema_* como guia)
-- Ejecutar solo en BD de prueba o si confirma reversar la migracion.
-- Orden: movimientos -> stock -> piezas catalogo -> clientes -> limpieza mig

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @ref = (SELECT valor FROM mig_config WHERE clave = 'referencia_movimiento');
SET @current_user_id = CAST((SELECT valor FROM mig_config WHERE clave = 'id_usuario_migracion') AS UNSIGNED);
SET @current_ip = '127.0.0.1';

-- 1) Movimientos de entrada migracion
DELETE FROM movimientos_inventario
WHERE referencia = @ref;

-- 2) Stock migrado
DELETE ps FROM piezas_stock ps
INNER JOIN mig_gema_stock ms ON ms.id_pieza_stock = ps.id_pieza_stock;

-- 3) Piezas catalogo migradas (solo sin stock residual)
DELETE p FROM piezas p
INNER JOIN mig_gema_artic ma ON ma.id_pieza = p.id_pieza
WHERE NOT EXISTS (
    SELECT 1 FROM piezas_stock ps WHERE ps.id_pieza_FK = p.id_pieza
);

-- 4) Clientes y usuarios migrados
DELETE ur FROM usuario_rol ur
INNER JOIN mig_gema_cliente mc ON mc.id_usuario = ur.id_usuario_FK;

DELETE c FROM clientes c
INNER JOIN mig_gema_cliente mc ON mc.id_cliente = c.id_cliente;

DELETE u FROM usuarios u
INNER JOIN mig_gema_cliente mc ON mc.id_usuario = u.id_usuario;

-- 5) Proveedores stub creados (opcional, solo los marcados en mig)
DELETE pr FROM proveedores pr
INNER JOIN mig_gema_proveedor mp ON mp.id_proveedor = pr.id_proveedor
WHERE mp.razon_legacy LIKE 'Prov Gema %'
  AND NOT EXISTS (
      SELECT 1 FROM piezas p WHERE p.id_proveedor_FK = pr.id_proveedor
  );

-- 6) Limpiar tablas de mapeo
TRUNCATE TABLE mig_gema_stock;
TRUNCATE TABLE mig_gema_artic;
TRUNCATE TABLE mig_gema_cliente;
TRUNCATE TABLE mig_gema_proveedor;
TRUNCATE TABLE mig_gema_metal;
TRUNCATE TABLE mig_gema_familia;
TRUNCATE TABLE mig_log;

SET FOREIGN_KEY_CHECKS = 1;

SELECT '99_rollback.sql completado.' AS mensaje,
       (SELECT COUNT(*) FROM mig_gema_stock) AS stock_mig_restante,
       (SELECT COUNT(*) FROM mig_gema_cliente) AS clientes_mig_restantes;
