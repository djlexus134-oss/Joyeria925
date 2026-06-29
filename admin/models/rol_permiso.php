<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class RolPermiso extends Sistema
{
    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT rp.id_rol_FK,
                       rp.id_permiso_FK,
                       r.nombre_rol,
                       p.nombre_permiso,
                       p.descripcion
                FROM rol_permiso rp
                INNER JOIN roles r ON rp.id_rol_FK = r.id_rol
                INNER JOIN permisos p ON rp.id_permiso_FK = p.id_permiso
                WHERE 1=1";
        if ($pat !== null) {
            $sql .= " AND (
                r.nombre_rol LIKE :busq OR p.nombre_permiso LIKE :busq2 OR IFNULL(p.descripcion, '') LIKE :busq3
            )";
        }
        $sql .= " ORDER BY r.nombre_rol ASC, p.nombre_permiso ASC";

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
        $idRol = $this->validarEnteroPositivo($data, 'id_rol_FK', 'El rol');
        $idPermiso = $this->validarEnteroPositivo($data, 'id_permiso_FK', 'El permiso');

        $stmt = $this->getDb()->prepare(
            "INSERT INTO rol_permiso (id_rol_FK, id_permiso_FK)
             VALUES (:id_rol_FK, :id_permiso_FK)"
        );

        $stmt->bindValue(':id_rol_FK', $idRol, PDO::PARAM_INT);
        $stmt->bindValue(':id_permiso_FK', $idPermiso, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function revocar($idRol, $idPermiso)
    {
        $stmt = $this->getDb()->prepare(
            "DELETE FROM rol_permiso
             WHERE id_rol_FK = :id_rol_FK AND id_permiso_FK = :id_permiso_FK"
        );

        $stmt->bindValue(':id_rol_FK', (int) $idRol, PDO::PARAM_INT);
        $stmt->bindValue(':id_permiso_FK', (int) $idPermiso, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function obtenerRolesActivos()
    {
        $sql = "SELECT id_rol, nombre_rol
                FROM roles
                WHERE activo = 1
                ORDER BY nombre_rol ASC";

        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPermisosActivos()
    {
        $sql = "SELECT id_permiso, nombre_permiso, descripcion
                FROM permisos
                WHERE activo = 1
                ORDER BY nombre_permiso ASC";

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
}
