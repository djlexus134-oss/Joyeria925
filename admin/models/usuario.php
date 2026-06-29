<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/list_search.php";

class Usuario extends Sistema
{
    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT u.id_usuario,
                       u.nombre,
                       u.primer_apellido,
                       u.segundo_apellido,
                       u.correo,
                       u.telefono,
                       u.activo,
                       ca.nom_calle,
                       co.nom_colonia,
                       cp.codigo_postal,
                       d.num_exterior,
                       d.num_interior
                FROM usuarios u
                LEFT JOIN direcciones d ON d.id_direccion = u.id_direccion_FK
                LEFT JOIN calles ca ON ca.id_calle = d.id_calle_FK
                LEFT JOIN colonias co ON co.id_colonia = ca.id_colonia_FK
                LEFT JOIN codigos_postales cp ON cp.id_codigo_postal = co.id_codigo_postal_FK
                WHERE u.activo = 1";
        if ($pat !== null) {
            $sql .= " AND (
                u.nombre LIKE :busq OR u.primer_apellido LIKE :busq2 OR u.segundo_apellido LIKE :busq3
                OR u.correo LIKE :busq4 OR u.telefono LIKE :busq5 OR ca.nom_calle LIKE :busq6
                OR co.nom_colonia LIKE :busq7 OR CAST(cp.codigo_postal AS CHAR) LIKE :busq8
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
            $stmt->bindValue(':busq7', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq8', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($idUsuario)
    {
        $sql = "SELECT u.*,
                       d.num_exterior,
                       d.num_interior,
                       d.id_calle_FK,
                       ca.id_colonia_FK,
                       co.id_codigo_postal_FK
                FROM usuarios u
                LEFT JOIN direcciones d ON d.id_direccion = u.id_direccion_FK
                LEFT JOIN calles ca ON ca.id_calle = d.id_calle_FK
                LEFT JOIN colonias co ON co.id_colonia = ca.id_colonia_FK
                WHERE u.id_usuario = :id_usuario";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_usuario', (int) $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerDirecciones()
    {
        $sql = "SELECT d.id_direccion,
                       CONCAT(ca.nom_calle, ' ', d.num_exterior, 
                              IF(d.num_interior IS NOT NULL, CONCAT(' apt. ', d.num_interior), ''),
                              ' - ', co.nom_colonia, ' ', cp.codigo_postal) as direccion_completa
                FROM direcciones d
                INNER JOIN calles ca ON ca.id_calle = d.id_calle_FK
                INNER JOIN colonias co ON co.id_colonia = ca.id_colonia_FK
                INNER JOIN codigos_postales cp ON cp.id_codigo_postal = co.id_codigo_postal_FK
                ORDER BY ca.nom_calle ASC, d.num_exterior ASC";

        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $nombre = $this->validarTexto($data, 'nombre', 50, 'El nombre');
        $primerApellido = $this->validarTexto($data, 'primer_apellido', 25, 'El primer apellido');
        $segundoApellido = $this->validarTextoOpcional($data, 'segundo_apellido', 25);
        $correo = $this->validarTexto($data, 'correo', 80, 'El correo electrónico');
        $telefono = $this->validarTexto($data, 'telefono', 15, 'El teléfono');
        
        if (!isset($data['contrasena']) || trim((string) $data['contrasena']) === '') {
            throw new InvalidArgumentException('La contraseña es obligatoria.');
        }
        
        $contrasena = (string) $data['contrasena'];

        $idDireccion = $this->validarEnteroOpcional($data, 'id_direccion_FK');

        // Validar unicidad de correo
        $sqlCheckCorreo = "SELECT id_usuario FROM usuarios WHERE correo = :correo";
        $stmtCheckCorreo = $this->getDb()->prepare($sqlCheckCorreo);
        $stmtCheckCorreo->bindValue(':correo', $correo, PDO::PARAM_STR);
        $stmtCheckCorreo->execute();
        if ($stmtCheckCorreo->fetch()) {
            throw new Exception("El correo ya está registrado");
        }

        // Validar unicidad de teléfono
        $sqlCheckTelefono = "SELECT id_usuario FROM usuarios WHERE telefono = :telefono";
        $stmtCheckTelefono = $this->getDb()->prepare($sqlCheckTelefono);
        $stmtCheckTelefono->bindValue(':telefono', $telefono, PDO::PARAM_STR);
        $stmtCheckTelefono->execute();
        if ($stmtCheckTelefono->fetch()) {
            throw new Exception("El teléfono ya está registrado");
        }

        $contraseniaHasheada = password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => 12]);

        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare(
            "INSERT INTO usuarios
            (nombre, primer_apellido, segundo_apellido, correo, telefono, contrasena, id_direccion_FK, activo)
            VALUES
            (:nombre, :primer_apellido, :segundo_apellido, :correo, :telefono, :contrasena, :id_direccion_FK, 1)"
        );

        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':primer_apellido', $primerApellido, PDO::PARAM_STR);
        $stmt->bindValue(':segundo_apellido', $segundoApellido, $segundoApellido === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
        $stmt->bindValue(':telefono', $telefono, PDO::PARAM_STR);
        $stmt->bindValue(':contrasena', $contraseniaHasheada, PDO::PARAM_STR);
        $stmt->bindValue(':id_direccion_FK', $idDireccion, $idDireccion === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->getDb()->lastInsertId();
    }

    public function actualizar($idUsuario, $data)
    {
        $nombre = $this->validarTexto($data, 'nombre', 50, 'El nombre');
        $primerApellido = $this->validarTexto($data, 'primer_apellido', 25, 'El primer apellido');
        $segundoApellido = $this->validarTextoOpcional($data, 'segundo_apellido', 25);
        $correo = $this->validarTexto($data, 'correo', 80, 'El correo electrónico');
        $telefono = $this->validarTexto($data, 'telefono', 15, 'El teléfono');

        $idDireccion = $this->validarEnteroOpcional($data, 'id_direccion_FK');

        // Validar unicidad de correo (excepto el actual)
        $sqlCheckCorreo = "SELECT id_usuario FROM usuarios WHERE correo = :correo AND id_usuario != :id_usuario";
        $stmtCheckCorreo = $this->getDb()->prepare($sqlCheckCorreo);
        $stmtCheckCorreo->bindValue(':correo', $correo, PDO::PARAM_STR);
        $stmtCheckCorreo->bindValue(':id_usuario', (int) $idUsuario, PDO::PARAM_INT);
        $stmtCheckCorreo->execute();
        if ($stmtCheckCorreo->fetch()) {
            throw new Exception("El correo ya está registrado");
        }

        // Validar unicidad de teléfono (excepto el actual)
        $sqlCheckTelefono = "SELECT id_usuario FROM usuarios WHERE telefono = :telefono AND id_usuario != :id_usuario";
        $stmtCheckTelefono = $this->getDb()->prepare($sqlCheckTelefono);
        $stmtCheckTelefono->bindValue(':telefono', $telefono, PDO::PARAM_STR);
        $stmtCheckTelefono->bindValue(':id_usuario', (int) $idUsuario, PDO::PARAM_INT);
        $stmtCheckTelefono->execute();
        if ($stmtCheckTelefono->fetch()) {
            throw new Exception("El teléfono ya está registrado");
        }

        // Si hay contraseña nueva, hashearla
        $contraseniaHasheada = null;
        if (isset($data['contrasena']) && trim((string) $data['contrasena']) !== '') {
            $contrasena = (string) $data['contrasena'];
            $contraseniaHasheada = password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        auth_mysql_set_audit_vars($this->getDb());

        if ($contraseniaHasheada !== null) {
            $stmt = $this->getDb()->prepare(
                "UPDATE usuarios
                SET nombre = :nombre,
                    primer_apellido = :primer_apellido,
                    segundo_apellido = :segundo_apellido,
                    correo = :correo,
                    telefono = :telefono,
                    contrasena = :contrasena,
                    id_direccion_FK = :id_direccion_FK
                WHERE id_usuario = :id_usuario"
            );

            $stmt->bindValue(':contrasena', $contraseniaHasheada, PDO::PARAM_STR);
        } else {
            $stmt = $this->getDb()->prepare(
                "UPDATE usuarios
                SET nombre = :nombre,
                    primer_apellido = :primer_apellido,
                    segundo_apellido = :segundo_apellido,
                    correo = :correo,
                    telefono = :telefono,
                    id_direccion_FK = :id_direccion_FK
                WHERE id_usuario = :id_usuario"
            );
        }

        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':primer_apellido', $primerApellido, PDO::PARAM_STR);
        $stmt->bindValue(':segundo_apellido', $segundoApellido, $segundoApellido === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
        $stmt->bindValue(':telefono', $telefono, PDO::PARAM_STR);
        $stmt->bindValue(':id_direccion_FK', $idDireccion, $idDireccion === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', (int) $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function eliminar($idUsuario)
    {
        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare(
            "UPDATE usuarios
            SET activo = 0
            WHERE id_usuario = :id_usuario"
        );

        $stmt->bindValue(':id_usuario', (int) $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function validarTexto($data, $campo, $max, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacío.');
        }

        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }

        return $valor;
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

    private function validarEnteroOpcional($data, $campo)
    {
        if (!isset($data[$campo]) || $data[$campo] === '' || (int) $data[$campo] <= 0) {
            return null;
        }

        return (int) $data[$campo];
    }
}
