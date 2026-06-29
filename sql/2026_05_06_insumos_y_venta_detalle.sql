-- Insumos (limpieza, empaque, etc.) + extension venta_detalle para lineas tipo insumo vs joya (serializada).
-- Ejecutar una sola vez sobre la misma BD que usa la app.
-- Nota MariaDB: no se define CHECK en venta_detalle (error 1901 con expresiones sobre varias columnas);
-- la coherencia tipo_linea / FKs se aplica en PHP (Ventas::procesarDetallesSiExisten).
-- Tras desplegar codigo, vuelve a aplicar sql/kpi_views.sql si usas la vista vw_productos_top_ventas.
--
-- RBAC opcional: alta de permisos INSUMO_LEER, INSUMO_CREAR, INSUMO_ACTUALIZAR, INSUMO_BORRAR
-- y su asignacion por rol (los administradores ya pasan auth sin permisos explícitos).

SET @schema := DATABASE();

-- ---------------------------------------------------------------------------
-- 1) Catalogo insumos
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS insumos (
    id_insumo INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    categoria VARCHAR(80) NULL DEFAULT NULL,
    sku_codigo VARCHAR(50) NULL DEFAULT NULL,
    costo_referencia DECIMAL(12,2) NULL DEFAULT NULL,
    precio_venta_sugerido DECIMAL(12,2) NULL DEFAULT NULL,
    observaciones VARCHAR(500) NULL DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_insumo),
    UNIQUE KEY uq_insumos_sku (sku_codigo),
    KEY idx_insumos_activo (activo),
    KEY idx_insumos_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) Existencia por tienda (cantidad acumulada)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS insumo_existencia (
    id_insumo_existencia INT NOT NULL AUTO_INCREMENT,
    id_insumo_FK INT NOT NULL,
    id_tienda_FK INT NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL DEFAULT 0,
    fecha_actualizado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_insumo_existencia),
    UNIQUE KEY uq_insumo_tienda (id_insumo_FK, id_tienda_FK),
    KEY idx_existencia_tienda (id_tienda_FK),
    CONSTRAINT fk_insumo_existencia_insumo
        FOREIGN KEY (id_insumo_FK) REFERENCES insumos (id_insumo)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_insumo_existencia_tienda
        FOREIGN KEY (id_tienda_FK) REFERENCES tiendas (id_tienda)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3) Venta detalle: permitir FK pieza stock nula e insumos
-- ---------------------------------------------------------------------------
SELECT CONSTRAINT_NAME
INTO @vd_fk_piezas_stock
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'venta_detalle'
  AND COLUMN_NAME = 'id_pieza_stock_FK'
  AND REFERENCED_TABLE_NAME IS NOT NULL
LIMIT 1;

SET @drop_fk_sql := IF(
    @vd_fk_piezas_stock IS NOT NULL,
    CONCAT('ALTER TABLE venta_detalle DROP FOREIGN KEY `', @vd_fk_piezas_stock, '`'),
    'SELECT 1'
);

PREPARE drop_fk FROM @drop_fk_sql;
EXECUTE drop_fk;
DEALLOCATE PREPARE drop_fk;

ALTER TABLE venta_detalle
    MODIFY id_pieza_stock_FK INT NULL DEFAULT NULL,
    ADD COLUMN tipo_linea ENUM('joya', 'insumo') NOT NULL DEFAULT 'joya',
    ADD COLUMN id_insumo_FK INT NULL DEFAULT NULL,
    ADD COLUMN id_tienda_FK INT NULL DEFAULT NULL,
    ADD CONSTRAINT fk_venta_detalle_insumo
        FOREIGN KEY (id_insumo_FK) REFERENCES insumos (id_insumo)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_venta_detalle_tienda_linea
        FOREIGN KEY (id_tienda_FK) REFERENCES tiendas (id_tienda)
        ON UPDATE CASCADE ON DELETE RESTRICT;

-- Reactivar FK piezas_stock (id_pieza_stock_FK puede ser NULL en lineas de insumo).
ALTER TABLE venta_detalle
    ADD CONSTRAINT fk_venta_detalle_piezas_stock
        FOREIGN KEY (id_pieza_stock_FK) REFERENCES piezas_stock (id_pieza_stock)
        ON UPDATE CASCADE ON DELETE RESTRICT;

-- Actualizar historico si existiera: todo lo previo era joyeria serializada.
UPDATE venta_detalle SET tipo_linea = 'joya' WHERE id_pieza_stock_FK IS NOT NULL;
