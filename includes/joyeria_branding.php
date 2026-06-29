<?php
declare(strict_types=1);

/** Nombre comercial visible en la tienda y panel admin. */
const JOYERIA_MARCA_NOMBRE = 'Platería 0.925';

/** Subtítulo bajo el logo (header público). */
const JOYERIA_MARCA_TAGLINE = 'Plata ley .925 · diseño y confianza';

/** Sufijo para etiquetas &lt;title&gt; de páginas públicas. */
const JOYERIA_MARCA_TITULO_SUFIJO = 'Plata ley .925';

/** Nombre en mayúsculas para footer. */
const JOYERIA_MARCA_FOOTER = 'PLATERÍA 0.925';

function joyeria_marca_nombre(): string
{
    return JOYERIA_MARCA_NOMBRE;
}

function joyeria_marca_tagline(): string
{
    return JOYERIA_MARCA_TAGLINE;
}

function joyeria_marca_titulo(string $seccion): string
{
    return $seccion . ' | ' . JOYERIA_MARCA_NOMBRE;
}

/**
 * Domicilio del negocio (configuración → contrato_domicilio_fuente_trabajo).
 */
function joyeria_negocio_domicilio(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    require_once __DIR__ . '/../admin/includes/configuracion_plantilla_defaults.php';
    $default = trim((string) (configuracion_plantilla_defaults()['contrato_domicilio_fuente_trabajo'] ?? ''));

    try {
        require_once __DIR__ . '/../admin/models/configuracion_general.php';
        $map = (new ConfiguracionGeneral())->leerConDefaults(['contrato_domicilio_fuente_trabajo']);
        $cached = trim((string) ($map['contrato_domicilio_fuente_trabajo'] ?? $default));
    } catch (Throwable $e) {
        error_log('joyeria_negocio_domicilio: ' . $e->getMessage());
        $cached = $default;
    }

    if ($cached === '') {
        $cached = $default;
    }

    return $cached;
}

/**
 * Narrativas del carrusel editorial de la landing (vitrina).
 *
 * @return list<array{etiqueta: string, titulo: string, texto: string, variant: string}>
 */
function joyeria_vitrina_narrativas(): array
{
    return [
        [
            'etiqueta' => 'Ley .925',
            'titulo' => 'Plata auténtica, brillo que perdura',
            'texto' => 'Trabajamos plata de ley .925 con procesos cuidadosos para que cada pieza conserve su tono, resistencia y el valor que buscas en una joya de confianza.',
            'variant' => 'vitrina-pane--mist',
        ],
        [
            'etiqueta' => 'Diseño',
            'titulo' => 'Piezas con identidad propia',
            'texto' => 'Desde lo clásico hasta lo contemporáneo: modelos pensados para acompañarte a diario o marcar un momento especial con presencia y equilibrio.',
            'variant' => 'vitrina-pane--noir',
        ],
        [
            'etiqueta' => 'Taller',
            'titulo' => 'Hecho para durar',
            'texto' => 'Seleccionamos materiales y acabados que respetan la plata y tu inversión: joyería pensada para usarse, conservarse y heredarse.',
            'variant' => 'vitrina-pane--sand',
        ],
        [
            'etiqueta' => 'Platería 0.925',
            'titulo' => 'Tu platería de confianza',
            'texto' => 'En Platería 0.925 encontrarás atención cercana, piezas seleccionadas y la certeza de llevar contigo plata ley .925 con estilo y respaldo.',
            'variant' => 'vitrina-pane--noir',
        ],
    ];
}
