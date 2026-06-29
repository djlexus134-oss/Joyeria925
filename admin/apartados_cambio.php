<?php
/**
 * Pagina deprecada. El cambio de pieza se reemplazo por las acciones
 * "Quitar pieza" y "Agregar pieza" en el modulo unificado de operaciones.
 */
require_once __DIR__ . '/../sistema.class.php';

$idApartado = isset($_GET['id_apartado']) ? (int) $_GET['id_apartado'] : 0;
$destinoQs = $idApartado > 0 ? ('&id_apartado=' . $idApartado) : '';
$destino = 'apartados_operaciones.php?accion=leer&destino=quitar' . $destinoQs;

header('Location: ' . $destino, true, 302);
exit;
