<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/list_search.php";
require_once __DIR__ . "/../includes/cliente_correo.php";
require_once __DIR__ . "/../../includes/telefono_helpers.php";
require_once __DIR__ . "/../includes/porcentaje_validacion.php";

class Cliente extends Sistema
{
    /** Contraseña en texto plano usada en el ultimo crear() de esta instancia (solo alta). */
    private ?string $ultimaContrasenaPlanoAlta = null;

    public function ultimaContrasenaPlanoAlta(): ?string
    {
        return $this->ultimaContrasenaPlanoAlta;
    }
    /**
     * Si el usuario ya tiene direccion, siempre se considera incluida.
     * Si no, depende del campo incluir_direccion === '1' en el formulario.
     */
    private function deseaIncluirDireccion(array $data, $idDireccionFkActual): bool
    {
        if ($idDireccionFkActual !== null && $idDireccionFkActual !== '' && (int) $idDireccionFkActual > 0) {
            return true;
        }

        return isset($data['incluir_direccion']) && (string) $data['incluir_direccion'] === '1';
    }

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT c.id_cliente,
                       c.descuento_porcentaje,
                       c.activo,
                       u.id_usuario,
                       u.nombre,
                       u.primer_apellido,
                       u.segundo_apellido,
                       u.correo,
                       u.telefono,
                       d.num_exterior,
                       d.num_interior,
                       ca.nom_calle,
                       co.nom_colonia,
                       cp.codigo_postal
                FROM clientes c
                INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                LEFT JOIN direcciones d ON d.id_direccion = u.id_direccion_FK
                LEFT JOIN calles ca ON ca.id_calle = d.id_calle_FK
                LEFT JOIN colonias co ON co.id_colonia = ca.id_colonia_FK
                LEFT JOIN codigos_postales cp ON cp.id_codigo_postal = co.id_codigo_postal_FK
                WHERE c.activo = 1";
        if ($pat !== null) {
            $nombreCompleto = joyeria_sql_nombre_completo('u');
            $sql .= " AND (
                {$nombreCompleto} LIKE :busq
                OR u.correo LIKE :busq2 OR u.telefono LIKE :busq3 OR co.nom_colonia LIKE :busq4
                OR CAST(cp.codigo_postal AS CHAR) LIKE :busq5 OR ca.nom_calle LIKE :busq6
            )";
        }
        $sql .= " ORDER BY u.primer_apellido ASC, u.segundo_apellido ASC, u.nombre ASC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq6', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($idCliente)
    {
        $sql = "SELECT c.id_cliente,
                       c.id_usuario_FK,
                       c.descuento_porcentaje,
                       c.rfc,
                       c.razon_social,
                       c.regimen_fiscal,
                       c.uso_cfdi,
                       c.codigo_postal_fiscal,
                       c.activo,
                       u.nombre,
                       u.primer_apellido,
                       u.segundo_apellido,
                       u.correo,
                       u.telefono,
                       u.id_direccion_FK,
                       d.num_exterior,
                       d.num_interior,
                       d.id_calle_FK,
                       ca.id_colonia_FK,
                       co.id_codigo_postal_FK,
                       co.nom_colonia,
                       cp.codigo_postal
                FROM clientes c
                INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                LEFT JOIN direcciones d ON d.id_direccion = u.id_direccion_FK
                LEFT JOIN calles ca ON ca.id_calle = d.id_calle_FK
                LEFT JOIN colonias co ON co.id_colonia = ca.id_colonia_FK
                LEFT JOIN codigos_postales cp ON cp.id_codigo_postal = co.id_codigo_postal_FK
                WHERE c.id_cliente = :id_cliente";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_cliente', (int) $idCliente, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $this->ultimaContrasenaPlanoAlta = null;

        $nombre = $this->validarTexto($data, 'nombre', 50, 'El nombre');
        $primerApellido = $this->validarTexto($data, 'primer_apellido', 25, 'El primer apellido');
        $segundoApellido = $this->validarTextoOpcional($data, 'segundo_apellido', 25);
        $telefono = $this->validarTexto($data, 'telefono', 15, 'El telefono');
        if (empty($data['telefono_ya_validado'])) {
            $telefono = $this->normalizarYValidarTelefonoUnico($telefono, null);
        } else {
            $telefono = mb_substr(trim($telefono), 0, 15);
        }
        $correoIngresado = $this->validarCorreoOpcional($data);
        $correo = $this->resolverCorreoAlta($correoIngresado, $telefono);

        $contrasenaPlano = isset($data['contrasena']) ? trim((string) $data['contrasena']) : '';
        if ($contrasenaPlano === '') {
            $contrasenaPlano = joyeria_cliente_generar_contrasena_temporal();
        }
        $this->ultimaContrasenaPlanoAlta = $contrasenaPlano;
        $contraseniaHasheada = password_hash($contrasenaPlano, PASSWORD_BCRYPT, ['cost' => 12]);

        $incluirDir = $this->deseaIncluirDireccion($data, null);
        $idDireccion = null;
        if ($incluirDir) {
            $numExterior = $this->validarEntero($data, 'num_exterior', 'El numero exterior');
            $numInterior = $this->validarEnteroOpcional($data, 'num_interior');
            $idCalle = $this->validarEntero($data, 'id_calle_FK', 'La calle');
        }

        $descuento = $this->validarDescuento($data['descuento_porcentaje'] ?? null);
        $fiscal = $this->parseDatosFiscales($data);

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db);

            if ($incluirDir) {
                $stmtDireccion = $db->prepare(
                    "INSERT INTO direcciones (num_exterior, num_interior, id_calle_FK)
                     VALUES (:num_exterior, :num_interior, :id_calle_FK)"
                );
                $stmtDireccion->bindValue(':num_exterior', $numExterior, PDO::PARAM_INT);
                $stmtDireccion->bindValue(':num_interior', $numInterior, $numInterior === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmtDireccion->bindValue(':id_calle_FK', $idCalle, PDO::PARAM_INT);
                $stmtDireccion->execute();
                $idDireccion = (int) $db->lastInsertId();
            }

            $correoVerificadoEn = null;
            if (!array_key_exists('correo_verificado', $data) || $data['correo_verificado'] !== false) {
                $correoVerificadoEn = date('Y-m-d H:i:s');
            }

            $stmtUsuario = $db->prepare(
                "INSERT INTO usuarios
                 (nombre, primer_apellido, segundo_apellido, contrasena, correo, telefono, id_direccion_FK, activo, correo_verificado_en)
                 VALUES
                 (:nombre, :primer_apellido, :segundo_apellido, :contrasena, :correo, :telefono, :id_direccion_FK, 1, :correo_verificado_en)"
            );
            $stmtUsuario->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmtUsuario->bindValue(':primer_apellido', $primerApellido, PDO::PARAM_STR);
            $stmtUsuario->bindValue(':segundo_apellido', $segundoApellido, $segundoApellido === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtUsuario->bindValue(':contrasena', $contraseniaHasheada, PDO::PARAM_STR);
            $stmtUsuario->bindValue(':correo', $correo, PDO::PARAM_STR);
            $stmtUsuario->bindValue(':telefono', $telefono, PDO::PARAM_STR);
            $stmtUsuario->bindValue(':id_direccion_FK', $idDireccion, $idDireccion === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmtUsuario->bindValue(
                ':correo_verificado_en',
                $correoVerificadoEn,
                $correoVerificadoEn === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            );
            $stmtUsuario->execute();
            $idUsuario = (int) $db->lastInsertId();

            $stmtCliente = $db->prepare(
                "INSERT INTO clientes
                 (id_usuario_FK, descuento_porcentaje, rfc, razon_social, regimen_fiscal, uso_cfdi, codigo_postal_fiscal, activo)
                 VALUES
                 (:id_usuario_FK, :descuento_porcentaje, :rfc, :razon_social, :regimen_fiscal, :uso_cfdi, :codigo_postal_fiscal, 1)"
            );
            $stmtCliente->bindValue(':id_usuario_FK', $idUsuario, PDO::PARAM_INT);
            $stmtCliente->bindValue(':descuento_porcentaje', $descuento, $descuento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->bindValue(':rfc', $fiscal['rfc'], $fiscal['rfc'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->bindValue(':razon_social', $fiscal['razon_social'], $fiscal['razon_social'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->bindValue(':regimen_fiscal', $fiscal['regimen_fiscal'], $fiscal['regimen_fiscal'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->bindValue(':uso_cfdi', $fiscal['uso_cfdi'], PDO::PARAM_STR);
            $stmtCliente->bindValue(':codigo_postal_fiscal', $fiscal['codigo_postal_fiscal'], $fiscal['codigo_postal_fiscal'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->execute();

            $idCliente = (int) $db->lastInsertId();
            $db->commit();
            return $idCliente;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar($idCliente, $data)
    {
        $cliente = $this->leerUno($idCliente);
        if (!$cliente || (int) ($cliente['activo'] ?? 0) !== 1) {
            throw new RuntimeException('Cliente no encontrado o inactivo.');
        }

        $nombre = $this->validarTexto($data, 'nombre', 50, 'El nombre');
        $primerApellido = $this->validarTexto($data, 'primer_apellido', 25, 'El primer apellido');
        $segundoApellido = $this->validarTextoOpcional($data, 'segundo_apellido', 25);
        $correo = $this->validarCorreoParaActualizar($data, (string) ($cliente['correo'] ?? ''));
        $telefono = $this->validarTexto($data, 'telefono', 15, 'El telefono');
        $telefono = $this->normalizarYValidarTelefonoUnico($telefono, (int) ($cliente['id_usuario_FK'] ?? 0));
        $idDirFk = isset($cliente['id_direccion_FK']) && $cliente['id_direccion_FK'] !== '' ? (int) $cliente['id_direccion_FK'] : null;
        if ($idDirFk !== null && $idDirFk <= 0) {
            $idDirFk = null;
        }
        $omitirActualizacionDir = isset($data['omitir_actualizacion_direccion'])
            && (string) $data['omitir_actualizacion_direccion'] === '1';
        $incluirDir = $this->deseaIncluirDireccion($data, $idDirFk);

        if (!$omitirActualizacionDir && $idDirFk !== null) {
            $numExterior = $this->validarEntero($data, 'num_exterior', 'El numero exterior');
            $numInterior = $this->validarEnteroOpcional($data, 'num_interior');
            $idCalle = $this->validarEntero($data, 'id_calle_FK', 'La calle');
        } elseif (!$omitirActualizacionDir && $incluirDir) {
            $numExterior = $this->validarEntero($data, 'num_exterior', 'El numero exterior');
            $numInterior = $this->validarEnteroOpcional($data, 'num_interior');
            $idCalle = $this->validarEntero($data, 'id_calle_FK', 'La calle');
        }

        $descuento = $this->validarDescuento($data['descuento_porcentaje'] ?? null);
        $fiscal = $this->parseDatosFiscales($data);

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db);

            if (!$omitirActualizacionDir && $idDirFk !== null) {
                $stmtDireccion = $db->prepare(
                    "UPDATE direcciones
                     SET num_exterior = :num_exterior,
                         num_interior = :num_interior,
                         id_calle_FK = :id_calle_FK
                     WHERE id_direccion = :id_direccion"
                );
                $stmtDireccion->bindValue(':num_exterior', $numExterior, PDO::PARAM_INT);
                $stmtDireccion->bindValue(':num_interior', $numInterior, $numInterior === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmtDireccion->bindValue(':id_calle_FK', $idCalle, PDO::PARAM_INT);
                $stmtDireccion->bindValue(':id_direccion', $idDirFk, PDO::PARAM_INT);
                $stmtDireccion->execute();
            } elseif (!$omitirActualizacionDir && $incluirDir) {
                $stmtInsDir = $db->prepare(
                    "INSERT INTO direcciones (num_exterior, num_interior, id_calle_FK)
                     VALUES (:num_exterior, :num_interior, :id_calle_FK)"
                );
                $stmtInsDir->bindValue(':num_exterior', $numExterior, PDO::PARAM_INT);
                $stmtInsDir->bindValue(':num_interior', $numInterior, $numInterior === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmtInsDir->bindValue(':id_calle_FK', $idCalle, PDO::PARAM_INT);
                $stmtInsDir->execute();
                $nuevaDir = (int) $db->lastInsertId();
                $stmtFk = $db->prepare('UPDATE usuarios SET id_direccion_FK = :id WHERE id_usuario = :u');
                $stmtFk->bindValue(':id', $nuevaDir, PDO::PARAM_INT);
                $stmtFk->bindValue(':u', (int) $cliente['id_usuario_FK'], PDO::PARAM_INT);
                $stmtFk->execute();
            }

            $sqlUsuario = "UPDATE usuarios
                           SET nombre = :nombre,
                               primer_apellido = :primer_apellido,
                               segundo_apellido = :segundo_apellido,
                               correo = :correo,
                               telefono = :telefono";

            $actualizarContrasena = isset($data['contrasena']) && trim((string) $data['contrasena']) !== '';
            $contraseniaHasheada = null;
            if ($actualizarContrasena) {
                $contrasenaPlano = trim((string) $data['contrasena']);
                $contraseniaHasheada = password_hash($contrasenaPlano, PASSWORD_BCRYPT, ['cost' => 12]);
                $sqlUsuario .= ", contrasena = :contrasena";
            }

            $sqlUsuario .= " WHERE id_usuario = :id_usuario AND activo = 1";

            $stmtUsuario = $db->prepare($sqlUsuario);
            $stmtUsuario->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmtUsuario->bindValue(':primer_apellido', $primerApellido, PDO::PARAM_STR);
            $stmtUsuario->bindValue(':segundo_apellido', $segundoApellido, $segundoApellido === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtUsuario->bindValue(':correo', $correo, PDO::PARAM_STR);
            $stmtUsuario->bindValue(':telefono', $telefono, PDO::PARAM_STR);
            if ($actualizarContrasena && $contraseniaHasheada !== null) {
                $stmtUsuario->bindValue(':contrasena', $contraseniaHasheada, PDO::PARAM_STR);
            }
            $stmtUsuario->bindValue(':id_usuario', (int) $cliente['id_usuario_FK'], PDO::PARAM_INT);
            $stmtUsuario->execute();

            $stmtCliente = $db->prepare(
                "UPDATE clientes
                 SET descuento_porcentaje = :descuento_porcentaje,
                     rfc = :rfc,
                     razon_social = :razon_social,
                     regimen_fiscal = :regimen_fiscal,
                     uso_cfdi = :uso_cfdi,
                     codigo_postal_fiscal = :codigo_postal_fiscal
                 WHERE id_cliente = :id_cliente AND activo = 1"
            );
            $stmtCliente->bindValue(':descuento_porcentaje', $descuento, $descuento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->bindValue(':rfc', $fiscal['rfc'], $fiscal['rfc'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->bindValue(':razon_social', $fiscal['razon_social'], $fiscal['razon_social'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->bindValue(':regimen_fiscal', $fiscal['regimen_fiscal'], $fiscal['regimen_fiscal'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->bindValue(':uso_cfdi', $fiscal['uso_cfdi'], PDO::PARAM_STR);
            $stmtCliente->bindValue(':codigo_postal_fiscal', $fiscal['codigo_postal_fiscal'], $fiscal['codigo_postal_fiscal'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmtCliente->bindValue(':id_cliente', (int) $idCliente, PDO::PARAM_INT);
            $stmtCliente->execute();

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function borrar($idCliente, $idUsuarioBaja)
    {
        $idUsuarioBaja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $cliente = $this->leerUno($idCliente);
        if (!$cliente || (int) ($cliente['activo'] ?? 0) !== 1) {
            return 0;
        }

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db);

            $stmtCliente = $db->prepare(
                "UPDATE clientes
                 SET activo = 0,
                     fecha_baja = NOW(),
                     id_usuario_baja = :id_usuario_baja
                 WHERE id_cliente = :id_cliente AND activo = 1"
            );
            $stmtCliente->bindValue(':id_usuario_baja', $idUsuarioBaja !== null ? (int) $idUsuarioBaja : null, $idUsuarioBaja !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmtCliente->bindValue(':id_cliente', (int) $idCliente, PDO::PARAM_INT);
            $stmtCliente->execute();

            $stmtUsuario = $db->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = :id_usuario");
            $stmtUsuario->bindValue(':id_usuario', (int) $cliente['id_usuario_FK'], PDO::PARAM_INT);
            $stmtUsuario->execute();

            $db->commit();
            return $stmtCliente->rowCount();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function validarTexto($data, $campo, $max, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es requerido.');
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacio.');
        }

        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }

        return $valor;
    }

    private function validarCorreoOpcional(array $data): string
    {
        $valor = isset($data['correo']) ? trim(strip_tags((string) $data['correo'])) : '';
        if ($valor === '') {
            return '';
        }

        $norm = joyeria_cliente_correo_normalizado($valor);
        if (!filter_var($norm, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electronico no es valido.');
        }

        if (mb_strlen($norm) > 80) {
            $norm = mb_substr($norm, 0, 80);
        }

        return $norm;
    }

    private function validarCorreoParaActualizar(array $data, string $correoActual): string
    {
        $valor = isset($data['correo']) ? trim(strip_tags((string) $data['correo'])) : '';
        if ($valor === '') {
            return joyeria_cliente_correo_normalizado($correoActual);
        }

        $norm = $this->validarCorreoOpcional($data);
        $actualNorm = joyeria_cliente_correo_normalizado($correoActual);
        if ($norm !== $actualNorm && $this->existeCorreoUsuario($norm)) {
            throw new InvalidArgumentException('El correo ya esta registrado.');
        }

        return $norm;
    }

    private function resolverCorreoAlta(string $correoIngresado, string $telefono): string
    {
        if ($correoIngresado !== '') {
            $this->asegurarCorreoUnico($correoIngresado);

            return $correoIngresado;
        }

        $telDigits = preg_replace('/[^0-9]/', '', $telefono) ?: '0';
        $base = 'cli' . substr($telDigits, -10) . '.' . time() . '.' . random_int(100, 999);
        $dominio = JOYERIA_CLIENTE_CORREO_SINTETICO_DOMINIO;
        $maxLocal = 80 - strlen($dominio);
        $local = mb_substr($base, 0, max(8, $maxLocal));
        $correo = $local . $dominio;
        $correo = mb_substr($correo, 0, 80);

        if ($this->existeCorreoUsuario($correo)) {
            $correo = mb_substr($local . '.dup' . random_int(10, 99) . $dominio, 0, 80);
        }

        return $correo;
    }

    private function asegurarCorreoUnico(string $correo): void
    {
        if ($this->existeCorreoUsuario($correo)) {
            throw new InvalidArgumentException('El correo ya esta registrado.');
        }
    }

    private function normalizarYValidarTelefonoUnico(string $telefono, ?int $excluirIdUsuario): string
    {
        $norm = joyeria_telefono_normalizado($telefono);
        if ($norm === '') {
            throw new InvalidArgumentException('El telefono no es valido.');
        }

        $nacionales = joyeria_telefono_digitos_nacionales($norm);
        if (strlen($nacionales) < 10) {
            throw new InvalidArgumentException('El telefono debe tener al menos 10 digitos.');
        }

        if (joyeria_existe_telefono_usuario($this->getDb(), $norm, $excluirIdUsuario)) {
            throw new InvalidArgumentException('El telefono ya esta registrado.');
        }

        return mb_substr($norm, 0, 15);
    }

    private function existeCorreoUsuario(string $correo): bool
    {
        $stmt = $this->getDb()->prepare(
            'SELECT 1 FROM usuarios WHERE LOWER(TRIM(correo)) = :correo LIMIT 1'
        );
        $stmt->bindValue(':correo', joyeria_cliente_correo_normalizado($correo), PDO::PARAM_STR);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    private function validarTextoOpcional($data, $campo, $max)
    {
        if (!isset($data[$campo])) {
            return null;
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            return null;
        }

        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }

        return $valor;
    }

    private function validarEntero($data, $campo, $label)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException($label . ' debe ser numerico.');
        }

        return (int) $data[$campo];
    }

    private function validarEnteroOpcional($data, $campo)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }

        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException('El ' . str_replace('_', ' ', $campo) . ' debe ser numerico.');
        }

        return (int) $data[$campo];
    }

    private function validarDescuento($descuento)
    {
        return joyeria_normalizar_porcentaje_0_100($descuento, true, 'El descuento');
    }

    /** @return array{rfc:?string, razon_social:?string, regimen_fiscal:?string, uso_cfdi:string, codigo_postal_fiscal:?string} */
    private function parseDatosFiscales(array $data): array
    {
        $rfc = isset($data['rfc']) ? strtoupper(trim((string) $data['rfc'])) : '';
        $rfc = $rfc === '' ? null : mb_substr($rfc, 0, 13);

        $razon = trim((string) ($data['razon_social'] ?? ''));
        $razon = $razon === '' ? null : mb_substr($razon, 0, 254);

        $regimen = trim((string) ($data['regimen_fiscal'] ?? ''));
        $regimen = $regimen === '' ? null : mb_substr($regimen, 0, 3);

        $uso = trim((string) ($data['uso_cfdi'] ?? 'G03'));
        if ($uso === '') {
            $uso = 'G03';
        }
        $uso = mb_substr($uso, 0, 5);

        $cp = trim((string) ($data['codigo_postal_fiscal'] ?? ''));
        $cp = $cp === '' ? null : mb_substr($cp, 0, 5);

        return [
            'rfc' => $rfc,
            'razon_social' => $razon,
            'regimen_fiscal' => $regimen,
            'uso_cfdi' => $uso,
            'codigo_postal_fiscal' => $cp,
        ];
    }
}
