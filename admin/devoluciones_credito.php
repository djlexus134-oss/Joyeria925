<?php
/**
 * Pagina deprecada. La pantalla "Devolucion con credito" se unifico con
 * "Devoluciones mostrador" en admin/devoluciones.php, donde el modo "Credito al monedero"
 * cubre el flujo de credito reusable para el cliente.
 */
require_once __DIR__ . '/../sistema.class.php';

$qs = [];
if (isset($_GET['accion'])) { $qs['accion'] = (string) $_GET['accion']; } else { $qs['accion'] = 'leer'; }
if (isset($_GET['cliente'])) { $qs['cliente'] = (string) $_GET['cliente']; }
if (isset($_GET['id_cliente_FK']) && !isset($qs['cliente'])) { $qs['cliente'] = (string) $_GET['id_cliente_FK']; }

$destino = 'devoluciones.php?' . http_build_query($qs);

header('Location: ' . $destino, true, 302);
exit;
