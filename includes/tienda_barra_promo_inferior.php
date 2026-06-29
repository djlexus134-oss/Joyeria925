<?php
declare(strict_types=1);

/**
 * Barra inferior fija deslizable en catálogo público / zona cliente.
 * Requiere $promoBarAudiencia ('visitante'|'cliente') definida por la página anfitriona.
 */
if (!isset($promoBarAudiencia) || !is_string($promoBarAudiencia)) {
    return;
}

require_once __DIR__ . '/catalogo_banner_promos.php';

joyeria_render_promo_barra_inferior($promoBarAudiencia);
