-- Modulo apartados: quitar / agregar pieza con recalculo automatico
-- + monedero de creditos del cliente (excedentes por baja de pieza).
-- Reemplaza el flujo de "cambio de pieza" (deprecado).
-- Ejecutar en la base de datos de Joyeria.

-- 1) Monedero del cliente: creditos a favor (excedente_apartado / devolucion / ajuste)
CREATE TABLE IF NOT EXISTS cliente_creditos (
    id_credito                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cliente_FK             INT NOT NULL,
    monto                     DECIMAL(10, 2) NOT NULL,
    monto_disponible          DECIMAL(10, 2) NOT NULL,
    tipo                      ENUM ('excedente_apartado', 'devolucion', 'ajuste') NOT NULL DEFAULT 'excedente_apartado',
    estado                    ENUM ('disponible', 'consumido', 'anulado') NOT NULL DEFAULT 'disponible',
    id_apartado_origen_FK     INT NULL,
    observaciones             VARCHAR(255) NULL,
    id_empleado_FK            INT NOT NULL,
    id_usuario_FK             INT NOT NULL,
    fecha_registro            TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    CONSTRAINT fk_cliente_credito_cliente
        FOREIGN KEY (id_cliente_FK) REFERENCES clientes (id_cliente)
            ON UPDATE CASCADE,
    CONSTRAINT fk_cliente_credito_apartado
        FOREIGN KEY (id_apartado_origen_FK) REFERENCES apartados (id_apartado)
            ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_cliente_credito_empleado
        FOREIGN KEY (id_empleado_FK) REFERENCES empleados (id_empleado)
            ON UPDATE CASCADE,
    CONSTRAINT fk_cliente_credito_usuario
        FOREIGN KEY (id_usuario_FK) REFERENCES usuarios (id_usuario)
            ON UPDATE CASCADE,
    CONSTRAINT chk_cliente_credito_montos
        CHECK (monto > 0 AND monto_disponible >= 0 AND monto_disponible <= monto)
) COLLATE = utf8mb4_0900_ai_ci;

CREATE INDEX idx_cliente_credito_cliente_estado
    ON cliente_creditos (id_cliente_FK, estado);
CREATE INDEX idx_cliente_credito_apartado
    ON cliente_creditos (id_apartado_origen_FK);

-- 2) Consumos de credito (trazabilidad para v2: gastar en otro apartado / POS)
CREATE TABLE IF NOT EXISTS cliente_credito_consumos (
    id_consumo            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_credito_FK         INT UNSIGNED NOT NULL,
    monto                 DECIMAL(10, 2) NOT NULL,
    tipo_uso              ENUM ('abono_apartado', 'venta_pos', 'alta_apartado', 'ajuste') NOT NULL,
    id_apartado_FK        INT NULL,
    id_venta_FK           INT NULL,
    id_apartado_pago_FK   INT NULL,
    id_empleado_FK        INT NULL,
    id_usuario_FK         INT NOT NULL,
    fecha_registro        TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    observaciones         VARCHAR(255) NULL,
    CONSTRAINT fk_credito_consumo_credito
        FOREIGN KEY (id_credito_FK) REFERENCES cliente_creditos (id_credito)
            ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_credito_consumo_apartado
        FOREIGN KEY (id_apartado_FK) REFERENCES apartados (id_apartado)
            ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_credito_consumo_apartado_pago
        FOREIGN KEY (id_apartado_pago_FK) REFERENCES apartado_pagos (id_pago)
            ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_credito_consumo_empleado
        FOREIGN KEY (id_empleado_FK) REFERENCES empleados (id_empleado)
            ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_credito_consumo_usuario
        FOREIGN KEY (id_usuario_FK) REFERENCES usuarios (id_usuario)
            ON UPDATE CASCADE,
    CONSTRAINT chk_credito_consumo_monto
        CHECK (monto > 0)
) COLLATE = utf8mb4_0900_ai_ci;

CREATE INDEX idx_credito_consumo_credito ON cliente_credito_consumos (id_credito_FK);
CREATE INDEX idx_credito_consumo_apartado ON cliente_credito_consumos (id_apartado_FK);

-- 3) Permisos del nuevo flujo (asignar a roles desde el panel)
INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('APARTADO_GESTION_QUITAR_PIEZA',  'Quitar piezas de apartados activos (libera stock, recalcula saldo, genera credito si excede)', 1),
    ('APARTADO_GESTION_AGREGAR_PIEZA', 'Agregar piezas a apartados activos (misma tienda, recalcula total y saldo)', 1),
    ('CLIENTE_CREDITO_LEER',           'Consultar el monedero de creditos del cliente', 1),
    ('CLIENTE_CREDITO_AJUSTAR',        'Ajustar manualmente creditos del cliente (uso administrativo)', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- 4) Asignar al rol ADMINISTRADOR (opcional, omitir si ya se asignan por panel)
INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre_rol = 'ADMINISTRADOR'
  AND p.nombre_permiso IN (
      'APARTADO_GESTION_QUITAR_PIEZA',
      'APARTADO_GESTION_AGREGAR_PIEZA',
      'CLIENTE_CREDITO_LEER',
      'CLIENTE_CREDITO_AJUSTAR'
  );
