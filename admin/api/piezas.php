<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/pieza.php';

header('Content-Type: application/json; charset=utf-8');

$app = new Pieza();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $pieza = $app->leerUno($id);
                if (!$pieza) {
                    api_fail('Pieza no encontrada.', 404);
                }
                $pieza['imagenes'] = $app->leerImagenes($id);
                api_ok(['data' => $pieza]);
            }

            api_ok(['data' => $app->leer()]);

        case 'POST':
            $data = api_json_body();
            foreach (['desc_pieza', 'id_sub_familia_FK', 'id_metal_FK', 'id_tienda_FK'] as $campo) {
                if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
                    api_fail('Falta el campo requerido: ' . $campo, 422);
                }
            }
            $metodoApi = isset($data['metodo_costo']) ? (string) $data['metodo_costo'] : 'directo';
            if ($metodoApi === 'por_gramo') {
                foreach (['peso_gr', 'precio_por_gramo'] as $campo) {
                    if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
                        api_fail('Falta el campo requerido en metodo por_gramo: ' . $campo, 422);
                    }
                }
            } else {
                if (!isset($data['costo']) || trim((string) $data['costo']) === '') {
                    api_fail('Falta el campo requerido: costo', 422);
                }
            }

            $idPieza = $app->crear($data, null);
            api_ok(['message' => 'Pieza creada correctamente', 'id_pieza' => $idPieza], 201);

        case 'PUT':
            if (!$id) {
                api_fail('Debes indicar el id de la pieza.', 422);
            }
            $data = api_json_body();
            $app->actualizar($id, $data, null);
            api_ok(['message' => 'Pieza actualizada correctamente']);

        case 'DELETE':
            if (!$id) {
                api_fail('Debes indicar el id de la pieza.', 422);
            }
            $auth = auth_user();
            $idUsuarioBaja = $auth['id_usuario'] ?? null;
            $cantidad = $app->borrar($id, $idUsuarioBaja !== null ? (int) $idUsuarioBaja : null);
            if (!$cantidad) {
                api_fail('No se pudo eliminar la pieza.', 404);
            }
            api_ok(['message' => 'Pieza dada de baja correctamente']);

        default:
            api_fail('Metodo no soportado.', 405);
    }
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
