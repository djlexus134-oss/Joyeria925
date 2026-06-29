<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class Tiendas extends Sistema
{
    const TABLE = 'tiendas';
    
    private function validarDireccion(array $data): array
    {
        if (!isset($data['num_exterior']) || trim((string) $data['num_exterior']) === '' || !is_numeric($data['num_exterior'])) {
            throw new InvalidArgumentException('El numero exterior es obligatorio y debe ser numerico.');
        }
        $numExterior = (int) $data['num_exterior'];
        if ($numExterior <= 0) {
            throw new InvalidArgumentException('El numero exterior debe ser mayor a cero.');
        }

        $numInterior = null;
        if (isset($data['num_interior']) && trim((string) $data['num_interior']) !== '') {
            if (!is_numeric($data['num_interior'])) {
                throw new InvalidArgumentException('El numero interior debe ser numerico cuando se capture.');
            }
            $numInterior = (int) $data['num_interior'];
            if ($numInterior < 0) {
                throw new InvalidArgumentException('El numero interior no puede ser negativo.');
            }
        }

        if (!isset($data['id_calle_FK']) || (int) $data['id_calle_FK'] <= 0) {
            throw new InvalidArgumentException('La calle es obligatoria.');
        }
        $idCalle = (int) $data['id_calle_FK'];

        return [
            'num_exterior' => $numExterior,
            'num_interior' => $numInterior,
            'id_calle_FK' => $idCalle,
        ];
    }

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT t.*, d.num_exterior, d.num_interior, c.nom_calle, col.nom_colonia, cp.codigo_postal
                FROM " . self::TABLE . " t
                INNER JOIN direcciones d ON t.id_direccion_FK = d.id_direccion
                INNER JOIN calles c ON d.id_calle_FK = c.id_calle
                INNER JOIN colonias col ON c.id_colonia_FK = col.id_colonia
                INNER JOIN codigos_postales cp ON col.id_codigo_postal_FK = cp.id_codigo_postal
                WHERE t.activo = 1";
        if ($pat !== null) {
            $sql .= " AND (
                t.nom_tienda LIKE :busq OR c.nom_calle LIKE :busq2 OR col.nom_colonia LIKE :busq3
                OR CAST(cp.codigo_postal AS CHAR) LIKE :busq4 OR CAST(d.num_exterior AS CHAR) LIKE :busq5
            )";
        }
        $sql .= " ORDER BY t.nom_tienda ASC";

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
        $stmt = $this->getDb()->prepare("SELECT t.*,
                                                d.num_exterior,
                                                d.num_interior,
                                                d.id_calle_FK,
                                                c.id_colonia_FK AS id_colonia,
                                                c.nom_calle,
                                                col.id_localidad_FK AS id_localidad,
                                                col.id_codigo_postal_FK AS id_codigo_postal,
                                                col.nom_colonia,
                                                l.id_municipio_FK AS id_municipio,
                                                l.nom_localidad,
                                                m.id_estado_FK AS id_estado,
                                                m.nom_municipio,
                                                e.id_pais_FK AS id_pais,
                                                e.nom_estado,
                                                p.nom_pais,
                                                cp.codigo_postal
                                         FROM " . self::TABLE . " t
                                         INNER JOIN direcciones d ON t.id_direccion_FK = d.id_direccion
                                         INNER JOIN calles c ON d.id_calle_FK = c.id_calle
                                         INNER JOIN colonias col ON c.id_colonia_FK = col.id_colonia
                                         INNER JOIN localidades l ON col.id_localidad_FK = l.id_localidad
                                         INNER JOIN municipios m ON l.id_municipio_FK = m.id_municipio
                                         INNER JOIN estados e ON m.id_estado_FK = e.id_estado
                                         INNER JOIN paises p ON e.id_pais_FK = p.id_pais
                                         INNER JOIN codigos_postales cp ON col.id_codigo_postal_FK = cp.id_codigo_postal
                                         WHERE t.id_tienda = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $nombre = $this->validarTexto($data, 'nom_tienda', 30, 'El nombre de la tienda');
        $direccion = $this->validarDireccion($data);
        $db = $this->getDb();

        try {
            $db->beginTransaction();

            $stmtDir = $db->prepare(
                "INSERT INTO direcciones (num_exterior, num_interior, id_calle_FK) VALUES (:num_exterior, :num_interior, :id_calle_FK)"
            );
            $stmtDir->bindValue(':num_exterior', $direccion['num_exterior'], PDO::PARAM_INT);
            $stmtDir->bindValue(':num_interior', $direccion['num_interior'], $direccion['num_interior'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmtDir->bindValue(':id_calle_FK', $direccion['id_calle_FK'], PDO::PARAM_INT);
            $stmtDir->execute();
            $idDireccion = (int) $db->lastInsertId();

            $stmtTienda = $db->prepare(
                "INSERT INTO " . self::TABLE . " (nom_tienda, id_direccion_FK, activo) VALUES (:nom_tienda, :id_direccion_FK, 1)"
            );
            $stmtTienda->bindValue(':nom_tienda', $nombre, PDO::PARAM_STR);
            $stmtTienda->bindValue(':id_direccion_FK', $idDireccion, PDO::PARAM_INT);
            $stmtTienda->execute();

            $db->commit();
            return $stmtTienda->rowCount();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar($id, $data)
    {
        $nombre = $this->validarTexto($data, 'nom_tienda', 30, 'El nombre de la tienda');
        $direccion = $this->validarDireccion($data);
        $db = $this->getDb();

        $stmtInfo = $db->prepare("SELECT id_direccion_FK FROM " . self::TABLE . " WHERE id_tienda = :id AND activo = 1");
        $stmtInfo->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmtInfo->execute();
        $rowInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        if (!$rowInfo) {
            throw new RuntimeException('La tienda no existe o esta inactiva.');
        }
        $idDireccion = (int) $rowInfo['id_direccion_FK'];

        try {
            $db->beginTransaction();

            $stmtDir = $db->prepare(
                "UPDATE direcciones SET num_exterior = :num_exterior, num_interior = :num_interior, id_calle_FK = :id_calle_FK WHERE id_direccion = :id_direccion"
            );
            $stmtDir->bindValue(':num_exterior', $direccion['num_exterior'], PDO::PARAM_INT);
            $stmtDir->bindValue(':num_interior', $direccion['num_interior'], $direccion['num_interior'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmtDir->bindValue(':id_calle_FK', $direccion['id_calle_FK'], PDO::PARAM_INT);
            $stmtDir->bindValue(':id_direccion', $idDireccion, PDO::PARAM_INT);
            $stmtDir->execute();

            $stmtTienda = $db->prepare(
                "UPDATE " . self::TABLE . " SET nom_tienda = :nom_tienda WHERE id_tienda = :id AND activo = 1"
            );
            $stmtTienda->bindValue(':nom_tienda', $nombre, PDO::PARAM_STR);
            $stmtTienda->bindValue(':id', (int) $id, PDO::PARAM_INT);
            $stmtTienda->execute();

            $db->commit();
            return ($stmtTienda->rowCount() > 0 || $stmtDir->rowCount() > 0) ? 1 : 0;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function borrar($id, $idUsuarioBaja = null)
    {
        $idUsuarioBaja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $stmt = $this->getDb()->prepare(
            "UPDATE " . self::TABLE . "
             SET activo = 0,
                 fecha_baja = NOW(),
                 id_usuario_baja = :id_usuario_baja
             WHERE id_tienda = :id AND activo = 1"
        );
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario_baja', $idUsuarioBaja !== null ? (int) $idUsuarioBaja : null, $idUsuarioBaja !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();
        return $stmt->rowCount();
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

}
