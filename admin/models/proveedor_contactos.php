<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class ProveedorContactos extends Sistema
{
    const TABLE = 'proveedor_contactos';

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT pc.*, p.razon_social
                FROM " . self::TABLE . " pc
                INNER JOIN proveedores p ON pc.id_proveedor_FK = p.id_proveedor
                WHERE pc.activo = 1 AND COALESCE(p.activo, 1) = 1";
        if ($pat !== null) {
            $sql .= " AND (
                pc.nombre LIKE :busq OR pc.correo LIKE :busq2 OR pc.telefono LIKE :busq3
                OR pc.puesto LIKE :busq4 OR p.razon_social LIKE :busq5
            )";
        }
        $sql .= " ORDER BY p.razon_social ASC, pc.nombre ASC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($id)
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM " . self::TABLE . " WHERE id_contacto = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $idProveedor = $this->validarEnteroPositivo($data, 'id_proveedor_FK', 'El proveedor');
        $nombre = $this->validarTexto($data, 'nombre', 100, 'El nombre del contacto');
        $telefono = $this->validarTextoOpcional($data, 'telefono', 20);
        $correo = $this->validarTextoOpcional($data, 'correo', 80);
        $puesto = $this->validarTextoOpcional($data, 'puesto', 50);

        $stmt = $this->getDb()->prepare(
            "INSERT INTO " . self::TABLE . "
             (id_proveedor_FK, nombre, telefono, correo, puesto, activo)
             VALUES
             (:id_proveedor_FK, :nombre, :telefono, :correo, :puesto, 1)"
        );

        $stmt->bindValue(':id_proveedor_FK', $idProveedor, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':telefono', $telefono, $telefono === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':correo', $correo, $correo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':puesto', $puesto, $puesto === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function actualizar($id, $data)
    {
        $idProveedor = $this->validarEnteroPositivo($data, 'id_proveedor_FK', 'El proveedor');
        $nombre = $this->validarTexto($data, 'nombre', 100, 'El nombre del contacto');
        $telefono = $this->validarTextoOpcional($data, 'telefono', 20);
        $correo = $this->validarTextoOpcional($data, 'correo', 80);
        $puesto = $this->validarTextoOpcional($data, 'puesto', 50);

        $stmt = $this->getDb()->prepare(
            "UPDATE " . self::TABLE . "
             SET id_proveedor_FK = :id_proveedor_FK,
                 nombre = :nombre,
                 telefono = :telefono,
                 correo = :correo,
                 puesto = :puesto
             WHERE id_contacto = :id AND activo = 1"
        );

        $stmt->bindValue(':id_proveedor_FK', $idProveedor, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':telefono', $telefono, $telefono === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':correo', $correo, $correo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':puesto', $puesto, $puesto === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function borrar($id, $idUsuarioBaja = null)
    {
        $idUsuarioBaja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $stmt = $this->getDb()->prepare(
            "UPDATE " . self::TABLE . "
             SET activo = 0,
                 fecha_baja = NOW(),
                 id_usuario_baja = :id_usuario_baja
             WHERE id_contacto = :id AND activo = 1"
        );
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario_baja', $idUsuarioBaja !== null ? (int) $idUsuarioBaja : null, $idUsuarioBaja !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function obtenerProveedoresActivos()
    {
        $sql = "SELECT id_proveedor, razon_social FROM proveedores WHERE COALESCE(activo, 1) = 1 ORDER BY razon_social ASC";
        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validarTexto($data, $campo, $max, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
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
