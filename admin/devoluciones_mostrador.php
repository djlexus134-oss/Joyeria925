<?php
/**
 * Pagina deprecada. La pantalla "Devoluciones mostrador" se unifico con
 * "Devolucion con credito" en admin/devoluciones.php, con un selector de modo
 * (efectivo, otra forma, monedero, solo inventario).
 */
require_once __DIR__ . '/../sistema.class.php';

$qs = [];
if (isset($_GET['accion'])) { $qs['accion'] = (string) $_GET['accion']; } else { $qs['accion'] = 'leer'; }
if (isset($_GET['cliente'])) { $qs['cliente'] = (string) $_GET['cliente']; }

$destino = 'devoluciones.php?' . http_build_query($qs);

header('Location: ' . $destino, true, 302);
exit;
