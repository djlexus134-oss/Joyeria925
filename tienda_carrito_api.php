<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/joyeria_json_guard.php';
joyeria_json_guard_begin();
require_once __DIR__ . '/admin/includes/tienda_auth.php';
require_once __DIR__ . '/admin/models/carrito.php';
require_once __DIR__ . '/includes/joyeria_imagen_publica.php';

header('Content-Type: application/json; charset=utf-8');

function joyeria_tca_out(array $data): void
{
    joyeria_json_clean_buffer();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($body)) $body = [];
$action = (string) ($body['action'] ?? $_GET['action'] ?? '');

if ($action === '') {
    joyeria_tca_out(['ok' => false, 'error' => 'Accion requerida.']);
}

// Para 'contar' y 'listar' permitimos sin login (devolviendo 0).
if (!tienda_is_logged_in()) {
    if ($action === 'contar') joyeria_tca_out(['ok' => true, 'count' => 0]);
    if ($action === 'listar') joyeria_tca_out(['ok' => true, 'items' => [], 'resumen' => ['total' => 0, 'tiendas' => [], 'multi_tienda' => false, 'items_count' => 0]]);
    joyeria_tca_out(['ok' => false, 'login_required' => true, 'error' => 'Debes iniciar sesion.']);
}

$user = tienda_auth_user();
$idCliente = (int) ($user['id_cliente'] ?? 0);
$carrito = new Carrito();

try {
    if ($action === 'contar') {
        joyeria_tca_out(['ok' => true, 'count' => $carrito->contar($idCliente)]);
    }

    if ($action === 'listar') {
        $items = $carrito->listar($idCliente);
        $resumen = $carrito->calcularResumen($items);
        $itemsOut = array_map(function ($it) {
            $url = joyeria_resolver_url_imagen((string) ($it['url_imagen'] ?? ''));
            $it['url_imagen_publica'] = $url;
            return $it;
        }, $items);
        joyeria_tca_out(['ok' => true, 'items' => $itemsOut, 'resumen' => $resumen]);
    }

    if ($action === 'agregar') {
        $idPieza = (int) ($body['id_pieza'] ?? 0);
        if ($idPieza <= 0) joyeria_tca_out(['ok' => false, 'error' => 'id_pieza requerido.']);
        $varianteValor = isset($body['variante_valor']) ? trim((string) $body['variante_valor']) : null;
        if ($varianteValor === '') {
            $varianteValor = null;
        }
        $varianteColor = isset($body['variante_color']) ? trim((string) $body['variante_color']) : null;
        if ($varianteColor === '') {
            $varianteColor = null;
        }
        $varianteTalla = isset($body['variante_talla']) ? trim((string) $body['variante_talla']) : null;
        if ($varianteTalla === '') {
            $varianteTalla = null;
        }
        $varianteValor1Id = isset($body['variante_valor1_id']) ? (int) $body['variante_valor1_id'] : null;
        if ($varianteValor1Id <= 0) {
            $varianteValor1Id = null;
        }
        $varianteValor2Id = isset($body['variante_valor2_id']) ? (int) $body['variante_valor2_id'] : null;
        if ($varianteValor2Id <= 0) {
            $varianteValor2Id = null;
        }
        $varianteEje1 = isset($body['variante_eje1']) ? trim((string) $body['variante_eje1']) : null;
        if ($varianteEje1 === '') {
            $varianteEje1 = null;
        }
        $varianteEje2 = isset($body['variante_eje2']) ? trim((string) $body['variante_eje2']) : null;
        if ($varianteEje2 === '') {
            $varianteEje2 = null;
        }
        $r = $carrito->agregar(
            $idCliente,
            $idPieza,
            $varianteValor,
            $varianteColor,
            $varianteTalla,
            $varianteValor1Id,
            $varianteValor2Id,
            $varianteEje1,
            $varianteEje2
        );
        if (!$r['ok']) joyeria_tca_out(['ok' => false, 'error' => $r['error'] ?? 'No se pudo agregar.']);
        joyeria_tca_out(['ok' => true, 'count' => $carrito->contar($idCliente)]);
    }

    if ($action === 'eliminar') {
        $idItem = (int) ($body['id_carrito_item'] ?? 0);
        if ($idItem <= 0) joyeria_tca_out(['ok' => false, 'error' => 'id_carrito_item requerido.']);
        $r = $carrito->eliminar($idCliente, $idItem);
        if (!$r['ok']) joyeria_tca_out(['ok' => false, 'error' => $r['error'] ?? 'No se pudo eliminar.']);
        joyeria_tca_out(['ok' => true, 'count' => $carrito->contar($idCliente)]);
    }

    if ($action === 'vaciar') {
        $carrito->vaciar($idCliente);
        joyeria_tca_out(['ok' => true, 'count' => 0]);
    }

    joyeria_tca_out(['ok' => false, 'error' => 'Accion no soportada.']);
} catch (Throwable $e) {
    error_log('tienda_carrito_api: ' . $e->getMessage());
    http_response_code(500);
    joyeria_tca_out(['ok' => false, 'error' => 'Error interno']);
}
