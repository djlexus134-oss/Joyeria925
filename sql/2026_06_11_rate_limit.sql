-- =====================================================================
-- Migracion: rate-limiting basico para endpoints sensibles de tienda
-- Fecha: 2026-06-11
-- Registra intentos por (accion, clave) en una ventana de tiempo para
-- frenar fuerza bruta de login y bombardeo de correos (registro,
-- recuperacion, reenvio de verificacion). Usado por
-- includes/rate_limit_helpers.php.
-- =====================================================================

CREATE TABLE IF NOT EXISTS rate_limit_intentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    accion VARCHAR(40) NOT NULL,
    clave VARCHAR(190) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_accion_clave_creado (accion, clave, creado_en),
    KEY idx_creado_en (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
