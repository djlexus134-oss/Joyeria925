<?php

declare(strict_types=1);

require_once __DIR__ . '/cliente_correo.php';

/**
 * Texto visible en selects de cliente (autocompletado).
 */
function joyeria_cliente_option_label(array $cli): string
{
    $nombre = trim((string) ($cli['nombre_completo'] ?? ''));
    if ($nombre === '') {
        $nombre = trim(implode(' ', array_filter([
            trim((string) ($cli['nombre'] ?? '')),
            trim((string) ($cli['primer_apellido'] ?? '')),
            trim((string) ($cli['segundo_apellido'] ?? '')),
        ])));
    }

    $telefono = trim((string) ($cli['telefono'] ?? ''));
    if ($telefono !== '') {
        return $nombre . ' — ' . $telefono;
    }

    $correo = trim((string) ($cli['correo'] ?? ''));
    if ($correo !== '' && joyeria_cliente_correo_es_entregable($correo)) {
        return $nombre . ' — ' . $correo;
    }

    return $nombre;
}

/**
 * Cadena para data-search (filtro client-side en fk-autocomplete).
 */
function joyeria_cliente_option_search_haystack(array $cli): string
{
    $parts = [
        trim((string) ($cli['nombre'] ?? '')),
        trim((string) ($cli['primer_apellido'] ?? '')),
        trim((string) ($cli['segundo_apellido'] ?? '')),
        trim((string) ($cli['nombre_completo'] ?? '')),
    ];

    $telefono = trim((string) ($cli['telefono'] ?? ''));
    if ($telefono !== '') {
        $parts[] = $telefono;
        $digits = preg_replace('/[^0-9+]/', '', $telefono) ?? '';
        if ($digits !== '' && $digits !== $telefono) {
            $parts[] = $digits;
        }
    }

    $correo = trim((string) ($cli['correo'] ?? ''));
    if ($correo !== '' && joyeria_cliente_correo_es_entregable($correo)) {
        $parts[] = joyeria_cliente_correo_normalizado($correo);
    }

    $haystack = [];
    foreach ($parts as $p) {
        $p = mb_strtolower(trim($p));
        if ($p !== '') {
            $haystack[] = $p;
        }
    }

    return implode(' ', array_unique($haystack));
}

/**
 * Construye fila de cliente para option dinamico (API / JS).
 *
 * @return array{label: string, search: string}
 */
function joyeria_cliente_option_meta(array $cli): array
{
    return [
        'label' => joyeria_cliente_option_label($cli),
        'search' => joyeria_cliente_option_search_haystack($cli),
    ];
}
