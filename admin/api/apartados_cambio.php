<?php
/**
 * Endpoint deprecado.
 *
 * El flujo de "cambio de pieza" se reemplazo por dos operaciones atomicas en
 * admin/api/apartados_gestion.php:
 *   - POST { tipo: "quitar_pieza", id_apartado_FK, id_apartado_detalle }
 *   - POST { tipo: "agregar_pieza", id_apartado_FK, codigo_pieza | id_pieza_stock_FK }
 *
 * Si lo abonado supera el nuevo total tras quitar piezas, el apartado se
 * auto-liquida y el excedente queda como credito en cliente_creditos.
 */

require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

api_fail(
    'Endpoint deprecado. Usa apartados_gestion.php con tipo=quitar_pieza o tipo=agregar_pieza.',
    410,
    [
        'reemplazo' => 'admin/api/apartados_gestion.php',
        'tipos' => ['quitar_pieza', 'agregar_pieza'],
    ]
);
