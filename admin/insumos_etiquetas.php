<?php
/**
 * Compatibilidad: redirige al modulo unificado de insumos.
 */
declare(strict_types=1);

$query = isset($_SERVER['QUERY_STRING']) ? trim((string) $_SERVER['QUERY_STRING']) : '';
$params = [];
if ($query !== '') {
    parse_str($query, $params);
}
unset($params['accion']);
$destino = 'insumos.php?accion=etiquetas';
if ($params !== []) {
    $destino .= '&' . http_build_query($params);
}
header('Location: ' . $destino, true, 302);
exit;
