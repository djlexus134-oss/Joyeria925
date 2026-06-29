-- Migracion Gema -> Joyeria: tablas de mapeo y configuracion.
-- Ejecutar una sola vez en la base destino (joyeria o joyeria_mig_test).
-- Requiere esquema gema_staging importado (ver 00_staging_import.md).

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Configuracion (editar antes de migrar si hace falta)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mig_config (
    clave       VARCHAR(64)  NOT NULL PRIMARY KEY,
    valor       VARCHAR(255) NOT NULL,
    descripcion VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mig_config (clave, valor, descripcion) VALUES
    ('gema_staging_db', 'gema_staging', 'Esquema origen con tablas artic/piezas/clien'),
    ('correo_dominio', '@migracion.local', 'Sufijo correos sinteticos gema{numcli}@...'),
    ('contrasena_migracion_plano', 'Migracion2026!', 'Password inicial clientes migrados (cambiar en mig_config + hash antes de produccion)'),
    ('referencia_movimiento', 'MIG-GEMA', 'Referencia en movimientos_inventario'),
    ('lote_commit', '500', 'Filas por lote interno en procedimientos')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- Hash bcrypt de Migracion2026! (cost 12) — generado con PHP password_hash
INSERT INTO mig_config (clave, valor, descripcion) VALUES
    ('contrasena_migracion_hash', '$2y$12$KeAYgKWt7PjDvbf5PwyhN.i.bX6bqrWBOsMYeMquS/G.k7uvtWd5q', 'Hash para usuarios cliente migrados')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- Defaults operativos (se rellenan en 02 si estan vacios)
INSERT INTO mig_config (clave, valor, descripcion) VALUES
    ('id_tienda_default', '', 'id_tienda para piezas migradas'),
    ('id_metal_default', '', 'id_metal si TIPAART no mapea'),
    ('id_usuario_migracion', '', 'id_usuario para bitacora y movimientos')
ON DUPLICATE KEY UPDATE clave = clave;

-- ---------------------------------------------------------------------------
-- Log de advertencias
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mig_log (
    id_log      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    etapa       VARCHAR(40)     NOT NULL,
    nivel       ENUM('INFO','WARN','ERROR') NOT NULL DEFAULT 'INFO',
    clave       VARCHAR(100)    NULL,
    mensaje     TEXT            NOT NULL,
    fecha_log   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mig_log_etapa (etapa, fecha_log)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Mapeos
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mig_gema_familia (
    codfam          VARCHAR(5)  NOT NULL PRIMARY KEY,
    id_familia      INT         NOT NULL,
    id_sub_familia  INT         NOT NULL,
    nom_familia     VARCHAR(50) NOT NULL,
    fecha_mig       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mig_gema_metal (
    tipaart     VARCHAR(5) NOT NULL PRIMARY KEY,
    id_metal    INT        NOT NULL,
    descripcion VARCHAR(30) NULL,
    fecha_mig   DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mig_gema_proveedor (
    codpro          DOUBLE NOT NULL PRIMARY KEY,
    id_proveedor    INT    NULL,
    razon_legacy    VARCHAR(100) NULL,
    fecha_mig       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mig_gema_cliente (
    numcli          DOUBLE NOT NULL PRIMARY KEY,
    id_usuario      INT    NOT NULL,
    id_cliente      INT    NOT NULL,
    correo_usado    VARCHAR(80) NOT NULL,
    telefono_usado  VARCHAR(15) NOT NULL,
    activo_destino  TINYINT NOT NULL,
    fecha_mig       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mig_gema_cliente_usuario (id_usuario),
    UNIQUE KEY uq_mig_gema_cliente_cliente (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mig_gema_artic (
    codart      BIGINT NOT NULL PRIMARY KEY,
    id_pieza    INT    NOT NULL,
    fecha_mig   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mig_gema_artic_pieza (id_pieza)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mig_gema_stock (
    artpie          BIGINT NOT NULL,
    codpie          BIGINT NOT NULL,
    id_pieza_stock  INT    NOT NULL,
    id_pieza        INT    NOT NULL,
    estado_destino  VARCHAR(20) NOT NULL,
    codigo_auxiliar VARCHAR(20) NOT NULL,
    codigo_barras   VARCHAR(50) NOT NULL,
    fecha_mig       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (artpie, codpie),
    UNIQUE KEY uq_mig_gema_stock_id (id_pieza_stock),
    UNIQUE KEY uq_mig_gema_stock_aux (codigo_auxiliar),
    UNIQUE KEY uq_mig_gema_stock_bar (codigo_barras)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Funcion: digito verificador EAN-13 (12 digitos base)
-- ---------------------------------------------------------------------------
DROP FUNCTION IF EXISTS mig_fn_ean13_check_digit;
DELIMITER $$
CREATE FUNCTION mig_fn_ean13_check_digit(p_base12 CHAR(12))
RETURNS CHAR(1)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_i INT DEFAULT 1;
    DECLARE v_sum INT DEFAULT 0;
    DECLARE v_d INT;
    DECLARE v_len INT;

    SET v_len = CHAR_LENGTH(p_base12);
    IF v_len <> 12 OR p_base12 NOT REGEXP '^[0-9]{12}$' THEN
        RETURN '0';
    END IF;

    WHILE v_i <= 12 DO
        SET v_d = CAST(SUBSTRING(p_base12, v_i, 1) AS UNSIGNED);
        IF (v_i - 1) MOD 2 = 0 THEN
            SET v_sum = v_sum + v_d;
        ELSE
            SET v_sum = v_sum + v_d * 3;
        END IF;
        SET v_i = v_i + 1;
    END WHILE;

    RETURN CAST((10 - (v_sum MOD 10)) MOD 10 AS CHAR);
END$$
DELIMITER ;

-- ---------------------------------------------------------------------------
-- Funcion: estado piezas_stock desde flags Gema
-- ---------------------------------------------------------------------------
DROP FUNCTION IF EXISTS mig_fn_estado_pieza_gema;
DELIMITER $$
CREATE FUNCTION mig_fn_estado_pieza_gema(
    p_btaltpie CHAR(1),
    p_brespie CHAR(1),
    p_brespie2 CHAR(1),
    p_clipie DOUBLE,
    p_clipie2 DOUBLE,
    p_factpie VARCHAR(20),
    p_albpie CHAR(1)
)
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    IF p_btaltpie = 'T' THEN
        RETURN 'reparacion';
    END IF;
    IF p_brespie = 'T' OR p_brespie2 = 'T'
       OR (p_clipie IS NOT NULL AND p_clipie <> 0)
       OR (p_clipie2 IS NOT NULL AND p_clipie2 <> 0) THEN
        RETURN 'apartada';
    END IF;
    IF (p_factpie IS NOT NULL AND TRIM(p_factpie) <> '') OR p_albpie = 'T' THEN
        RETURN 'vendida';
    END IF;
    RETURN 'disponible';
END$$
DELIMITER ;

-- ---------------------------------------------------------------------------
-- Codigos legacy Gema por unidad (ARTPIE / CODPIE)
-- ---------------------------------------------------------------------------
DROP FUNCTION IF EXISTS mig_fn_legacy_auxiliar_gema;
DELIMITER $$
CREATE FUNCTION mig_fn_legacy_auxiliar_gema(p_artpie BIGINT, p_codpie BIGINT)
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    RETURN LEFT(CONCAT(CAST(p_artpie AS CHAR), '/', CAST(p_codpie AS CHAR)), 20);
END$$
DELIMITER ;

DROP FUNCTION IF EXISTS mig_fn_legacy_barras_gema;
DELIMITER $$
CREATE FUNCTION mig_fn_legacy_barras_gema(p_artpie BIGINT, p_codpie BIGINT)
RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
    DECLARE v_base12 CHAR(12);
    DECLARE v_art6 CHAR(6);
    DECLARE v_cod4 CHAR(4);

    SET v_art6 = LPAD(CAST(LEAST(p_artpie, 999999) AS CHAR), 6, '0');
    SET v_cod4 = LPAD(CAST(LEAST(p_codpie, 999999) AS CHAR), 4, '0');
    SET v_base12 = CONCAT('20', v_art6, v_cod4);

    RETURN CONCAT(v_base12, mig_fn_ean13_check_digit(v_base12));
END$$
DELIMITER ;

-- Comparaciones texto entre gema_staging / mig_* (unicode_ci) y tablas joyeria (0900/uca1400)
DROP FUNCTION IF EXISTS mig_fn_ci;
DELIMITER $$
CREATE FUNCTION mig_fn_ci(p TEXT)
RETURNS TEXT
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci
DETERMINISTIC
BEGIN
    IF p IS NULL THEN RETURN NULL; END IF;
    RETURN LOWER(TRIM(CONVERT(p USING utf8mb4))) COLLATE utf8mb4_unicode_ci;
END$$
DELIMITER ;

DROP FUNCTION IF EXISTS mig_fn_cs;
DELIMITER $$
CREATE FUNCTION mig_fn_cs(p TEXT)
RETURNS TEXT
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci
DETERMINISTIC
BEGIN
    IF p IS NULL THEN RETURN NULL; END IF;
    RETURN TRIM(CONVERT(p USING utf8mb4)) COLLATE utf8mb4_unicode_ci;
END$$
DELIMITER ;

SELECT '01_mig_tablas_mapeo.sql completado.' AS mensaje;
