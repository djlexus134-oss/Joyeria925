<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/list_search.php";

class Empleado extends Sistema
{
    private function deseaIncluirDireccion(array $data, $idDireccionFk): bool
    {
        if ($idDireccionFk !== null && $idDireccionFk !== '' && (int) $idDireccionFk > 0) {
            return true;
        }

        return isset($data['incluir_direccion']) && (string) $data['incluir_direccion'] === '1';
    }

    const MAX_NAME_LENGTH = 50;
    const MAX_APELLIDO_LENGTH = 25;
    const MAX_CURP_LENGTH = 18;
    const MAX_RFC_LENGTH = 13;
    const MAX_NSS_LENGTH = 11;
    const MAX_CORREO_LENGTH = 80;
    const MAX_TELEFONO_LENGTH = 15;

    /**
     * Recorre todos los resultsets de un SP y devuelve el ultimo registro util encontrado.
     * Esto evita perder mensajes cuando el proveedor devuelve multiples resultsets.
     */
    /**
     * Actualiza contrasena bcrypt del usuario vinculado al empleado.
     * El SP sp_crud_empleado en UPDATE no persiste p_contrasena; esto lo corrige en PHP.
     */
    private function actualizarContrasenaUsuario(int $idUsuario, string $hashBcrypt): void
    {
        if ($idUsuario <= 0 || trim($hashBcrypt) === '') {
            return;
        }

        auth_mysql_set_audit_vars($this->getDb(), $idUsuario);

        $stmt = $this->getDb()->prepare(
            'UPDATE usuarios SET contrasena = :contrasena WHERE id_usuario = :id_usuario'
        );
        $stmt->bindValue(':contrasena', $hashBcrypt, PDO::PARAM_STR);
        $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function obtenerResultadoSP(PDOStatement $stmt): array
    {
        $resultado = [];

        do {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row)) {
                $resultado = $row;
            }
        } while ($stmt->nextRowset());

        $stmt->closeCursor();
        return $resultado;
    }

    /**
     * Obtiene todos los empleados activos con sus datos relacionados
     * @return array Lista de empleados con información de usuario, puesto y dirección
     */
    public function leer(?string $busqueda = null)
    {
        $stmt = $this->getDb()->prepare("CALL sp_crud_empleado(
            'READ',
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL
        )");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        while ($stmt->nextRowset()) {
            // Consumir cualquier resultset pendiente del SP para evitar bloqueos en llamadas posteriores.
        }
        $stmt->closeCursor();

        return joyeria_filter_rows_by_search($rows, $busqueda, [
            'id_empleado',
            'nombre',
            'primer_apellido',
            'segundo_apellido',
            'nombre_puesto',
            'correo',
            'telefono',
            'salario',
            'nom_municipio',
            'nom_estado',
        ]);
    }

    /**
     * Obtiene un empleado específico por su ID
     * @param int $id_empleado ID del empleado a obtener
     * @return array Datos del empleado con información relacionada
     */
    public function leerUno($id_empleado)
    {
        $stmt = $this->getDb()->prepare("CALL sp_crud_empleado(
            'READ',
            :id_empleado,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL
        )");
        $stmt->bindParam(':id_empleado', $id_empleado, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        while ($stmt->nextRowset()) {
            // Consumir cualquier resultset pendiente del SP para evitar bloqueos en llamadas posteriores.
        }
        $stmt->closeCursor();
        return $row;
    }

    /**
     * Crea un nuevo empleado con su usuario y dirección asociados
     * @param array $data Datos del nuevo empleado
     *        - nombre: Nombre del empleado
     *        - primer_apellido: Primer apellido
     *        - segundo_apellido: Segundo apellido
     *        - contrasena: Contraseña encriptada
     *        - correo: Correo electrónico
     *        - telefono: Número de teléfono
     *        - id_puesto_FK: ID del puesto
     *        - salario: Salario del empleado
     *        - curp: CURP del empleado
     *        - rfc: RFC del empleado
     *        - nss: Número de Seguro Social
     *        - num_exterior: Número exterior de la dirección
     *        - num_interior: Número interior de la dirección
     *        - id_calle_FK: ID de la calle
     * @return int Número de filas afectadas
     */
    public function crear($data)
    {
        $segundoApellido = isset($data['segundo_apellido']) && trim((string) $data['segundo_apellido']) !== ''
            ? trim((string) $data['segundo_apellido'])
            : null;
        $nss = isset($data['nss']) && trim((string) $data['nss']) !== ''
            ? trim((string) $data['nss'])
            : null;
        $idDirFk = null;
        $incluirDir = $this->deseaIncluirDireccion($data, $idDirFk);

        $numExterior = null;
        $numInterior = null;
        $idCalle = null;
        if ($incluirDir) {
            if (!isset($data['num_exterior']) || trim((string) $data['num_exterior']) === '' || !is_numeric($data['num_exterior'])) {
                throw new InvalidArgumentException('El numero exterior es obligatorio.');
            }
            $numExterior = (int) $data['num_exterior'];
            if ($numExterior <= 0) {
                throw new InvalidArgumentException('El numero exterior debe ser mayor a cero.');
            }
            $numInterior = isset($data['num_interior']) && trim((string) $data['num_interior']) !== ''
                ? (int) $data['num_interior']
                : null;
            if (!isset($data['id_calle_FK']) || trim((string) $data['id_calle_FK']) === '' || (int) $data['id_calle_FK'] <= 0) {
                throw new InvalidArgumentException('La calle es obligatoria.');
            }
            $idCalle = (int) $data['id_calle_FK'];
        }

        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare("CALL sp_crud_empleado(
            'CREATE',
            NULL,
            :id_puesto_FK,
            :salario,
            :curp,
            :rfc,
            :nss,
            NULL,
            :nombre,
            :primer_apellido,
            :segundo_apellido,
            :contrasena,
            :correo,
            :telefono,
            :num_exterior,
            :num_interior,
            :id_calle_FK
        )");

        $stmt->bindValue(':nombre', trim((string) $data['nombre']), PDO::PARAM_STR);
        $stmt->bindValue(':primer_apellido', trim((string) $data['primer_apellido']), PDO::PARAM_STR);
        $stmt->bindValue(':segundo_apellido', $segundoApellido, $segundoApellido === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':contrasena', (string) $data['contrasena'], PDO::PARAM_STR);
        $stmt->bindValue(':correo', trim((string) $data['correo']), PDO::PARAM_STR);
        $stmt->bindValue(':telefono', trim((string) $data['telefono']), PDO::PARAM_STR);
        $stmt->bindValue(':id_puesto_FK', (int) $data['id_puesto_FK'], PDO::PARAM_INT);
        $stmt->bindValue(':salario', (string) $data['salario'], PDO::PARAM_STR);
        $stmt->bindValue(':curp', strtoupper(trim((string) $data['curp'])), PDO::PARAM_STR);
        $stmt->bindValue(':rfc', strtoupper(trim((string) $data['rfc'])), PDO::PARAM_STR);
        $stmt->bindValue(':nss', $nss, $nss === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':num_exterior', $numExterior, $numExterior === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':num_interior', $numInterior, $numInterior === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_calle_FK', $idCalle, $idCalle === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

        $stmt->execute();
        $result = $this->obtenerResultadoSP($stmt);

        if (!empty($result['Mensaje']) && stripos($result['Mensaje'], 'Error:') === 0) {
            throw new RuntimeException($result['Mensaje']);
        }

        if (!empty($result['Mensaje']) && stripos($result['Mensaje'], 'Error BD') === 0) {
            throw new RuntimeException($result['Mensaje']);
        }

        return isset($result['id_empleado_generado']) ? (int) $result['id_empleado_generado'] : 0;
    }

    /**
     * Actualiza los datos de un empleado existente
     * @param int $id_empleado ID del empleado a actualizar
     * @param array $data Datos a actualizar (mismo formato que crear)
     * @return int Número de filas afectadas
     */
    public function actualizar($id_empleado, $data)
    {
        $segundoApellido = isset($data['segundo_apellido']) && trim((string) $data['segundo_apellido']) !== ''
            ? trim((string) $data['segundo_apellido'])
            : null;
        $nss = isset($data['nss']) && trim((string) $data['nss']) !== ''
            ? trim((string) $data['nss'])
            : null;
        $prev = $this->leerUno($id_empleado);
        if (!is_array($prev)) {
            throw new RuntimeException('Empleado no encontrado.');
        }
        $idDirFkPrev = isset($prev['id_direccion_FK']) ? $prev['id_direccion_FK'] : null;
        if ($idDirFkPrev !== null && $idDirFkPrev !== '' && (int) $idDirFkPrev <= 0) {
            $idDirFkPrev = null;
        }
        $incluirDir = $this->deseaIncluirDireccion($data, $idDirFkPrev);

        $numExterior = null;
        $numInterior = null;
        $idCalle = null;
        $exigeDir = ($idDirFkPrev !== null && (int) $idDirFkPrev > 0) || $incluirDir;

        if ($exigeDir) {
            if (!isset($data['num_exterior']) || trim((string) $data['num_exterior']) === '' || !is_numeric($data['num_exterior'])) {
                throw new InvalidArgumentException('El numero exterior es obligatorio.');
            }
            $numExterior = (int) $data['num_exterior'];
            if ($numExterior <= 0) {
                throw new InvalidArgumentException('El numero exterior debe ser mayor a cero.');
            }
            $numInterior = isset($data['num_interior']) && trim((string) $data['num_interior']) !== ''
                ? (int) $data['num_interior']
                : null;
            if (!isset($data['id_calle_FK']) || trim((string) $data['id_calle_FK']) === '' || (int) $data['id_calle_FK'] <= 0) {
                throw new InvalidArgumentException('La calle es obligatoria.');
            }
            $idCalle = (int) $data['id_calle_FK'];
        }

        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare("CALL sp_crud_empleado(
            'UPDATE',
            :id_empleado,
            :id_puesto_FK,
            :salario,
            :curp,
            :rfc,
            :nss,
            NULL,
            :nombre,
            :primer_apellido,
            :segundo_apellido,
            :contrasena,
            :correo,
            :telefono,
            :num_exterior,
            :num_interior,
            :id_calle_FK
        )");

        $stmt->bindValue(':id_empleado', (int) $id_empleado, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', trim((string) $data['nombre']), PDO::PARAM_STR);
        $stmt->bindValue(':primer_apellido', trim((string) $data['primer_apellido']), PDO::PARAM_STR);
        $stmt->bindValue(':segundo_apellido', $segundoApellido, $segundoApellido === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':correo', trim((string) $data['correo']), PDO::PARAM_STR);
        $stmt->bindValue(':telefono', trim((string) $data['telefono']), PDO::PARAM_STR);
        $stmt->bindValue(':id_puesto_FK', (int) $data['id_puesto_FK'], PDO::PARAM_INT);
        $stmt->bindValue(':salario', (string) $data['salario'], PDO::PARAM_STR);
        $stmt->bindValue(':curp', strtoupper(trim((string) $data['curp'])), PDO::PARAM_STR);
        $stmt->bindValue(':rfc', strtoupper(trim((string) $data['rfc'])), PDO::PARAM_STR);
        $stmt->bindValue(':nss', $nss, $nss === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':num_exterior', $numExterior, $numExterior === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':num_interior', $numInterior, $numInterior === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_calle_FK', $idCalle, $idCalle === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

        $contrasenaNueva = null;
        if (isset($data['contrasena']) && $data['contrasena'] !== null && trim((string) $data['contrasena']) !== '') {
            $contrasenaNueva = (string) $data['contrasena'];
        }
        $stmt->bindValue(':contrasena', $contrasenaNueva, $contrasenaNueva === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $stmt->execute();
        $result = $this->obtenerResultadoSP($stmt);

        if (!empty($result['Mensaje']) && stripos($result['Mensaje'], 'Error:') === 0) {
            throw new RuntimeException($result['Mensaje']);
        }

        if (!empty($result['Mensaje']) && stripos($result['Mensaje'], 'Error BD') === 0) {
            throw new RuntimeException($result['Mensaje']);
        }

        if ($contrasenaNueva !== null) {
            $idUsuario = (int) ($prev['id_usuario'] ?? 0);
            if ($idUsuario > 0) {
                $this->actualizarContrasenaUsuario($idUsuario, $contrasenaNueva);
            }
        }

        return 1;
    }

    /**
     * Realiza un borrado lógico de un empleado (soft delete)
     * @param int $id_empleado ID del empleado a dar de baja
     * @param int $id_usuario_baja ID del usuario que realiza la baja (para auditoría)
     * @return int Número de filas afectadas
     */
    public function borrar($id_empleado, $id_usuario_baja)
    {
        $id_usuario_baja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare("CALL sp_crud_empleado(
            'DELETE',
            :id_empleado,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            :id_usuario_baja,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL
        )");
        
        $stmt->bindValue(':id_empleado', (int) $id_empleado, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario_baja', $id_usuario_baja !== null ? (int) $id_usuario_baja : null, $id_usuario_baja !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);

        $stmt->execute();
        $result = $this->obtenerResultadoSP($stmt);

        if (!empty($result['Mensaje']) && stripos($result['Mensaje'], 'Error:') === 0) {
            throw new RuntimeException($result['Mensaje']);
        }

        if (!empty($result['Mensaje']) && stripos($result['Mensaje'], 'Error BD') === 0) {
            throw new RuntimeException($result['Mensaje']);
        }

        return 1;
    }
}
