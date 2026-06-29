<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class Permiso extends Sistema
{
    const MAX_NAME_LENGTH = 50;

    public function leer(?string $busqueda = null)
    {
        $stmt = $this->getDb()->prepare("CALL sp_gestion_permisos('SELECT', NULL, NULL, NULL, NULL)");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return joyeria_filter_rows_by_search($rows, $busqueda, ['id_permiso', 'nombre_permiso', 'descripcion']);
    }

    public function leerUno($id_permiso)
    {
        $stmt = $this->getDb()->prepare("CALL sp_gestion_permisos('SELECT_ID', :id_permiso, NULL, NULL, NULL)");
        $stmt->bindParam(':id_permiso', $id_permiso, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }   

    public function crear($data)
    {
        $descripcion = isset($data['descripcion']) && trim((string) $data['descripcion']) !== ''
            ? trim((string) $data['descripcion'])
            : null;
        $stmt = $this->getDb()->prepare("CALL sp_gestion_permisos('INSERT', NULL , :nombre, :descripcion, NULL)");
        $stmt->bindValue(':nombre', trim((string) $data['nombre_permiso']), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function actualizar($id_permiso, $data)
    {
        $descripcion = isset($data['descripcion']) && trim((string) $data['descripcion']) !== ''
            ? trim((string) $data['descripcion'])
            : null;
        $stmt = $this->getDb()->prepare("CALL sp_gestion_permisos('UPDATE', :id_permiso, :nombre, :descripcion, NULL)");
        $stmt->bindValue(':id_permiso', (int) $id_permiso, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', trim((string) $data['nombre_permiso']), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function borrar($id_permiso, $id_usuario_baja)
    {
        $id_usuario_baja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $stmt = $this->getDb()->prepare("CALL sp_gestion_permisos('DELETE', :id_permiso, NULL, NULL, :id_usuario)");
        $stmt->bindParam(':id_permiso', $id_permiso, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', $id_usuario_baja, $id_usuario_baja === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
