<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";
require_once __DIR__ . "/../includes/porcentaje_validacion.php";
require_once __DIR__ . "/../includes/PromocionTiendaResolver.php";

class Promociones extends Sistema
{
    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT pr.id_promocion,
                       pr.nombre,
                       pr.porcentaje_descuento,
                       pr.fecha_inicio,
                       pr.fecha_fin,
                       pr.activa,
                       pr.aplica_todas_familias,
                       p.desc_pieza,
                       sf.nom_sub_familia,
                       f.nom_familia
                FROM promociones pr
                LEFT JOIN piezas p ON p.id_pieza = pr.id_pieza_FK
                LEFT JOIN sub_familia sf ON sf.id_sub_familia = pr.id_subfamilia_FK
                LEFT JOIN familias f ON f.id_familia = pr.id_familia_FK
                WHERE 1=1";
        if ($pat !== null) {
            $sql .= " AND (
                pr.nombre LIKE :busq OR p.desc_pieza LIKE :busq2 OR sf.nom_sub_familia LIKE :busq3
                OR f.nom_familia LIKE :busq4 OR CAST(pr.porcentaje_descuento AS CHAR) LIKE :busq5
                OR CAST(pr.id_promocion AS CHAR) LIKE :busq6
            )";
        }
        $sql .= " ORDER BY pr.id_promocion DESC";

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

    public function listarVigentes(): array
    {
        require_once __DIR__ . '/../includes/PromocionTiendaResolver.php';

        return (new PromocionTiendaResolver())->listarVigentes();
    }

    public function leerUno($idPromocion)
    {
        $sql = "SELECT pr.*
                FROM promociones pr
                WHERE pr.id_promocion = :id_promocion";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_promocion', (int) $idPromocion, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerCatalogos()
    {
        return [
            'piezas' => $this->getDb()->query("SELECT p.id_pieza, p.desc_pieza, sf.nom_sub_familia, m.nom_metal
                                              FROM piezas p
                                              INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                                              INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                                              WHERE p.activo = 1
                                              ORDER BY p.desc_pieza ASC")->fetchAll(PDO::FETCH_ASSOC),
            'subfamilias' => $this->getDb()->query("SELECT id_sub_familia, nom_sub_familia, id_familia_FK
                                                   FROM sub_familia
                                                   WHERE activo = 1
                                                   ORDER BY nom_sub_familia ASC")->fetchAll(PDO::FETCH_ASSOC),
            'familias' => $this->getDb()->query("SELECT id_familia, nom_familia
                                               FROM familias
                                               WHERE activo = 1
                                               ORDER BY nom_familia ASC")->fetchAll(PDO::FETCH_ASSOC),
            'metales' => $this->getDb()->query("SELECT id_metal, nom_metal
                                               FROM metales
                                               WHERE activo = 1
                                               ORDER BY nom_metal ASC")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function crear($data)
    {
        $nombre = $this->validarTexto($data, 'nombre', 100, 'El nombre de la promocion');
        $porcentaje = $this->validarPorcentajeDescuento($data, 'porcentaje_descuento');

        $fechaInicio = $this->validarFecha($data, 'fecha_inicio', 'La fecha de inicio');
        $fechaFin = $this->validarFecha($data, 'fecha_fin', 'La fecha de fin');

        // Validar que fecha_fin >= fecha_inicio
        if (strtotime($fechaFin) < strtotime($fechaInicio)) {
            throw new Exception("La fecha de fin debe ser mayor o igual a la fecha de inicio");
        }

        $alcance = $this->resolverAlcancePromocion($data);
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 65535);

        $stmt = $this->getDb()->prepare(
            "INSERT INTO promociones
            (nombre, porcentaje_descuento, fecha_inicio, fecha_fin, id_pieza_FK, id_subfamilia_FK, id_familia_FK, id_metal_FK, aplica_todas_familias, observaciones, activa)
            VALUES
            (:nombre, :porcentaje_descuento, :fecha_inicio, :fecha_fin, :id_pieza_FK, :id_subfamilia_FK, :id_familia_FK, :id_metal_FK, :aplica_todas_familias, :observaciones, 1)"
        );

        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':porcentaje_descuento', $porcentaje, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $fechaFin, PDO::PARAM_STR);
        $stmt->bindValue(':id_pieza_FK', $alcance['id_pieza_FK'], $alcance['id_pieza_FK'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_subfamilia_FK', $alcance['id_subfamilia_FK'], $alcance['id_subfamilia_FK'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_familia_FK', $alcance['id_familia_FK'], $alcance['id_familia_FK'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_metal_FK', $alcance['id_metal_FK'], $alcance['id_metal_FK'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':aplica_todas_familias', $alcance['aplica_todas_familias'], PDO::PARAM_INT);
        $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        PromocionTiendaResolver::limpiarCache();

        return (int) $this->getDb()->lastInsertId();
    }

    public function actualizar($idPromocion, $data)
    {
        $nombre = $this->validarTexto($data, 'nombre', 100, 'El nombre de la promocion');
        $porcentaje = $this->validarPorcentajeDescuento($data, 'porcentaje_descuento');

        $fechaInicio = $this->validarFecha($data, 'fecha_inicio', 'La fecha de inicio');
        $fechaFin = $this->validarFecha($data, 'fecha_fin', 'La fecha de fin');

        // Validar que fecha_fin >= fecha_inicio
        if (strtotime($fechaFin) < strtotime($fechaInicio)) {
            throw new Exception("La fecha de fin debe ser mayor o igual a la fecha de inicio");
        }

        $alcance = $this->resolverAlcancePromocion($data);
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 65535);

        $stmt = $this->getDb()->prepare(
            "UPDATE promociones
            SET nombre = :nombre,
                porcentaje_descuento = :porcentaje_descuento,
                fecha_inicio = :fecha_inicio,
                fecha_fin = :fecha_fin,
                id_pieza_FK = :id_pieza_FK,
                id_subfamilia_FK = :id_subfamilia_FK,
                id_familia_FK = :id_familia_FK,
                id_metal_FK = :id_metal_FK,
                aplica_todas_familias = :aplica_todas_familias,
                observaciones = :observaciones
            WHERE id_promocion = :id_promocion"
        );

        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':porcentaje_descuento', $porcentaje, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $fechaFin, PDO::PARAM_STR);
        $stmt->bindValue(':id_pieza_FK', $alcance['id_pieza_FK'], $alcance['id_pieza_FK'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_subfamilia_FK', $alcance['id_subfamilia_FK'], $alcance['id_subfamilia_FK'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_familia_FK', $alcance['id_familia_FK'], $alcance['id_familia_FK'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_metal_FK', $alcance['id_metal_FK'], $alcance['id_metal_FK'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':aplica_todas_familias', $alcance['aplica_todas_familias'], PDO::PARAM_INT);
        $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id_promocion', (int) $idPromocion, PDO::PARAM_INT);
        $stmt->execute();

        PromocionTiendaResolver::limpiarCache();
    }

    public function eliminar($idPromocion)
    {
        $stmt = $this->getDb()->prepare(
            "UPDATE promociones
            SET activa = 0
            WHERE id_promocion = :id_promocion"
        );

        $stmt->bindValue(':id_promocion', (int) $idPromocion, PDO::PARAM_INT);
        $stmt->execute();

        PromocionTiendaResolver::limpiarCache();
    }

    /**
     * @return array{
     *   aplica_todas_familias: int,
     *   id_pieza_FK: ?int,
     *   id_subfamilia_FK: ?int,
     *   id_familia_FK: ?int
     * }
     */
    private function resolverAlcancePromocion(array $data): array
    {
        $todasFamilias = isset($data['aplica_todas_familias'])
            && (string) $data['aplica_todas_familias'] !== ''
            && (string) $data['aplica_todas_familias'] !== '0';

        if ($todasFamilias) {
            return [
                'aplica_todas_familias' => 1,
                'id_pieza_FK' => null,
                'id_subfamilia_FK' => null,
                'id_familia_FK' => null,
                'id_metal_FK' => null,
            ];
        }

        $idPieza = $this->validarEnteroOpcional($data, 'id_pieza_FK');
        $idSubfamilia = $this->validarEnteroOpcional($data, 'id_subfamilia_FK')
            ?? $this->validarEnteroOpcional($data, 'id_sub_familia_FK');
        $idFamilia = $this->validarEnteroOpcional($data, 'id_familia_FK');
        $idMetal = $this->validarEnteroOpcional($data, 'id_metal_FK');

        if ($idPieza === null && $idSubfamilia === null && $idFamilia === null && $idMetal === null) {
            throw new Exception('Debe seleccionar todas las familias o al menos una pieza, subfamilia, familia o metal.');
        }

        return [
            'aplica_todas_familias' => 0,
            'id_pieza_FK' => $idPieza,
            'id_subfamilia_FK' => $idSubfamilia,
            'id_familia_FK' => $idFamilia,
            'id_metal_FK' => $idMetal,
        ];
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

    private function validarPorcentajeDescuento(array $data, string $campo): string
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException('El porcentaje de descuento es obligatorio.');
        }

        $normalizado = joyeria_normalizar_porcentaje_0_100($data[$campo], false, 'El porcentaje de descuento');
        if ($normalizado === null) {
            throw new InvalidArgumentException('El porcentaje de descuento es obligatorio.');
        }

        return $normalizado;
    }

    private function validarEnteroOpcional($data, $campo)
    {
        if (!isset($data[$campo]) || $data[$campo] === '' || (int) $data[$campo] <= 0) {
            return null;
        }

        return (int) $data[$campo];
    }

    private function validarFecha($data, $campo, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = trim((string) $data[$campo]);
        if ($valor === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacia.');
        }

        // Validar formato de fecha YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            throw new InvalidArgumentException($label . ' debe tener formato YYYY-MM-DD.');
        }

        // Validar que sea una fecha válida
        $parsedDate = strtotime($valor);
        if ($parsedDate === false) {
            throw new InvalidArgumentException($label . ' no es una fecha valida.');
        }

        return $valor;
    }
}

