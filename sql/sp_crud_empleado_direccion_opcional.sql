-- Reemplaza sp_crud_empleado: direccion opcional en CREATE/UPDATE y READ con LEFT JOIN.
-- Ejecutar en la misma base/schema que el procedimiento actual (p. ej. joyeria).
-- Ajusta referencias de tablas si no usas el esquema joyeria.

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_crud_empleado$$

CREATE PROCEDURE sp_crud_empleado(
    IN p_accion VARCHAR(10),
    IN p_id_empleado INT,
    IN p_id_puesto_FK INT,
    IN p_salario DECIMAL(10,2),
    IN p_curp VARCHAR(18),
    IN p_rfc VARCHAR(13),
    IN p_nss VARCHAR(11),
    IN p_id_usuario_baja INT,
    IN p_nombre VARCHAR(50),
    IN p_primer_apellido VARCHAR(25),
    IN p_segundo_apellido VARCHAR(25),
    IN p_contrasena VARCHAR(255),
    IN p_correo VARCHAR(80),
    IN p_telefono VARCHAR(15),
    IN p_num_exterior INT,
    IN p_num_interior INT,
    IN p_id_calle_FK INT
)
BEGIN
    DECLARE v_id_direccion INT;
    DECLARE v_id_usuario INT;
    DECLARE v_sqlstate CHAR(5);
    DECLARE v_errno INT;
    DECLARE v_error_text TEXT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            v_sqlstate = RETURNED_SQLSTATE,
            v_errno = MYSQL_ERRNO,
            v_error_text = MESSAGE_TEXT;
        ROLLBACK;
        SELECT CONCAT('Error BD [', v_errno, '/', v_sqlstate, ']: ', v_error_text) AS Mensaje;
    END;

    IF p_accion = 'CREATE' THEN
        IF p_id_puesto_FK IS NULL OR NOT EXISTS (SELECT 1 FROM puestos WHERE id_puesto = p_id_puesto_FK) THEN
            SELECT 'Error: El puesto seleccionado no existe.' AS Mensaje;
        ELSEIF EXISTS (
            SELECT 1 FROM usuarios
            WHERE correo COLLATE utf8mb4_unicode_ci = TRIM(p_correo) COLLATE utf8mb4_unicode_ci
        ) THEN
            SELECT 'Error: Ya existe un usuario con ese correo (activo o inactivo).' AS Mensaje;
        ELSEIF EXISTS (
            SELECT 1 FROM empleados
            WHERE curp COLLATE utf8mb4_unicode_ci = UPPER(TRIM(p_curp)) COLLATE utf8mb4_unicode_ci
        ) THEN
            SELECT 'Error: Ya existe un empleado con esa CURP (activo o inactivo).' AS Mensaje;
        ELSEIF EXISTS (
            SELECT 1 FROM empleados
            WHERE rfc COLLATE utf8mb4_unicode_ci = UPPER(TRIM(p_rfc)) COLLATE utf8mb4_unicode_ci
        ) THEN
            SELECT 'Error: Ya existe un empleado con ese RFC (activo o inactivo).' AS Mensaje;
        ELSEIF p_nss IS NOT NULL AND TRIM(p_nss) <> '' AND EXISTS (
            SELECT 1 FROM empleados
            WHERE nss COLLATE utf8mb4_unicode_ci = TRIM(p_nss) COLLATE utf8mb4_unicode_ci
        ) THEN
            SELECT 'Error: Ya existe un empleado con ese NSS (activo o inactivo).' AS Mensaje;
        ELSE
            IF p_id_calle_FK IS NULL THEN
                IF (p_num_exterior IS NOT NULL AND p_num_exterior > 0) THEN
                    SELECT 'Error: La direccion esta incompleta (selecciona una calle valida).' AS Mensaje;
                ELSEIF p_num_interior IS NOT NULL THEN
                    SELECT 'Error: No se puede capturar numero interior sin direccion completa.' AS Mensaje;
                ELSE
                    START TRANSACTION;
                    INSERT INTO usuarios (nombre, primer_apellido, segundo_apellido, contrasena, correo, telefono, id_direccion_FK, activo)
                    VALUES (TRIM(p_nombre), TRIM(p_primer_apellido), TRIM(p_segundo_apellido), p_contrasena, TRIM(p_correo), TRIM(p_telefono), NULL, 1);
                    SET v_id_usuario = LAST_INSERT_ID();
                    INSERT INTO empleados (id_usuario_FK, id_puesto_FK, salario, curp, rfc, nss, activo)
                    VALUES (v_id_usuario, p_id_puesto_FK, p_salario, UPPER(TRIM(p_curp)), UPPER(TRIM(p_rfc)), TRIM(p_nss), 1);
                    COMMIT;
                    SELECT 'Empleado creado exitosamente' AS Mensaje, LAST_INSERT_ID() AS id_empleado_generado;
                END IF;
            ELSE
                IF p_num_exterior IS NULL OR p_num_exterior <= 0 THEN
                    SELECT 'Error: El numero exterior debe ser mayor a cero.' AS Mensaje;
                ELSEIF NOT EXISTS (SELECT 1 FROM calles WHERE id_calle = p_id_calle_FK) THEN
                    SELECT 'Error: La calle seleccionada no existe.' AS Mensaje;
                ELSE
                    START TRANSACTION;
                    INSERT INTO direcciones (num_exterior, num_interior, id_calle_FK)
                    VALUES (p_num_exterior, p_num_interior, p_id_calle_FK);
                    SET v_id_direccion = LAST_INSERT_ID();
                    INSERT INTO usuarios (nombre, primer_apellido, segundo_apellido, contrasena, correo, telefono, id_direccion_FK, activo)
                    VALUES (TRIM(p_nombre), TRIM(p_primer_apellido), TRIM(p_segundo_apellido), p_contrasena, TRIM(p_correo), TRIM(p_telefono), v_id_direccion, 1);
                    SET v_id_usuario = LAST_INSERT_ID();
                    INSERT INTO empleados (id_usuario_FK, id_puesto_FK, salario, curp, rfc, nss, activo)
                    VALUES (v_id_usuario, p_id_puesto_FK, p_salario, UPPER(TRIM(p_curp)), UPPER(TRIM(p_rfc)), TRIM(p_nss), 1);
                    COMMIT;
                    SELECT 'Empleado creado exitosamente' AS Mensaje, LAST_INSERT_ID() AS id_empleado_generado;
                END IF;
            END IF;
        END IF;

    ELSEIF p_accion = 'READ' THEN
        SELECT
            e.id_empleado, u.nombre, u.primer_apellido, u.segundo_apellido,
            e.salario, e.curp, e.rfc, e.nss, p.nombre_puesto,
            u.correo, u.telefono,
            d.num_exterior, d.num_interior, d.id_direccion,
            c.id_calle, c.nom_calle,
            col.id_colonia, col.nom_colonia,
            cp.id_codigo_postal, cp.codigo_postal,
            loc.id_localidad, loc.nom_localidad,
            mun.id_municipio, mun.nom_municipio,
            est.id_estado, est.nom_estado,
            pai.id_pais, pai.nom_pais,
            u.id_usuario, e.id_puesto_FK, u.id_direccion_FK
        FROM empleados e
        INNER JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
        INNER JOIN puestos p ON e.id_puesto_FK = p.id_puesto
        LEFT JOIN direcciones d ON u.id_direccion_FK = d.id_direccion
        LEFT JOIN calles c ON d.id_calle_FK = c.id_calle
        LEFT JOIN colonias col ON c.id_colonia_FK = col.id_colonia
        LEFT JOIN codigos_postales cp ON col.id_codigo_postal_FK = cp.id_codigo_postal
        LEFT JOIN localidades loc ON col.id_localidad_FK = loc.id_localidad
        LEFT JOIN municipios mun ON loc.id_municipio_FK = mun.id_municipio
        LEFT JOIN estados est ON mun.id_estado_FK = est.id_estado
        LEFT JOIN paises pai ON est.id_pais_FK = pai.id_pais
        WHERE (e.id_empleado = p_id_empleado OR p_id_empleado IS NULL)
          AND e.activo = 1;

    ELSEIF p_accion = 'UPDATE' THEN
        IF p_id_empleado IS NULL OR NOT EXISTS (SELECT 1 FROM empleados WHERE id_empleado = p_id_empleado AND activo = 1) THEN
            SELECT 'Error: El empleado no existe o ya fue dado de baja.' AS Mensaje;
        ELSEIF p_id_puesto_FK IS NULL OR NOT EXISTS (SELECT 1 FROM puestos WHERE id_puesto = p_id_puesto_FK) THEN
            SELECT 'Error: El puesto seleccionado no existe.' AS Mensaje;
        ELSEIF EXISTS (
            SELECT 1 FROM empleados e
            WHERE e.curp COLLATE utf8mb4_unicode_ci = UPPER(TRIM(p_curp)) COLLATE utf8mb4_unicode_ci
              AND e.id_empleado <> p_id_empleado AND e.activo = 1
        ) THEN
            SELECT 'Error: Ya existe otro empleado activo con esa CURP.' AS Mensaje;
        ELSEIF EXISTS (
            SELECT 1 FROM empleados e
            WHERE e.rfc COLLATE utf8mb4_unicode_ci = UPPER(TRIM(p_rfc)) COLLATE utf8mb4_unicode_ci
              AND e.id_empleado <> p_id_empleado AND e.activo = 1
        ) THEN
            SELECT 'Error: Ya existe otro empleado activo con ese RFC.' AS Mensaje;
        ELSEIF p_nss IS NOT NULL AND TRIM(p_nss) <> '' AND EXISTS (
            SELECT 1 FROM empleados e
            WHERE e.nss COLLATE utf8mb4_unicode_ci = TRIM(p_nss) COLLATE utf8mb4_unicode_ci
              AND e.id_empleado <> p_id_empleado AND e.activo = 1
        ) THEN
            SELECT 'Error: Ya existe otro empleado activo con ese NSS.' AS Mensaje;
        ELSE
            START TRANSACTION;

            SELECT id_usuario_FK INTO v_id_usuario FROM empleados WHERE id_empleado = p_id_empleado;
            SELECT id_direccion_FK INTO v_id_direccion FROM usuarios WHERE id_usuario = v_id_usuario;

            IF EXISTS (
                SELECT 1 FROM usuarios
                WHERE correo COLLATE utf8mb4_unicode_ci = TRIM(p_correo) COLLATE utf8mb4_unicode_ci
                  AND id_usuario <> v_id_usuario
            ) THEN
                ROLLBACK;
                SELECT 'Error: Ya existe otro usuario con ese correo (activo o inactivo).' AS Mensaje;
            ELSE
                IF v_id_direccion IS NOT NULL THEN
                    IF p_id_calle_FK IS NULL OR p_num_exterior IS NULL OR p_num_exterior <= 0 THEN
                        ROLLBACK;
                        SELECT 'Error: El numero exterior debe ser mayor a cero y la calle es obligatoria.' AS Mensaje;
                    ELSEIF NOT EXISTS (SELECT 1 FROM calles WHERE id_calle = p_id_calle_FK) THEN
                        ROLLBACK;
                        SELECT 'Error: La calle seleccionada no existe.' AS Mensaje;
                    ELSE
                        UPDATE direcciones
                        SET num_exterior = p_num_exterior, num_interior = p_num_interior, id_calle_FK = p_id_calle_FK
                        WHERE id_direccion = v_id_direccion;

                        UPDATE usuarios
                        SET nombre = TRIM(p_nombre), primer_apellido = TRIM(p_primer_apellido), segundo_apellido = TRIM(p_segundo_apellido),
                            correo = TRIM(p_correo), telefono = TRIM(p_telefono),
                            contrasena = IF(p_contrasena IS NOT NULL AND TRIM(p_contrasena) <> '', p_contrasena, contrasena)
                        WHERE id_usuario = v_id_usuario;

                        UPDATE empleados
                        SET id_puesto_FK = p_id_puesto_FK, salario = p_salario, curp = UPPER(TRIM(p_curp)), rfc = UPPER(TRIM(p_rfc)), nss = TRIM(p_nss)
                        WHERE id_empleado = p_id_empleado;

                        COMMIT;
                        SELECT 'Empleado actualizado exitosamente' AS Mensaje;
                    END IF;
                ELSE
                    IF p_id_calle_FK IS NULL OR p_num_exterior IS NULL OR p_num_exterior <= 0 THEN
                        UPDATE usuarios
                        SET nombre = TRIM(p_nombre), primer_apellido = TRIM(p_primer_apellido), segundo_apellido = TRIM(p_segundo_apellido),
                            correo = TRIM(p_correo), telefono = TRIM(p_telefono),
                            contrasena = IF(p_contrasena IS NOT NULL AND TRIM(p_contrasena) <> '', p_contrasena, contrasena)
                        WHERE id_usuario = v_id_usuario;

                        UPDATE empleados
                        SET id_puesto_FK = p_id_puesto_FK, salario = p_salario, curp = UPPER(TRIM(p_curp)), rfc = UPPER(TRIM(p_rfc)), nss = TRIM(p_nss)
                        WHERE id_empleado = p_id_empleado;

                        COMMIT;
                        SELECT 'Empleado actualizado exitosamente' AS Mensaje;
                    ELSEIF NOT EXISTS (SELECT 1 FROM calles WHERE id_calle = p_id_calle_FK) THEN
                        ROLLBACK;
                        SELECT 'Error: La calle seleccionada no existe.' AS Mensaje;
                    ELSE
                        INSERT INTO direcciones (num_exterior, num_interior, id_calle_FK)
                        VALUES (p_num_exterior, p_num_interior, p_id_calle_FK);
                        SET v_id_direccion = LAST_INSERT_ID();

                        UPDATE usuarios
                        SET nombre = TRIM(p_nombre), primer_apellido = TRIM(p_primer_apellido), segundo_apellido = TRIM(p_segundo_apellido),
                            correo = TRIM(p_correo), telefono = TRIM(p_telefono), id_direccion_FK = v_id_direccion,
                            contrasena = IF(p_contrasena IS NOT NULL AND TRIM(p_contrasena) <> '', p_contrasena, contrasena)
                        WHERE id_usuario = v_id_usuario;

                        UPDATE empleados
                        SET id_puesto_FK = p_id_puesto_FK, salario = p_salario, curp = UPPER(TRIM(p_curp)), rfc = UPPER(TRIM(p_rfc)), nss = TRIM(p_nss)
                        WHERE id_empleado = p_id_empleado;

                        COMMIT;
                        SELECT 'Empleado actualizado exitosamente' AS Mensaje;
                    END IF;
                END IF;
            END IF;
        END IF;

    ELSEIF p_accion = 'DELETE' THEN
        START TRANSACTION;

        SELECT id_usuario_FK INTO v_id_usuario FROM empleados WHERE id_empleado = p_id_empleado;

        UPDATE empleados
        SET activo = 0, fecha_baja = CURRENT_TIMESTAMP(), id_usuario_baja = p_id_usuario_baja
        WHERE id_empleado = p_id_empleado;

        UPDATE usuarios
        SET activo = 0
        WHERE id_usuario = v_id_usuario;

        COMMIT;
        SELECT 'Empleado dado de baja exitosamente' AS Mensaje;

    ELSE
        SELECT 'Accion no reconocida. Use CREATE, READ, UPDATE o DELETE.' AS Mensaje;
    END IF;

END$$

DELIMITER ;
