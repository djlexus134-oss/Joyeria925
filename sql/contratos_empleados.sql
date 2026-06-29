-- Crear tabla contratos_empleados (collation compatible MariaDB en VPS)
CREATE TABLE IF NOT EXISTS contratos_empleados
(
    id_contrato       INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado_FK    INT NOT NULL,
    tipo_contrato     ENUM ('Indeterminado', 'Tiempo Determinado', 'Obra Determinada', 'Periodo de Prueba', 'Capacitacion Inicial') NOT NULL,
    fecha_inicio      DATE NOT NULL,
    fecha_fin         DATE NULL,
    ruta_archivo      VARCHAR(255) NULL,
    observaciones     TEXT NULL,
    activo            TINYINT(1) DEFAULT 1 NOT NULL,
    fecha_registro    TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NULL,
    fecha_baja        TIMESTAMP NULL,
    id_usuario_baja   INT NULL,
    CONSTRAINT contratos_empleados_ibfk_1
        FOREIGN KEY (id_empleado_FK) REFERENCES empleados (id_empleado) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT contratos_empleados_usuario_baja_fk
        FOREIGN KEY (id_usuario_baja) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX id_empleado_FK (id_empleado_FK),
    INDEX idx_activo (activo),
    INDEX idx_fecha_registro (fecha_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentarios descriptivos (omitidos si la tabla ya existia con otro collation)
