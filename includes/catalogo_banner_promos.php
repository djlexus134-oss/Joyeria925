<?php
declare(strict_types=1);

require_once __DIR__ . '/joyeria_imagen_publica.php';

/**
 * Fallback si la BD está vacía o hay error de conexión.
 *
 * @return array<int, array<string, mixed>>
 */
function joyeria_promociones_banner_fallback(): array
{
    return [
        [
            'variant' => 'mayoreo',
            'eyebrow' => 'Mayoreo',
            'titulo' => '50% en selección desde $6,000 MXN',
            'texto' => 'En compras a precio de etiqueta desde seis mil pesos elegibles: armamos tu selección y te llevas medio precio sobre piezas marcadas como mayoreo. Pregunta en sala o por correo para condiciones y vigencia.',
            'cta_label' => 'Ver piezas',
            'cta_href' => '#catalogo',
            'fuente_imagen' => 'ninguna',
            'id_pieza_fk' => null,
            'imagen_catalogo' => false,
        ],
        [
            'variant' => 'pieza',
            'eyebrow' => 'Pieza del momento',
            'titulo' => 'Un detalle que abraza sin apretar',
            'texto' => 'La plata bien trabajada vive contigo todos los días: en la luz del café, en el abrazo de quien amas. Elige algo que te recuerde que lo bello también es sencillo.',
            'cta_label' => 'Explorar catálogo',
            'cta_href' => '#catalogo',
            'fuente_imagen' => 'catalogo_rotacion',
            'id_pieza_fk' => null,
            'imagen_catalogo' => true,
        ],
        [
            'variant' => 'trabajo',
            'eyebrow' => 'Hecho aquí',
            'titulo' => 'Tradición de taller, acabados de hoy',
            'texto' => 'Combinamos oficio joyero con piezas pensadas para durar y para regalar con orgullo. Pasa por el centro de Celaya o escríbenos para un pedido especial.',
            'cta_label' => 'Contacto',
            'cta_href' => 'mailto:djlexus134@gmail.com',
            'fuente_imagen' => 'ninguna',
            'id_pieza_fk' => null,
            'imagen_catalogo' => false,
        ],
    ];
}

/**
 * @param array<string, mixed> $row Fila PDO de promociones_banner.
 * @return array<string, mixed>
 */
function joyeria_promociones_banner_mapear_fila_publica(array $row): array
{
    $fuente = (string) ($row['fuente_imagen'] ?? 'ninguna');

    return [
        'variant' => (string) ($row['variante'] ?? 'mayoreo'),
        'eyebrow' => (string) ($row['eyebrow'] ?? ''),
        'titulo' => (string) ($row['titulo'] ?? ''),
        'texto' => (string) ($row['texto'] ?? ''),
        'cta_label' => trim((string) ($row['cta_label'] ?? '')),
        'cta_href' => trim((string) ($row['cta_href'] ?? '')),
        'fuente_imagen' => $fuente,
        'id_pieza_fk' => isset($row['id_pieza_fk']) && $row['id_pieza_fk'] !== null && $row['id_pieza_fk'] !== ''
            ? (int) $row['id_pieza_fk']
            : null,
        'imagen_catalogo' => $fuente === 'catalogo_rotacion',
    ];
}

/**
 * Franja intercalada del catálogo requiere título y texto (no aplica a barras superior/inferior).
 *
 * @param array<string, mixed> $row
 */
function joyeria_promociones_banner_tiene_contenido_franja(array $row): bool
{
    return trim((string) ($row['titulo'] ?? '')) !== ''
        && trim((string) ($row['texto'] ?? '')) !== '';
}

/**
 * @param ''|'visitante'|'cliente' $audiencia '' = ambos (solo para admin previews; aquí no se usa).
 * @return array<int, array<string, mixed>>
 */
function joyeria_cargar_promociones_banner_catalogo(string $audiencia): array
{
    try {
        require_once __DIR__ . '/../admin/models/promociones_banner.php';
        $model = new PromocionesBanner();
        $rows = $model->listarActivosParaFrontend($audiencia);
        $out = [];
        $hayBannersActivos = false;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $hayBannersActivos = true;
            if (!joyeria_promociones_banner_tiene_contenido_franja($row)) {
                continue;
            }
            $mapped = joyeria_promociones_banner_mapear_fila_publica($row);
            if (((string) ($mapped['variant'] ?? '')) === 'tradicion') {
                continue;
            }
            $out[] = $mapped;
        }
        if ($out !== []) {
            return $out;
        }
        if ($hayBannersActivos) {
            return [];
        }
    } catch (Throwable $e) {
        error_log('Promociones banner catalogo: ' . $e->getMessage());
    }

    return joyeria_promociones_banner_fallback();
}

/**
 * Lista plana desde filas SQL + mapa id_pieza => imagen ya resuelta.
 *
 * @param array<int, array<string, mixed>> $filasPiezas
 * @return array{0: array<int, array<string, mixed>>, 1: array<int, mixed>, 2: array<int, array{url: string, desc: string}>}
 */
function joyeria_banner_catalogo_arrays_imagen(array $filasPiezas): array
{
    $listadoPlano = [];
    $conImagen = [];
    $mapPorId = [];
    foreach ($filasPiezas as $p) {
        if (!is_array($p)) {
            continue;
        }
        $listadoPlano[] = $p;
        $uid = (int) ($p['id_pieza'] ?? 0);
        $u = joyeria_resolver_url_imagen((string) ($p['url_imagen'] ?? ''));
        if ($u === null) {
            continue;
        }
        $conImagen[] = $p;
        if ($uid > 0) {
            $mapPorId[$uid] = [
                'url' => $u,
                'desc' => trim((string) ($p['desc_pieza'] ?? '')),
            ];
        }
    }

    return [$listadoPlano, $conImagen, $mapPorId];
}

/**
 * @param array<string, mixed> $tplPlantilla Datos banner (fallback o BD).
 * @param array<int, array{url: string, desc: string}> $mapPiezaImg
 * @return array{0: ?string, 1: string} url, texto alt
 */
function joyeria_banner_resolver_visual(
    array $tplPlantilla,
    int $indiceSaltosBanner,
    int $piezasConImgCount,
    array $listaPiezasConImagen,
    array $mapPiezaImg
): array {
    $fuente = (string) ($tplPlantilla['fuente_imagen'] ?? 'ninguna');

    if ($fuente === 'catalogo_rotacion' && $piezasConImgCount > 0 && $listaPiezasConImagen !== []) {
        $pz = $listaPiezasConImagen[$indiceSaltosBanner % $piezasConImgCount];
        $url = joyeria_resolver_url_imagen((string) ($pz['url_imagen'] ?? ''));
        $desc = trim((string) ($pz['desc_pieza'] ?? ''));

        return [$url, $desc !== '' ? $desc : 'Pieza destacada'];
    }

    if ($fuente === 'pieza_fija') {
        $pid = isset($tplPlantilla['id_pieza_fk']) ? (int) $tplPlantilla['id_pieza_fk'] : 0;
        if ($pid > 0 && isset($mapPiezaImg[$pid])) {
            $d = $mapPiezaImg[$pid]['desc'];

            return [$mapPiezaImg[$pid]['url'], $d !== '' ? $d : 'Pieza destacada'];
        }
    }

    return [null, ''];
}

/**
 * Agrupa las filas de catálogo en piezasPorFamilia (misma agrupación que index visitante).
 *
 * @param array<int, mixed> $filasPiezas
 * @return array<int, array{nom_familia: string, items: array<int, mixed>}>
 */
/**
 * Marca única promo (landing / zona cliente): mismo marcado HTML que index visitante.
 */
function joyeria_catalogo_banner_render_strip(array $tplPromo, ?string $imgPromoUrl, string $imgPromoAlt): void
{
    if (!joyeria_promociones_banner_tiene_contenido_franja($tplPromo)) {
        return;
    }

    $variantPromo = (string) ($tplPromo['variant'] ?? $tplPromo['variante'] ?? 'mayoreo');
    $ebPromo = trim((string) ($tplPromo['eyebrow'] ?? ''));
    $titPromo = (string) ($tplPromo['titulo'] ?? 'Promoción');
    $ariaPromo = $ebPromo !== '' ? ($ebPromo . ': ' . $titPromo) : $titPromo;
    ?>
    <aside class="catalog-promo-stripe <?php echo htmlspecialchars('catalog-promo-stripe--' . $variantPromo, ENT_QUOTES, 'UTF-8'); ?>"
        aria-label="<?php echo htmlspecialchars($ariaPromo, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="catalog-promo-stripe-inner">
            <div class="catalog-promo-stripe-copy">
                <?php if ($ebPromo !== ''): ?>
                <p class="catalog-promo-stripe-eyebrow"><?php echo htmlspecialchars($ebPromo, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <h3 class="catalog-promo-stripe-title"><?php echo htmlspecialchars((string) ($tplPromo['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="catalog-promo-stripe-text"><?php echo htmlspecialchars((string) ($tplPromo['texto'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (trim((string) ($tplPromo['cta_label'] ?? '')) !== ''): ?>
                    <a class="catalog-promo-stripe-cta" href="<?php echo htmlspecialchars((string) ($tplPromo['cta_href'] ?? '#catalogo'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) ($tplPromo['cta_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($imgPromoUrl !== null): ?>
                <div class="catalog-promo-stripe-visual">
                    <div class="catalog-promo-stripe-frame">
                        <img src="<?php echo htmlspecialchars($imgPromoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="<?php echo htmlspecialchars($imgPromoAlt !== '' ? $imgPromoAlt : 'Pieza destacada', ENT_QUOTES, 'UTF-8'); ?>"
                            width="480" height="480" loading="lazy">
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </aside>
    <?php
}

/**
 * Fecha legible para segmentos del ticker (ej. 23 DE JUNIO DE 2026).
 */
function joyeria_promo_ticker_fecha(?string $fecha): string
{
    if ($fecha === null || trim($fecha) === '') {
        return '';
    }
    $ts = strtotime($fecha);
    if ($ts === false) {
        return '';
    }
    static $meses = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
        5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
        9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];
    $dia = (int) date('j', $ts);
    $mes = $meses[(int) date('n', $ts)] ?? '';
    $anio = date('Y', $ts);

    return $dia . ' DE ' . $mes . ' DE ' . $anio;
}

/**
 * @param array<string, mixed> $row Fila de promociones_banner con campos ticker.
 * @return array<int, string>
 */
function joyeria_promocion_ticker_segmentos_desde_banner(array $row): array
{
    $raw = trim((string) ($row['ticker_segmentos'] ?? ''));
    if ($raw !== '') {
        $parts = preg_split('/[\r\n|]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $seg = trim((string) $part);
            if ($seg !== '') {
                $out[] = mb_strtoupper($seg, 'UTF-8');
            }
        }
        if ($out !== []) {
            return $out;
        }
    }

    $out = [];
    $eyebrow = trim((string) ($row['eyebrow'] ?? ''));
    if ($eyebrow !== '') {
        $out[] = mb_strtoupper($eyebrow, 'UTF-8');
    }
    $titulo = trim((string) ($row['titulo'] ?? ''));
    if ($titulo !== '') {
        $out[] = mb_strtoupper($titulo, 'UTF-8');
    }
    $fi = joyeria_promo_ticker_fecha(isset($row['fecha_inicio']) ? (string) $row['fecha_inicio'] : null);
    $ff = joyeria_promo_ticker_fecha(isset($row['fecha_fin']) ? (string) $row['fecha_fin'] : null);
    if ($fi !== '' && $ff !== '') {
        $out[] = 'DEL ' . $fi . ' AL ' . $ff;
    } elseif ($ff !== '') {
        $out[] = 'HASTA EL ' . $ff;
    }
    $cta = trim((string) ($row['cta_label'] ?? ''));
    if ($cta !== '') {
        $out[] = mb_strtoupper($cta, 'UTF-8');
    }

    return $out;
}

/**
 * @param 'visitante'|'cliente' $audiencia
 * @return array<int, string>
 */
function joyeria_cargar_segmentos_barra_inferior(string $audiencia): array
{
    static $cache = [];

    if (isset($cache[$audiencia])) {
        return $cache[$audiencia];
    }

    $segmentos = [];
    try {
        require_once __DIR__ . '/../admin/models/promociones_banner.php';
        $model = new PromocionesBanner();
        $rows = $model->listarActivosParaBarraInferior($audiencia);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (joyeria_promocion_ticker_segmentos_desde_banner($row) as $seg) {
                $segmentos[] = $seg;
            }
        }
    } catch (Throwable $e) {
        error_log('Barra inferior promo: ' . $e->getMessage());
    }

    $cache[$audiencia] = $segmentos;

    return $segmentos;
}

/**
 * @param array<int, string> $segmentos
 */
function joyeria_render_promo_barra_inferior_segmentos(array $segmentos): void
{
    if ($segmentos === []) {
        return;
    }

    $last = count($segmentos) - 1;
    foreach ($segmentos as $i => $seg) {
        echo '<span class="promo-bar-inferior-segment">';
        echo htmlspecialchars($seg, ENT_QUOTES, 'UTF-8');
        echo '</span>';
        if ($i < $last) {
            echo '<span class="promo-bar-inferior-dot" aria-hidden="true"></span>';
        }
    }
}

/**
 * @param 'visitante'|'cliente' $audiencia
 */
function joyeria_render_promo_barra_inferior(string $audiencia): void
{
    $segmentos = joyeria_cargar_segmentos_barra_inferior($audiencia);
    if ($segmentos === []) {
        return;
    }
    ?>
    <div class="promo-bar-inferior" role="region" aria-label="Promoción vigente">
        <div class="promo-bar-inferior-viewport">
            <div class="promo-bar-inferior-track">
                <div class="promo-bar-inferior-group">
                    <?php joyeria_render_promo_barra_inferior_segmentos($segmentos); ?>
                </div>
                <div class="promo-bar-inferior-group" aria-hidden="true">
                    <?php joyeria_render_promo_barra_inferior_segmentos($segmentos); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function joyeria_banner_agrupar_piezas_por_familia(array $filasPiezas): array
{
    $piezasPorFamilia = [];
    foreach ($filasPiezas as $row) {
        if (!is_array($row)) {
            continue;
        }
        $idFam = (int) ($row['id_familia'] ?? 0);
        $nomFam = trim((string) ($row['nom_familia'] ?? ''));
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

    return $piezasPorFamilia;
}
