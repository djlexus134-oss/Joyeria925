<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/variantes.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$accion = trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? ''));

if ($accion === 'listar') {
    if (!auth_can_module_action('variantes', 'LEER')) {
        api_fail('No tienes permiso para consultar variantes.', 403);
    }
} elseif (!auth_can_module_action('variantes', 'CREAR')) {
    api_fail('No tienes permiso para crear variantes.', 403);
}

try {
    $app = new Variantes();
    if (!$app->tieneTablas()) {
        api_fail('El catalogo de variantes no esta disponible. Ejecuta la migracion SQL.', 503);
    }

    if ($accion === 'listar') {
        api_ok($app->listarCatalogoParaSelect());
    }

    if ($accion === 'crear_tipo') {
        api_require_method('POST');
        $data = api_json_body();
        $nombre = api_string($data, 'nombre', true);
        if (strlen($nombre) > 50) {
            api_fail('El nombre no puede exceder 50 caracteres.', 422);
        }
        $payload = [
            'nombre' => $nombre,
            'slug' => api_string($data, 'slug', false) ?? $nombre,
            'es_talla' => !empty($data['es_talla']) ? 1 : 0,
        ];
        $id = $app->crearTipo($payload);
        $tipo = $app->leerUnoTipo($id);
        api_ok([
            'message' => 'Tipo creado correctamente',
            'tipo' => $tipo,
            'valores' => [],
        ], 201);
    }

    if ($accion === 'crear_valor') {
        api_require_method('POST');
        $data = api_json_body();
        $idTipo = api_int($data, 'id_variante_tipo', true);
        $valor = api_string($data, 'valor', true);
        if (strlen($valor) > 40) {
            api_fail('El valor no puede exceder 40 caracteres.', 422);
        }
        $id = $app->crearValor([
            'id_variante_tipo_FK' => $idTipo,
            'valor' => $valor,
        ]);
        $row = $app->leerUnoValor($id);
        api_ok([
            'message' => 'Valor creado correctamente',
            'valor' => $row,
        ], 201);
    }

    api_fail('Accion no valida.', 400);
} catch (InvalidArgumentException $e) {
    api_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
