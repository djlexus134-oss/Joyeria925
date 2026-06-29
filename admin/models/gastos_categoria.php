<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class GastosCategoria extends Sistema
{
    const TABLE = 'gastos_categoria';

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT * FROM " . self::TABLE . " WHERE activo = 1";
        if ($pat !== null) {
            $sql .= " AND (nombre LIKE :busq OR IFNULL(descripcion, '') LIKE :busq2)";
        }
        $sql .= " ORDER BY nombre ASC";
        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($id)
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM " . self::TABLE . " WHERE id_categoria_gasto = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $nombre = $this->validarTexto($data, 'nombre', 50, 'El nombre de la categoria');
        $descripcion = $this->validarOpcional($data, 'descripcion');

        $stmt = $this->getDb()->prepare("INSERT INTO " . self::TABLE . " (nombre, descripcion, activo) VALUES (:nombre, :descripcion, 1)");
        $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function actualizar($id, $data)
    {
        $nombre = $this->validarTexto($data, 'nombre', 50, 'El nombre de la categoria');
        $descripcion = $this->validarOpcional($data, 'descripcion');

        $stmt = $this->getDb()->prepare("UPDATE " . self::TABLE . " SET nombre = :nombre, descripcion = :descripcion WHERE id_categoria_gasto = :id AND activo = 1");
        $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function borrar($id)
    {
        $stmt = $this->getDb()->prepare("UPDATE " . self::TABLE . " SET activo = 0 WHERE id_categoria_gasto = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
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

    private function validarOpcional($data, $campo)
    {
        if (!isset($data[$campo])) {
            return null;
        }
        $valor = trim(strip_tags((string) $data[$campo]));
        return $valor === '' ? null : $valor;
    }
}
