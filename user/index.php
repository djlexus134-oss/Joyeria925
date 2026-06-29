<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/tienda_auth.php';

if (!tienda_is_logged_in()) {
    header('Location: ../index.php');
    exit;
}

$tiendaUser = tienda_auth_user();
$nombreMostrar = isset($tiendaUser['nombre_completo']) ? trim((string) $tiendaUser['nombre_completo']) : '';
if ($nombreMostrar === '' && !empty($tiendaUser['correo'])) {
    $nombreMostrar = (string) $tiendaUser['correo'];
}

require_once __DIR__ . '/../includes/joyeria_branding.php';
require_once __DIR__ . '/../admin/models/pieza.php';
require_once __DIR__ . '/../includes/catalogo_banner_promos.php';
require_once __DIR__ . '/../includes/promociones_tienda_publica.php';
require_once __DIR__ . '/../includes/catalogo_variantes_publico.php';

$promoAudiencia = 'cliente';
$ctxPreciosCliente = joyeria_cargar_contexto_precios_cliente();

/**
 * Vista cliente: misma base que landing visitante, con navegación extendida
 * (Compras / Carrito) y rutas relativas a /user.
 */
$piezasCatalogo = [];
$catalogoError = false;

try {
    $piezaModel = new Pieza();
    $piezasCatalogo = $piezaModel->listarCatalogoPublico();
    $piezasCatalogo = $piezaModel->adjuntarResumenVariantesCatalogo($piezasCatalogo);
} catch (Throwable $e) {
    $catalogoError = true;
    error_log('Catalogo cliente: ' . $e->getMessage());
}

$piezasPorFamilia = [];
foreach ($piezasCatalogo as $row) {
    if (!is_array($row)) {
        continue;
    }
    $idFam = (int) ($row['id_familia'] ?? 0);
    $nomFam = preg_replace('/\s+/', ' ', trim((string) ($row['nom_familia'] ?? ''))) ?? '';
    if ($nomFam === '') {
        $nomFam = 'Sin categoría';
    }
    if (!isset($piezasPorFamilia[$idFam])) {
        $piezasPorFamilia[$idFam] = [
            'nom_familia' => $nomFam,
            'items' => [],
        ];
    }
    $piezasPorFamilia[$idFam]['items'][] = $row;
}

uasort(
    $piezasPorFamilia,
    static function (array $a, array $b): int {
        return strcmp($a['nom_familia'], $b['nom_familia']);
    }
);

function joyeria_precio_referencia_cliente(array $pieza): float
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

function joyeria_user_img_src(?string $src): ?string
{
    if ($src === null) {
        return null;
    }
    $s = trim($src);
    if ($s === '') {
        return '';
    }
    if (preg_match('~^(https?:)?//~i', $s) === 1) {
        return $s;
    }
    if (str_starts_with($s, '/') || str_starts_with($s, '../')) {
        return $s;
    }

    return '../' . $s;
}

$listadoPiezasFamiliaPlanes = [];
foreach ($piezasPorFamilia as $grupoLf) {
    foreach (($grupoLf['items'] ?? []) as $pLf) {
        if (is_array($pLf)) {
            $listadoPiezasFamiliaPlanes[] = $pLf;
        }
    }
}
[, $listadoPlanoPiezasConImagen, $imagenPiezaPorIdBanner] = joyeria_banner_catalogo_arrays_imagen($listadoPiezasFamiliaPlanes);
$listadoPlanoPiezas = $listadoPiezasFamiliaPlanes;

$familiasUnicas = [];
$subfamiliasUnicas = [];
$metalesUnicos = [];
$precioMin = null;
$precioMax = null;
foreach ($piezasCatalogo as $rowFil) {
    if (!is_array($rowFil)) {
        continue;
    }

    $idF = (int) ($rowFil['id_familia'] ?? 0);
    $nF = preg_replace('/\s+/', ' ', trim((string) ($rowFil['nom_familia'] ?? ''))) ?? '';
    if ($idF > 0 && $nF !== '' && !isset($familiasUnicas[$idF])) {
        $familiasUnicas[$idF] = $nF;
    }

    $idSF = (int) ($rowFil['id_sub_familia'] ?? 0);
    $nSF = preg_replace('/\s+/', ' ', trim((string) ($rowFil['nom_sub_familia'] ?? ''))) ?? '';
    if ($idSF > 0 && $nSF !== '' && !isset($subfamiliasUnicas[$idSF])) {
        $subfamiliasUnicas[$idSF] = [
            'nombre' => $nSF,
            'id_familia' => $idF,
        ];
    }

    $idM = (int) ($rowFil['id_metal'] ?? 0);
    $nM = trim((string) ($rowFil['nom_metal'] ?? ''));
    if ($idM > 0 && $nM !== '' && !isset($metalesUnicos[$idM])) {
        $metalesUnicos[$idM] = $nM;
    }

    $precioFil = joyeria_precio_tienda_publica(
        $rowFil,
        $promoAudiencia,
        $ctxPreciosCliente['id_cliente'],
        $ctxPreciosCliente['subtotal_lista_carrito']
    );
    $pvFil = (float) ($precioFil['precio_final'] ?? 0);
    if ($pvFil > 0) {
        if ($precioMin === null || $pvFil < $precioMin) {
            $precioMin = $pvFil;
        }
        if ($precioMax === null || $pvFil > $precioMax) {
            $precioMax = $pvFil;
        }
    }
}

asort($familiasUnicas, SORT_NATURAL | SORT_FLAG_CASE);
uasort($subfamiliasUnicas, static function (array $a, array $b): int {
    return strcasecmp($a['nombre'], $b['nombre']);
});
asort($metalesUnicos, SORT_NATURAL | SORT_FLAG_CASE);

$menuCatalogoFamilias = [];
foreach ($subfamiliasUnicas as $idSubMenu => $datosSubMenu) {
    $idFamMenu = (int) ($datosSubMenu['id_familia'] ?? 0);
    $nomSubMenu = trim((string) ($datosSubMenu['nombre'] ?? ''));
    if ($idFamMenu <= 0 || !isset($familiasUnicas[$idFamMenu])) {
        continue;
    }
    if ($nomSubMenu === '') {
        continue;
    }
    if (!isset($menuCatalogoFamilias[$idFamMenu])) {
        $menuCatalogoFamilias[$idFamMenu] = [
            'nombre' => (string) $familiasUnicas[$idFamMenu],
            'subfamilias' => [],
            '_sub_keys' => [],
        ];
    }
    $nomSubNormalizado = mb_strtolower(preg_replace('/\s+/', ' ', $nomSubMenu) ?? $nomSubMenu, 'UTF-8');
    if (isset($menuCatalogoFamilias[$idFamMenu]['_sub_keys'][$nomSubNormalizado])) {
        continue;
    }
    $menuCatalogoFamilias[$idFamMenu]['_sub_keys'][$nomSubNormalizado] = true;
    $menuCatalogoFamilias[$idFamMenu]['subfamilias'][(int) $idSubMenu] = $nomSubMenu;
}
foreach ($menuCatalogoFamilias as $idFamMenu => $grupoMenu) {
    if ($grupoMenu['subfamilias'] === []) {
        unset($menuCatalogoFamilias[$idFamMenu]);
        continue;
    }
    asort($menuCatalogoFamilias[$idFamMenu]['subfamilias'], SORT_NATURAL | SORT_FLAG_CASE);
    unset($menuCatalogoFamilias[$idFamMenu]['_sub_keys']);
}
uasort($menuCatalogoFamilias, static function (array $a, array $b): int {
    return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
});

$vitrinaNarrativas = joyeria_vitrina_narrativas();

$fuenteVitrina = $listadoPlanoPiezasConImagen !== [] ? $listadoPlanoPiezasConImagen : $listadoPlanoPiezas;
$piezasVitrinaCount = count($fuenteVitrina);

$familiasDestacadas = [];
foreach ($piezasPorFamilia as $idFamDest => $grupoDest) {
    $piezaRef = null;
    $imgRef = null;
    foreach (($grupoDest['items'] ?? []) as $cand) {
        if (!is_array($cand)) {
            continue;
        }
        $imgCand = joyeria_user_img_src(joyeria_resolver_url_imagen((string) ($cand['url_imagen'] ?? '')));
        if ($imgCand === null) {
            continue;
        }
        $piezaRef = $cand;
        $imgRef = $imgCand;
        break;
    }
    if ($piezaRef === null || $imgRef === null) {
        continue;
    }
    $familiasDestacadas[] = [
        'id_familia' => (int) $idFamDest,
        'nom_familia' => (string) ($grupoDest['nom_familia'] ?? 'Colección'),
        'id_pieza' => (int) ($piezaRef['id_pieza'] ?? 0),
        'desc_pieza' => (string) ($piezaRef['desc_pieza'] ?? ''),
        'img' => $imgRef,
    ];
}

$promocionalesCatalogo = array_merge(
    joyeria_cargar_promociones_descuento_catalogo(),
    joyeria_cargar_promociones_banner_catalogo('cliente')
);
$promocionalesCount = count($promocionalesCatalogo);
$promoBarInferiorSegmentos = joyeria_cargar_segmentos_barra_inferior($promoAudiencia);
$promoBarInferiorActiva = $promoBarInferiorSegmentos !== [];

?><!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(joyeria_marca_titulo('Mi cuenta'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../css/main.css">
</head>

<body<?php echo $promoBarInferiorActiva ? ' class="has-promo-bar-inferior"' : ''; ?>>

    <div class="usuario-zona-bar border-bottom py-2 px-3 small d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: #fafafa;">
        <span class="text-muted">Hola, <strong class="text-dark"><?php echo htmlspecialchars($nombreMostrar, ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <span class="d-flex flex-wrap gap-3">
            <a href="logout.php" class="link-dark text-decoration-none">Cerrar sesión</a>
        </span>
    </div>

    <div class="buscador-overlay" id="buscadorPiezas" role="dialog" aria-modal="true" aria-labelledby="buscadorPiezasTitulo" aria-hidden="true">
        <div class="buscador-backdrop" data-accion="cerrar" tabindex="-1"></div>
        <div class="buscador-panel" role="document">
            <div class="buscador-head">
                <div>
                    <p class="buscador-eyebrow">Buscar en el catálogo</p>
                    <h2 class="buscador-title" id="buscadorPiezasTitulo">Encuentra tu pieza</h2>
                </div>
                <button type="button" class="buscador-close" data-accion="cerrar" aria-label="Cerrar buscador">&times;</button>
            </div>

            <div class="buscador-grid">
                <label class="buscador-field buscador-field--full">
                    <span class="buscador-label">Descripción</span>
                    <input type="search" class="buscador-input" data-filtro="texto" placeholder="Anillo, dije, cadena..." autocomplete="off">
                </label>

                <label class="buscador-field">
                    <span class="buscador-label">Familia</span>
                    <select class="buscador-input" data-filtro="familia">
                        <option value="">Todas</option>
                        <?php foreach ($familiasUnicas as $idFamOpt => $nomFamOpt): ?>
                            <option value="<?php echo (int) $idFamOpt; ?>"><?php echo htmlspecialchars((string) $nomFamOpt, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="buscador-field">
                    <span class="buscador-label">Subfamilia</span>
                    <select class="buscador-input" data-filtro="subfamilia">
                        <option value="">Todas</option>
                        <?php foreach ($subfamiliasUnicas as $idSubOpt => $datosSubOpt): ?>
                            <option value="<?php echo (int) $idSubOpt; ?>" data-id-familia="<?php echo (int) $datosSubOpt['id_familia']; ?>"><?php echo htmlspecialchars((string) $datosSubOpt['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="buscador-field">
                    <span class="buscador-label">Metal</span>
                    <select class="buscador-input" data-filtro="metal">
                        <option value="">Todos</option>
                        <?php foreach ($metalesUnicos as $idMetOpt => $nomMetOpt): ?>
                            <option value="<?php echo (int) $idMetOpt; ?>"><?php echo htmlspecialchars((string) $nomMetOpt, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="buscador-field buscador-field--range">
                    <span class="buscador-label">Rango de precio (MXN)</span>
                    <div class="buscador-range">
                        <input type="number" class="buscador-input" data-filtro="precio-min" min="0" step="1" placeholder="<?php echo $precioMin !== null ? htmlspecialchars((string) (int) floor($precioMin), ENT_QUOTES, 'UTF-8') : 'Mín'; ?>" inputmode="numeric">
                        <span class="buscador-range-sep" aria-hidden="true">—</span>
                        <input type="number" class="buscador-input" data-filtro="precio-max" min="0" step="1" placeholder="<?php echo $precioMax !== null ? htmlspecialchars((string) (int) ceil($precioMax), ENT_QUOTES, 'UTF-8') : 'Máx'; ?>" inputmode="numeric">
                    </div>
                </div>
            </div>

            <div class="buscador-foot">
                <span class="buscador-resumen" data-resumen aria-live="polite">&nbsp;</span>
                <div class="buscador-actions">
                    <button type="button" class="buscador-btn buscador-btn--ghost" data-accion="limpiar">Limpiar</button>
                    <button type="button" class="buscador-btn buscador-btn--solid" data-accion="cerrar">Ver resultados</button>
                </div>
            </div>
        </div>
    </div>

    <header class="header">
        <div class="logo">
            <h1><?php echo htmlspecialchars(joyeria_marca_nombre(), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars(joyeria_marca_tagline(), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <nav class="nav">
            <ul>
                <li class="nav-item-dropdown">
                    <a href="../catalogo.php" class="nav-link-dropdown">Catálogo</a>
                    <?php if ($menuCatalogoFamilias !== []): ?>
                        <div class="nav-dropdown-panel" aria-label="Familias y subfamilias del catálogo">
                            <?php foreach ($menuCatalogoFamilias as $idFamMenu => $grupoMenu): ?>
                                <section class="nav-dropdown-family">
                                    <h3><?php echo htmlspecialchars((string) ($grupoMenu['nombre'] ?? 'Familia'), ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <ul>
                                        <?php foreach (($grupoMenu['subfamilias'] ?? []) as $idSubMenu => $nomSubMenu): ?>
                                            <li>
                                                <a href="../catalogo.php?fam=<?php echo (int) $idFamMenu; ?>&sub=<?php echo (int) $idSubMenu; ?>">
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
                <li><a href="compras.php">Compras</a></li>
            </ul>
        </nav>

        <div class="header-icons">
            <a href="#buscadorPiezas" class="icon" id="btnAbrirBuscador" role="button" aria-controls="buscadorPiezas" aria-expanded="false" aria-label="Buscar piezas" title="Buscar piezas">
                <svg viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="7"></circle>
                    <line x1="16.65" y1="16.65" x2="21" y2="21"></line>
                </svg>
            </a>
            <a href="cuenta.php" class="icon" aria-label="Mi cuenta" title="Mi cuenta">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4 20c0-4 4-6 8-6s8 2 8 6"></path>
                </svg>
            </a>
            <a href="carrito.php" class="icon cart" aria-label="Carrito" title="Carrito">
                <svg viewBox="0 0 24 24">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.6 13h10.8l2-8H6"></path>
                </svg>
                <span class="cart-count d-none">0</span>
            </a>
        </div>
    </header>

    <section class="vitrina-editorial" id="vitrina" data-vitrina="1" tabindex="0" aria-roledescription="carrusel">
        <div class="vitrina-inner">
            <div class="vitrina-stage">
                <?php foreach ($vitrinaNarrativas as $vi => $n):
                    $piezaV = ($piezasVitrinaCount > 0)
                        ? $fuenteVitrina[$vi % $piezasVitrinaCount]
                        : null;
                    $tituloPieza = $piezaV ? htmlspecialchars((string) ($piezaV['desc_pieza'] ?? ''), ENT_QUOTES, 'UTF-8') : 'Próximamente en sala';
                    $precioInfoVitrina = $piezaV ? joyeria_precio_tienda_publica(
                        $piezaV,
                        $promoAudiencia,
                        $ctxPreciosCliente['id_cliente'],
                        $ctxPreciosCliente['subtotal_lista_carrito']
                    ) : null;
                    $imgUrlV = $piezaV ? joyeria_user_img_src(joyeria_resolver_url_imagen((string) ($piezaV['url_imagen'] ?? ''))) : null;
                    $idPiezaV = $piezaV ? (int) ($piezaV['id_pieza'] ?? 0) : 0;
                    $variantesResumenV = ($piezaV && is_array($piezaV['variantes_resumen'] ?? null))
                        ? $piezaV['variantes_resumen']
                        : [];
                    $tieneVariantesV = !empty($variantesResumenV['tiene_variantes']);
                    ?>
                    <article class="vitrina-pane <?php echo htmlspecialchars($n['variant'], ENT_QUOTES, 'UTF-8'); ?><?php echo $vi === 0 ? ' is-active' : ''; ?>"
                        id="pane-vitrina-<?php echo (int) $vi; ?>"
                        role="group"
                        aria-label="<?php echo htmlspecialchars($n['etiqueta'] . ': ' . $n['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                        aria-hidden="<?php echo $vi === 0 ? 'false' : 'true'; ?>"
                        data-vitrina-pane
                        data-vitrina-index="<?php echo (int) $vi; ?>">
                        <div class="vitrina-pane-grid">
                            <div class="vitrina-pane-copy">
                                <p class="vitrina-pane-eyebrow"><?php echo htmlspecialchars($n['etiqueta'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <h3 class="vitrina-pane-title"><?php echo htmlspecialchars($n['titulo'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="vitrina-pane-text"><?php echo htmlspecialchars($n['texto'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="vitrina-pane-pieza-meta">
                                    <span class="vitrina-pane-pieza-label">En esta vista</span>
                                    <?php if ($idPiezaV > 0): ?>
                                        <button type="button" class="vitrina-pane-pieza-trigger"
                                                data-pieza-action="ver"
                                                data-id-pieza="<?php echo $idPiezaV; ?>"
                                                <?php if ($tieneVariantesV): ?>data-tiene-variantes="1"<?php endif; ?>
                                                aria-label="Ver detalle: <?php echo $tituloPieza; ?>">
                                            <strong class="vitrina-pane-pieza-name"><?php echo $tituloPieza; ?></strong>
                                            <?php if ($precioInfoVitrina !== null): ?>
                                                <span class="vitrina-pane-pieza-price"><?php joyeria_render_precio_producto_catalogo($precioInfoVitrina, ['prefijo_desde' => true, 'tag' => 'span']); ?></span>
                                            <?php endif; ?>
                                        </button>
                                    <?php else: ?>
                                        <strong class="vitrina-pane-pieza-name"><?php echo $tituloPieza; ?></strong>
                                        <?php if ($precioInfoVitrina !== null): ?>
                                            <span class="vitrina-pane-pieza-price"><?php joyeria_render_precio_producto_catalogo($precioInfoVitrina, ['prefijo_desde' => true, 'tag' => 'span']); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="vitrina-pane-actions">
                                    <?php if ($idPiezaV > 0): ?>
                                        <button type="button" class="vitrina-pane-cta vitrina-pane-cta--pieza"
                                                data-pieza-action="ver"
                                                data-id-pieza="<?php echo $idPiezaV; ?>"
                                                <?php if ($tieneVariantesV): ?>data-tiene-variantes="1"<?php endif; ?>>
                                            Ver pieza
                                        </button>
                                    <?php endif; ?>
                                    <a href="#catalogo" class="vitrina-pane-cta">Ver todo el catálogo</a>
                                </div>
                            </div>
                            <div class="vitrina-pane-visual">
                                <?php if ($piezaV && $imgUrlV !== null): ?>
                                    <?php if ($idPiezaV > 0): ?>
                                        <button type="button" class="vitrina-photo-frame vitrina-pane-pieza-trigger"
                                                data-pieza-action="ver"
                                                data-id-pieza="<?php echo $idPiezaV; ?>"
                                                <?php if ($tieneVariantesV): ?>data-tiene-variantes="1"<?php endif; ?>
                                                aria-label="Ver detalle: <?php echo $tituloPieza; ?>">
                                            <img src="<?php echo htmlspecialchars($imgUrlV, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $tituloPieza; ?>" width="640" height="720" loading="<?php echo $vi === 0 ? 'eager' : 'lazy'; ?>">
                                        </button>
                                    <?php else: ?>
                                        <div class="vitrina-photo-frame">
                                            <img src="<?php echo htmlspecialchars($imgUrlV, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $tituloPieza; ?>" width="640" height="720" loading="<?php echo $vi === 0 ? 'eager' : 'lazy'; ?>">
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="vitrina-photo-placeholder" aria-hidden="true">
                                        <span class="vitrina-placeholder-ring"></span>
                                        <span class="vitrina-placeholder-shimmer"></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    <?php
                endforeach;
                ?>
            </div>
        </div>
    </section>

    <?php if ($familiasDestacadas !== []): ?>
    <section class="familias-spotlight" aria-label="Colecciones por familia">
        <div class="familias-spotlight-inner">
            <p class="familias-spotlight-eyebrow">Familia</p>
            <div class="familias-spotlight-stage">
            <div class="catalog-carousel familias-spotlight-carousel" data-catalog-carousel>
                <button type="button" class="catalog-carousel-nav catalog-carousel-prev" aria-label="Ver familia anterior">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <div class="catalog-carousel-track familias-spotlight-track" tabindex="0" role="region" aria-label="Carrusel de familias destacadas">
                    <?php foreach ($familiasDestacadas as $fd): ?>
                        <?php
                            $nomFamDest = htmlspecialchars((string) ($fd['nom_familia'] ?? 'Colección'), ENT_QUOTES, 'UTF-8');
                            $descFamDestRaw = trim((string) ($fd['desc_pieza'] ?? ''));
                            $descFamDest = htmlspecialchars($descFamDestRaw !== '' ? $descFamDestRaw : 'Pieza destacada', ENT_QUOTES, 'UTF-8');
                            $hrefFam = '#familia-' . (int) ($fd['id_familia'] ?? 0);
                            $imgFam = htmlspecialchars((string) (joyeria_user_img_src((string) ($fd['img'] ?? '')) ?? ''), ENT_QUOTES, 'UTF-8');
                        ?>
                        <a class="catalog-carousel-card familias-spotlight-card" href="<?php echo $hrefFam; ?>" aria-label="Ver familia <?php echo $nomFamDest; ?>">
                            <img src="<?php echo $imgFam; ?>" alt="<?php echo $descFamDest; ?>" loading="lazy" width="560" height="720">
                            <span class="familias-spotlight-overlay"></span>
                            <span class="familias-spotlight-copy">
                                <span class="familias-spotlight-kicker">Familia</span>
                                <strong><?php echo $nomFamDest; ?></strong>
                                <em><?php echo $descFamDest; ?></em>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="catalog-carousel-nav catalog-carousel-next" aria-label="Ver siguiente familia">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
            <div class="familias-spotlight-dots" role="tablist" aria-label="Familias destacadas"></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="colecciones catalogo-section" id="catalogo">
        <div class="catalogo-inner">
            <?php if ($catalogoError): ?>
                <p class="catalogo-empty text-muted">No pudimos cargar el catálogo en este momento. Intenta más tarde.</p>
            <?php elseif ($piezasPorFamilia === []): ?>
                <p class="catalogo-empty text-muted">Pronto publicaremos nuevas piezas en esta vitrina.</p>
            <?php else: ?>
                <?php
                $filaCatalogo = 0;
                $promoRotate = 0;
                $promocionalesCount = count($promocionalesCatalogo);
                $piezasConImgCount = count($listadoPlanoPiezasConImagen);
                ?>
                <div class="catalogo-flow">
                <?php foreach ($piezasPorFamilia as $idFamKey => $grupo): ?>
                    <?php
                    $itemsConImagen = [];
                    foreach ($grupo['items'] as $piezaItem) {
                        if (!is_array($piezaItem)) {
                            continue;
                        }
                        $imgPathItem = joyeria_user_img_src(joyeria_resolver_url_imagen((string) ($piezaItem['url_imagen'] ?? '')));
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
                    <div class="catalog-group" id="familia-<?php echo (int) $idFamKey; ?>">
                        <h3 class="catalog-group-title"><?php echo htmlspecialchars($grupo['nom_familia'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="catalog-carousel catalog-carousel--pieces" data-catalog-carousel>
                            <button type="button" class="catalog-carousel-nav catalog-carousel-prev" aria-label="Mostrar pieza anterior">
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </button>
                            <div class="catalog-carousel-track" tabindex="0" role="region" aria-label="Piezas de <?php echo htmlspecialchars($grupo['nom_familia'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php foreach ($itemsConImagen as $pieza): ?>
                                    <?php
                                        if (!is_array($pieza)) {
                                            continue;
                                        }
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
                                        $imgSrc = (string) ($pieza['__img_resuelta'] ?? '');
                                        $imgHref = htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8');
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
                                            <img src="<?php echo $imgHref; ?>" alt="<?php echo $desc; ?>" loading="lazy" width="400" height="400">
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
                    $filaCatalogo++;
                    if ($promocionalesCount > 0 && $filaCatalogo % 3 === 0):
                        $tplPromo = $promocionalesCatalogo[$promoRotate % $promocionalesCount];
                        $promoRotate++;
                        [$imgPromoUrl, $imgPromoAlt] = joyeria_banner_resolver_visual(
                            $tplPromo,
                            $promoRotate - 1,
                            $piezasConImgCount,
                            $listadoPlanoPiezasConImagen,
                            $imagenPiezaPorIdBanner
                        );
                        $imgPromoUrl = joyeria_user_img_src($imgPromoUrl);
                        joyeria_catalogo_banner_render_strip($tplPromo, $imgPromoUrl, $imgPromoAlt);
                    endif; ?>
                <?php endforeach; ?>
                </div>
                <p class="catalogo-empty catalogo-empty-busqueda text-muted is-hidden" data-empty-busqueda>No encontramos piezas que coincidan con tu búsqueda. Prueba ajustar los filtros.</p>
            <?php endif; ?>
        </div>
    </section>

    <footer class="footer">

    <div class="footer-container">

        <div class="footer-col">
            <h4>DIRECCIÓN</h4>
            <p class="footer-address">
                <?php echo htmlspecialchars(joyeria_negocio_domicilio(), ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <h4>CONTACTO</h4>
            <a href="mailto:djlexus134@gmail.com" class="footer-link">
                djlexus134@gmail.com
            </a>

            <div class="footer-brand">
                <?php echo htmlspecialchars(JOYERIA_MARCA_FOOTER, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>

        <div class="footer-col">
            <h4>MAPA DE SITIO</h4>  
            <ul class="footer-menu">
                <li><a href="#vitrina">Vitrina</a></li>
                <li><a href="#catalogo">Catálogo</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Nuestra Historia</a></li>
                <li><a href="#">Mayoreo</a></li>
                <li><a href="#">Promociones y restricciones</a></li>
            </ul>
        </div>
    </div>
</footer>
    <?php
    $promoBarAudiencia = $promoAudiencia;
    require __DIR__ . '/../includes/tienda_barra_promo_inferior.php';
    ?>
    <?php
    $modalPiezaApiUrl = '../tienda_pieza_api.php';
    $modalCarritoApiUrl = '../tienda_carrito_api.php';
    require __DIR__ . '/../includes/modal_pieza_detalle.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/site-nav.js"></script>
    <script src="../js/catalog-carousel.js"></script>
    <script src="../js/catalog-vitrina.js"></script>
    <script src="../js/landing-motion.js"></script>
    <script src="../js/catalog-search.js"></script>
    <script src="../js/tienda-carrito.js"></script>
</body>
</html>