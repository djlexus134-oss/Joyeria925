-- Fragmento: vacia TODOS los datos operativos y de catalogo (no solo Gema).
-- Requiere SET FOREIGN_KEY_CHECKS = 0 antes de ejecutar.
-- Usado por export_datos_gema_vps.ps1 y documentado en 00_limpiar_datos_previos_gema.sql.

DELETE FROM cola_impresion;
DELETE FROM venta_descuentos;
DELETE FROM venta_pagos;
DELETE FROM venta_detalle;
DELETE FROM ventas;
DELETE FROM devoluciones;
DELETE FROM apartado_cambios_pieza;
DELETE FROM apartado_pagos;
DELETE FROM apartado_detalle;
DELETE FROM apartados;
DELETE FROM factura_detalle;
DELETE FROM facturas;
-- TRUNCATE (mas fiable que DELETE) para evitar 1062 en clientes al re-importar con ids fijos del dump
TRUNCATE TABLE cliente_credito_consumos;
TRUNCATE TABLE cliente_creditos;
TRUNCATE TABLE clientes;
DELETE FROM cierre_caja;
DELETE FROM cierres_caja;
DELETE FROM reparaciones;
DELETE FROM gasto_comprobantes;
DELETE FROM gastos;
DELETE FROM usuario_notificacion;
DELETE FROM notificaciones;
DELETE FROM token_recuperacion_contrasena;
DELETE FROM auditoria_recuperacion_contrasena;
DELETE FROM auditoria_detalle;
DELETE FROM auditorias_inventario;
DELETE FROM bitacora_movimientos;
DELETE FROM pedido_proveedor_detalle;
DELETE FROM pedidos_proveedor;
DELETE FROM recepcion_proveedor_detalle;
DELETE FROM recepciones_proveedor;
DELETE FROM pagos_proveedores;
DELETE FROM proveedor_contactos;
DELETE FROM catalogo_compra;
DELETE FROM promociones_banner;
DELETE FROM promociones;
DELETE FROM precio_historico;
DELETE FROM imagenes_piezas;
DELETE FROM movimientos_inventario;
DELETE FROM piezas_stock;
DELETE FROM piezas;
DELETE FROM insumo_existencia;
DELETE FROM insumos;
DELETE FROM sub_familia;
DELETE FROM familias;
DELETE FROM metales;
DELETE FROM proveedores;

-- Usuarios que NO son empleados (clientes ya truncados arriba)
DELETE ur FROM usuario_rol ur
LEFT JOIN empleados e ON e.id_usuario_FK = ur.id_usuario_FK
WHERE e.id_empleado IS NULL;

DELETE u FROM usuarios u
LEFT JOIN empleados e ON e.id_usuario_FK = u.id_usuario
WHERE e.id_empleado IS NULL;

-- Direcciones huerfanas (solo quedan las de empleados/admin)
DELETE d FROM direcciones d
LEFT JOIN usuarios u ON u.id_direccion_FK = d.id_direccion
WHERE u.id_usuario IS NULL;

TRUNCATE TABLE mig_gema_stock;
TRUNCATE TABLE mig_gema_artic;
TRUNCATE TABLE mig_gema_cliente;
TRUNCATE TABLE mig_gema_proveedor;
TRUNCATE TABLE mig_gema_metal;
TRUNCATE TABLE mig_gema_familia;
TRUNCATE TABLE mig_log;
TRUNCATE TABLE mig_config;
