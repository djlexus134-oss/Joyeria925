<?php
declare(strict_types=1);

/**
 * Normaliza talla/color de stock y mantiene compatibilidad con variante_tipo / variante_valor.
 *
 * @return array{0: string, 1: ?string, 2: ?string, 3: ?string} [tipo, valor, talla, color]
 */
function joyeria_normalizar_variantes_stock(?string $talla, ?string $color): array
{
    $talla = trim((string) ($talla ?? ''));
    $color = trim((string) ($color ?? ''));
    if ($talla !== '' && mb_strlen($talla) > 40) {
        $talla = mb_substr($talla, 0, 40);
    }
    if ($color !== '' && mb_strlen($color) > 40) {
        $color = mb_substr($color, 0, 40);
    }

    if ($talla === '' && $color === '') {
        return ['ninguna', null, null, null];
    }
    if ($talla !== '' && $color !== '') {
        return ['ninguna', null, $talla, $color];
    }
    if ($talla !== '') {
        return ['talla', $talla, $talla, null];
    }

    return ['color', $color, null, $color];
}

/**
 * Resuelve talla/color desde fila de stock (columnas nuevas o legacy).
 *
 * @param array<string, mixed> $row
 * @return array{talla: string, color: string}
 */
function joyeria_extraer_talla_color_stock(array $row): array
{
    $talla = trim((string) ($row['variante_talla'] ?? ''));
    $color = trim((string) ($row['variante_color'] ?? ''));
    $tipo = trim((string) ($row['variante_tipo'] ?? 'ninguna'));
    $valor = trim((string) ($row['variante_valor'] ?? ''));

    if ($talla === '' && $tipo === 'talla' && $valor !== '') {
        $talla = $valor;
    }
    if ($color === '' && $tipo === 'color' && $valor !== '') {
        $color = $valor;
    }

    return ['talla' => $talla, 'color' => $color];
}

/**
 * @param array<string, mixed> $row
 * @return list<array{tipo: string, valor: string, es_talla: bool, slug: string}>
 */
function joyeria_extraer_ejes_stock(array $row): array
{
    $ejes = [];

    $eje1Valor = trim((string) ($row['variante_eje1_valor'] ?? ''));
    if ($eje1Valor !== '') {
        $ejes[] = [
            'tipo' => trim((string) ($row['variante_eje1_tipo'] ?? 'Variante')),
            'valor' => $eje1Valor,
            'es_talla' => (int) ($row['variante_eje1_es_talla'] ?? 0) === 1,
            'slug' => trim((string) ($row['variante_eje1_slug'] ?? '')),
        ];
    }

    $eje2Valor = trim((string) ($row['variante_eje2_valor'] ?? ''));
    if ($eje2Valor !== '') {
        $ejes[] = [
            'tipo' => trim((string) ($row['variante_eje2_tipo'] ?? 'Variante')),
            'valor' => $eje2Valor,
            'es_talla' => (int) ($row['variante_eje2_es_talla'] ?? 0) === 1,
            'slug' => trim((string) ($row['variante_eje2_slug'] ?? '')),
        ];
    }

    if ($ejes !== []) {
        return $ejes;
    }

    $extra = joyeria_extraer_talla_color_stock($row);
    if ($extra['color'] !== '') {
        $ejes[] = [
            'tipo' => 'Color',
            'valor' => $extra['color'],
            'es_talla' => false,
            'slug' => 'color',
        ];
    }
    if ($extra['talla'] !== '') {
        $ejes[] = [
            'tipo' => 'Talla',
            'valor' => $extra['talla'],
            'es_talla' => true,
            'slug' => 'talla',
        ];
    }

    return $ejes;
}

/**
 * Texto legible para visitante/cliente: "Color: Rosa · Talla: 7"
 *
 * @param array<string, mixed>|null $item
 */
function joyeria_texto_variante_stock(?array $item): string
{
    if ($item === null) {
        return '';
    }

    $parts = joyeria_partes_texto_variante_stock($item);
    if ($parts === []) {
        return '';
    }

    return implode(' · ', $parts);
}

/**
 * @param array<string, mixed>|null $item
 * @return list<string>
 */
function joyeria_partes_texto_variante_stock(?array $item): array
{
    if ($item === null) {
        return [];
    }

    $parts = [];
    foreach (joyeria_extraer_ejes_stock($item) as $eje) {
        $parts[] = $eje['tipo'] . ': ' . $eje['valor'];
    }

    return $parts;
}

/**
 * Etiqueta corta para POS/etiquetas: "Rosa T7" o "T7" o "Rosa"
 *
 * @param array<string, mixed> $row
 */
function joyeria_etiqueta_corta_variante_stock(array $row): string
{
    $chunks = [];
    foreach (joyeria_extraer_ejes_stock($row) as $eje) {
        if ($eje['es_talla']) {
            $chunks[] = 'T' . $eje['valor'];
        } else {
            $chunks[] = $eje['valor'];
        }
    }

    return implode(' ', $chunks);
}

/**
 * Texto compacto para etiqueta mariposa (pad medio): "Rosa · T7", "T7" o "Rosa".
 *
 * @param array<string, mixed> $row
 */
function joyeria_texto_etiqueta_variante(array $row): string
{
    $parts = [];
    foreach (joyeria_extraer_ejes_stock($row) as $eje) {
        if ($eje['es_talla']) {
            $parts[] = 'T' . $eje['valor'];
        } else {
            $parts[] = $eje['valor'];
        }
    }
    if ($parts === []) {
        return '';
    }

    $texto = implode(' · ', $parts);
    if (mb_strlen($texto) > 14) {
        $texto = mb_substr($texto, 0, 13) . '…';
    }

    return $texto;
}

/**
 * @param list<string> $valores
 * @return list<string>
 */
function joyeria_ordenar_valores_talla(array $valores): array
{
    $valores = array_values(array_unique(array_filter(array_map('trim', $valores), static fn (string $v): bool => $v !== '')));
    usort($valores, static function (string $a, string $b): int {
        $na = is_numeric($a) ? (float) $a : null;
        $nb = is_numeric($b) ? (float) $b : null;
        if ($na !== null && $nb !== null) {
            return $na <=> $nb;
        }

        return strnatcasecmp($a, $b);
    });

    return $valores;
}

/**
 * @param list<string> $valores
 * @return list<string>
 */
function joyeria_ordenar_valores_natural(array $valores): array
{
    $valores = array_values(array_unique(array_filter(array_map('trim', $valores), static fn (string $v): bool => $v !== '')));
    sort($valores, SORT_NATURAL | SORT_FLAG_CASE);

    return $valores;
}

/**
 * Compara etiquetas de talla (numerica menor a mayor; resto natural).
 */
function joyeria_comparar_valores_talla(string $a, string $b): int
{
    $a = trim($a);
    $b = trim($b);
    $na = $a !== '' && is_numeric($a) ? (float) $a : null;
    $nb = $b !== '' && is_numeric($b) ? (float) $b : null;
    if ($na !== null && $nb !== null) {
        return $na <=> $nb;
    }

    return strnatcasecmp($a, $b);
}

/**
 * Ordena filas de variante_valores cuando el tipo es talla (por columna valor).
 *
 * @param list<array<string, mixed>> $filas
 * @return list<array<string, mixed>>
 */
function joyeria_ordenar_filas_variante_por_talla(array $filas): array
{
    if ($filas === []) {
        return $filas;
    }
    usort($filas, static function (array $a, array $b): int {
        $va = isset($a['valor']) ? trim((string) $a['valor']) : '';
        $vb = isset($b['valor']) ? trim((string) $b['valor']) : '';

        return joyeria_comparar_valores_talla($va, $vb);
    });

    return $filas;
}

function joyeria_sql_join_variantes_stock(string $alias = 'ps'): string
{
    return "
        LEFT JOIN variante_valores vv1 ON vv1.id_variante_valor = {$alias}.variante_valor1_id
        LEFT JOIN variante_tipos vt1 ON vt1.id_variante_tipo = vv1.id_variante_tipo_FK
        LEFT JOIN variante_valores vv2 ON vv2.id_variante_valor = {$alias}.variante_valor2_id
        LEFT JOIN variante_tipos vt2 ON vt2.id_variante_tipo = vv2.id_variante_tipo_FK
    ";
}

function joyeria_sql_select_variantes_stock(): string
{
    return "
        vt1.nombre AS variante_eje1_tipo,
        vv1.valor AS variante_eje1_valor,
        vt1.es_talla AS variante_eje1_es_talla,
        vt1.slug AS variante_eje1_slug,
        vt2.nombre AS variante_eje2_tipo,
        vv2.valor AS variante_eje2_valor,
        vt2.es_talla AS variante_eje2_es_talla,
        vt2.slug AS variante_eje2_slug
    ";
}

/**
 * Resuelve columnas legacy + FK desde IDs de catalogo.
 *
 * @return array{
 *     variante_valor1_id: ?int,
 *     variante_valor2_id: ?int,
 *     variante_talla: ?string,
 *     variante_color: ?string,
 *     variante_tipo: string,
 *     variante_valor: ?string
 * }
 */
function joyeria_resolver_variantes_desde_catalogo(PDO $db, ?int $valor1Id, ?int $valor2Id): array
{
    $valor1Id = ($valor1Id !== null && $valor1Id > 0) ? $valor1Id : null;
    $valor2Id = ($valor2Id !== null && $valor2Id > 0) ? $valor2Id : null;

    if ($valor1Id === null && $valor2Id === null) {
        return [
            'variante_valor1_id' => null,
            'variante_valor2_id' => null,
            'variante_talla' => null,
            'variante_color' => null,
            'variante_tipo' => 'ninguna',
            'variante_valor' => null,
        ];
    }

    $ids = array_values(array_filter([$valor1Id, $valor2Id], static fn (?int $id): bool => $id !== null));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT vv.id_variante_valor, vv.valor, vt.slug, vt.es_talla
         FROM variante_valores vv
         INNER JOIN variante_tipos vt ON vt.id_variante_tipo = vv.id_variante_tipo_FK
         WHERE vv.id_variante_valor IN ({$placeholders}) AND vv.activo = 1 AND vt.activo = 1"
    );
    foreach ($ids as $i => $id) {
        $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(int) $row['id_variante_valor']] = $row;
    }

    if ($valor1Id !== null && !isset($map[$valor1Id])) {
        throw new InvalidArgumentException('El valor de variante (eje 1) no existe en el catalogo.');
    }
    if ($valor2Id !== null && !isset($map[$valor2Id])) {
        throw new InvalidArgumentException('El valor de variante (eje 2) no existe en el catalogo.');
    }

    $talla = null;
    $color = null;
    foreach ([$valor1Id, $valor2Id] as $id) {
        if ($id === null || !isset($map[$id])) {
            continue;
        }
        $row = $map[$id];
        $valor = trim((string) $row['valor']);
        if ((int) ($row['es_talla'] ?? 0) === 1) {
            $talla = $valor;
        } elseif (trim((string) ($row['slug'] ?? '')) === 'color') {
            $color = $valor;
        }
    }

    [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor] = joyeria_normalizar_variantes_stock($talla, $color);

    return [
        'variante_valor1_id' => $valor1Id,
        'variante_valor2_id' => $valor2Id,
        'variante_talla' => $varianteTalla,
        'variante_color' => $varianteColor,
        'variante_tipo' => $varianteTipo,
        'variante_valor' => $varianteValor,
    ];
}

function joyeria_tiene_columnas_variante_catalogo(PDO $db): bool
{
    static $cache = [];
    $key = spl_object_hash($db);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM piezas_stock LIKE 'variante_valor1_id'");
        $cache[$key] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}
