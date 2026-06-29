<?php
$esEdicion = isset($banner) && is_array($banner) && ($banner !== []);
$formTitle = $esEdicion ? 'Editar banner' : 'Nuevo banner';
$formAction = $esEdicion
    ? ('promociones_banner.php?accion=actualizar&id=' . urlencode((string) (int) ($banner['id_promocion_banner'] ?? 0)))
    : 'promociones_banner.php?accion=crear';

$ordenVal = isset($_POST['orden']) ? (string) $_POST['orden'] : ($esEdicion ? (string) (int) ($banner['orden'] ?? 0) : '0');
$variVal = isset($_POST['variante']) ? (string) $_POST['variante'] : ($esEdicion ? (string) ($banner['variante'] ?? 'mayoreo') : 'mayoreo');
$eyebRow = isset($_POST['eyebrow']) ? (string) $_POST['eyebrow'] : ($esEdicion ? (string) ($banner['eyebrow'] ?? '') : '');
$tituVal = isset($_POST['titulo']) ? (string) $_POST['titulo'] : ($esEdicion ? (string) ($banner['titulo'] ?? '') : '');
$textVal = isset($_POST['texto']) ? (string) $_POST['texto'] : ($esEdicion ? (string) ($banner['texto'] ?? '') : '');
$ctalVal = isset($_POST['cta_label']) ? (string) $_POST['cta_label'] : ($esEdicion ? (string) ($banner['cta_label'] ?? '') : '');
$ctahVal = isset($_POST['cta_href']) ? (string) $_POST['cta_href'] : ($esEdicion ? (string) ($banner['cta_href'] ?? '') : '');
$fuenteVal = isset($_POST['fuente_imagen']) ? (string) $_POST['fuente_imagen'] : ($esEdicion ? (string) ($banner['fuente_imagen'] ?? 'ninguna') : 'ninguna');
$idPiezaVal = isset($_POST['id_pieza_fk']) ? (string) $_POST['id_pieza_fk'] : ($esEdicion ? (string) (int) ($banner['id_pieza_fk'] ?? 0) : '');
$fechaI = isset($_POST['fecha_inicio']) ? (string) $_POST['fecha_inicio'] : ($esEdicion ? (string) ($banner['fecha_inicio'] ?? '') : '');
$fechaF = isset($_POST['fecha_fin']) ? (string) $_POST['fecha_fin'] : ($esEdicion ? (string) ($banner['fecha_fin'] ?? '') : '');

$chkVV = isset($_POST['visible_visitante'])
    ? !empty($_POST['visible_visitante'])
    : ($esEdicion ? (int) ($banner['visible_visitante'] ?? 0) === 1 : true);
$chkVC = isset($_POST['visible_cliente'])
    ? !empty($_POST['visible_cliente'])
    : ($esEdicion ? (int) ($banner['visible_cliente'] ?? 0) === 1 : true);
$chkAct = isset($_POST['activo'])
    ? !empty($_POST['activo'])
    : ($esEdicion ? (int) ($banner['activo'] ?? 0) === 1 : true);
$chkTicker = isset($_POST['visible_ticker'])
    ? !empty($_POST['visible_ticker'])
    : ($esEdicion ? (int) ($banner['visible_ticker'] ?? 0) === 1 : false);
$chkBarraInferior = isset($_POST['visible_barra_inferior'])
    ? !empty($_POST['visible_barra_inferior'])
    : ($esEdicion ? (int) ($banner['visible_barra_inferior'] ?? 0) === 1 : false);
$tickerSegVal = isset($_POST['ticker_segmentos'])
    ? (string) $_POST['ticker_segmentos']
    : ($esEdicion ? (string) ($banner['ticker_segmentos'] ?? '') : '');
$esBannerBarra = $chkTicker || $chkBarraInferior;
?>

<div class="form-section">
    <h3><i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i> <?php echo htmlspecialchars($formTitle); ?></h3>

    <?php if (isset($mensaje) && $mensaje !== ''): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars((string) $mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || ($banner ?? null) !== null): ?>
    <form action="<?php echo htmlspecialchars($formAction); ?>" method="POST" class="admin-form">

        <?php if ($esEdicion): ?>
        <input type="hidden" name="_ver" value="1">
        <div class="form-row">
            <label class="form-group d-flex gap-2 align-items-center">
                <input type="checkbox" name="activo" value="1" <?php echo $chkAct ? 'checked' : ''; ?>>
                <span>Banner activo</span>
            </label>
        </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="orden">Orden<span class="required">*</span></label>
                <input type="number" class="form-input" id="orden" name="orden" min="0" max="65535"
                       value="<?php echo htmlspecialchars($ordenVal); ?>" required>
            </div>
            <div class="form-group">
                <label for="variante">Estilo CSS (variacion)</label>
                <select class="form-input" id="variante" name="variante" required>
                    <?php foreach (['mayoreo', 'pieza', 'trabajo', 'tradicion'] as $v): ?>
                        <option value="<?php echo htmlspecialchars($v); ?>" <?php echo $variVal === $v ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <label class="form-group d-flex gap-2 align-items-center">
                <input type="checkbox" name="visible_visitante" value="1" <?php echo $chkVV ? 'checked' : ''; ?>>
                <span>Visible en sitio público (catálogo visitante)</span>
            </label>
            <label class="form-group d-flex gap-2 align-items-center">
                <input type="checkbox" name="visible_cliente" value="1" <?php echo $chkVC ? 'checked' : ''; ?>>
                <span>Visible en zona cliente (usuario logueado)</span>
            </label>
        </div>

        <div class="form-divider">
            <p><strong>Barra superior (ticker negro):</strong></p>
        </div>

        <div class="form-row">
            <label class="form-group d-flex gap-2 align-items-center">
                <input type="checkbox" name="visible_ticker" value="1" id="visible_ticker" <?php echo $chkTicker ? 'checked' : ''; ?>>
                <span>Mostrar en barra superior fija</span>
            </label>
        </div>

        <div class="form-group">
            <label for="ticker_segmentos">Segmentos de la barra (opcional)</label>
            <textarea class="form-input" id="ticker_segmentos" name="ticker_segmentos" rows="4" maxlength="1024"
                      placeholder="50% OFF|+ HASTA 12 MSI*|DEL 23 DE JUNIO AL 27 DE JULIO 2026|COMPRA AHORA"><?php echo htmlspecialchars($tickerSegVal); ?></textarea>
            <small class="form-text text-muted d-block mt-1">
                Un mensaje por línea o separados con <code>|</code>. Si lo dejas vacío, se arma desde subtítulo, título, fechas y botón. Aplica a barra superior (ticker) y barra inferior si las activas.
            </small>
        </div>

        <?php
        require_once __DIR__ . '/../../../includes/catalogo_banner_promos.php';
        $previewTickerRow = [
            'eyebrow' => $eyebRow,
            'titulo' => $tituVal,
            'cta_label' => $ctalVal,
            'fecha_inicio' => $fechaI !== '' ? $fechaI : null,
            'fecha_fin' => $fechaF !== '' ? $fechaF : null,
            'ticker_segmentos' => $tickerSegVal,
        ];
        $previewTickerSegs = joyeria_promocion_ticker_segmentos_desde_banner($previewTickerRow);
        if ($previewTickerSegs !== []):
        ?>
        <div class="form-group">
            <label><i class="bi bi-eye"></i> Vista previa barra superior</label>
            <div class="promo-ticker-bar promo-ticker-bar--preview" role="presentation">
                <div class="promo-ticker-viewport">
                    <div class="promo-ticker-track promo-ticker-track--static">
                        <div class="promo-ticker-group">
                            <?php
                            $lastPreview = count($previewTickerSegs) - 1;
                            foreach ($previewTickerSegs as $pi => $pseg):
                            ?>
                                <span class="promo-ticker-segment"><?php echo htmlspecialchars($pseg, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($pi < $lastPreview): ?>
                                    <span class="promo-ticker-dot" aria-hidden="true"></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-divider">
            <p><strong>Barra inferior fija (deslizable):</strong></p>
        </div>

        <div class="form-row">
            <label class="form-group d-flex gap-2 align-items-center">
                <input type="checkbox" name="visible_barra_inferior" value="1" id="visible_barra_inferior" <?php echo $chkBarraInferior ? 'checked' : ''; ?>>
                <span>Visible en barra inferior fija (catálogo)</span>
            </label>
        </div>

        <?php
        $previewBarraRow = [
            'eyebrow' => $eyebRow,
            'titulo' => $tituVal,
            'cta_label' => $ctalVal,
            'fecha_inicio' => $fechaI !== '' ? $fechaI : null,
            'fecha_fin' => $fechaF !== '' ? $fechaF : null,
            'ticker_segmentos' => $tickerSegVal,
        ];
        $previewBarraSegs = joyeria_promocion_ticker_segmentos_desde_banner($previewBarraRow);
        if ($previewBarraSegs !== []):
        ?>
        <div class="form-group">
            <label><i class="bi bi-eye"></i> Vista previa barra inferior</label>
            <div class="promo-bar-inferior promo-bar-inferior--preview" role="presentation">
                <div class="promo-bar-inferior-viewport">
                    <div class="promo-bar-inferior-track promo-bar-inferior-track--static">
                        <div class="promo-bar-inferior-group">
                            <?php
                            $lastPreviewBarra = count($previewBarraSegs) - 1;
                            foreach ($previewBarraSegs as $pi => $pseg):
                            ?>
                                <span class="promo-bar-inferior-segment"><?php echo htmlspecialchars($pseg, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($pi < $lastPreviewBarra): ?>
                                    <span class="promo-bar-inferior-dot" aria-hidden="true"></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="eyebrow">Subtitulo corto / eyebrow (opcional)</label>
            <input type="text" class="form-input" id="eyebrow" name="eyebrow" maxlength="255"
                   value="<?php echo htmlspecialchars($eyebRow); ?>">
        </div>

        <div class="form-group">
            <label for="titulo">Titulo<?php if (!$esBannerBarra): ?><span class="required">*</span><?php else: ?> <small class="text-muted fw-normal">(opcional para barras)</small><?php endif; ?></label>
            <input type="text" class="form-input" id="titulo" name="titulo" maxlength="255"
                   <?php echo $esBannerBarra ? '' : 'required'; ?>
                   value="<?php echo htmlspecialchars($tituVal); ?>">
        </div>

        <div class="form-group">
            <label for="texto">Texto<?php if (!$esBannerBarra): ?><span class="required">*</span><?php else: ?> <small class="text-muted fw-normal">(opcional para barras)</small><?php endif; ?></label>
            <textarea class="form-input" id="texto" name="texto" rows="5"<?php echo $esBannerBarra ? '' : ' required'; ?>><?php echo htmlspecialchars($textVal); ?></textarea>
            <?php if ($esBannerBarra): ?>
            <small class="form-text text-muted d-block mt-1">
                Obligatorios solo para franjas intercaladas del catálogo. En barras superior/inferior usa los segmentos deslizables.
            </small>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="cta_label">Texto boton (opcional)</label>
                <input type="text" class="form-input" id="cta_label" name="cta_label" maxlength="120"
                       value="<?php echo htmlspecialchars($ctalVal); ?>">
            </div>
            <div class="form-group">
                <label for="cta_href">Enlace del boton (opcional)</label>
                <input type="text" class="form-input" id="cta_href" name="cta_href" maxlength="512"
                       value="<?php echo htmlspecialchars($ctahVal); ?>"
                       placeholder="#catalogo o mailto:...">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="fuente_imagen">Imagen lateral</label>
                <select class="form-input" id="fuente_imagen" name="fuente_imagen" required>
                    <option value="ninguna" <?php echo $fuenteVal === 'ninguna' ? 'selected' : ''; ?>>Sin imagen</option>
                    <option value="catalogo_rotacion" <?php echo $fuenteVal === 'catalogo_rotacion' ? 'selected' : ''; ?>>Rotar piezas del catálogo visible</option>
                    <option value="pieza_fija" <?php echo $fuenteVal === 'pieza_fija' ? 'selected' : ''; ?>>Pieza fija</option>
                </select>
            </div>
            <div class="form-group" id="grp-pieza-fija">
                <label for="id_pieza_fk">Pieza (solo si imagen es pieza fija)</label>
                <select class="form-input" id="id_pieza_fk" name="id_pieza_fk">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach (($catalogosPiezas ?? []) as $pz): ?>
                        <option value="<?php echo (int) ($pz['id_pieza'] ?? 0); ?>"
                            <?php echo ($idPiezaVal !== '' && (string) (int) $idPiezaVal === (string) (int) ($pz['id_pieza'] ?? 0)) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) (($pz['desc_pieza'] ?? '') . ' — ' . ($pz['nom_sub_familia'] ?? ''))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="fecha_inicio">Vigencia desde (opcional)</label>
                <input type="date" class="form-input" id="fecha_inicio" name="fecha_inicio"
                       value="<?php echo htmlspecialchars($fechaI); ?>">
            </div>
            <div class="form-group">
                <label for="fecha_fin">Vigencia hasta (opcional)</label>
                <input type="date" class="form-input" id="fecha_fin" name="fecha_fin"
                       value="<?php echo htmlspecialchars($fechaF); ?>">
            </div>
        </div>

        <script>
            (function () {
                var sel = document.getElementById('fuente_imagen');
                var grp = document.getElementById('grp-pieza-fija');
                function sync() {
                    if (!grp) return;
                    grp.style.display = (sel && sel.value === 'pieza_fija') ? '' : 'none';
                }
                if (sel) sel.addEventListener('change', sync);
                sync();
            })();
            (function () {
                var chkTicker = document.getElementById('visible_ticker');
                var chkBarra = document.getElementById('visible_barra_inferior');
                var titulo = document.getElementById('titulo');
                var texto = document.getElementById('texto');
                if (!titulo || !texto) return;

                function esBannerBarra() {
                    return (chkTicker && chkTicker.checked) || (chkBarra && chkBarra.checked);
                }

                function syncTituloTextoRequired() {
                    var opcional = esBannerBarra();
                    titulo.required = !opcional;
                    texto.required = !opcional;
                }

                if (chkTicker) chkTicker.addEventListener('change', syncTituloTextoRequired);
                if (chkBarra) chkBarra.addEventListener('change', syncTituloTextoRequired);
                syncTituloTextoRequired();
            })();
        </script>

        <div class="form-actions-row">
            <button type="submit" class="btn-action-primary"><?php echo $esEdicion ? 'Guardar' : 'Crear'; ?></button>
            <a href="promociones_banner.php?accion=leer" class="btn-action-secondary">Volver</a>
        </div>
    </form>
    <?php else: ?>
        <p class="alert-message warning">Banner no encontrado.</p>
        <p><a href="promociones_banner.php">Volver</a></p>
    <?php endif; ?>
</div>
