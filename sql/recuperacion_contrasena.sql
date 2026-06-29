-- Tabla para gestionar tokens de recuperación de contraseña
CREATE TABLE IF NOT EXISTS token_recuperacion_contrasena
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
    CONSTRAINT token_recuperacion_usuario_fk
        FOREIGN KEY (id_usuario_FK) REFERENCES usuarios (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_token (token),
    INDEX idx_usuario (id_usuario_FK),
    INDEX idx_vencimiento (fecha_vencimiento),
    INDEX idx_utilizado (utilizado)
) ENGINE=InnoDB DEFAULT CHARSET=UTF8MB4 COLLATE=utf8mb4_0900_ai_ci;

-- Tabla para auditar intentos de recuperación de contraseña (seguridad)
CREATE TABLE IF NOT EXISTS auditoria_recuperacion_contrasena
(
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
    correo_solicitante VARCHAR(255) NOT NULL,
    tipo_evento ENUM('solicitud', 'validacion_token', 'reset_exitoso', 'reset_fallido') NOT NULL,
    ip_origen VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    descripcion TEXT NULL,
    fecha_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    INDEX idx_correo (correo_solicitante),
    INDEX idx_evento (tipo_evento),
    INDEX idx_fecha (fecha_evento)
) ENGINE=InnoDB DEFAULT CHARSET=UTF8MB4 COLLATE=utf8mb4_0900_ai_ci;
