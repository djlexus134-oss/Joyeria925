<?php

declare(strict_types=1);

/**
 * Normaliza el término de búsqueda de listados (GET q).
 */
function joyeria_list_search_normalize(?string $q): string
{
    $s = trim((string) $q);
    if ($s !== '' && mb_strlen($s) > 120) {
        $s = mb_substr($s, 0, 120);
    }

    return $s;
}

/**
 * Indica si hay filtro de búsqueda activo (después de normalizar).
 */
function joyeria_list_search_active(?string $q): bool
{
    return joyeria_list_search_normalize($q ?? '') !== '';
}

/**
 * Escapa % _ \ para usar el término dentro de un patrón LIKE.
 */
function joyeria_like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

/**
 * Patrón enlazable para LIKE '%...%' o null si no hay búsqueda.
 */
function joyeria_like_pattern(?string $q): ?string
{
    $n = joyeria_list_search_normalize($q ?? '');
    if ($n === '') {
        return null;
    }

    return '%' . joyeria_like_escape($n) . '%';
}

/**
 * Expresión SQL de nombre completo (nombre + apellidos; omite NULL).
 * Solo para alias de tabla/usuario ya fijo en el código (no input de usuario).
 */
function joyeria_sql_nombre_completo(string $alias = 'u'): string
{
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'u';

    return "TRIM(CONCAT_WS(' ', {$a}.nombre, {$a}.primer_apellido, {$a}.segundo_apellido))";
}

/**
 * Filtra filas ya cargadas (p. ej. resultados de CALL) por coincidencia en columnas.
 *
 * @param array<int, array<string, mixed>> $rows
 * @param string[] $keys
 * @return array<int, array<string, mixed>>
 */
function joyeria_filter_rows_by_search(array $rows, ?string $q, array $keys): array
{
    $n = joyeria_list_search_normalize($q ?? '');
    if ($n === '') {
        return $rows;
    }

    return array_values(array_filter($rows, static function (array $row) use ($n, $keys): bool {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = $row[$key];
            if ($v === null || $v === '') {
                continue;
            }
            if (mb_stripos((string) $v, $n, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }));
}
