<?php

/**
 * Normaliza nombres de catalogo para comparacion case-insensitive sin acentos.
 */
function joyeria_norm_catalog_label(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n',
    ];

    return strtr($value, $map);
}

/**
 * @throws InvalidArgumentException si ya existe otro registro activo con el mismo nombre normalizado
 */
function joyeria_assert_catalog_name_unique(
    PDO $db,
    string $table,
    string $nameColumn,
    string $name,
    string $idColumn,
    ?int $excludeId = null,
    string $activeCondition = 'activo = 1'
): void {
    $name = trim($name);
    if ($name === '') {
        return;
    }

    $sql = "SELECT {$idColumn} AS id FROM {$table} WHERE {$activeCondition}
            AND LOWER(TRIM({$nameColumn})) = LOWER(TRIM(:name))";
    if ($excludeId !== null && $excludeId > 0) {
        $sql .= " AND {$idColumn} <> :exclude_id";
    }
    $sql .= ' LIMIT 1';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    if ($excludeId !== null && $excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new InvalidArgumentException('Ya existe un registro activo con ese nombre.');
    }
}

/**
 * Impuestos no tienen columna activo: unicidad por tipo_impuesto normalizado.
 */
function joyeria_assert_impuesto_tipo_unique(PDO $db, string $tipo, ?int $excludeId = null): void
{
    $tipo = trim($tipo);
    if ($tipo === '') {
        return;
    }

    $sql = 'SELECT id_impuesto FROM impuestos
            WHERE LOWER(TRIM(tipo_impuesto)) = LOWER(TRIM(:tipo))';
    if ($excludeId !== null && $excludeId > 0) {
        $sql .= ' AND id_impuesto <> :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    if ($excludeId !== null && $excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new InvalidArgumentException('Ya existe un impuesto con ese tipo.');
    }
}
