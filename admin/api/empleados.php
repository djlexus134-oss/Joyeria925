<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/empleado.php';
require_once __DIR__ . '/../includes/WhatsAppService.php';

header('Content-Type: application/json; charset=utf-8');

$app = new Empleado();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            if (!auth_has_permission('EMPLEADO_LEER')) {
                api_fail('No tienes permiso para ver empleados.', 403);
            }
            if ($id) {
                $empleado = $app->leerUno($id);
                if (!$empleado) {
                    api_fail('Empleado no encontrado.', 404);
                }
                api_ok(['data' => $empleado]);
            }

            api_ok(['data' => $app->leer()]);
            break;

        case 'POST':
            if (!auth_has_permission('EMPLEADO_CREAR')) {
                api_fail('No tienes permiso para crear empleados.', 403);
            }
            $data = api_json_body();
            foreach (['nombre', 'primer_apellido', 'correo', 'telefono', 'contrasena', 'id_puesto_FK', 'salario', 'curp', 'rfc'] as $campo) {
                if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
                    api_fail('Falta el campo requerido: ' . $campo, 422);
                }
            }
            $incluirDir = isset($data['incluir_direccion']) && (string) $data['incluir_direccion'] === '1';
            if ($incluirDir) {
                foreach (['num_exterior', 'id_calle_FK'] as $campo) {
                    if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
                        api_fail('Falta el campo requerido con incluir_direccion=1: ' . $campo, 422);
                    }
                }
            }

            $telefonoEmpleado = trim((string) ($data['telefono'] ?? ''));
            $nombreEmpleado = trim((string) ($data['nombre'] ?? '') . ' ' . (string) ($data['primer_apellido'] ?? ''));

            $data['contrasena'] = api_bcrypt((string) $data['contrasena']);
            $idEmpleado = $app->crear($data);

            $payload = ['message' => 'Empleado creado correctamente', 'id_empleado' => $idEmpleado];
            try {
                $resultWa = WhatsAppService::enviarBienvenidaEmpleado($telefonoEmpleado, $nombreEmpleado);
                $payload['whatsapp_enviado'] = !empty($resultWa['success']) && empty($resultWa['skipped']);
                if (!empty($resultWa['skipped'])) {
                    $payload['whatsapp_omitido'] = true;
                } elseif (empty($resultWa['success']) && isset($resultWa['message'])) {
                    $payload['whatsapp_mensaje'] = $resultWa['message'];
                }
            } catch (Throwable $e) {
                error_log('empleados.php WhatsApp: ' . $e->getMessage());
            }

            api_ok($payload, 201);

        case 'PUT':
            if (!auth_has_permission('EMPLEADO_ACTUALIZAR')) {
                api_fail('No tienes permiso para actualizar empleados.', 403);
            }
            if (!$id) {
                api_fail('Debes indicar el id del empleado.', 422);
            }
            $data = api_json_body();
            if (isset($data['contrasena']) && trim((string) $data['contrasena']) !== '') {
                $data['contrasena'] = api_bcrypt((string) $data['contrasena']);
            } else {
                unset($data['contrasena']);
            }
            $app->actualizar($id, $data);
            api_ok(['message' => 'Empleado actualizado correctamente']);

        case 'DELETE':
            if (!auth_has_permission('EMPLEADO_BORRAR')) {
                api_fail('No tienes permiso para eliminar empleados.', 403);
            }
            if (!$id) {
                api_fail('Debes indicar el id del empleado.', 422);
            }
            $auth = auth_user();
            $idUsuarioBaja = $auth['id_usuario'] ?? null;
            $cantidad = $app->borrar($id, $idUsuarioBaja !== null ? (int) $idUsuarioBaja : null);
            if (!$cantidad) {
                api_fail('No se pudo eliminar el empleado.', 404);
            }
            api_ok(['message' => 'Empleado dado de baja correctamente']);

        default:
            api_fail('Metodo no soportado.', 405);
    }
} catch (Throwable $e) {
    error_log('empleados.php: ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
