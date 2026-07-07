-- Modulo de Gestion de Gastos: catalogo de categorias y registro de gastos.
-- La app (admin/gastos.php + admin/models/gastos.php) espera estas tablas.
-- Ejecutar una sola vez sobre la misma BD que usa la app.
-- Sintomas si falta: el modulo carga el encabezado "Gestion de Gastos" pero el
-- contenido sale en blanco (PDOException "table 'gastos' doesn't exist" oculta
-- por display_errors apagado).

-- ---------------------------------------------------------------------------
-- 1) Catalogo de categorias de gasto
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gastos_categoria (
    id_categoria_gasto INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) NULL DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_categoria_gasto),
    UNIQUE KEY uq_gastos_categoria_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) Tabla de gastos
-- ---------------------------------------------------------------------------
-- Sin FOREIGN KEYs a proposito: evita que el CREATE falle si el tipo/engine
-- de empleados.id_empleado o forma_pago.id_forma_pago no coincide. La app ya
-- valida la integridad en Gastos::verificarExiste().
CREATE TABLE IF NOT EXISTS gastos (
    id_gasto INT NOT NULL AUTO_INCREMENT,
    id_categoria_FK INT NOT NULL,
    concepto VARCHAR(150) NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    fecha_gasto DATE NOT NULL,
    id_forma_pago_FK INT NULL DEFAULT NULL,
    id_empleado_FK INT NOT NULL,
    afecta_caja TINYINT(1) NOT NULL DEFAULT 0,
    observaciones TEXT NULL DEFAULT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_gasto),
    KEY idx_gastos_categoria (id_categoria_FK),
    KEY idx_gastos_forma_pago (id_forma_pago_FK),
    KEY idx_gastos_empleado (id_empleado_FK),
    KEY idx_gastos_fecha (fecha_gasto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2b) Compatibilidad: si ya existia una tabla 'gastos' vieja del esquema base,
--     agrega las columnas que el modelo (admin/models/gastos.php) necesita.
--     ADD COLUMN IF NOT EXISTS es soportado por MariaDB; no falla si ya existen.
-- ---------------------------------------------------------------------------
ALTER TABLE gastos ADD COLUMN IF NOT EXISTS id_forma_pago_FK INT NULL DEFAULT NULL;
ALTER TABLE gastos ADD COLUMN IF NOT EXISTS afecta_caja TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE gastos ADD COLUMN IF NOT EXISTS observaciones TEXT NULL DEFAULT NULL;
ALTER TABLE gastos ADD COLUMN IF NOT EXISTS fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE gastos ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1;

-- ---------------------------------------------------------------------------
-- 3) Categorias base (opcional; borra si no las quieres)
-- ---------------------------------------------------------------------------
INSERT INTO gastos_categoria (nombre, descripcion, activo)
VALUES
    ('Servicios', 'Luz, agua, internet, telefono', 1),
    ('Renta', 'Renta del local', 1),
    ('Nomina', 'Pagos y adelantos a empleados', 1),
    ('Insumos', 'Material de empaque y consumibles', 1),
    ('Mantenimiento', 'Reparaciones y mantenimiento', 1),
    ('Otros', 'Gastos varios', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- ---------------------------------------------------------------------------
-- 4) Permisos RBAC (idempotente; si ya existen no pasa nada)
-- ---------------------------------------------------------------------------
INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('GASTO_LEER', 'Consultar gastos', 1),
    ('GASTO_CREAR', 'Registrar gastos', 1),
    ('GASTO_ACTUALIZAR', 'Editar gastos', 1),
    ('GASTO_BORRAR', 'Eliminar gastos', 1),
    ('GASTO_CATEGORIA_LEER', 'Consultar categorias de gasto', 1),
    ('GASTO_CATEGORIA_CREAR', 'Crear categorias de gasto', 1),
    ('GASTO_CATEGORIA_ACTUALIZAR', 'Editar categorias de gasto', 1),
    ('GASTO_CATEGORIA_BORRAR', 'Eliminar categorias de gasto', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);
