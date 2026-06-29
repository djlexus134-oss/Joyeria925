<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/gastos.php';

header('Content-Type: application/json; charset=utf-8');

$app = new Gastos();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            if (!auth_has_permission('GASTO_LEER')) {
                api_fail('No tienes permiso para ver gastos.', 403);
            }
            if ($id) {
                $gasto = $app->leerUno($id);
                if (!$gasto) {
                    api_fail('Gasto no encontrado.', 404);
                }
                api_ok(['data' => $gasto]);
            }

            api_ok(['data' => $app->leer()]);
            break;

        case 'POST':
            if (!auth_has_permission('GASTO_CREAR')) {
                api_fail('No tienes permiso para crear gastos.', 403);
            }
            $data = api_json_body();
            foreach (['id_categoria_FK', 'concepto', 'monto', 'fecha_gasto', 'id_empleado_FK'] as $campo) {
                if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
                    api_fail('Falta el campo requerido: ' . $campo, 422);
                }
            }

            $idGasto = $app->crear($data);
            api_ok(['message' => 'Gasto creado correctamente', 'id_gasto' => $idGasto], 201);
            break;

        case 'PUT':
            if (!auth_has_permission('GASTO_ACTUALIZAR')) {
                api_fail('No tienes permiso para actualizar gastos.', 403);
            }
            if (!$id) {
                api_fail('Debes indicar el id del gasto.', 422);
            }
            $data = api_json_body();
            $cantidad = $app->actualizar($id, $data);
            api_ok(['message' => $cantidad ? 'Gasto actualizado correctamente' : 'No se realizaron cambios']);
            break;

        case 'DELETE':
            if (!auth_has_permission('GASTO_BORRAR')) {
                api_fail('No tienes permiso para eliminar gastos.', 403);
            }
            if (!$id) {
                api_fail('Debes indicar el id del gasto.', 422);
            }
            $cantidad = $app->borrar($id);
            if (!$cantidad) {
                api_fail('No se pudo eliminar el gasto.', 404);
            }
            api_ok(['message' => 'Gasto eliminado correctamente']);
            break;

        default:
            api_fail('Metodo no soportado.', 405);
    }
} catch (Throwable $e) {
    error_log('gastos.php: ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
