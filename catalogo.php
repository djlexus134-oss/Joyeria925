<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/models/pieza.php';
require_once __DIR__ . '/admin/models/sub_familia.php';
require_once __DIR__ . '/includes/catalogo_banner_promos.php';
require_once __DIR__ . '/includes/promociones_tienda_publica.php';
require_once __DIR__ . '/includes/catalogo_variantes_publico.php';
require_once __DIR__ . '/admin/includes/tienda_auth.php';

$tiendaUser = tienda_auth_user();
$isCliente = $tiendaUser !== null;
$nombreMostrar = '';
if ($isCliente) {
    $nombreMostrar = isset($tiendaUser['nombre_completo']) ? trim((string) $tiendaUser['nombre_completo']) : '';
    if ($nombreMostrar === '' && !empty($tiendaUser['correo'])) {
        $nombreMostrar = (string) $tiendaUser['correo'];
    }
}
$hrefCatalogoGeneral = $isCliente ? 'user/index.php#catalogo' : 'index.php#catalogo';
$hrefInicio = $isCliente ? 'user/index.php' : 'index.php';

function joyeria_precio_catalogo_publico(array $pieza): float
{
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

$idFamilia = isset($_GET['fam']) ? (int) $_GET['fam'] : 0;
$idSubFamilia = isset($_GET['sub']) ? (int) $_GET['sub'] : 0;

$subfamiliaModel = new SubFamilia();
$subfamiliasActivas = $subfamiliaModel->leer();
$familiasMenu = [];
foreach ($subfamiliasActivas as $subRow) {
    if (!is_array($subRow)) {
        continue;
    }
    $idFamRow = (int) ($subRow['id_familia_FK'] ?? 0);
    $idSubRow = (int) ($subRow['id_sub_familia'] ?? 0);
    $nomFamRaw = (string) ($subRow['nom_familia'] ?? '');
    $nomSubRaw = (string) ($subRow['nom_sub_familia'] ?? '');
    $nomFamRow = preg_replace('/\s+/', ' ', trim($nomFamRaw)) ?? '';
    $nomSubRow = preg_replace('/\s+/', ' ', trim($nomSubRaw)) ?? '';
    if ($idFamRow <= 0 || $idSubRow <= 0 || $nomFamRow === '' || $nomSubRow === '') {
        continue;
    }
    if (!isset($familiasMenu[$idFamRow])) {
        $familiasMenu[$idFamRow] = [
            'nombre' => $nomFamRow,
            'subfamilias' => [],
            '_sub_keys' => [],
        ];
    }
    $subKey = mb_strtolower(preg_replace('/\s+/', ' ', $nomSubRow) ?? $nomSubRow, 'UTF-8');
    if (isset($familiasMenu[$idFamRow]['_sub_keys'][$subKey])) {
        continue;
    }
    $familiasMenu[$idFamRow]['_sub_keys'][$subKey] = true;
    $familiasMenu[$idFamRow]['subfamilias'][$idSubRow] = $nomSubRow;
}
uasort($familiasMenu, static function (array $a, array $b): int {
    return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
});
foreach ($familiasMenu as $famKey => $famGroup) {
    if ($famGroup['subfamilias'] === []) {
        unset($familiasMenu[$famKey]);
        continue;
    }
    asort($familiasMenu[$famKey]['subfamilias'], SORT_NATURAL | SORT_FLAG_CASE);
    unset($familiasMenu[$famKey]['_sub_keys']);
}

if ($idSubFamilia > 0) {
    $subSeleccionada = $subfamiliaModel->leerUno($idSubFamilia);
    $idFamReal = (int) ($subSeleccionada['id_familia_FK'] ?? 0);
    $activa = (int) ($subSeleccionada['activo'] ?? 0) === 1;
    if (!$subSeleccionada || $idFamReal <= 0 || !$activa) {
        header('Location: catalogo.php', true, 302);
        exit;
    }
    $idFamilia = $idFamReal;
}

if ($idFamilia > 0 && $idSubFamilia <= 0 && isset($familiasMenu[$idFamilia])) {
    $firstSub = (int) array_key_first($familiasMenu[$idFamilia]['subfamilias']);
    if ($firstSub > 0) {
        header('Location: catalogo.php?fam=' . $idFamilia . '&sub=' . $firstSub, true, 302);
        exit;
    }
}

if ($idFamilia <= 0 && $idSubFamilia <= 0 && $familiasMenu !== []) {
    $firstFam = (int) array_key_first($familiasMenu);
    $firstSub = (int) array_key_first($familiasMenu[$firstFam]['subfamilias']);
    if ($firstFam > 0 && $firstSub > 0) {
        header('Location: catalogo.php?fam=' . $firstFam . '&sub=' . $firstSub, true, 302);
        exit;
    }
}

$piezasCatalogo = [];
$catalogoError = false;
try {
    $piezaModel = new Pieza();
    $piezasCatalogo = $piezaModel->listarCatalogoPublicoFiltrado(
        $idFamilia > 0 ? $idFamilia : null,
        null
    );
    $piezasCatalogo = $piezaModel->adjuntarResumenVariantesCatalogo($piezasCatalogo);
} catch (Throwable $e) {
    $catalogoError = true;
    error_log('Catalogo por subfamilia: ' . $e->getMessage());
}

$subfamiliasConPiezas = [];
foreach ($piezasCatalogo as $pieza) {
    if (!is_array($pieza)) {
        continue;
    }
    $idSub = (int) ($pieza['id_sub_familia'] ?? 0);
    if ($idSub <= 0) {
        continue;
    }
    $nomSub = trim((string) ($pieza['nom_sub_familia'] ?? ''));
    if ($nomSub === '') {
        $nomSub = 'Subfamilia';
    }
    if (!isset($subfamiliasConPiezas[$idSub])) {
        $subfamiliasConPiezas[$idSub] = [
            'nombre' => $nomSub,
            'items' => [],
        ];
    }
    $subfamiliasConPiezas[$idSub]['items'][] = $pieza;
}

if ($idSubFamilia > 0 && !isset($subfamiliasConPiezas[$idSubFamilia])) {
    $idSubFamilia = 0;
}
if ($idSubFamilia <= 0 && $subfamiliasConPiezas !== []) {
    $firstSubConPiezas = (int) array_key_first($subfamiliasConPiezas);
    if ($firstSubConPiezas > 0) {
        header('Location: catalogo.php?fam=' . $idFamilia . '&sub=' . $firstSubConPiezas, true, 302);
        exit;
    }
}

$ordenSubfamilias = array_keys($subfamiliasConPiezas);
usort($ordenSubfamilias, static function (int $a, int $b) use ($subfamiliasConPiezas, $idSubFamilia): int {
    if ($a === $idSubFamilia) {
        return -1;
    }
    if ($b === $idSubFamilia) {
        return 1;
    }
    return strcasecmp(
        (string) ($subfamiliasConPiezas[$a]['nombre'] ?? ''),
        (string) ($subfamiliasConPiezas[$b]['nombre'] ?? '')
    );
});

$nomFamiliaSeleccionada = '';
$nomSubfamiliaSeleccionada = '';
if ($idFamilia > 0 && isset($familiasMenu[$idFamilia])) {
    $nomFamiliaSeleccionada = (string) ($familiasMenu[$idFamilia]['nombre'] ?? '');
}
if ($idSubFamilia > 0 && isset($subfamiliasConPiezas[$idSubFamilia])) {
    $nomSubfamiliaSeleccionada = (string) ($subfamiliasConPiezas[$idSubFamilia]['nombre'] ?? '');
}

$promocionalesCatalogo = array_merge(
    joyeria_cargar_promociones_descuento_catalogo(),
    joyeria_cargar_promociones_banner_catalogo($isCliente ? 'cliente' : 'visitante')
);
$promocionalesCount = count($promocionalesCatalogo);
$promoAudiencia = $isCliente ? 'cliente' : 'visitante';
$ctxPreciosCliente = joyeria_cargar_contexto_precios_cliente();
$piezasPlanas = [];
foreach ($subfamiliasConPiezas as $grupoSub) {
    foreach (($grupoSub['items'] ?? []) as $piezaSub) {
        if (is_array($piezaSub)) {
            $piezasPlanas[] = $piezaSub;
        }
    }
}
[, $piezasConImagen, $imagenPiezaPorIdBanner] = joyeria_banner_catalogo_arrays_imagen($piezasPlanas);
$piezasConImgCount = count($piezasConImagen);
$promoBarInferiorSegmentos = joyeria_cargar_segmentos_barra_inferior($promoAudiencia);
$promoBarInferiorActiva = $promoBarInferiorSegmentos !== [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo | Platería El Ángel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
</head>
<body<?php echo $promoBarInferiorActiva ? ' class="has-promo-bar-inferior"' : ''; ?>>

    <?php if ($isCliente): ?>
        <div class="usuario-zona-bar border-bottom py-2 px-3 small d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: #fafafa;">
            <span class="text-muted">Hola, <strong class="text-dark"><?php echo htmlspecialchars($nombreMostrar, ENT_QUOTES, 'UTF-8'); ?></strong></span>
            <span class="d-flex flex-wrap gap-3">
                <a href="user/logout.php" class="link-dark text-decoration-none">Cerrar sesión</a>
            </span>
        </div>
    <?php endif; ?>

    <header class="header">
        <div class="logo">
            <h1>Platería El Ángel</h1>
            <p>Artesanía y elegancia en plata</p>
        </div>
        <nav class="nav">
            <ul>
                <li class="nav-item-dropdown">
                    <a href="catalogo.php" class="nav-link-dropdown">Catálogo</a>
                    <?php if ($familiasMenu !== []): ?>
                        <div class="nav-dropdown-panel" aria-label="Familias y subfamilias del catálogo">
                            <?php foreach ($familiasMenu as $idFamMenu => $grupoMenu): ?>
                                <section class="nav-dropdown-family">
                                    <h3><?php echo htmlspecialchars((string) ($grupoMenu['nombre'] ?? 'Familia'), ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <ul>
                                        <?php foreach (($grupoMenu['subfamilias'] ?? []) as $idSubMenu => $nomSubMenu): ?>
                                            <li>
                                                <a href="catalogo.php?fam=<?php echo (int) $idFamMenu; ?>&sub=<?php echo (int) $idSubMenu; ?>">
                                                    <?php echo htmlspecialchars((string) $nomSubMenu, ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </li>
                <li><a href="<?php echo htmlspecialchars($hrefInicio, ENT_QUOTES, 'UTF-8'); ?>#vitrina">Inicio</a></li>
                <li><a href="<?php echo htmlspecialchars($hrefCatalogoGeneral, ENT_QUOTES, 'UTF-8'); ?>">Catálogo general</a></li>
            </ul>
        </nav>

        <div class="header-icons">
            <a href="<?php echo htmlspecialchars($hrefInicio, ENT_QUOTES, 'UTF-8'); ?>#buscadorPiezas" class="icon" aria-label="Buscar piezas" title="Buscar piezas">
                <svg viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="7"></circle>
                    <line x1="16.65" y1="16.65" x2="21" y2="21"></line>
                </svg>
            </a>
            <?php if ($isCliente): ?>
                <a href="user/cuenta.php" class="icon" aria-label="Mi cuenta" title="Mi cuenta">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 20c0-4 4-6 8-6s8 2 8 6"></path>
                    </svg>
                </a>
                <a href="user/carrito.php" class="icon cart" aria-label="Carrito" title="Carrito">
                    <svg viewBox="0 0 24 24">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.6 13h10.8l2-8H6"></path>
                    </svg>
                    <span class="cart-count">0</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="icon" aria-label="Tu cuenta" title="Tu cuenta">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 20c0-4 4-6 8-6s8 2 8 6"></path>
                    </svg>
                </a>
                <a href="login.php" class="icon cart" aria-label="Carrito" title="Inicia sesión para ver tu carrito">
                    <svg viewBox="0 0 24 24">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.6 13h10.8l2-8H6"></path>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <section class="colecciones catalogo-section">
        <div class="catalogo-inner">
            <div class="catalogo-page-head">
                <p class="catalogo-page-kicker">Catálogo filtrado</p>
                <h2 class="catalogo-page-title">
                    <?php echo htmlspecialchars($nomSubfamiliaSeleccionada !== '' ? $nomSubfamiliaSeleccionada : 'Subfamilia', ENT_QUOTES, 'UTF-8'); ?>
                </h2>
                <p class="catalogo-page-meta text-muted">
                    <?php if ($nomFamiliaSeleccionada !== ''): ?>
                        Familia: <?php echo htmlspecialchars($nomFamiliaSeleccionada, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </p>
                <div class="mt-3">
                    <a class="btn btn-outline-dark rounded-pill px-4" href="<?php echo htmlspecialchars($hrefCatalogoGeneral, ENT_QUOTES, 'UTF-8'); ?>">
                        Volver al catálogo general
                    </a>
                </div>
            </div>

            <?php if ($catalogoError): ?>
                <p class="catalogo-empty text-muted">No pudimos cargar esta subfamilia en este momento.</p>
            <?php elseif ($subfamiliasConPiezas === []): ?>
                <p class="catalogo-empty text-muted">No encontramos piezas activas para esta subfamilia.</p>
            <?php else: ?>
                <?php
                $promoIndex = 0;
                $promoCount = count($promocionalesCatalogo);
                $subfamiliaRenderIndex = 0;
                ?>
                <div class="catalogo-flow">
                    <?php foreach ($ordenSubfamilias as $idSubRender): ?>
                        <?php
                        $grupoSub = $subfamiliasConPiezas[$idSubRender] ?? ['nombre' => 'Subfamilia', 'items' => []];
                        $itemsSub = isset($grupoSub['items']) && is_array($grupoSub['items']) ? $grupoSub['items'] : [];
                        $itemsConImagen = [];
                        foreach ($itemsSub as $piezaItem) {
                            if (!is_array($piezaItem)) {
                                continue;
                            }
                            $imgPathItem = joyeria_resolver_url_imagen((string) ($piezaItem['url_imagen'] ?? ''));
                            if ($imgPathItem === null) {
                                continue;
                            }
                            $piezaItem['__img_resuelta'] = $imgPathItem;
                            $itemsConImagen[] = $piezaItem;
                        }
                        if ($itemsConImagen === []) {
                            continue;
                        }
                        ?>
                        <div class="catalog-group" id="subfamilia-<?php echo (int) $idSubRender; ?>">
                            <h3 class="catalog-group-title">
                                <?php echo htmlspecialchars((string) ($grupoSub['nombre'] ?? 'Subfamilia'), ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <div class="catalog-carousel catalog-carousel--pieces" data-catalog-carousel>
                                <button type="button" class="catalog-carousel-nav catalog-carousel-prev" aria-label="Mostrar pieza anterior">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                </button>
                                <div class="catalog-carousel-track" tabindex="0" role="region" aria-label="Piezas de <?php echo htmlspecialchars((string) ($grupoSub['nombre'] ?? 'Subfamilia'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php foreach ($itemsConImagen as $pieza): ?>
                                        <?php
                                        $idPiezaRow = (int) ($pieza['id_pieza'] ?? 0);
                                        $idFamRow = (int) ($pieza['id_familia'] ?? 0);
                                        $idSubRow = (int) ($pieza['id_sub_familia'] ?? 0);
                                        $idMetalRow = (int) ($pieza['id_metal'] ?? 0);
                                        $desc = htmlspecialchars((string) ($pieza['desc_pieza'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $descRaw = htmlspecialchars(mb_strtolower((string) ($pieza['desc_pieza'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8');
                                        $sub = htmlspecialchars((string) ($pieza['nom_sub_familia'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $metal = htmlspecialchars((string) ($pieza['nom_metal'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $precioInfo = joyeria_precio_tienda_publica(
                                            $pieza,
                                            $promoAudiencia,
                                            $ctxPreciosCliente['id_cliente'],
                                            $ctxPreciosCliente['subtotal_lista_carrito']
                                        );
                                        $pv = (float) ($precioInfo['precio_final'] ?? 0);
                                        $imgSrc = $pieza['__img_resuelta'];
                                        $variantesResumen = is_array($pieza['variantes_resumen'] ?? null) ? $pieza['variantes_resumen'] : [];
                                        $tieneVariantes = !empty($variantesResumen['tiene_variantes']);
                                        ?>
                                        <article class="producto-card catalog-carousel-card"
                                            id="pieza-<?php echo $idPiezaRow; ?>"
                                            data-id-pieza="<?php echo $idPiezaRow; ?>"
                                            data-pieza-action="ver"
                                            data-id-familia="<?php echo $idFamRow; ?>"
                                            data-id-subfamilia="<?php echo $idSubRow; ?>"
                                            data-id-metal="<?php echo $idMetalRow; ?>"
                                            data-precio="<?php echo number_format($pv, 2, '.', ''); ?>"
                                            data-desc="<?php echo $descRaw; ?>"
                                            <?php if ($tieneVariantes): ?>data-tiene-variantes="1"<?php endif; ?>
                                            style="cursor:pointer;">
                                            <div class="producto-img">
                                                <img src="<?php echo htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $desc; ?>" loading="lazy" width="400" height="400">
                                            </div>
                                            <h3><?php echo $desc; ?></h3>
                                            <div class="producto-meta">
                                                <?php if ($sub !== ''): ?>
                                                    <span class="badge rounded-pill text-bg-light border producto-badge"><?php echo $sub; ?></span>
                                                <?php endif; ?>
                                                <?php if ($metal !== ''): ?>
                                                    <span class="badge rounded-pill text-bg-light border producto-badge"><?php echo $metal; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php joyeria_render_precio_producto_catalogo($precioInfo); ?>
                                            <div class="producto-card-actions d-flex gap-2 mt-2">
                                                <button type="button" class="btn btn-sm btn-dark flex-grow-1" data-pieza-action="agregar" data-id-pieza="<?php echo $idPiezaRow; ?>">
                                                    <i class="bi bi-cart-plus" aria-hidden="true"></i>
                                                    <?php echo $tieneVariantes ? 'Elegir y agregar' : 'Agregar'; ?>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-dark" data-pieza-action="ver" data-id-pieza="<?php echo $idPiezaRow; ?>" aria-label="Ver detalle">
                                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="catalog-carousel-nav catalog-carousel-next" aria-label="Mostrar siguiente pieza">
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <?php
                        $subfamiliaRenderIndex++;
                        if ($promoCount > 0 && $subfamiliaRenderIndex % 2 === 0):
                            $tplPromo = $promocionalesCatalogo[$promoIndex % $promoCount];
                            [$imgPromoUrl, $imgPromoAlt] = joyeria_banner_resolver_visual(
                                $tplPromo,
                                $promoIndex,
                                $piezasConImgCount,
                                $piezasConImagen,
                                $imagenPiezaPorIdBanner
                            );
                            joyeria_catalogo_banner_render_strip($tplPromo, $imgPromoUrl, $imgPromoAlt);
                            $promoIndex++;
                        endif;
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php
    $promoBarAudiencia = $promoAudiencia;
    require __DIR__ . '/includes/tienda_barra_promo_inferior.php';
    ?>

    <?php require __DIR__ . '/includes/modal_pieza_detalle.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/site-nav.js"></script>
    <script src="js/catalog-carousel.js"></script>
    <script src="js/tienda-carrito.js"></script>
</body>
</html>
