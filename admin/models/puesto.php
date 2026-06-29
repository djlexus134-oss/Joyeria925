<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class Puesto extends Sistema
{
    const MAX_NAME_LENGTH = 50;

    public function leer(?string $busqueda = null)
    {
        $stmt = $this->getDb()->prepare("CALL sp_gestion_puestos('SELECT', NULL, NULL, NULL, NULL)");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return joyeria_filter_rows_by_search($rows, $busqueda, ['id_puesto', 'nombre_puesto', 'descripcion']);
    }

    public function leerUno($id_puesto)
    {
        $stmt = $this->getDb()->prepare("CALL sp_gestion_puestos('SELECT_ID', :id_puesto, NULL, NULL, NULL)");
        $stmt->bindParam(':id_puesto', $id_puesto, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }   

    public function crear($data)
    {
        $descripcion = isset($data['descripcion']) && trim((string) $data['descripcion']) !== ''
            ? trim((string) $data['descripcion'])
            : null;
        $stmt = $this->getDb()->prepare("CALL sp_gestion_puestos('INSERT', NULL , :nombre, :descripcion, NULL)");
        $stmt->bindValue(':nombre', trim((string) $data['nombre_puesto']), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function actualizar($id_puesto, $data)
    {
        $descripcion = isset($data['descripcion']) && trim((string) $data['descripcion']) !== ''
            ? trim((string) $data['descripcion'])
            : null;
        $stmt = $this->getDb()->prepare("CALL sp_gestion_puestos('UPDATE', :id_puesto, :nombre, :descripcion, NULL)");
        $stmt->bindValue(':id_puesto', (int) $id_puesto, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', trim((string) $data['nombre_puesto']), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function borrar($id_puesto, $id_usuario_baja)
    {
        $id_usuario_baja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $stmt = $this->getDb()->prepare("CALL sp_gestion_puestos('DELETE', :id_puesto, NULL, NULL, :id_usuario)");
        $stmt->bindParam(':id_puesto', $id_puesto, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', $id_usuario_baja, $id_usuario_baja === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
