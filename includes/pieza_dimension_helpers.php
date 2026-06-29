<?php
declare(strict_types=1);

/**
 * Extrae la cantidad numérica de un valor de dimensión (entrada o almacenado).
 */
function joyeria_extraer_cantidad_dimension(?string $valor): string
{
    if ($valor === null) {
        return '';
    }
    $v = trim($valor);
    if ($v === '') {
        return '';
    }

    $v = preg_replace('/^(largo|ancho|alto)\s*:\s*/iu', '', $v) ?? $v;
    $v = trim($v);
    $v = preg_replace('/\s*cm\s*$/iu', '', $v) ?? $v;
    $v = trim($v);

    if (preg_match('/^([\d]+(?:[.,]\d+)?)/u', $v, $m)) {
        return str_replace(',', '.', $m[1]);
    }

    return $v;
}

/**
 * Formato persistido: "Ancho: 50 cm".
 */
function joyeria_formatear_dimension_pieza(string $etiqueta, ?string $cantidad): ?string
{
    $cant = joyeria_extraer_cantidad_dimension($cantidad);
    if ($cant === '') {
        return null;
    }

    $num = (float) str_replace(',', '.', $cant);
    if ($num <= 0) {
        return null;
    }

    $display = fmod($num, 1.0) === 0.0
        ? (string) (int) round($num)
        : rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');

    return trim($etiqueta) . ': ' . $display . ' cm';
}

function joyeria_normalizar_dimension_pieza(string $etiqueta, ?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    return joyeria_formatear_dimension_pieza($etiqueta, $raw);
}

/**
 * Texto para mostrar en UI (solo cantidad + unidad), p. ej. "60 cm".
 */
function joyeria_valor_dimension_con_unidad(string $etiqueta, ?string $valor): ?string
{
    $formatted = joyeria_formatear_dimension_pieza($etiqueta, $valor);
    if ($formatted === null) {
        return null;
    }

    $prefix = trim($etiqueta) . ': ';
    if (str_starts_with($formatted, $prefix)) {
        return substr($formatted, strlen($prefix));
    }

    return $formatted;
}
