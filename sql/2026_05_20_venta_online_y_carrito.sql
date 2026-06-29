-- =====================================================================
-- Migracion: Venta en linea + Carrito de compras + Notificaciones
-- Fecha: 2026-05-20
-- Sistema: Joyeria Plateria El Angel
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) piezas_stock: estado "reservada_online" con TTL y dueno de carrito
-- ---------------------------------------------------------------------
-- Incluir TODOS los estados ya usados en admin/models/piezas_stock.php mas reservada_online (carrito web).
ALTER TABLE piezas_stock
    MODIFY COLUMN estado ENUM(
        'disponible','vendida','apartada','defectuosa','reparacion','reservada_online'
    ) NOT NULL DEFAULT 'disponible';

ALTER TABLE piezas_stock
    ADD COLUMN IF NOT EXISTS reservada_hasta DATETIME NULL AFTER estado,
    ADD COLUMN IF NOT EXISTS id_carrito_owner INT NULL AFTER reservada_hasta;

CREATE INDEX idx_piezas_stock_estado_reserva ON piezas_stock (estado, reservada_hasta);
CREATE INDEX idx_piezas_stock_carrito_owner ON piezas_stock (id_carrito_owner);

-- ---------------------------------------------------------------------
-- 2) ventas: agregar origen, estado_pago, estado_entrega, tienda, etc.
-- ---------------------------------------------------------------------
ALTER TABLE ventas
    MODIFY COLUMN id_empleado_FK INT NULL;

ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS origen ENUM('mostrador','online') NOT NULL DEFAULT 'mostrador' AFTER id_cliente_FK,
    ADD COLUMN IF NOT EXISTS id_tienda_FK INT NULL AFTER origen,
    ADD COLUMN IF NOT EXISTS estado_pago ENUM('pendiente','pagado','rechazado','reembolsado') NOT NULL DEFAULT 'pagado' AFTER origen,
    ADD COLUMN IF NOT EXISTS id_pago_externo VARCHAR(80) NULL AFTER estado_pago,
    ADD COLUMN IF NOT EXISTS referencia_pago VARCHAR(160) NULL AFTER id_pago_externo,
    ADD COLUMN IF NOT EXISTS estado_entrega ENUM('pendiente','lista_recoger','entregada','cancelada') NOT NULL DEFAULT 'entregada' AFTER referencia_pago,
    ADD COLUMN IF NOT EXISTS fecha_lista_recoger DATETIME NULL AFTER estado_entrega,
    ADD COLUMN IF NOT EXISTS fecha_entregada DATETIME NULL AFTER fecha_lista_recoger,
    ADD COLUMN IF NOT EXISTS entregada_por_FK INT NULL AFTER fecha_entregada,
    ADD COLUMN IF NOT EXISTS aceptacion_entrega_tienda TINYINT(1) NOT NULL DEFAULT 0 AFTER entregada_por_FK;

-- FK opcionales
ALTER TABLE ventas
    ADD CONSTRAINT fk_ventas_tienda FOREIGN KEY (id_tienda_FK) REFERENCES tiendas(id_tienda),
    ADD CONSTRAINT fk_ventas_entregada_por FOREIGN KEY (entregada_por_FK) REFERENCES empleados(id_empleado);

CREATE INDEX idx_ventas_origen_estado ON ventas (origen, estado_pago);
CREATE INDEX idx_ventas_id_pago_externo ON ventas (id_pago_externo);
CREATE INDEX idx_ventas_estado_entrega ON ventas (estado_entrega);

-- Asegurar coherencia para ventas previas (todas son de mostrador y pagadas)
UPDATE ventas SET origen = 'mostrador', estado_pago = 'pagado', estado_entrega = 'entregada'
WHERE origen IS NULL OR origen = '';

-- ---------------------------------------------------------------------
-- 3) carrito_items: carrito persistente por cliente
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carrito_items (
    id_carrito_item INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente_FK INT NOT NULL,
    id_pieza_stock_FK INT NOT NULL UNIQUE,
    precio_unitario_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_carrito_items_cliente FOREIGN KEY (id_cliente_FK) REFERENCES clientes(id_cliente),
    CONSTRAINT fk_carrito_items_pieza_stock FOREIGN KEY (id_pieza_stock_FK) REFERENCES piezas_stock(id_pieza_stock),
    INDEX idx_carrito_items_cliente (id_cliente_FK),
    INDEX idx_carrito_items_fecha (fecha_alta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) notificaciones: ampliar para usos del sistema (venta online, pickup)
-- ---------------------------------------------------------------------
ALTER TABLE notificaciones
    ADD COLUMN IF NOT EXISTS tipo VARCHAR(60) NULL AFTER mensaje,
    ADD COLUMN IF NOT EXISTS id_referencia INT NULL AFTER tipo,
    ADD COLUMN IF NOT EXISTS id_tienda_FK INT NULL AFTER id_referencia,
    ADD COLUMN IF NOT EXISTS id_destino_usuario_FK INT NULL AFTER id_tienda_FK;

ALTER TABLE notificaciones
    ADD CONSTRAINT fk_notif_tienda FOREIGN KEY (id_tienda_FK) REFERENCES tiendas(id_tienda),
    ADD CONSTRAINT fk_notif_destino_usuario FOREIGN KEY (id_destino_usuario_FK) REFERENCES usuarios(id_usuario);

CREATE INDEX idx_notif_tipo ON notificaciones (tipo);
CREATE INDEX idx_notif_tienda_destino ON notificaciones (id_tienda_FK, id_destino_usuario_FK);
CREATE INDEX idx_notif_referencia ON notificaciones (tipo, id_referencia);

-- ---------------------------------------------------------------------
-- 5) RBAC: nuevos permisos para Ventas en linea y Notificaciones panel
-- ---------------------------------------------------------------------
INSERT IGNORE INTO permisos (nombre_permiso, descripcion) VALUES
('VENTA_ONLINE_LEER',       'Ver listado de ventas en linea'),
('VENTA_ONLINE_ACTUALIZAR', 'Marcar piezas listas/entregadas en ventas en linea'),
('VENTA_ONLINE_CREAR',      'Crear / iniciar pedidos en linea (uso interno)'),
('VENTA_ONLINE_BORRAR',     'Cancelar ventas en linea'),
('NOTIFICACION_PANEL_LEER', 'Ver campana de notificaciones del panel');

-- Asignar a rol ADMINISTRADOR si existe
INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
JOIN permisos p ON p.nombre_permiso IN (
    'VENTA_ONLINE_LEER','VENTA_ONLINE_ACTUALIZAR','VENTA_ONLINE_CREAR','VENTA_ONLINE_BORRAR','NOTIFICACION_PANEL_LEER'
)
WHERE r.nombre_rol = 'ADMINISTRADOR';
