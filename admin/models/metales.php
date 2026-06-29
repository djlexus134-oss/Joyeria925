<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class Metales extends Sistema
{
    const TABLE = 'metales';
    const MAX_NAME_LENGTH = 25;

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT * FROM " . self::TABLE . " WHERE activo = 1";
        if ($pat !== null) {
            $sql .= " AND nom_metal LIKE :busq";
        }
        $sql .= " ORDER BY nom_metal ASC";
        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($id_metal)
    {
        $sql = "SELECT * FROM " . self::TABLE . " WHERE id_metal = :id_metal";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':id_metal', $id_metal, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $nomMetal = $this->validarNombre($data);
        $precioTienda = $this->validarPrecioOpcional($data, 'precio_tienda', 'precio de tienda');
        $precioMercado = $this->validarPrecioOpcional($data, 'precio_mercado', 'precio de mercado');

        $sql = "INSERT INTO " . self::TABLE . " (nom_metal, precio_tienda, precio_mercado, activo) VALUES (:nom_metal, :precio_tienda, :precio_mercado, 1)";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':nom_metal', $nomMetal, PDO::PARAM_STR);
        $stmt->bindValue(':precio_tienda', $precioTienda, $precioTienda === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':precio_mercado', $precioMercado, $precioMercado === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function actualizar($id_metal, $data)
    {
        $nomMetal = $this->validarNombre($data);
        $precioTienda = $this->validarPrecioOpcional($data, 'precio_tienda', 'precio de tienda');
        $precioMercado = $this->validarPrecioOpcional($data, 'precio_mercado', 'precio de mercado');

        $sql = "UPDATE " . self::TABLE . " SET nom_metal = :nom_metal, precio_tienda = :precio_tienda, precio_mercado = :precio_mercado WHERE id_metal = :id_metal AND activo = 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':nom_metal', $nomMetal, PDO::PARAM_STR);
        $stmt->bindValue(':precio_tienda', $precioTienda, $precioTienda === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':precio_mercado', $precioMercado, $precioMercado === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':id_metal', $id_metal, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function borrar($id_metal, $id_usuario_baja = null)
    {
        $id_usuario_baja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $sql = "UPDATE " . self::TABLE . " SET activo = 0, fecha_baja = NOW(), id_usuario_baja = :id_usuario_baja WHERE id_metal = :id_metal";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_usuario_baja', $id_usuario_baja, $id_usuario_baja === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':id_metal', $id_metal, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function validarNombre($data)
    {
        if (!isset($data['nom_metal'])) {
            throw new InvalidArgumentException('El nombre del metal es requerido.');
        }

        $nombre = trim(strip_tags((string) $data['nom_metal']));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del metal no puede estar vacio.');
        }

        if (mb_strlen($nombre) > self::MAX_NAME_LENGTH) {
            $nombre = mb_substr($nombre, 0, self::MAX_NAME_LENGTH);
        }

        return $nombre;
    }

    private function validarPrecioOpcional($data, $key, $label)
    {
        if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
            return null;
        }

        if (!is_numeric($data[$key])) {
            throw new InvalidArgumentException('El ' . $label . ' debe ser numerico.');
        }

        $valor = (float) $data[$key];
        if ($valor < 0) {
            throw new InvalidArgumentException('El ' . $label . ' no puede ser negativo.');
        }

        return $valor;
    }
}
