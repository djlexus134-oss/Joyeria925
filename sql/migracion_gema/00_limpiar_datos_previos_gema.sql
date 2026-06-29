-- Limpia TODOS los datos operativos, catalogo, clientes y usuarios no-empleado.
-- Omite tablas que no existan (BD sin migraciones 2026_*).
-- NO borra: configuracion_general, empleados, roles, permisos, tiendas, forma_pago, etc.

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS sp_limpiar_delete_if_exists;
DROP PROCEDURE IF EXISTS sp_limpiar_truncate_if_exists;
DROP PROCEDURE IF EXISTS sp_limpiar_usuarios_no_empleado;
DROP PROCEDURE IF EXISTS sp_limpiar_direcciones_huerfanas;

DELIMITER $$

CREATE PROCEDURE sp_limpiar_delete_if_exists(IN p_tabla VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = p_tabla
    ) THEN
        SET @sql_limpiar = CONCAT('DELETE FROM `', p_tabla, '`');
        PREPARE stmt_limpiar FROM @sql_limpiar;
        EXECUTE stmt_limpiar;
        DEALLOCATE PREPARE stmt_limpiar;
    END IF;
END$$

CREATE PROCEDURE sp_limpiar_truncate_if_exists(IN p_tabla VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = p_tabla
    ) THEN
        SET @sql_limpiar = CONCAT('TRUNCATE TABLE `', p_tabla, '`');
        PREPARE stmt_limpiar FROM @sql_limpiar;
        EXECUTE stmt_limpiar;
        DEALLOCATE PREPARE stmt_limpiar;
    END IF;
END$$

CREATE PROCEDURE sp_limpiar_usuarios_no_empleado()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'empleados'
    ) AND EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'usuarios'
    ) THEN
        IF EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'usuario_rol'
        ) THEN
            DELETE ur FROM usuario_rol ur
            LEFT JOIN empleados e ON e.id_usuario_FK = ur.id_usuario_FK
            WHERE e.id_empleado IS NULL;
        END IF;

        DELETE u FROM usuarios u
        LEFT JOIN empleados e ON e.id_usuario_FK = u.id_usuario
        WHERE e.id_empleado IS NULL;
    END IF;
END$$

CREATE PROCEDURE sp_limpiar_direcciones_huerfanas()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'direcciones'
    ) AND EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'usuarios'
    ) THEN
        DELETE d FROM direcciones d
        LEFT JOIN usuarios u ON u.id_direccion_FK = d.id_direccion
        WHERE u.id_usuario IS NULL;
    END IF;
END$$

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 0;

-- Operacion y catalogo (orden: ver tablas_limpiar_gema.txt)
CALL sp_limpiar_delete_if_exists('cola_impresion');
CALL sp_limpiar_delete_if_exists('venta_descuentos');
CALL sp_limpiar_delete_if_exists('venta_pagos');
CALL sp_limpiar_delete_if_exists('venta_detalle');
CALL sp_limpiar_delete_if_exists('ventas');
CALL sp_limpiar_delete_if_exists('devoluciones');
CALL sp_limpiar_delete_if_exists('devoluciones_detalle');
CALL sp_limpiar_delete_if_exists('apartado_cambios_pieza');
CALL sp_limpiar_delete_if_exists('apartado_pagos');
CALL sp_limpiar_delete_if_exists('apartado_detalle');
CALL sp_limpiar_delete_if_exists('apartados');
CALL sp_limpiar_delete_if_exists('factura_detalle');
CALL sp_limpiar_delete_if_exists('facturas');
CALL sp_limpiar_delete_if_exists('cliente_credito_consumos');
CALL sp_limpiar_delete_if_exists('cliente_creditos');
CALL sp_limpiar_truncate_if_exists('cliente_credito_consumos');
CALL sp_limpiar_truncate_if_exists('cliente_creditos');
CALL sp_limpiar_truncate_if_exists('clientes');
CALL sp_limpiar_delete_if_exists('cierre_caja');
CALL sp_limpiar_delete_if_exists('cierre_caja_detalle');
CALL sp_limpiar_delete_if_exists('cierres_caja');
CALL sp_limpiar_delete_if_exists('reparaciones');
CALL sp_limpiar_delete_if_exists('gasto_comprobantes');
CALL sp_limpiar_delete_if_exists('gastos');
CALL sp_limpiar_delete_if_exists('usuario_notificacion');
CALL sp_limpiar_delete_if_exists('notificaciones');
CALL sp_limpiar_delete_if_exists('token_recuperacion_contrasena');
CALL sp_limpiar_delete_if_exists('auditoria_recuperacion_contrasena');
CALL sp_limpiar_delete_if_exists('auditoria_detalle');
CALL sp_limpiar_delete_if_exists('auditorias_inventario');
CALL sp_limpiar_delete_if_exists('bitacora_movimientos');
CALL sp_limpiar_delete_if_exists('pedido_proveedor_detalle');
CALL sp_limpiar_delete_if_exists('pedidos_proveedor');
CALL sp_limpiar_delete_if_exists('recepcion_proveedor_detalle');
CALL sp_limpiar_delete_if_exists('recepciones_proveedor');
CALL sp_limpiar_delete_if_exists('pagos_proveedores');
CALL sp_limpiar_delete_if_exists('proveedor_contactos');
CALL sp_limpiar_delete_if_exists('catalogo_compra');
CALL sp_limpiar_delete_if_exists('promociones_banner');
CALL sp_limpiar_delete_if_exists('promociones');
CALL sp_limpiar_delete_if_exists('precio_historico');
CALL sp_limpiar_delete_if_exists('imagenes_piezas');
CALL sp_limpiar_delete_if_exists('movimientos_inventario');
CALL sp_limpiar_delete_if_exists('piezas_stock');
CALL sp_limpiar_delete_if_exists('piezas');
CALL sp_limpiar_delete_if_exists('insumo_existencia');
CALL sp_limpiar_delete_if_exists('insumos');
CALL sp_limpiar_delete_if_exists('sub_familia');
CALL sp_limpiar_delete_if_exists('familias');
CALL sp_limpiar_delete_if_exists('metales');
CALL sp_limpiar_delete_if_exists('proveedores');

CALL sp_limpiar_usuarios_no_empleado();
CALL sp_limpiar_direcciones_huerfanas();

CALL sp_limpiar_truncate_if_exists('mig_gema_stock');
CALL sp_limpiar_truncate_if_exists('mig_gema_artic');
CALL sp_limpiar_truncate_if_exists('mig_gema_cliente');
CALL sp_limpiar_truncate_if_exists('mig_gema_proveedor');
CALL sp_limpiar_truncate_if_exists('mig_gema_metal');
CALL sp_limpiar_truncate_if_exists('mig_gema_familia');
CALL sp_limpiar_truncate_if_exists('mig_log');
CALL sp_limpiar_truncate_if_exists('mig_config');

SET FOREIGN_KEY_CHECKS = 1;

DROP PROCEDURE IF EXISTS sp_limpiar_delete_if_exists;
DROP PROCEDURE IF EXISTS sp_limpiar_truncate_if_exists;
DROP PROCEDURE IF EXISTS sp_limpiar_usuarios_no_empleado;
DROP PROCEDURE IF EXISTS sp_limpiar_direcciones_huerfanas;

SELECT '00_limpiar_datos_previos_gema.sql completado (todos los datos operativos).' AS mensaje;
