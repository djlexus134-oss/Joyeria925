-- Migracion Gema: clien -> usuarios + clientes
-- Ejecutar despues de 02_mig_catalogos.sql

SET NAMES utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';

SET @current_user_id = CAST((SELECT valor FROM mig_config WHERE clave = 'id_usuario_migracion') AS UNSIGNED);
SET @current_ip = '127.0.0.1';

DROP PROCEDURE IF EXISTS sp_mig_gema_clientes;
DELIMITER $$
CREATE PROCEDURE sp_mig_gema_clientes()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_numcli DOUBLE;
    DECLARE v_nom VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_apel VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_email VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_tel VARCHAR(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_dto DOUBLE;
    DECLARE v_bloq CHAR(1);
    DECLARE v_activo TINYINT;
    DECLARE v_nombre VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_ap1 VARCHAR(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_ap2 VARCHAR(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_correo VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_telefono VARCHAR(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_hash VARCHAR(255);
    DECLARE v_id_usuario INT;
    DECLARE v_id_cliente INT;
    DECLARE v_id_rol_cliente INT;
    DECLARE v_dom VARCHAR(32);
    DECLARE v_descuento TINYINT;

    DECLARE cur CURSOR FOR
        SELECT c.numcli,
               TRIM(IFNULL(c.nomcli, '')),
               TRIM(IFNULL(c.apelcli, '')),
               TRIM(COALESCE(NULLIF(TRIM(c.email1cli), ''), NULLIF(TRIM(c.emailcli), ''), '')),
               TRIM(COALESCE(NULLIF(TRIM(c.telcel1cli), ''), NULLIF(TRIM(c.tel1cli), ''), '')),
               c.dtocli,
               IFNULL(c.bloqcli, 'F')
        FROM gema_staging.clien c;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    SET v_hash = (SELECT valor FROM mig_config WHERE clave = 'contrasena_migracion_hash');
    SET v_dom = (SELECT valor FROM mig_config WHERE clave = 'correo_dominio');

    BEGIN
        DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
        SELECT id_rol INTO v_id_rol_cliente
        FROM roles
        WHERE activo = 1 AND mig_fn_ci(nombre_rol) = 'cliente'
        LIMIT 1;
    END;

    OPEN cur;
    cli_loop: LOOP
        FETCH cur INTO v_numcli, v_nom, v_apel, v_email, v_tel, v_dto, v_bloq;
        IF v_done THEN LEAVE cli_loop; END IF;

        IF EXISTS (SELECT 1 FROM mig_gema_cliente WHERE numcli = v_numcli) THEN
            ITERATE cli_loop;
        END IF;

        SET v_activo = IF(v_bloq = 'T', 0, 1);

        SET v_nombre = LEFT(IF(v_nom = '' OR LOWER(v_nom) = 'null', 'Cliente', v_nom), 50);
        SET v_ap1 = LEFT(SUBSTRING_INDEX(IF(v_apel = '' OR LOWER(v_apel) = 'null', 'Migrado', v_apel), ' ', 1), 25);
        SET v_ap2 = LEFT(NULLIF(TRIM(SUBSTRING(IF(v_apel = '' OR LOWER(v_apel) = 'null', '', v_apel), LENGTH(v_ap1) + 1)), ''), 25);

        SET v_correo = LOWER(TRIM(v_email));
        IF v_correo = '' OR v_correo = 'null' OR v_correo NOT LIKE '%@%' THEN
            SET v_correo = CONCAT('gema', CAST(v_numcli AS UNSIGNED), v_dom);
        END IF;

        SET v_correo = LEFT(v_correo, 80);
        IF EXISTS (SELECT 1 FROM usuarios u WHERE mig_fn_ci(u.correo) = mig_fn_ci(v_correo)) THEN
            SET v_correo = CONCAT('gema', CAST(v_numcli AS UNSIGNED), '.dup', v_dom);
            SET v_correo = LEFT(v_correo, 80);
        END IF;

        SET v_telefono = REGEXP_REPLACE(IF(v_tel = '' OR LOWER(v_tel) = 'null', '', v_tel), '[^0-9+]', '');
        IF v_telefono = '' OR CHAR_LENGTH(v_telefono) < 7 THEN
            SET v_telefono = CONCAT('9', LPAD(CAST(v_numcli AS UNSIGNED) % 10000000000, 10, '0'));
        END IF;
        SET v_telefono = LEFT(v_telefono, 15);

        IF EXISTS (SELECT 1 FROM usuarios u WHERE mig_fn_cs(u.telefono) = mig_fn_cs(v_telefono)) THEN
            SET v_telefono = LEFT(CONCAT('8', LPAD(CAST(v_numcli AS UNSIGNED) % 1000000000, 9, '0')), 15);
        END IF;

        SET v_descuento = LEAST(100, GREATEST(0, CAST(IFNULL(v_dto, 0) AS UNSIGNED)));

        INSERT INTO usuarios (
            nombre, primer_apellido, segundo_apellido, contrasena, correo, telefono,
            id_direccion_FK, activo
        ) VALUES (
            v_nombre, v_ap1, v_ap2, v_hash, v_correo, v_telefono,
            NULL, v_activo
        );
        SET v_id_usuario = LAST_INSERT_ID();

        INSERT INTO clientes (id_usuario_FK, descuento_porcentaje, activo)
        VALUES (v_id_usuario, v_descuento, v_activo);
        SET v_id_cliente = LAST_INSERT_ID();

        IF v_id_rol_cliente IS NOT NULL THEN
            INSERT IGNORE INTO usuario_rol (id_usuario_FK, id_rol_FK)
            VALUES (v_id_usuario, v_id_rol_cliente);
        END IF;

        INSERT INTO mig_gema_cliente (numcli, id_usuario, id_cliente, correo_usado, telefono_usado, activo_destino)
        VALUES (v_numcli, v_id_usuario, v_id_cliente, v_correo, v_telefono, v_activo);

    END LOOP;
    CLOSE cur;

    INSERT INTO mig_log (etapa, nivel, mensaje)
    SELECT 'clientes', 'INFO', CONCAT('Clientes migrados: ', COUNT(*)) FROM mig_gema_cliente;
END$$
DELIMITER ;

CALL sp_mig_gema_clientes();

SELECT '03_mig_clientes.sql completado.' AS mensaje,
       (SELECT COUNT(*) FROM mig_gema_cliente) AS clientes_migrados,
       (SELECT COUNT(*) FROM gema_staging.clien) AS clientes_origen;
