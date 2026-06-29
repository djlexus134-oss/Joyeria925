<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/PromocionTiendaResolver.php';
require_once __DIR__ . '/../admin/includes/DescuentoTiendaService.php';

/** @var PromocionTiendaResolver|null */
$GLOBALS['joyeria_promocion_tienda_resolver'] = $GLOBALS['joyeria_promocion_tienda_resolver'] ?? null;

function joyeria_promocion_tienda_resolver(): PromocionTiendaResolver
{
    if (!($GLOBALS['joyeria_promocion_tienda_resolver'] instanceof PromocionTiendaResolver)) {
        $GLOBALS['joyeria_promocion_tienda_resolver'] = new PromocionTiendaResolver();
    }

    return $GLOBALS['joyeria_promocion_tienda_resolver'];
}

/**
 * @param callable(array): float $calcularPrecioLista
 * @return array{
 *   precio_lista: float,
 *   precio_final: float,
 *   descuento_monto: float,
 *   porcentaje: float,
 *   tiene_promocion: bool,
 *   promocion: ?array
 * }
 */
function joyeria_precio_catalogo_con_promo(array $pieza, ?callable $calcularPrecioLista = null): array
{
    if ($calcularPrecioLista !== null) {
        $precioLista = (float) $calcularPrecioLista($pieza);
        $resolver = joyeria_promocion_tienda_resolver();
        $idPieza = (int) ($pieza['id_pieza'] ?? 0);
        $idSub = (int) ($pieza['id_sub_familia'] ?? $pieza['id_subfamilia_FK'] ?? 0);
        $idFam = (int) ($pieza['id_familia'] ?? $pieza['id_familia_FK'] ?? 0);
        $promo = $resolver->resolverParaPieza($idPieza, $idSub, $idFam);

        if ($promo === null) {
            return [
                'precio_lista' => $precioLista,
                'precio_final' => $precioLista,
                'descuento_monto' => 0.0,
                'porcentaje' => 0.0,
                'tiene_promocion' => false,
                'promocion' => null,
            ];
        }

        $precios = $resolver->calcularPrecios($precioLista, (float) ($promo['porcentaje_descuento'] ?? 0));

        return [
            'precio_lista' => $precios['precio_lista'],
            'precio_final' => $precios['precio_final'],
            'descuento_monto' => $precios['descuento_monto'],
            'porcentaje' => $precios['porcentaje'],
            'tiene_promocion' => $precios['descuento_monto'] > 0,
            'promocion' => $promo,
        ];
    }

    return joyeria_promocion_tienda_resolver()->resolverPrecioPieza($pieza);
}

function joyeria_promocion_es_todas_familias(array $promo): bool
{
    return !empty($promo['aplica_todas_familias']) && (int) $promo['aplica_todas_familias'] === 1;
}

/**
 * Texto corto del alcance para listados admin.
 */
function joyeria_promocion_texto_alcance(array $promo): string
{
    if (joyeria_promocion_es_todas_familias($promo)) {
        return 'Todas las familias (catálogo completo)';
    }
    $partes = [];
    if (!empty($promo['desc_pieza'])) {
        $partes[] = 'Pieza: ' . (string) $promo['desc_pieza'];
    }
    if (!empty($promo['nom_sub_familia'])) {
        $partes[] = 'Subfamilia: ' . (string) $promo['nom_sub_familia'];
    }
    if (!empty($promo['nom_familia'])) {
        $partes[] = 'Familia: ' . (string) $promo['nom_familia'];
    }

    return $partes !== [] ? implode(' · ', $partes) : '—';
}

/**
 * @param array<string, mixed> $promo Fila de promociones con JOINs.
 * @return array<string, mixed> Formato compatible con joyeria_catalogo_banner_render_strip.
 */
function joyeria_promocion_a_stripe_catalogo(array $promo): array
{
    $pct = number_format((float) ($promo['porcentaje_descuento'] ?? 0), 0);
    $fechaFin = (string) ($promo['fecha_fin'] ?? '');
    $fechaIni = (string) ($promo['fecha_inicio'] ?? '');
    $fechaFinFmt = $fechaFin !== '' ? date('d/m/Y', strtotime($fechaFin)) : '';
    $fechaIniFmt = $fechaIni !== '' ? date('d/m/Y', strtotime($fechaIni)) : '';

    $alcance = '';
    $ctaHref = '#catalogo';
    $idPiezaFk = null;
    $fuenteImagen = 'catalogo_rotacion';

    if (joyeria_promocion_es_todas_familias($promo)) {
        $alcance = 'todas las familias del catálogo';
    } elseif (!empty($promo['desc_pieza']) && !empty($promo['id_pieza_FK'])) {
        $alcance = 'la pieza ' . (string) $promo['desc_pieza'];
        $ctaHref = '#pieza-' . (int) $promo['id_pieza_FK'];
        $idPiezaFk = (int) $promo['id_pieza_FK'];
        $fuenteImagen = 'pieza_fija';
    } elseif (!empty($promo['nom_sub_familia']) && !empty($promo['id_subfamilia_FK'])) {
        $alcance = 'la subfamilia ' . (string) $promo['nom_sub_familia'];
        $ctaHref = '#catalogo';
    } elseif (!empty($promo['nom_familia']) && !empty($promo['id_familia_FK'])) {
        $alcance = 'la familia ' . (string) $promo['nom_familia'];
        $ctaHref = '#familia-' . (int) $promo['id_familia_FK'];
    } else {
        $alcance = 'piezas seleccionadas';
    }

    $texto = $pct . '% de descuento en ' . $alcance . '.';
    if ($fechaIniFmt !== '' && $fechaFinFmt !== '') {
        $texto .= ' Vigente del ' . $fechaIniFmt . ' al ' . $fechaFinFmt . '.';
    }
    $obs = trim((string) ($promo['observaciones'] ?? ''));
    if ($obs !== '') {
        $texto .= ' ' . $obs;
    }

    return [
        'variant' => 'descuento',
        'eyebrow' => $fechaFinFmt !== '' ? ('Hasta ' . $fechaFinFmt) : 'Promoción',
        'titulo' => (string) ($promo['nombre'] ?? 'Promoción'),
        'texto' => $texto,
        'cta_label' => 'Ver piezas',
        'cta_href' => $ctaHref,
        'fuente_imagen' => $fuenteImagen,
        'id_pieza_fk' => $idPiezaFk,
        'imagen_catalogo' => $fuenteImagen === 'catalogo_rotacion',
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function joyeria_cargar_promociones_descuento_catalogo(): array
{
    try {
        $rows = joyeria_promocion_tienda_resolver()->listarVigentes();
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = joyeria_promocion_a_stripe_catalogo($row);
        }

        return $out;
    } catch (Throwable $e) {
        error_log('Promociones descuento catalogo: ' . $e->getMessage());

        return [];
    }
}

/**
 * Estado calculado para listado admin.
 */
function joyeria_promocion_estado_tienda(array $promo): string
{
    if ((int) ($promo['activa'] ?? 0) !== 1) {
        return 'Inactiva';
    }

    $hoy = strtotime(date('Y-m-d'));
    $ini = strtotime((string) ($promo['fecha_inicio'] ?? ''));
    $fin = strtotime((string) ($promo['fecha_fin'] ?? ''));

    if ($ini !== false && $hoy < $ini) {
        return 'Programada';
    }
    if ($fin !== false && $hoy > $fin) {
        return 'Vencida';
    }

    return 'Vigente';
}

/**
 * Pieza con precios por variante en stock (sin costo maestro en catalogo).
 */
function joyeria_pieza_usa_precio_desde_stock(array $pieza): bool
{
    if (!array_key_exists('costo', $pieza) || $pieza['costo'] === null || trim((string) $pieza['costo']) === '') {
        return true;
    }

    return (float) $pieza['costo'] <= 0.009;
}

/**
 * Precio minimo entre unidades disponibles (usa columna precalculada del listado si existe).
 */
function joyeria_pieza_precio_minimo_stock_disponible(array $pieza): ?float
{
    if (array_key_exists('precio_min_stock_disponible', $pieza)
        && $pieza['precio_min_stock_disponible'] !== null
        && $pieza['precio_min_stock_disponible'] !== '') {
        $desdeListado = (float) $pieza['precio_min_stock_disponible'];

        return $desdeListado > 0.009 ? $desdeListado : null;
    }

    $idPieza = (int) ($pieza['id_pieza'] ?? 0);
    if ($idPieza <= 0) {
        return null;
    }

    static $cache = [];
    if (array_key_exists($idPieza, $cache)) {
        return $cache[$idPieza];
    }

    require_once __DIR__ . '/../admin/models/pieza.php';
    $cache[$idPieza] = (new Pieza())->precioMinimoStockDisponible($idPieza);

    return $cache[$idPieza];
}

function joyeria_precio_lista_desde_pieza_maestra(array $pieza, string $audiencia): float
{
    if ($audiencia === 'cliente') {
        $costoFila = (float) ($pieza['costo'] ?? 0);
        $aumentoFila = ($pieza['aumento_pct'] !== null && $pieza['aumento_pct'] !== '')
            ? (float) $pieza['aumento_pct']
            : 0.0;
        $pvFila = round($costoFila * (1 + $aumentoFila / 100), 2);
        if ($pvFila > 0) {
            $pvFila = ceil($pvFila / 5) * 5;
        }

        return (float) $pvFila;
    }

    return PromocionTiendaResolver::precioListaDesdePieza($pieza);
}

/**
 * @return array{precio_lista: float, precio_desde: bool}
 */
function joyeria_resolver_precio_lista_catalogo_pieza(array $pieza, string $audiencia): array
{
    if (joyeria_pieza_usa_precio_desde_stock($pieza)) {
        $minStock = joyeria_pieza_precio_minimo_stock_disponible($pieza);
        if ($minStock !== null && $minStock > 0.009) {
            return [
                'precio_lista' => $minStock,
                'precio_desde' => true,
            ];
        }
    }

    return [
        'precio_lista' => joyeria_precio_lista_desde_pieza_maestra($pieza, $audiencia),
        'precio_desde' => false,
    ];
}

/**
 * Resuelve precio de catálogo con promoción vigente (visitante o cliente).
 *
 * @param 'visitante'|'cliente' $audiencia
 * @return array<string, mixed>
 */
function joyeria_precio_tienda_publica(
    array $pieza,
    string $audiencia = 'visitante',
    int $idCliente = 0,
    ?float $subtotalJoyasListaCarrito = null
): array {
    if ($idCliente <= 0 && $audiencia === 'cliente') {
        require_once __DIR__ . '/../admin/includes/tienda_auth.php';
        if (tienda_is_logged_in()) {
            $u = tienda_auth_user();
            $idCliente = (int) ($u['id_cliente'] ?? 0);
        }
    }

    $audienciaCalc = $idCliente > 0 ? 'cliente' : $audiencia;
    $resLista = joyeria_resolver_precio_lista_catalogo_pieza($pieza, $audienciaCalc);
    $precioLista = (float) $resLista['precio_lista'];
    $precioDesde = !empty($resLista['precio_desde']);
    $listaCapturada = $precioLista;

    if ($idCliente > 0) {
        $info = (new DescuentoTiendaService())->calcularPreciosPieza(
            $pieza,
            $precioLista,
            $idCliente,
            $subtotalJoyasListaCarrito
        );

        return [
            'precio_lista' => $info['precio_lista'],
            'precio_final' => $info['precio_final'],
            'descuento_monto' => $info['descuento_monto'],
            'porcentaje' => $info['porcentaje'],
            'tiene_promocion' => $info['tiene_promocion'],
            'promocion' => $info['promocion'],
            'descuento_origen' => $info['descuento_origen'] ?? 'ninguno',
            'precio_desde' => $precioDesde,
        ];
    }

    $info = joyeria_precio_catalogo_con_promo(
        $pieza,
        static function () use ($listaCapturada): float {
            return $listaCapturada;
        }
    );
    $info['precio_desde'] = $precioDesde;

    return $info;
}

/**
 * Precio público a partir de un precio lista explícito (p. ej. piezas_stock.precio_venta).
 *
 * @param 'visitante'|'cliente' $audiencia
 * @return array<string, mixed>
 */
function joyeria_precio_tienda_publica_lista(
    array $pieza,
    float $precioLista,
    string $audiencia = 'visitante',
    int $idCliente = 0,
    ?float $subtotalJoyasListaCarrito = null,
    bool $precioDesde = false
): array {
    if ($precioLista <= 0.009) {
        return joyeria_precio_tienda_publica($pieza, $audiencia, $idCliente, $subtotalJoyasListaCarrito);
    }

    if ($idCliente <= 0 && $audiencia === 'cliente') {
        require_once __DIR__ . '/../admin/includes/tienda_auth.php';
        if (tienda_is_logged_in()) {
            $u = tienda_auth_user();
            $idCliente = (int) ($u['id_cliente'] ?? 0);
        }
    }

    $listaCapturada = $precioLista;

    if ($idCliente > 0) {
        $info = (new DescuentoTiendaService())->calcularPreciosPieza(
            $pieza,
            $precioLista,
            $idCliente,
            $subtotalJoyasListaCarrito
        );

        return [
            'precio_lista' => $info['precio_lista'],
            'precio_final' => $info['precio_final'],
            'descuento_monto' => $info['descuento_monto'],
            'porcentaje' => $info['porcentaje'],
            'tiene_promocion' => $info['tiene_promocion'],
            'promocion' => $info['promocion'],
            'descuento_origen' => $info['descuento_origen'] ?? 'ninguno',
            'precio_desde' => $precioDesde,
        ];
    }

    $info = joyeria_precio_catalogo_con_promo(
        $pieza,
        static function () use ($listaCapturada): float {
            return $listaCapturada;
        }
    );
    $info['precio_desde'] = $precioDesde;

    return $info;
}

/**
 * @param array<string, mixed> $precioInfo
 * @return array<string, mixed>
 */
function joyeria_compactar_precio_info_api(array $precioInfo, bool $precioDesde = false): array
{
    $precioLista = (float) ($precioInfo['precio_lista'] ?? 0);
    $precio = (float) ($precioInfo['precio_final'] ?? $precioLista);
    $desde = $precioDesde || !empty($precioInfo['precio_desde']);
    $prefijo = $desde ? 'Desde ' : '';
    $promo = is_array($precioInfo['promocion'] ?? null) ? $precioInfo['promocion'] : null;

    return [
        'precio' => $precio,
        'precio_lista' => $precioLista,
        'precio_desde' => $desde,
        'precio_formateado' => $prefijo . '$' . number_format($precio, 2, '.', ',') . ' MXN',
        'precio_lista_formateado' => $prefijo . '$' . number_format($precioLista, 2, '.', ',') . ' MXN',
        'tiene_promocion' => !empty($precioInfo['tiene_promocion']),
        'porcentaje_descuento' => (float) ($precioInfo['porcentaje'] ?? 0),
        'promocion_nombre' => $promo !== null ? (string) ($promo['nombre'] ?? '') : '',
    ];
}

/**
 * Enriquece resumen de variantes con precios públicos por opción/celda.
 *
 * @param array<string, mixed> $variantesResumen
 * @param array<string, mixed> $pieza
 * @param 'visitante'|'cliente' $audiencia
 * @return array<string, mixed>
 */
function joyeria_enriquecer_variantes_resumen_precios(
    array $variantesResumen,
    array $pieza,
    string $audiencia,
    int $idCliente = 0,
    ?float $subtotalJoyasListaCarrito = null
): array {
    if (empty($variantesResumen['tiene_variantes'])) {
        return $variantesResumen;
    }

    $resolver = static function (?float $lista, bool $desde = false) use (
        $pieza,
        $audiencia,
        $idCliente,
        $subtotalJoyasListaCarrito
    ): ?array {
        if ($lista === null || $lista <= 0.009) {
            return null;
        }

        $info = joyeria_precio_tienda_publica_lista(
            $pieza,
            $lista,
            $audiencia,
            $idCliente,
            $subtotalJoyasListaCarrito,
            $desde
        );

        return joyeria_compactar_precio_info_api($info, $desde);
    };

    if (is_array($variantesResumen['variantes'] ?? null)) {
        foreach ($variantesResumen['variantes'] as $idx => $variante) {
            if (!is_array($variante)) {
                continue;
            }
            $lista = isset($variante['precio']) ? (float) $variante['precio'] : 0.0;
            $precioInfo = $resolver($lista > 0.009 ? $lista : null);
            if ($precioInfo !== null) {
                $variantesResumen['variantes'][$idx]['precio_info'] = $precioInfo;
            }
        }
    }

    $matrizPrecios = is_array($variantesResumen['matriz_precios'] ?? null)
        ? $variantesResumen['matriz_precios']
        : [];
    if ($matrizPrecios !== []) {
        $matrizInfo = [];
        foreach ($matrizPrecios as $v1 => $filas) {
            if (!is_array($filas)) {
                continue;
            }
            foreach ($filas as $v2 => $listaRaw) {
                $lista = (float) $listaRaw;
                $precioInfo = $resolver($lista > 0.009 ? $lista : null);
                if ($precioInfo === null) {
                    continue;
                }
                if (!isset($matrizInfo[$v1])) {
                    $matrizInfo[$v1] = [];
                }
                $matrizInfo[$v1][$v2] = $precioInfo;
            }
        }
        if ($matrizInfo !== []) {
            $variantesResumen['matriz_precios_info'] = $matrizInfo;
        }
    }

    return $variantesResumen;
}

/**
 * Contexto de precios para cliente logueado (subtotal lista del carrito).
 *
 * @return array{id_cliente: int, subtotal_lista_carrito: ?float}
 */
function joyeria_cargar_contexto_precios_cliente(): array
{
    require_once __DIR__ . '/../admin/includes/tienda_auth.php';
    if (!tienda_is_logged_in()) {
        return ['id_cliente' => 0, 'subtotal_lista_carrito' => null];
    }

    $u = tienda_auth_user();
    $idCliente = (int) ($u['id_cliente'] ?? 0);
    if ($idCliente <= 0) {
        return ['id_cliente' => 0, 'subtotal_lista_carrito' => null];
    }

    require_once __DIR__ . '/../admin/models/carrito.php';
    $carrito = new Carrito();
    $items = $carrito->listar($idCliente);
    $resumen = $carrito->calcularResumen($items);

    return [
        'id_cliente' => $idCliente,
        'subtotal_lista_carrito' => (float) ($resumen['subtotal_lista'] ?? 0),
    ];
}

/**
 * Renderiza bloque de precio en tarjeta de producto o vitrina.
 *
 * @param array<string, mixed> $precioInfo
 * @param array{prefijo_desde?: bool, tag?: string, clase_extra?: string} $opts
 */
function joyeria_render_precio_producto_catalogo(array $precioInfo, array $opts = []): void
{
    $lista = (float) ($precioInfo['precio_lista'] ?? 0);
    $final = (float) ($precioInfo['precio_final'] ?? $lista);
    $pct = (float) ($precioInfo['porcentaje'] ?? 0);
    $tienePromo = (!empty($precioInfo['tiene_promocion']) || !empty($precioInfo['promocion']))
        && $pct > 0
        && $lista > 0
        && ($lista - $final) >= 0.01;

    $prefijoDesde = !empty($opts['prefijo_desde']) || !empty($precioInfo['precio_desde']);
    $tag = (string) ($opts['tag'] ?? 'div');
    if (!in_array($tag, ['div', 'span', 'p'], true)) {
        $tag = 'div';
    }
    $claseExtra = trim((string) ($opts['clase_extra'] ?? ''));

    if ($tienePromo): ?>
        <<?php echo $tag; ?> class="precio precio-con-promo<?php echo $claseExtra !== '' ? ' ' . htmlspecialchars($claseExtra, ENT_QUOTES, 'UTF-8') : ''; ?>">
            <?php if ($pct > 0): ?>
                <span class="producto-badge-promo">-<?php echo htmlspecialchars(number_format($pct, 0), ENT_QUOTES, 'UTF-8'); ?>%</span>
            <?php endif; ?>
            <span class="precio-lista-tachado">$<?php echo htmlspecialchars(number_format($lista, 2, '.', ','), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="precio-promo"><?php if ($prefijoDesde): ?>Desde <?php endif; ?>$<?php echo htmlspecialchars(number_format($final, 2, '.', ','), ENT_QUOTES, 'UTF-8'); ?> MXN</span>
        </<?php echo $tag; ?>>
    <?php else: ?>
        <<?php echo $tag; ?> class="precio<?php echo $claseExtra !== '' ? ' ' . htmlspecialchars($claseExtra, ENT_QUOTES, 'UTF-8') : ''; ?>"><?php if ($prefijoDesde): ?>Desde <?php endif; ?>$<?php echo htmlspecialchars(number_format($final, 2, '.', ','), ENT_QUOTES, 'UTF-8'); ?> MXN</<?php echo $tag; ?>>
    <?php endif;
}
