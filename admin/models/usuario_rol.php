<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class UsuarioRol extends Sistema
{
    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT ur.id_usuario_FK,
                       ur.id_rol_FK,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS nombre_usuario,
                       u.correo,
                       r.nombre_rol
                FROM usuario_rol ur
                INNER JOIN usuarios u ON ur.id_usuario_FK = u.id_usuario
                INNER JOIN roles r ON ur.id_rol_FK = r.id_rol
                WHERE 1=1";
        if ($pat !== null) {
            $sql .= " AND (
                CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) LIKE :busq
                OR u.correo LIKE :busq2 OR r.nombre_rol LIKE :busq3
            )";
        }
        $sql .= " ORDER BY u.nombre ASC, u.primer_apellido ASC, r.nombre_rol ASC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function asignar($data)
    {
        $idUsuario = $this->validarEnteroPositivo($data, 'id_usuario_FK', 'El usuario');
        $idRol = $this->validarEnteroPositivo($data, 'id_rol_FK', 'El rol');
        if (!$this->usuarioEsEmpleadoActivo($idUsuario)) {
            throw new InvalidArgumentException('Solo se pueden asignar roles a usuarios que sean empleados activos.');
        }

        $stmt = $this->getDb()->prepare(
            "INSERT INTO usuario_rol (id_usuario_FK, id_rol_FK)
             VALUES (:id_usuario_FK, :id_rol_FK)"
        );

        $stmt->bindValue(':id_usuario_FK', $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':id_rol_FK', $idRol, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function revocar($idUsuario, $idRol)
    {
        $stmt = $this->getDb()->prepare(
            "DELETE FROM usuario_rol
             WHERE id_usuario_FK = :id_usuario_FK AND id_rol_FK = :id_rol_FK"
        );

        $stmt->bindValue(':id_usuario_FK', (int) $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':id_rol_FK', (int) $idRol, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function obtenerUsuariosActivos()
    {
        $sql = "SELECT u.id_usuario,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS nombre_completo,
                       u.correo
                FROM usuarios u
                INNER JOIN empleados e ON e.id_usuario_FK = u.id_usuario
                WHERE COALESCE(u.activo, 1) = 1
                  AND COALESCE(e.activo, 1) = 1
                ORDER BY u.nombre ASC, u.primer_apellido ASC";

        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerRolesActivos()
    {
        $sql = "SELECT id_rol, nombre_rol
                FROM roles
                WHERE activo = 1
                ORDER BY nombre_rol ASC";

        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validarEnteroPositivo($data, $campo, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = (int) $data[$campo];
        if ($valor <= 0) {
            throw new InvalidArgumentException($label . ' no es valido.');
        }

        return $valor;
    }

    private function usuarioEsEmpleadoActivo($idUsuario)
    {
        $stmt = $this->getDb()->prepare(
            "SELECT 1
             FROM empleados e
             INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
             WHERE e.id_usuario_FK = :id_usuario
               AND COALESCE(e.activo, 1) = 1
               AND COALESCE(u.activo, 1) = 1
             LIMIT 1"
        );
        $stmt->bindValue(':id_usuario', (int) $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}
