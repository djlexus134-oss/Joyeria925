<?php
/**
 * Compatibilidad: redirige al modulo unificado de proveedores.
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
    'leer' => 'contactos',
    'crear' => 'contacto_crear',
    'actualizar' => 'contacto_actualizar',
    'borrar' => 'contacto_borrar',
];
$accionNueva = $mapAccion[$accionLegacy] ?? 'contactos';

$destino = 'proveedores.php?accion=' . rawurlencode($accionNueva);
if ($params !== []) {
    $destino .= '&' . http_build_query($params);
}

header('Location: ' . $destino, true, 302);
exit;
