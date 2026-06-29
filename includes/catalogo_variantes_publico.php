<?php
declare(strict_types=1);

require_once __DIR__ . '/variantes_stock_helpers.php';

/**
 * Helpers de variantes para catalogo publico y tienda en linea.
 */

/**
 * @param array<string, mixed> $variantesResumen
 */
function joyeria_render_variantes_catalogo_card(array $variantesResumen): void
{
    if (empty($variantesResumen['tiene_variantes'])) {
        return;
    }

    $modo = (string) ($variantesResumen['modo'] ?? $variantesResumen['variante_tipo'] ?? 'ninguna');
    if ($modo === 'talla_color' || $modo === 'dos_ejes') {
        joyeria_render_variantes_matriz_catalogo_card($variantesResumen);

        return;
    }

    if (!is_array($variantesResumen['variantes'] ?? null)) {
        return;
    }
    $variantes = $variantesResumen['variantes'];
    if ($variantes === []) {
        return;
    }
    $etiqueta = (string) ($variantesResumen['variante_etiqueta'] ?? 'Variante');
    ?>
    <div class="producto-variantes-resumen" aria-label="Disponibilidad por <?php echo htmlspecialchars(strtolower($etiqueta), ENT_QUOTES, 'UTF-8'); ?>">
        <span class="producto-variantes-etiqueta"><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?>:</span>
        <?php foreach ($variantes as $var): ?>
            <?php
            if (!is_array($var)) {
                continue;
            }
            $valor = trim((string) ($var['valor'] ?? $var['valor1'] ?? ''));
            $cant = (int) ($var['cantidad'] ?? 0);
            if ($valor === '' || $cant <= 0) {
                continue;
            }
            ?>
            <span class="producto-variante-chip" title="<?php echo htmlspecialchars($cant . ' disponible(s)', ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>
                <strong>(<?php echo $cant; ?>)</strong>
            </span>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * @param array<string, mixed> $variantesResumen
 */
function joyeria_render_variantes_matriz_catalogo_card(array $variantesResumen): void
{
    $ejes = is_array($variantesResumen['ejes'] ?? null) ? $variantesResumen['ejes'] : [];
    $valores1 = is_array($ejes[0]['valores'] ?? null) ? $ejes[0]['valores'] : [];
    $valores2 = is_array($ejes[1]['valores'] ?? null) ? $ejes[1]['valores'] : [];
    $matriz = is_array($variantesResumen['matriz'] ?? null) ? $variantesResumen['matriz'] : [];

    if ($valores1 === [] || $valores2 === []) {
        $valores1 = is_array($variantesResumen['colores'] ?? null) ? $variantesResumen['colores'] : [];
        $valores2 = is_array($variantesResumen['tallas'] ?? null) ? $variantesResumen['tallas'] : [];
    }
    if ($valores1 === [] || $valores2 === []) {
        return;
    }

    $tipo1 = trim((string) ($ejes[0]['tipo'] ?? 'Opción'));
    $tipo2 = trim((string) ($ejes[1]['tipo'] ?? 'Opción'));
    $esTalla2 = !empty($ejes[1]['es_talla']);
    $etiqResumen = $tipo1 . ' y ' . $tipo2;
    ?>
    <div class="producto-variantes-resumen producto-variantes-matriz" aria-label="Disponibilidad por <?php echo htmlspecialchars(strtolower($etiqResumen), ENT_QUOTES, 'UTF-8'); ?>">
        <span class="producto-variantes-etiqueta"><?php echo htmlspecialchars($etiqResumen, ENT_QUOTES, 'UTF-8'); ?>:</span>
        <?php foreach ($valores1 as $valor1): ?>
            <?php
            $v1Str = trim((string) $valor1);
            if ($v1Str === '' || !isset($matriz[$v1Str]) || !is_array($matriz[$v1Str])) {
                continue;
            }
            $partes2 = [];
            foreach ($valores2 as $valor2) {
                $v2Str = trim((string) $valor2);
                if ($v2Str === '' || !isset($matriz[$v1Str][$v2Str])) {
                    continue;
                }
                $cant = (int) $matriz[$v1Str][$v2Str];
                if ($cant <= 0) {
                    continue;
                }
                $pref = $esTalla2 ? 'T' : '';
                $partes2[] = htmlspecialchars($pref . $v2Str, ENT_QUOTES, 'UTF-8') . ' (' . $cant . ')';
            }
            if ($partes2 === []) {
                continue;
            }
            ?>
            <span class="producto-variante-grupo" title="Disponibilidad en <?php echo htmlspecialchars($tipo1, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($v1Str, ENT_QUOTES, 'UTF-8'); ?>">
                <strong><?php echo htmlspecialchars($v1Str, ENT_QUOTES, 'UTF-8'); ?>:</strong>
                <?php echo implode(' · ', $partes2); ?>
            </span>
        <?php endforeach; ?>
    </div>
    <?php
}
