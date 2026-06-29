-- Verificación de correo en registro de clientes (tienda en línea)

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS correo_verificado_en DATETIME NULL DEFAULT NULL
        COMMENT 'NULL = pendiente de confirmar por correo (solo altas tienda)';

-- Usuarios existentes: considerarlos verificados para no bloquear acceso
UPDATE usuarios
SET correo_verificado_en = COALESCE(correo_verificado_en, NOW())
WHERE correo_verificado_en IS NULL;

CREATE TABLE IF NOT EXISTS token_verificacion_correo_tienda
(
    id_token INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario_FK INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    correo_destino VARCHAR(255) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    fecha_vencimiento TIMESTAMP NOT NULL,
    utilizado TINYINT(1) DEFAULT 0,
    fecha_utilizacion TIMESTAMP NULL,
    ip_origen VARCHAR(45) NULL,
    user_agent TEXT NULL,
    CONSTRAINT token_verificacion_correo_usuario_fk
        FOREIGN KEY (id_usuario_FK) REFERENCES usuarios (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_token (token),
    INDEX idx_usuario (id_usuario_FK),
    INDEX idx_vencimiento (fecha_vencimiento),
    INDEX idx_utilizado (utilizado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
