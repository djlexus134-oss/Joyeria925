<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/includes/tienda_auth.php';
require_once __DIR__ . '/admin/models/pieza.php';
require_once __DIR__ . '/includes/joyeria_imagen_publica.php';
require_once __DIR__ . '/includes/promociones_tienda_publica.php';
require_once __DIR__ . '/includes/pieza_dimension_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function joyeria_tpa_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$idPieza = isset($_GET['id_pieza']) ? (int) $_GET['id_pieza'] : 0;
if ($idPieza <= 0) {
    joyeria_tpa_out(['ok' => false, 'error' => 'id_pieza invalido']);
}

try {
    $piezaModel = new Pieza();
    $pieza = $piezaModel->leerUno($idPieza);
    if (!$pieza) {
        joyeria_tpa_out(['ok' => false, 'error' => 'Pieza no encontrada']);
    }

    $sistema = new Sistema();
    $db = $sistema->getDb();

    // Subfamilia + familia
    $stmtCat = $db->prepare(
        "SELECT sf.nom_sub_familia, f.id_familia, f.nom_familia, m.nom_metal, t.id_tienda, t.nom_tienda
         FROM piezas p
         INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
         INNER JOIN familias f ON f.id_familia = sf.id_familia_FK
         INNER JOIN metales m ON m.id_metal = p.id_metal_FK
         INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
         WHERE p.id_pieza = :id LIMIT 1"
    );
    $stmtCat->bindValue(':id', $idPieza, PDO::PARAM_INT);
    $stmtCat->execute();
    $cat = $stmtCat->fetch(PDO::FETCH_ASSOC) ?: [];

    // Imagenes
    $imagenesRaw = $piezaModel->leerImagenes($idPieza);
    $imagenes = [];
    foreach ($imagenesRaw as $img) {
        $url = joyeria_resolver_url_imagen((string) ($img['url_imagen'] ?? ''));
        if ($url !== null) {
            $imagenes[] = [
                'url' => $url,
                'es_principal' => (int) ($img['es_principal'] ?? 0) === 1,
            ];
        }
    }

    $stockDisponible = $piezaModel->contarStockDisponible($idPieza);
    $comprableOnline = Pieza::esComprableOnlinePorStock($stockDisponible);
    $variantesResumen = $piezaModel->resumenVariantesDisponibles($idPieza);

    $piezaPrecio = array_merge($pieza, [
        'id_sub_familia' => (int) ($pieza['id_sub_familia_FK'] ?? 0),
        'id_familia' => (int) ($cat['id_familia'] ?? 0),
    ]);

    $ctxPrecios = joyeria_cargar_contexto_precios_cliente();
    $audiencia = tienda_is_logged_in() ? 'cliente' : 'visitante';
    $variantesResumen = joyeria_enriquecer_variantes_resumen_precios(
        $variantesResumen,
        $piezaPrecio,
        $audiencia,
        $ctxPrecios['id_cliente'],
        $ctxPrecios['subtotal_lista_carrito']
    );

    $precioInfo = joyeria_precio_tienda_publica(
        $piezaPrecio,
        $audiencia,
        $ctxPrecios['id_cliente'],
        $ctxPrecios['subtotal_lista_carrito']
    );
    $precioLista = (float) ($precioInfo['precio_lista'] ?? 0);
    $precio = (float) ($precioInfo['precio_final'] ?? $precioLista);
    $precioDesde = !empty($precioInfo['precio_desde']);
    $prefijoPrecio = $precioDesde ? 'Desde ' : '';
    $tienePromo = !empty($precioInfo['tiene_promocion']);
    $promo = is_array($precioInfo['promocion'] ?? null) ? $precioInfo['promocion'] : null;
    $precioDefault = joyeria_compactar_precio_info_api($precioInfo, $precioDesde);

    $nomTienda = (string) ($cat['nom_tienda'] ?? '');
    $legend = 'Entrega exclusivamente en tienda. Tu pieza queda apartada en la sucursal '
        . $nomTienda . ' y la podras recoger con identificacion oficial y tu numero de orden.';

    joyeria_tpa_out([
        'ok' => true,
        'pieza' => [
            'id_pieza' => $idPieza,
            'desc_pieza' => (string) ($pieza['desc_pieza'] ?? ''),
            'peso_gr' => $pieza['peso_gr'] !== null ? (string) $pieza['peso_gr'] : null,
            'largo' => (string) ($pieza['largo'] ?? ''),
            'ancho' => (string) ($pieza['ancho'] ?? ''),
            'alto_cm' => joyeria_valor_dimension_con_unidad('Alto', isset($pieza['largo']) ? (string) $pieza['largo'] : null),
            'ancho_cm' => joyeria_valor_dimension_con_unidad('Ancho', isset($pieza['ancho']) ? (string) $pieza['ancho'] : null),
            'observaciones' => (string) ($pieza['observaciones'] ?? ''),
            'precio' => $precio,
            'precio_lista' => $precioLista,
            'precio_desde' => $precioDesde,
            'precio_formateado' => $prefijoPrecio . '$' . number_format($precio, 2, '.', ',') . ' MXN',
            'precio_lista_formateado' => $prefijoPrecio . '$' . number_format($precioLista, 2, '.', ',') . ' MXN',
            'tiene_promocion' => $tienePromo,
            'porcentaje_descuento' => (float) ($precioInfo['porcentaje'] ?? 0),
            'promocion_nombre' => $promo !== null ? (string) ($promo['nombre'] ?? '') : '',
            'nom_familia' => (string) ($cat['nom_familia'] ?? ''),
            'nom_sub_familia' => (string) ($cat['nom_sub_familia'] ?? ''),
            'nom_metal' => (string) ($cat['nom_metal'] ?? ''),
            'id_tienda' => (int) ($cat['id_tienda'] ?? 0),
            'nom_tienda' => $nomTienda,
            'imagenes' => $imagenes,
            'stock_disponible' => $stockDisponible,
            'comprable_online' => $comprableOnline,
            'variantes' => $variantesResumen,
            'precio_default' => $precioDefault,
            'entrega_en_tienda' => true,
            'leyenda_entrega' => $legend,
        ],
    ]);
} catch (Throwable $e) {
    error_log('tienda_pieza_api: ' . $e->getMessage());
    http_response_code(500);
    joyeria_tpa_out(['ok' => false, 'error' => 'Error interno']);
}
