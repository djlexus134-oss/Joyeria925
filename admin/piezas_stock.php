<?php
/**
 * Compatibilidad: redirige al modulo unificado de piezas.
 */
declare(strict_types=1);

$query = isset($_SERVER['QUERY_STRING']) ? trim((string) $_SERVER['QUERY_STRING']) : '';
$params = [];
if ($query !== '') {
    parse_str($query, $params);
}

$accionLegacy = isset($params['accion']) ? (string) $params['accion'] : 'leer';
unset($params['accion']);

$mapAccion = [
    'leer' => 'stock',
    'crear' => 'stock_crear',
    'actualizar' => 'stock_actualizar',
    'borrar' => 'stock_borrar',
];
$accionNueva = $mapAccion[$accionLegacy] ?? 'stock';

$destino = 'pieza.php?accion=' . rawurlencode($accionNueva);
if ($params !== []) {
    $destino .= '&' . http_build_query($params);
}

header('Location: ' . $destino, true, 302);
exit;
