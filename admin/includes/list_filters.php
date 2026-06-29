<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/joyeria_timezone.php';

/**
 * Fecha Y-m-d valida o null.
 */
function joyeria_parse_date_ymd(?string $value): ?string
{
    $v = trim((string) $value);
    if ($v === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $v);
    if (!$dt || $dt->format('Y-m-d') !== $v) {
        return null;
    }

    return $v;
}

/**
 * Filtro fecha en GET: si la clave no viene en la peticion, usa $defaultYmd; si viene vacia, null.
 */
function joyeria_filtro_fecha_ymd_desde_get(array $get, string $key, string $defaultYmd): ?string
{
    if (!array_key_exists($key, $get)) {
        return $defaultYmd;
    }

    return joyeria_parse_date_ymd(isset($get[$key]) ? (string) $get[$key] : null);
}

/**
 * @return array<string, mixed>
 */
function joyeria_ventas_filtros_desde_get(array $get): array
{
    $hoy = joyeria_today_ymd();
    $fechaDesde = joyeria_filtro_fecha_ymd_desde_get($get, 'fecha_desde', $hoy);
    $fechaHasta = joyeria_filtro_fecha_ymd_desde_get($get, 'fecha_hasta', $hoy);
    if ($fechaDesde !== null && $fechaHasta !== null && $fechaDesde > $fechaHasta) {
        [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
    }

    $idCliente = null;
    if (isset($get['id_cliente']) && (string) $get['id_cliente'] !== '') {
        $idCliente = max(0, (int) $get['id_cliente']);
    }

    $idEmpleado = null;
    if (isset($get['id_empleado']) && (string) $get['id_empleado'] !== '') {
        $tmp = (int) $get['id_empleado'];
        if ($tmp > 0) {
            $idEmpleado = $tmp;
        }
    }

    $estadosPermitidos = ['completada', 'cancelada', 'devuelta'];
    $estado = null;
    if (isset($get['estado']) && in_array((string) $get['estado'], $estadosPermitidos, true)) {
        $estado = (string) $get['estado'];
    }

    $origen = null;
    if (isset($get['origen'])) {
        $o = (string) $get['origen'];
        if ($o === 'liquidacion') {
            $origen = 'liquidacion';
        } elseif ($o === 'directa') {
            $origen = 'directa';
        }
    }

    return [
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
        'id_cliente' => $idCliente,
        'id_empleado' => $idEmpleado,
        'estado' => $estado,
        'origen' => $origen,
    ];
}

function joyeria_ventas_filtros_activos(array $filtros): bool
{
    foreach ($filtros as $v) {
        if ($v !== null && $v !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Parametros GET no vacios para enlaces y formularios.
 *
 * @return array<string, string|int>
 */
function joyeria_ventas_filtros_a_query(array $filtros, ?string $busqueda = null): array
{
    $q = [];
    if ($busqueda !== null && trim($busqueda) !== '') {
        $q['q'] = trim($busqueda);
    }
    if (!empty($filtros['fecha_desde'])) {
        $q['fecha_desde'] = (string) $filtros['fecha_desde'];
    }
    if (!empty($filtros['fecha_hasta'])) {
        $q['fecha_hasta'] = (string) $filtros['fecha_hasta'];
    }
    if (array_key_exists('id_cliente', $filtros) && $filtros['id_cliente'] !== null) {
        $q['id_cliente'] = (int) $filtros['id_cliente'];
    }
    if (!empty($filtros['id_empleado'])) {
        $q['id_empleado'] = (int) $filtros['id_empleado'];
    }
    if (!empty($filtros['estado'])) {
        $q['estado'] = (string) $filtros['estado'];
    }
    if (!empty($filtros['origen'])) {
        $q['origen'] = (string) $filtros['origen'];
    }

    return $q;
}
