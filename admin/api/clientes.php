<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../models/cliente.php';
require_once __DIR__ . '/../includes/cliente_correo.php';
require_once __DIR__ . '/../includes/cliente_select.php';
require_once __DIR__ . '/../includes/WhatsAppService.php';

header('Content-Type: application/json; charset=utf-8');

$app = new Cliente();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $cliente = $app->leerUno($id);
                if (!$cliente) {
                    api_fail('Cliente no encontrado.', 404);
                }
                api_ok(['data' => $cliente]);
            }

            api_ok(['data' => $app->leer()]);

        case 'POST':
            if (!auth_has_permission('CLIENTE_CREAR')) {
                api_fail('No tienes permiso para crear clientes.', 403);
            }
            $data = api_json_body();
            foreach (['nombre', 'primer_apellido', 'telefono'] as $campo) {
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

            $idCliente = $app->crear($data);
            $contrasenaPlano = $app->ultimaContrasenaPlanoAlta() ?? '';
            $clienteNuevo = $app->leerUno($idCliente);

            $resultMail = ['success' => true, 'skipped' => true];
            if (is_array($clienteNuevo) && $contrasenaPlano !== '') {
                $resultMail = joyeria_cliente_enviar_credenciales_mail(
                    joyeria_cliente_datos_para_correo($clienteNuevo),
                    $contrasenaPlano,
                    false
                );
            }

            $payload = [
                'message' => 'Cliente creado correctamente',
                'id_cliente' => $idCliente,
            ];
            if (is_array($clienteNuevo)) {
                $optionMeta = joyeria_cliente_option_meta($clienteNuevo);
                $payload['option_label'] = $optionMeta['label'];
                $payload['option_search'] = $optionMeta['search'];
                if (array_key_exists('descuento_porcentaje', $clienteNuevo)
                    && $clienteNuevo['descuento_porcentaje'] !== null
                    && $clienteNuevo['descuento_porcentaje'] !== '') {
                    $payload['descuento_porcentaje'] = number_format(
                        (float) $clienteNuevo['descuento_porcentaje'],
                        2,
                        '.',
                        ''
                    );
                }
            }
            $payload = joyeria_cliente_adjuntar_estado_correo_respuesta($payload, $resultMail);

            if (is_array($clienteNuevo)) {
                try {
                    $resultWa = WhatsAppService::enviarBienvenidaCliente(
                        (string) ($clienteNuevo['telefono'] ?? ''),
                        trim((string) ($clienteNuevo['nombre'] ?? '') . ' ' . (string) ($clienteNuevo['primer_apellido'] ?? ''))
                    );
                    $payload['whatsapp_enviado'] = !empty($resultWa['success']) && empty($resultWa['skipped']);
                    if (!empty($resultWa['skipped'])) {
                        $payload['whatsapp_omitido'] = true;
                    } elseif (empty($resultWa['success']) && isset($resultWa['message'])) {
                        $payload['whatsapp_mensaje'] = $resultWa['message'];
                    }
                } catch (Throwable $e) {
                    error_log('clientes.php WhatsApp: ' . $e->getMessage());
                }
            }

            api_ok($payload, 201);

        case 'PUT':
            if (!auth_has_permission('CLIENTE_ACTUALIZAR')) {
                api_fail('No tienes permiso para actualizar clientes.', 403);
            }
            if (!$id) {
                api_fail('Debes indicar el id del cliente.', 422);
            }
            $data = api_json_body();
            $clienteAntes = $app->leerUno($id);
            if (!$clienteAntes) {
                api_fail('Cliente no encontrado.', 404);
            }

            $contrasenaCapturada = isset($data['contrasena']) && trim((string) $data['contrasena']) !== '';
            $contrasenaPlano = joyeria_cliente_resolver_contrasena_para_correo($clienteAntes, $data);
            $contrasenaGenerada = $contrasenaPlano !== null
                && !$contrasenaCapturada
                && joyeria_cliente_correo_cambio($clienteAntes, $data);

            $app->actualizar($id, $data);

            $payload = ['message' => 'Cliente actualizado correctamente'];
            if ($contrasenaPlano !== null) {
                $clienteDespues = $app->leerUno($id);
                $datosCorreo = is_array($clienteDespues)
                    ? joyeria_cliente_datos_para_correo($clienteDespues)
                    : joyeria_cliente_datos_para_correo($data);

                $resultMail = joyeria_cliente_enviar_credenciales_mail(
                    $datosCorreo,
                    $contrasenaPlano,
                    true
                );
                $payload = joyeria_cliente_adjuntar_estado_correo_respuesta(
                    $payload,
                    $resultMail,
                    $contrasenaGenerada
                );
            }

            api_ok($payload);

        case 'DELETE':
            if (!auth_has_permission('CLIENTE_BORRAR')) {
                api_fail('No tienes permiso para eliminar clientes.', 403);
            }
            if (!$id) {
                api_fail('Debes indicar el id del cliente.', 422);
            }
            $idUsuarioBaja = null;
            $auth = auth_user();
            if ($auth !== null && isset($auth['id_usuario'])) {
                $idUsuarioBaja = (int) $auth['id_usuario'];
            }
            $cantidad = $app->borrar($id, $idUsuarioBaja);
            if (!$cantidad) {
                api_fail('No se pudo eliminar el cliente.', 404);
            }
            api_ok(['message' => 'Cliente dado de baja correctamente']);

        default:
            api_fail('Metodo no soportado.', 405);
    }
} catch (Throwable $e) {
    error_log('clientes.php: ' . $e->getMessage());
    api_fail('Error interno del servidor.', 500);
}
