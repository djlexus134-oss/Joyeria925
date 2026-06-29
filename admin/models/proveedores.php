<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class Proveedores extends Sistema
{
    const TABLE = 'proveedores';

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT p.*, d.num_exterior, d.num_interior, c.nom_calle, col.nom_colonia, cp.codigo_postal
                FROM " . self::TABLE . " p
                LEFT JOIN direcciones d ON p.id_direccion_FK = d.id_direccion
                LEFT JOIN calles c ON d.id_calle_FK = c.id_calle
                LEFT JOIN colonias col ON c.id_colonia_FK = col.id_colonia
                LEFT JOIN codigos_postales cp ON col.id_codigo_postal_FK = cp.id_codigo_postal
                WHERE COALESCE(p.activo, 1) = 1";
        if ($pat !== null) {
            $sql .= " AND (
                p.razon_social LIKE :busq OR p.rfc LIKE :busq2 OR col.nom_colonia LIKE :busq3
                OR c.nom_calle LIKE :busq4 OR CAST(cp.codigo_postal AS CHAR) LIKE :busq5
                OR CAST(d.num_exterior AS CHAR) LIKE :busq6
            )";
        }
        $sql .= " ORDER BY p.razon_social ASC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq6', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($id)
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM " . self::TABLE . " WHERE id_proveedor = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $razonSocial = $this->validarTexto($data, 'razon_social', 100, 'La razon social');
        $incluirDir = isset($data['incluir_direccion']) && (string) $data['incluir_direccion'] === '1';
        $nuevaRapida = isset($data['nueva_direccion_rapida']) && (string) $data['nueva_direccion_rapida'] === '1';

        if (!$incluirDir) {
            $idDireccion = null;
        } elseif ($nuevaRapida) {
            $idDireccion = $this->insertarDireccionRapida($data);
        } else {
            $idDireccion = $this->validarEnteroOpcionalPositivo($data, 'id_direccion_FK');
            if ($idDireccion === null || $idDireccion <= 0) {
                throw new InvalidArgumentException('Selecciona una direccion del catalogo o captura una direccion rapida.');
            }
        }
        $nomComercial = $this->validarTextoOpcional($data, 'nom_comercial', 100);
        $rfc = $this->validarTextoOpcional($data, 'rfc', 13);
        $tipoPersona = $this->validarTipoPersonaOpcional($data, 'tipo_persona');
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 2000);

        $stmt = $this->getDb()->prepare(
            "INSERT INTO " . self::TABLE . " (razon_social, nom_comercial, id_direccion_FK, rfc, tipo_persona, observaciones, activo)
             VALUES (:razon_social, :nom_comercial, :id_direccion_FK, :rfc, :tipo_persona, :observaciones, 1)"
        );

        $stmt->bindValue(':razon_social', $razonSocial, PDO::PARAM_STR);
        $stmt->bindValue(':nom_comercial', $nomComercial, $nomComercial === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id_direccion_FK', $idDireccion, $idDireccion === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':rfc', $rfc, $rfc === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':tipo_persona', $tipoPersona, $tipoPersona === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $stmt->execute();
        return $stmt->rowCount();
    }

    public function actualizar($id, $data)
    {
        $actualRow = $this->leerUno($id);
        if (!$actualRow || (int) ($actualRow['activo'] ?? 1) !== 1) {
            throw new InvalidArgumentException('Proveedor no encontrado o inactivo.');
        }
        $idDirActual = isset($actualRow['id_direccion_FK']) && (int) $actualRow['id_direccion_FK'] > 0
            ? (int) $actualRow['id_direccion_FK']
            : null;

        $razonSocial = $this->validarTexto($data, 'razon_social', 100, 'La razon social');
        $incluirDir = isset($data['incluir_direccion']) && (string) $data['incluir_direccion'] === '1';
        $nuevaRapida = isset($data['nueva_direccion_rapida']) && (string) $data['nueva_direccion_rapida'] === '1';

        if (!$incluirDir) {
            $idDireccion = $idDirActual;
        } elseif ($nuevaRapida) {
            $idDireccion = $this->insertarDireccionRapida($data);
        } else {
            $idDireccion = $this->validarEnteroOpcionalPositivo($data, 'id_direccion_FK');
            if ($incluirDir && ($idDireccion === null || $idDireccion <= 0)) {
                throw new InvalidArgumentException('Selecciona una direccion del catalogo.');
            }
        }

        if ($idDirActual !== null && ($idDireccion === null || $idDireccion <= 0)) {
            throw new InvalidArgumentException('La direccion es obligatoria para este proveedor.');
        }
        $nomComercial = $this->validarTextoOpcional($data, 'nom_comercial', 100);
        $rfc = $this->validarTextoOpcional($data, 'rfc', 13);
        $tipoPersona = $this->validarTipoPersonaOpcional($data, 'tipo_persona');
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 2000);

        $stmt = $this->getDb()->prepare(
            "UPDATE " . self::TABLE . "
             SET razon_social = :razon_social,
                 nom_comercial = :nom_comercial,
                 id_direccion_FK = :id_direccion_FK,
                 rfc = :rfc,
                 tipo_persona = :tipo_persona,
                 observaciones = :observaciones
             WHERE id_proveedor = :id AND COALESCE(activo, 1) = 1"
        );

        $stmt->bindValue(':razon_social', $razonSocial, PDO::PARAM_STR);
        $stmt->bindValue(':nom_comercial', $nomComercial, $nomComercial === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id_direccion_FK', $idDireccion, $idDireccion === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':rfc', $rfc, $rfc === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':tipo_persona', $tipoPersona, $tipoPersona === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->rowCount();
    }

    public function borrar($id)
    {
        $stmt = $this->getDb()->prepare("UPDATE " . self::TABLE . " SET activo = 0 WHERE id_proveedor = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function insertarDireccionRapida(array $data): int
    {
        if (!isset($data['num_exterior']) || trim((string) $data['num_exterior']) === '' || !is_numeric($data['num_exterior'])) {
            throw new InvalidArgumentException('El numero exterior es obligatorio para la direccion rapida.');
        }
        $numExterior = (int) $data['num_exterior'];
        if ($numExterior <= 0) {
            throw new InvalidArgumentException('El numero exterior debe ser mayor a cero.');
        }
        $numInterior = null;
        if (isset($data['num_interior']) && trim((string) $data['num_interior']) !== '') {
            if (!is_numeric($data['num_interior'])) {
                throw new InvalidArgumentException('El numero interior debe ser numerico.');
            }
            $numInterior = (int) $data['num_interior'];
        }
        if (!isset($data['id_calle_FK']) || trim((string) $data['id_calle_FK']) === '' || (int) $data['id_calle_FK'] <= 0) {
            throw new InvalidArgumentException('La calle es obligatoria para la direccion rapida.');
        }
        $idCalle = (int) $data['id_calle_FK'];

        $db = $this->getDb();
        $stmt = $db->prepare(
            'INSERT INTO direcciones (num_exterior, num_interior, id_calle_FK) VALUES (:num_exterior, :num_interior, :id_calle_FK)'
        );
        $stmt->bindValue(':num_exterior', $numExterior, PDO::PARAM_INT);
        $stmt->bindValue(':num_interior', $numInterior, $numInterior === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_calle_FK', $idCalle, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $db->lastInsertId();
    }

    public function obtenerDirecciones()
    {
        $sql = "SELECT d.id_direccion,
                       d.num_exterior,
                       d.num_interior,
                       c.nom_calle,
                       col.nom_colonia,
                       cp.codigo_postal
                FROM direcciones d
                INNER JOIN calles c ON d.id_calle_FK = c.id_calle
                INNER JOIN colonias col ON c.id_colonia_FK = col.id_colonia
                INNER JOIN codigos_postales cp ON col.id_codigo_postal_FK = cp.id_codigo_postal
                ORDER BY cp.codigo_postal ASC, col.nom_colonia ASC, c.nom_calle ASC, d.num_exterior ASC";

        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerContactosActivosPorProveedorIds(array $idsProveedor): array
    {
        $ids = array_values(array_filter(array_map('intval', $idsProveedor), static function ($id) {
            return $id > 0;
        }));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id_contacto, id_proveedor_FK, nombre, telefono, correo, puesto
                FROM proveedor_contactos
                WHERE activo = 1 AND id_proveedor_FK IN ($placeholders)
                ORDER BY nombre ASC";
        $stmt = $this->getDb()->prepare($sql);
        foreach ($ids as $idx => $idProveedor) {
            $stmt->bindValue($idx + 1, $idProveedor, PDO::PARAM_INT);
        }
        $stmt->execute();

        $mapa = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $contacto) {
            $idProveedor = (int) ($contacto['id_proveedor_FK'] ?? 0);
            if ($idProveedor <= 0) {
                continue;
            }
            if (!isset($mapa[$idProveedor])) {
                $mapa[$idProveedor] = [];
            }
            $mapa[$idProveedor][] = $contacto;
        }

        return $mapa;
    }

    private function validarTexto($data, $campo, $max, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es requerida.');
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacia.');
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

    private function validarEnteroOpcionalPositivo($data, $campo)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }

        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException('La direccion seleccionada no es valida.');
        }

        $valor = (int) $data[$campo];
        if ($valor <= 0) {
            return null;
        }

        return $valor;
    }

    private function validarTipoPersonaOpcional($data, $campo)
    {
        if (!isset($data[$campo])) {
            return null;
        }

        $valor = trim((string) $data[$campo]);
        if ($valor === '') {
            return null;
        }

        $permitidos = ['Fisica', 'Moral'];
        if (!in_array($valor, $permitidos, true)) {
            throw new InvalidArgumentException('El tipo de persona no es valido.');
        }

        return $valor;
    }
}
