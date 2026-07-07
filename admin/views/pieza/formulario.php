<?php
require_once __DIR__ . '/../../../includes/pieza_dimension_helpers.php';

$esEdicion = isset($pieza) && !empty($pieza);
$titulo = $esEdicion ? 'Editar Pieza' : 'Nueva Pieza';
$accionForm = $esEdicion
    ? 'pieza.php?accion=actualizar&id=' . urlencode((string) $pieza['id_pieza'])
    : 'pieza.php?accion=crear';

$desc_pieza = $_POST['desc_pieza'] ?? ($esEdicion ? ($pieza['desc_pieza'] ?? '') : '');
$id_familia_FK = $_POST['id_familia_FK'] ?? '';
$id_sub_familia_FK = $_POST['id_sub_familia_FK'] ?? ($esEdicion ? ($pieza['id_sub_familia_FK'] ?? '') : '');
$id_metal_FK = $_POST['id_metal_FK'] ?? ($esEdicion ? ($pieza['id_metal_FK'] ?? '') : '');
$id_proveedor_FK = $_POST['id_proveedor_FK'] ?? ($esEdicion ? ($pieza['id_proveedor_FK'] ?? '') : '');
$id_tienda_FK = $_POST['id_tienda_FK'] ?? ($esEdicion ? ($pieza['id_tienda_FK'] ?? '') : (($idTiendaDefault ?? null) !== null ? (string) $idTiendaDefault : ''));
$peso_gr = $_POST['peso_gr'] ?? ($esEdicion ? ($pieza['peso_gr'] ?? '') : '');
$precio_por_gramo = $_POST['precio_por_gramo'] ?? ($esEdicion ? ($pieza['precio_por_gramo'] ?? '') : '');
$costo = $_POST['costo'] ?? ($esEdicion ? ($pieza['costo'] ?? '') : '');
$aumento_pct = $_POST['aumento_pct'] ?? ($esEdicion ? ($pieza['aumento_pct'] ?? '') : ($markupPctDefault ?? ''));
$stockTipoCodigoDefault = isset($tipoCodigoBarrasDefault) && in_array((string) $tipoCodigoBarrasDefault, ['EAN13', 'CODE128', 'QR'], true)
    ? (string) $tipoCodigoBarrasDefault
    : 'CODE128';
$largoRaw = $_POST['largo'] ?? ($esEdicion ? ($pieza['largo'] ?? '') : '');
$anchoRaw = $_POST['ancho'] ?? ($esEdicion ? ($pieza['ancho'] ?? '') : '');
$largo = joyeria_extraer_cantidad_dimension(is_string($largoRaw) ? $largoRaw : '');
$ancho = joyeria_extraer_cantidad_dimension(is_string($anchoRaw) ? $anchoRaw : '');
$observaciones = $_POST['observaciones'] ?? ($esEdicion ? ($pieza['observaciones'] ?? '') : '');
$imagenesPieza = $imagenesPieza ?? [];

$metodo_costo = $_POST['metodo_costo'] ?? '';
if ($metodo_costo === '') {
    $metodo_costo = ($precio_por_gramo !== '' && $precio_por_gramo !== null) ? 'por_gramo' : 'directo';
}

if ($id_familia_FK === '' && $id_sub_familia_FK !== '') {
    foreach (($catalogos['subfamilias'] ?? []) as $subfamiliaActual) {
        if ((string) ($subfamiliaActual['id_sub_familia'] ?? '') === (string) $id_sub_familia_FK) {
            $id_familia_FK = (string) ($subfamiliaActual['id_familia_FK'] ?? '');
            break;
        }
    }
}
?>

<div class="form-section">
    <style>
        .pieza-fk-create-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        .pieza-fk-create-select {
            flex: 1 1 auto;
            min-width: 0;
        }
        .pieza-fk-create-btn {
            flex: 0 0 auto;
            height: 42px;
            padding: 0 12px;
            border: 1px solid #c9a962;
            border-radius: 8px;
            background: #fff8e8;
            color: #6b4f1d;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
        }
        .pieza-fk-create-btn:hover {
            background: #f7e8c7;
        }
        .input-with-suffix {
            display: flex;
            align-items: stretch;
        }
        .input-with-suffix .form-input {
            flex: 1 1 auto;
            min-width: 0;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .input-with-suffix .input-suffix {
            display: inline-flex;
            align-items: center;
            padding: 0 12px;
            border: 1px solid #ced4da;
            border-left: 0;
            border-radius: 0 8px 8px 0;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 0.9rem;
            white-space: nowrap;
        }
    </style>
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($pieza)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form" enctype="multipart/form-data" id="form-pieza">
            <fieldset class="form-fieldset">
                <legend><i class="bi bi-gem"></i> Información base</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="desc_pieza"><i class="bi bi-card-text"></i> Descripción:</label>
                        <input type="text" class="form-input" name="desc_pieza" id="desc_pieza" maxlength="100" value="<?php echo htmlspecialchars($desc_pieza); ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="id_familia_FK"><i class="bi bi-collection"></i> Familia:</label>
                        <div class="pieza-fk-create-row">
                            <div class="pieza-fk-create-select">
                                <select class="form-input" name="id_familia_FK" id="id_familia_FK" required>
                                    <option value="">-- Selecciona familia --</option>
                                    <?php foreach (($catalogos['familias'] ?? []) as $familia): ?>
                                        <option value="<?php echo htmlspecialchars((string) $familia['id_familia']); ?>" data-usa-talla="<?php echo (int) ($familia['usa_talla'] ?? 0); ?>" <?php echo ((string) $id_familia_FK === (string) $familia['id_familia']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $familia['nom_familia']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="pieza-fk-create-btn" id="btn-nueva-familia">
                                <i class="bi bi-plus-circle"></i> Nueva
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="id_sub_familia_FK"><i class="bi bi-diagram-3"></i> Subfamilia:</label>
                        <div class="pieza-fk-create-row">
                            <div class="pieza-fk-create-select">
                                <select class="form-input" name="id_sub_familia_FK" id="id_sub_familia_FK" required>
                                    <option value="">-- Selecciona subfamilia --</option>
                                    <?php if ($id_familia_FK !== ''): ?>
                                        <?php foreach (($catalogos['subfamilias'] ?? []) as $subfamilia): ?>
                                            <?php if ((string) ($subfamilia['id_familia_FK'] ?? '') !== (string) $id_familia_FK) {
                                                continue;
                                            } ?>
                                            <option value="<?php echo htmlspecialchars((string) $subfamilia['id_sub_familia']); ?>" <?php echo ((string) $id_sub_familia_FK === (string) $subfamilia['id_sub_familia']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars((string) $subfamilia['nom_sub_familia']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button type="button" class="pieza-fk-create-btn" id="btn-nueva-subfamilia">
                                <i class="bi bi-plus-circle"></i> Nueva
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="id_metal_FK"><i class="bi bi-gem"></i> Metal:</label>
                        <div class="pieza-fk-create-row">
                            <div class="pieza-fk-create-select">
                                <select class="form-input" name="id_metal_FK" id="id_metal_FK" required>
                                    <option value="">-- Selecciona metal --</option>
                                    <?php foreach (($catalogos['metales'] ?? []) as $metal): ?>
                                        <option value="<?php echo $metal['id_metal']; ?>" <?php echo ((string) $id_metal_FK === (string) $metal['id_metal']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($metal['nom_metal']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="pieza-fk-create-btn" id="btn-nuevo-metal">
                                <i class="bi bi-plus-circle"></i> Nuevo
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="id_proveedor_FK"><i class="bi bi-truck"></i> Proveedor (opcional):</label>
                        <select class="form-input" name="id_proveedor_FK" id="id_proveedor_FK">
                            <option value="">-- Sin proveedor --</option>
                            <?php foreach (($catalogos['proveedores'] ?? []) as $proveedor): ?>
                                <option value="<?php echo $proveedor['id_proveedor']; ?>" <?php echo ((string) $id_proveedor_FK === (string) $proveedor['id_proveedor']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($proveedor['razon_social']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_tienda_FK"><i class="bi bi-shop"></i> Tienda:</label>
                        <select class="form-input" name="id_tienda_FK" id="id_tienda_FK" required>
                            <option value="">-- Selecciona tienda --</option>
                            <?php foreach (($catalogos['tiendas'] ?? []) as $tienda): ?>
                                <option value="<?php echo $tienda['id_tienda']; ?>" <?php echo ((string) $id_tienda_FK === (string) $tienda['id_tienda']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tienda['nom_tienda']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="largo"><i class="bi bi-arrows-vertical"></i> Alto (cm, opcional):</label>
                        <div class="input-with-suffix">
                            <input type="number" class="form-input" name="largo" id="largo" min="0" step="0.01" inputmode="decimal"
                                   value="<?php echo htmlspecialchars($largo); ?>" placeholder="Ej. 50">
                            <span class="input-suffix">cm</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ancho"><i class="bi bi-arrows-expand"></i> Ancho (cm, opcional):</label>
                        <div class="input-with-suffix">
                            <input type="number" class="form-input" name="ancho" id="ancho" min="0" step="0.01" inputmode="decimal"
                                   value="<?php echo htmlspecialchars($ancho); ?>" placeholder="Ej. 30">
                            <span class="input-suffix">cm</span>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-fieldset">
                <legend><i class="bi bi-cash-stack"></i> Costos y Precio de Venta</legend>

                <div class="form-row">
                    <div class="form-group" style="width:100%;">
                        <label><i class="bi bi-sliders"></i> Modo de precio de esta pieza:</label>
                        <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;">
                            <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;">
                                <input type="radio" name="modo_precio_form" id="modo_precio_catalogo" value="catalogo" checked>
                                Precio fijo de catálogo (costo + margen)
                            </label>
                            <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;">
                                <input type="radio" name="modo_precio_form" id="modo_precio_grilla" value="grilla">
                                Precio por variante — se define en la grilla de stock
                            </label>
                        </div>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Elige "por variante" para piezas como cadenas cuyo precio varía según la medida. Los campos de costo se deshabilitarán.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="width:100%;">
                        <label><i class="bi bi-calculator"></i> Método de cálculo del costo:</label>
                        <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;">
                            <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;">
                                <input type="radio" name="metodo_costo" value="directo" <?php echo $metodo_costo === 'directo' ? 'checked' : ''; ?>>
                                Costo directo
                            </label>
                            <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;">
                                <input type="radio" name="metodo_costo" value="por_gramo" <?php echo $metodo_costo === 'por_gramo' ? 'checked' : ''; ?>>
                                Por precio por gramo (peso x precio_por_gramo)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="peso_gr"><i class="bi bi-speedometer"></i> Peso (gr) <span id="peso-required-mark" style="display:none;color:#c0392b;">*</span>:</label>
                        <input type="number" class="form-input" name="peso_gr" id="peso_gr" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $peso_gr); ?>">
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Obligatorio en modo "por gramo"; opcional en modo directo.</small>
                    </div>

                    <div class="form-group" id="grupo-precio-por-gramo">
                        <label for="precio_por_gramo"><i class="bi bi-coin"></i> Precio por gramo:</label>
                        <input type="number" class="form-input" name="precio_por_gramo" id="precio_por_gramo" step="0.0001" min="0" value="<?php echo htmlspecialchars((string) $precio_por_gramo); ?>">
                    </div>

                    <div class="form-group">
                        <label for="costo"><i class="bi bi-currency-dollar"></i> Costo:</label>
                        <input type="number" class="form-input" name="costo" id="costo" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $costo); ?>">
                        <small class="form-hint" id="costo-hint"><i class="bi bi-info-circle"></i></small>
                    </div>

                    <div class="form-group">
                        <label for="aumento_pct"><i class="bi bi-graph-up-arrow"></i> Aumento (%):</label>
                        <input type="number" class="form-input" name="aumento_pct" id="aumento_pct" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $aumento_pct); ?>">
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Ejemplo: 80 = +80%. Si está vacío, precio_venta = costo.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="width:100%;">
                        <div class="alert-message info" style="margin:0;" id="preview-precio-wrap">
                            <p style="margin:0;">
                                <i class="bi bi-tag"></i>
                                Precio de venta calculado:
                                <strong>$<span id="preview-precio-venta">0.00</span></strong>
                            </p>
                            <p style="margin:4px 0 0;opacity:0.88;font-size:0.92rem;">
                                Costo $<span id="preview-costo">0.00</span>
                                + aumento <span id="preview-aumento">0</span>%
                                → $<span id="preview-precio-bruto">0.00</span>
                                <span style="opacity:0.75;"> (redondeo al siguiente múltiplo de $5)</span>
                            </p>
                        </div>
                        <div class="alert-message warning" id="aviso-costo-desde-grilla" style="display:none;margin-top:6px;">
                            <p style="margin:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <span><i class="bi bi-grid-3x3-gap"></i> El precio de venta se asignará desde la grilla de variantes. Los campos de costo están deshabilitados.</span>
                                <button type="button" class="btn-action-secondary" id="btn-restaurar-costo" style="padding:2px 10px;font-size:0.85rem;">
                                    <i class="bi bi-pencil"></i> Editar costo
                                </button>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="width:100%;">
                        <label for="observaciones"><i class="bi bi-chat-square-text"></i> Observaciones:</label>
                        <textarea class="form-input" name="observaciones" id="observaciones" rows="3"><?php echo htmlspecialchars((string) $observaciones); ?></textarea>
                    </div>
                </div>
            </fieldset>

            <?php
            $piezaUrlImagenActual = ($esEdicion && !empty($pieza['url_imagen'])) ? (string) $pieza['url_imagen'] : null;
            $mostrarImagenActual = $esEdicion;
            $piezaFotoScriptPath = __DIR__ . '/../../js/pieza-foto-capture.js';
            require __DIR__ . '/partials/foto_capture_fields.php';
            ?>

            <?php if ($esEdicion): ?>
            <fieldset class="form-fieldset">
                <legend><i class="bi bi-grid"></i> Imagenes registradas</legend>
                    <div class="form-row">
                        <div class="form-group" style="width:100%;">
                            <label><i class="bi bi-grid"></i> Galeria actual:</label>

                            <?php
                            $piezaGaleriaOrigenFoto = false;
                            require __DIR__ . '/partials/galeria_imagenes_acciones.php';
                            ?>
                        </div>
                    </div>
            </fieldset>
            <?php endif; ?>

            <?php if (is_file($piezaFotoScriptPath)): ?>
                <script src="js/pieza-foto-capture.js?v=<?php echo (int) filemtime($piezaFotoScriptPath); ?>"></script>
            <?php endif; ?>

            <input type="hidden" name="accion_guardar" id="accion_guardar" value="guardar">
            <input type="hidden" name="stock_cantidad" id="stock_cantidad_hidden" value="">
            <input type="hidden" name="stock_tipo_codigo" id="stock_tipo_codigo_hidden"
                   value="<?php echo htmlspecialchars($stockTipoCodigoDefault); ?>">
            <input type="hidden" name="stock_encolar_etiquetas" id="stock_encolar_etiquetas_hidden" value="1">
            <input type="hidden" name="stock_variante_modo" id="stock_variante_modo_hidden" value="ninguna">
            <input type="hidden" name="stock_eje1_tipo_id" id="stock_eje1_tipo_id_hidden" value="">
            <input type="hidden" name="stock_eje2_tipo_id" id="stock_eje2_tipo_id_hidden" value="">
            <input type="hidden" name="stock_matriz" id="stock_matriz_hidden" value="">
            <input type="hidden" name="costo_desde_grilla" id="costo_desde_grilla" value="0">
            <input type="hidden" name="grilla_metodo" id="grilla_metodo_hidden" value="directo">

            <div class="form-actions">
                <?php if ($esEdicion): ?>
                    <button type="submit" class="btn-action-primary" id="btn-guardar-normal">
                        <i class="bi bi-check-lg"></i> Guardar Cambios
                    </button>
                    <button type="button" class="btn-action-secondary"
                            data-etiqueta-accion="rango"
                            data-id-pieza="<?php echo (int) $pieza['id_pieza']; ?>"
                            data-titulo-pieza="<?php echo htmlspecialchars((string) $pieza['desc_pieza'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="bi bi-printer"></i> Encolar etiquetas
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-action-primary" id="btn-abrir-stock" style="background:#1f7a4d;">
                        <i class="bi bi-box-seam"></i> Guardar y generar stock
                    </button>
                <?php endif; ?>
                <a href="pieza.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>

        <?php if (!$esEdicion): ?>
            <div id="modal-stock" class="ja-modal-overlay" style="display:none;">
                <div class="ja-modal-card">
                    <h4 style="margin-top:0;">
                        <i class="bi bi-box-seam"></i> Generar stock para esta pieza
                    </h4>
                    <p style="margin-top:0;">Indica cuantas unidades quieres dar de alta junto con la pieza.</p>

                    <div class="form-group">
                        <label for="stock_variante_modo"><i class="bi bi-tags"></i> Variantes por unidad:</label>
                        <select class="form-input" id="stock_variante_modo">
                            <option value="ninguna">Ninguna</option>
                            <option value="ejes">Por ejes (grilla generable)</option>
                        </select>
                        <small class="form-hint" id="hint-variante-talla" style="display:none;">
                            <i class="bi bi-info-circle"></i> Los tipos marcados como talla solo estan disponibles para familias de anillos.
                        </small>
                    </div>

                    <div class="form-group" id="grp-cantidad">
                        <label for="stock_cantidad"><i class="bi bi-123"></i> Cantidad de piezas a generar:</label>
                        <input type="number" class="form-input" id="stock_cantidad" min="1" max="500" step="1" value="1">
                    </div>

                    <div class="form-group" id="grp-ejes-variantes" style="display:none;">
                        <label><i class="bi bi-grid-3x3-gap"></i> Grilla de variantes:</label>
                        <div class="form-row matriz-etiquetas-row">
                            <div class="form-group">
                                <label for="stock_eje1_tipo">Eje 1 (filas)</label>
                                <select class="form-input" id="stock_eje1_tipo"></select>
                                <div id="eje1-valores-list" class="variante-valores-checklist"></div>
                                <div style="display:flex; gap:8px; margin-top:6px; flex-wrap:wrap;">
                                    <button type="button" class="btn-action-secondary" id="btn-agregar-valor-eje1">
                                        <i class="bi bi-plus-lg"></i> Agregar valor
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="stock_eje2_tipo">Eje 2 (columnas, opcional)</label>
                                <select class="form-input" id="stock_eje2_tipo">
                                    <option value="">-- Solo eje 1 --</option>
                                </select>
                                <div id="eje2-valores-list" class="variante-valores-checklist"></div>
                                <div style="display:flex; gap:8px; margin-top:6px; flex-wrap:wrap;">
                                    <button type="button" class="btn-action-secondary" id="btn-agregar-valor-eje2">
                                        <i class="bi bi-plus-lg"></i> Agregar valor
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" id="grilla-metodo-wrap" style="margin-top:10px;padding:10px;background:#f8f8f8;border-radius:6px;border:1px solid #e2e2e2;">
                            <label style="font-weight:600;margin-bottom:6px;display:block;"><i class="bi bi-calculator"></i> Método de precio en la grilla:</label>
                            <div style="display:flex;gap:18px;flex-wrap:wrap;">
                                <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;" id="label-grilla-catalogo-uniforme">
                                    <input type="radio" name="grilla_metodo_radio" value="catalogo_uniforme" checked>
                                    Precio del catálogo para todas (costo + margen del formulario)
                                </label>
                                <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;">
                                    <input type="radio" name="grilla_metodo_radio" value="directo">
                                    Costo por medida/fila (+ margen del modal)
                                </label>
                                <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;">
                                    <input type="radio" name="grilla_metodo_radio" value="por_gramo">
                                    Por peso × precio/gramo
                                </label>
                            </div>
                            <small class="form-hint" id="grilla-metodo-hint-uniforme" style="margin-top:4px;"><i class="bi bi-info-circle"></i> La grilla solo mostrará cantidades; el precio se tomará del formulario principal.</small>
                        </div>
                        <div class="form-group" id="grp-grilla-pgr" style="display:none;">
                            <label for="grilla_precio_por_gramo"><i class="bi bi-coin"></i> Precio por gramo (global para toda la grilla):</label>
                            <input type="number" class="form-input" id="grilla_precio_por_gramo" step="0.0001" min="0" placeholder="Ej. 75.50" style="max-width:200px;">
                            <small class="form-hint"><i class="bi bi-info-circle"></i> Costo por gramo × peso de cada fila; al total se aplica el aumento (%) indicado arriba y redondeo al siguiente múltiplo de $5.</small>
                        </div>

                        <button type="button" class="btn-action-secondary" id="btn-generar-matriz" style="margin:8px 0;">
                            <i class="bi bi-table"></i> Generar grilla
                        </button>
                        <div id="matriz-variantes-wrap" class="matriz-variantes-wrap"></div>
                        <small class="form-hint">Total a generar: <strong id="matriz-total">0</strong> (max 500).</small>
                    </div>

                    <div class="form-group" id="grp-stock-aumento">
                        <label for="stock_aumento_pct"><i class="bi bi-graph-up-arrow"></i> Aumento (%):</label>
                        <input type="number" class="form-input" id="stock_aumento_pct" step="0.01" min="0" style="max-width:200px;" value="<?php echo htmlspecialchars((string) $aumento_pct); ?>">
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Margen sobre costo para calcular precio de venta (redondeo al siguiente múltiplo de $5).</small>
                    </div>

                    <div class="alert-message info" id="modal-precio-info-normal" style="margin:6px 0 14px;">
                        <p style="margin:0;">
                            <i class="bi bi-info-circle"></i>
                            Se asignará <strong>$<span id="modal-precio-venta">0.00</span></strong> como precio de venta a cada unidad.
                        </p>
                        <p style="margin:4px 0 0;opacity:0.88;font-size:0.92rem;">
                            Costo $<span id="modal-costo">0.00</span>
                            + aumento <span id="modal-aumento">0</span>%
                            → $<span id="modal-precio-bruto">0.00</span>
                            <span style="opacity:0.75;"> (redondeo al siguiente múltiplo de $5)</span>
                        </p>
                    </div>
                    <div class="alert-message success" id="modal-precio-info-grilla" style="margin:6px 0 14px;display:none;">
                        <p style="margin:0;">
                            <i class="bi bi-grid-3x3-gap"></i>
                            Precios por variante con margen del modal (<span id="modal-grilla-aumento-label">0</span>%):
                        </p>
                        <p style="margin:4px 0 0;font-size:0.92rem;" id="modal-grilla-resumen-precios">
                            Genera la grilla para ver el desglose costo → precio de venta por medida.
                        </p>
                    </div>

                    <div class="form-group">
                        <label class="form-group">
                            <input type="checkbox" id="stock_encolar_etiquetas" value="1" checked>
                            Encolar etiquetas al guardar (cola de impresión)
                        </label>
                    </div>

                    <div class="form-actions" style="margin-top:0;">
                        <button type="button" class="btn-action-primary" id="btn-confirmar-stock">
                            <i class="bi bi-check2-circle"></i> Confirmar y guardar
                        </button>
                        <button type="button" class="btn-action-secondary" id="btn-cerrar-stock">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <script src="js/fk-autocomplete.js"></script>
        <script>
            (function () {
                var familiaSelect = document.getElementById('id_familia_FK');
                var subfamiliaSelect = document.getElementById('id_sub_familia_FK');
                var metalSelect = document.getElementById('id_metal_FK');
                var proveedorSelect = document.getElementById('id_proveedor_FK');
                var tiendaSelect = document.getElementById('id_tienda_FK');
                var btnNuevaFamilia = document.getElementById('btn-nueva-familia');
                var btnNuevaSubfamilia = document.getElementById('btn-nueva-subfamilia');
                var btnNuevoMetal = document.getElementById('btn-nuevo-metal');
                var selectedSubfamilia = '<?php echo htmlspecialchars((string) $id_sub_familia_FK, ENT_QUOTES, 'UTF-8'); ?>';
                var familiaAutocomplete;
                var subfamiliaAutocomplete;
                var metalAutocomplete;
                var subfamiliaRequestSeq = 0;

                if (!familiaSelect || !subfamiliaSelect || !window.JoyeriaFkAutocomplete) {
                    return;
                }

                var hadPreloadedSubs = false;
                for (var oi = 0; oi < subfamiliaSelect.options.length; oi++) {
                    if (subfamiliaSelect.options[oi].value !== '') {
                        hadPreloadedSubs = true;
                        break;
                    }
                }

                familiaAutocomplete = JoyeriaFkAutocomplete.initSelectAutocomplete({
                    selectId: 'id_familia_FK',
                    allowEmpty: false,
                    placeholder: 'Buscar familia...'
                });
                subfamiliaAutocomplete = JoyeriaFkAutocomplete.initSelectAutocomplete({
                    selectId: 'id_sub_familia_FK',
                    allowEmpty: false,
                    placeholder: 'Buscar subfamilia...'
                });
                if (metalSelect) {
                    metalAutocomplete = JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_metal_FK', allowEmpty: false, placeholder: 'Buscar metal...' });
                }
                if (proveedorSelect) {
                    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_proveedor_FK', allowEmpty: true, placeholder: 'Buscar proveedor...' });
                }
                if (tiendaSelect) {
                    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_tienda_FK', allowEmpty: false, placeholder: 'Buscar tienda...' });
                }
                familiaSelect.addEventListener('change', function () {
                    cargarSubfamilias(familiaSelect.value, null);
                });

                var poblarSoloPlaceholder = function (mensaje) {
                    subfamiliaSelect.innerHTML = '';
                    var placeholderOpt = document.createElement('option');
                    placeholderOpt.value = '';
                    placeholderOpt.textContent = mensaje;
                    subfamiliaSelect.appendChild(placeholderOpt);
                    subfamiliaSelect.value = '';
                    if (subfamiliaAutocomplete) {
                        subfamiliaAutocomplete.refresh();
                    }
                };

                function cargarSubfamilias(idFamilia, preselectId) {
                    var requestedFamily = String(idFamilia || '');
                    subfamiliaRequestSeq += 1;
                    var requestId = subfamiliaRequestSeq;

                    if (!idFamilia) {
                        poblarSoloPlaceholder('-- Selecciona subfamilia --');
                        subfamiliaSelect.disabled = true;
                        if (subfamiliaAutocomplete) {
                            subfamiliaAutocomplete.refresh();
                        }
                        return Promise.resolve();
                    }

                    subfamiliaSelect.disabled = true;
                    if (subfamiliaAutocomplete) {
                        subfamiliaAutocomplete.refresh();
                    }
                    poblarSoloPlaceholder('Cargando subfamilias...');

                    return fetch('api/get_sub_familias.php?id_familia=' + encodeURIComponent(idFamilia))
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('No se pudieron cargar las subfamilias.');
                            }
                            return response.json();
                        })
                        .then(function (subfamilias) {
                            if (requestId !== subfamiliaRequestSeq || String(familiaSelect.value || '') !== requestedFamily) {
                                return;
                            }
                            if (!Array.isArray(subfamilias)) {
                                throw new Error('Respuesta invalida de subfamilias.');
                            }
                            subfamiliaSelect.innerHTML = '';
                            var emptyOpt = document.createElement('option');
                            emptyOpt.value = '';
                            emptyOpt.textContent = '-- Selecciona subfamilia --';
                            subfamiliaSelect.appendChild(emptyOpt);
                            subfamilias.forEach(function (sf) {
                                var opt = document.createElement('option');
                                opt.value = String(sf.id_sub_familia);
                                opt.textContent = sf.nom_sub_familia;
                                subfamiliaSelect.appendChild(opt);
                            });
                            if (subfamiliaAutocomplete) {
                                subfamiliaAutocomplete.refresh();
                            }
                            if (preselectId && subfamilias.some(function (sf) { return String(sf.id_sub_familia) === String(preselectId); })) {
                                subfamiliaSelect.value = String(preselectId);
                            } else {
                                subfamiliaSelect.value = '';
                            }
                            subfamiliaSelect.disabled = false;
                            if (subfamiliaAutocomplete) {
                                subfamiliaAutocomplete.refresh();
                            }
                        })
                        .catch(function () {
                            if (requestId !== subfamiliaRequestSeq || String(familiaSelect.value || '') !== requestedFamily) {
                                return;
                            }
                            poblarSoloPlaceholder('No se pudieron cargar subfamilias');
                            subfamiliaSelect.disabled = true;
                            if (subfamiliaAutocomplete) {
                                subfamiliaAutocomplete.refresh();
                            }
                        });
                }

                async function crearFamiliaInline() {
                    var nombre = prompt('Nombre de la nueva familia:');
                    if (!nombre) {
                        return;
                    }
                    try {
                        var response = await fetch('api/familia_crear.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ nom_familia: nombre })
                        });
                        var json = await response.json();
                        if (!response.ok || !json || !json.success || !json.id_familia) {
                            throw new Error((json && json.error) ? json.error : 'No se pudo crear la familia.');
                        }
                        var opt = document.createElement('option');
                        opt.value = String(json.id_familia);
                        opt.textContent = String(json.nom_familia || nombre);
                        familiaSelect.appendChild(opt);
                        familiaSelect.value = String(json.id_familia);
                        if (familiaAutocomplete) {
                            familiaAutocomplete.refresh();
                        }
                        cargarSubfamilias(familiaSelect.value, null);
                    } catch (error) {
                        alert(error.message || 'No se pudo crear la familia.');
                    }
                }

                async function crearSubfamiliaInline() {
                    var idFamiliaActual = String(familiaSelect.value || '').trim();
                    if (!idFamiliaActual) {
                        alert('Primero selecciona una familia.');
                        return;
                    }
                    var nombre = prompt('Nombre de la nueva subfamilia:');
                    if (!nombre) {
                        return;
                    }
                    try {
                        var response = await fetch('api/subfamilia_crear.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                nom_sub_familia: nombre,
                                id_familia_FK: parseInt(idFamiliaActual, 10)
                            })
                        });
                        var json = await response.json();
                        if (!response.ok || !json || !json.success || !json.id_sub_familia) {
                            throw new Error((json && json.error) ? json.error : 'No se pudo crear la subfamilia.');
                        }
                        await cargarSubfamilias(idFamiliaActual, String(json.id_sub_familia));
                    } catch (error) {
                        alert(error.message || 'No se pudo crear la subfamilia.');
                    }
                }

                async function crearMetalInline() {
                    var nombre = prompt('Nombre del nuevo metal:');
                    if (!nombre) {
                        return;
                    }
                    try {
                        var response = await fetch('api/metal_crear.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ nom_metal: nombre })
                        });
                        var json = await response.json();
                        if (!response.ok || !json || !json.success || !json.id_metal) {
                            throw new Error((json && json.error) ? json.error : 'No se pudo crear el metal.');
                        }
                        var opt = document.createElement('option');
                        opt.value = String(json.id_metal);
                        opt.textContent = String(json.nom_metal || nombre);
                        metalSelect.appendChild(opt);
                        metalSelect.value = String(json.id_metal);
                        if (metalAutocomplete) {
                            metalAutocomplete.refresh();
                        }
                    } catch (error) {
                        alert(error.message || 'No se pudo crear el metal.');
                    }
                }

                if (btnNuevaFamilia) {
                    btnNuevaFamilia.addEventListener('click', crearFamiliaInline);
                }
                if (btnNuevaSubfamilia) {
                    btnNuevaSubfamilia.addEventListener('click', crearSubfamiliaInline);
                }
                if (btnNuevoMetal && metalSelect) {
                    btnNuevoMetal.addEventListener('click', crearMetalInline);
                }

                if (familiaSelect.value) {
                    if (!hadPreloadedSubs) {
                        cargarSubfamilias(familiaSelect.value, selectedSubfamilia);
                    } else {
                        subfamiliaSelect.disabled = false;
                        if (subfamiliaAutocomplete) {
                            subfamiliaAutocomplete.refresh();
                        }
                    }
                } else {
                    subfamiliaSelect.disabled = true;
                    if (subfamiliaAutocomplete) {
                        subfamiliaAutocomplete.refresh();
                    }
                }
            })();
        </script>

        <script>
            (function () {
                var pesoInput = document.getElementById('peso_gr');
                var precioGrInput = document.getElementById('precio_por_gramo');
                var costoInput = document.getElementById('costo');
                var aumentoInput = document.getElementById('aumento_pct');
                var grupoPpg = document.getElementById('grupo-precio-por-gramo');
                var pesoMark = document.getElementById('peso-required-mark');
                var costoHint = document.getElementById('costo-hint');
                var radiosMetodo = document.querySelectorAll('input[name="metodo_costo"]');

                var previewCosto = document.getElementById('preview-costo');
                var previewAumento = document.getElementById('preview-aumento');
                var previewPv = document.getElementById('preview-precio-venta');
                var previewBruto = document.getElementById('preview-precio-bruto');

                var modalCosto = document.getElementById('modal-costo');
                var modalAumento = document.getElementById('modal-aumento');
                var modalPv = document.getElementById('modal-precio-venta');
                var modalBruto = document.getElementById('modal-precio-bruto');

                function metodoActual() {
                    for (var i = 0; i < radiosMetodo.length; i++) {
                        if (radiosMetodo[i].checked) {
                            return radiosMetodo[i].value;
                        }
                    }
                    return 'directo';
                }

                function fmt(n) {
                    if (!isFinite(n)) { return '0.00'; }
                    return (Math.round(n * 100) / 100).toFixed(2);
                }

                function recalcularCostoYPreview() {
                    var metodo = metodoActual();
                    var costoNum = 0;

                    if (metodo === 'por_gramo') {
                        var peso = parseFloat(pesoInput.value);
                        var ppg = parseFloat(precioGrInput.value);
                        if (isFinite(peso) && isFinite(ppg) && peso > 0 && ppg > 0) {
                            costoNum = Math.round(peso * ppg * 100) / 100;
                            costoInput.value = fmt(costoNum);
                        } else {
                            costoInput.value = '';
                        }
                    } else {
                        var c = parseFloat(costoInput.value);
                        if (isFinite(c) && c > 0) {
                            costoNum = c;
                        }
                    }

                    var aum = parseFloat(aumentoInput.value);
                    if (!isFinite(aum) || aum < 0) { aum = 0; }
                    var pvBruto = costoNum * (1 + aum / 100);
                    var pv = pvBruto;
                    if (pv > 0) {
                        pv = Math.ceil(pv / 5) * 5;
                    }

                    if (previewCosto) { previewCosto.textContent = fmt(costoNum); }
                    if (previewAumento) { previewAumento.textContent = (aum % 1 === 0 ? aum.toFixed(0) : fmt(aum)); }
                    if (previewBruto) { previewBruto.textContent = fmt(pvBruto); }
                    if (previewPv) { previewPv.textContent = fmt(pv); }
                    if (modalCosto) { modalCosto.textContent = fmt(costoNum); }
                    if (modalAumento) { modalAumento.textContent = (aum % 1 === 0 ? aum.toFixed(0) : fmt(aum)); }
                    if (modalBruto) { modalBruto.textContent = fmt(pvBruto); }
                    if (modalPv) { modalPv.textContent = fmt(pv); }

                    if (typeof window.joyeriaActualizarPreviewsGrillaPieza === 'function') {
                        window.joyeriaActualizarPreviewsGrillaPieza();
                    }
                }

                function aplicarMetodo() {
                    var metodo = metodoActual();
                    if (metodo === 'por_gramo') {
                        if (grupoPpg) { grupoPpg.style.display = ''; }
                        if (pesoMark) { pesoMark.style.display = ''; }
                        pesoInput.required = true;
                        precioGrInput.required = true;
                        costoInput.readOnly = true;
                        costoInput.required = false;
                        if (costoHint) {
                            costoHint.innerHTML = '<i class="bi bi-info-circle"></i> Calculado automaticamente como peso x precio_por_gramo.';
                        }
                    } else {
                        if (grupoPpg) { grupoPpg.style.display = 'none'; }
                        if (pesoMark) { pesoMark.style.display = 'none'; }
                        pesoInput.required = false;
                        precioGrInput.required = false;
                        precioGrInput.value = '';
                        costoInput.readOnly = false;
                        costoInput.required = true;
                        if (costoHint) {
                            costoHint.innerHTML = '<i class="bi bi-info-circle"></i> Captura el costo directamente.';
                        }
                    }
                    recalcularCostoYPreview();
                }

                for (var ri = 0; ri < radiosMetodo.length; ri++) {
                    radiosMetodo[ri].addEventListener('change', aplicarMetodo);
                }
                ['input', 'change'].forEach(function (evt) {
                    pesoInput.addEventListener(evt, recalcularCostoYPreview);
                    precioGrInput.addEventListener(evt, recalcularCostoYPreview);
                    costoInput.addEventListener(evt, recalcularCostoYPreview);
                    aumentoInput.addEventListener(evt, recalcularCostoYPreview);
                });

                aplicarMetodo();
            })();
        </script>

        <?php if (!$esEdicion): ?>
            <?php
            $catalogoVariantesJson = json_encode($catalogoVariantes ?? ['tipos' => []], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
            ?>
            <script>
                window.JOYERIA_CATALOGO_VARIANTES = <?php echo $catalogoVariantesJson ?: '{"tipos":[]}'; ?>;
            </script>
            <script>
                (function () {
                    var btnAbrir = document.getElementById('btn-abrir-stock');
                    var btnCerrar = document.getElementById('btn-cerrar-stock');
                    var btnConfirmar = document.getElementById('btn-confirmar-stock');
                    var modal = document.getElementById('modal-stock');
                    var form = document.getElementById('form-pieza');
                    var inputCantidad = document.getElementById('stock_cantidad');
                    var hiddenAccion = document.getElementById('accion_guardar');
                    var hiddenCantidad = document.getElementById('stock_cantidad_hidden');
                    var hiddenEncolar = document.getElementById('stock_encolar_etiquetas_hidden');
                    var chkEncolar = document.getElementById('stock_encolar_etiquetas');
                    var hiddenModo = document.getElementById('stock_variante_modo_hidden');
                    var hiddenEje1 = document.getElementById('stock_eje1_tipo_id_hidden');
                    var hiddenEje2 = document.getElementById('stock_eje2_tipo_id_hidden');
                    var hiddenMatriz = document.getElementById('stock_matriz_hidden');
                    var hiddenCostoDesdeGrilla = document.getElementById('costo_desde_grilla');
                    var hiddenGrillaMetodo = document.getElementById('grilla_metodo_hidden');
                    var avisoGrilla = document.getElementById('aviso-costo-desde-grilla');
                    var previewPrecioWrap = document.getElementById('preview-precio-wrap');
                    var btnRestaurarCosto = document.getElementById('btn-restaurar-costo');
                    var radiosGrillaMetodo = document.querySelectorAll('input[name="grilla_metodo_radio"]');
                    var grpGrillaPgr = document.getElementById('grp-grilla-pgr');
                    var inputGrillaPgr = document.getElementById('grilla_precio_por_gramo');
                    var inputStockAumento = document.getElementById('stock_aumento_pct');
                    var modalPrecioInfoNormal = document.getElementById('modal-precio-info-normal');
                    var modalPrecioInfoGrilla = document.getElementById('modal-precio-info-grilla');

                    var familiaSelect = document.getElementById('id_familia_FK');
                    var selModo = document.getElementById('stock_variante_modo');
                    var grpCantidad = document.getElementById('grp-cantidad');
                    var grpEjes = document.getElementById('grp-ejes-variantes');
                    var hintTalla = document.getElementById('hint-variante-talla');
                    var selEje1 = document.getElementById('stock_eje1_tipo');
                    var selEje2 = document.getElementById('stock_eje2_tipo');
                    var contEje1 = document.getElementById('eje1-valores-list');
                    var contEje2 = document.getElementById('eje2-valores-list');
                    var wrapMatriz = document.getElementById('matriz-variantes-wrap');
                    var spanMatrizTotal = document.getElementById('matriz-total');
                    var btnGenerarMatriz = document.getElementById('btn-generar-matriz');
                    var btnAgregarValorEje1 = document.getElementById('btn-agregar-valor-eje1');
                    var btnAgregarValorEje2 = document.getElementById('btn-agregar-valor-eje2');

                    var catalogo = window.JOYERIA_CATALOGO_VARIANTES || { tipos: [] };

                    // Modo de precio del formulario (catalogo | grilla)
                    var radiosModoPrecio = document.querySelectorAll('input[name="modo_precio_form"]');
                    var labelGrillaCatalogoUniforme = document.getElementById('label-grilla-catalogo-uniforme');
                    var hintUniforme = document.getElementById('grilla-metodo-hint-uniforme');

                    function modoPrecioActual() {
                        for (var i = 0; i < radiosModoPrecio.length; i++) {
                            if (radiosModoPrecio[i].checked) { return radiosModoPrecio[i].value; }
                        }
                        return 'catalogo';
                    }

                    function actualizarOpcionCatalogoUniforme() {
                        var esGrilla = modoPrecioActual() === 'grilla';
                        var esCatalogo = !esGrilla;
                        var radioDirecto = document.querySelector('input[name="grilla_metodo_radio"][value="directo"]');
                        var radioPorGramo = document.querySelector('input[name="grilla_metodo_radio"][value="por_gramo"]');
                        var labelDirecto = radioDirecto ? radioDirecto.closest('label') : null;
                        var labelPorGramo = radioPorGramo ? radioPorGramo.closest('label') : null;
                        var radioUniforme = labelGrillaCatalogoUniforme
                            ? labelGrillaCatalogoUniforme.querySelector('input[value="catalogo_uniforme"]')
                            : null;

                        if (labelGrillaCatalogoUniforme) {
                            labelGrillaCatalogoUniforme.style.display = esGrilla ? 'none' : '';
                        }
                        if (radioUniforme) {
                            radioUniforme.disabled = esGrilla;
                        }
                        if (labelDirecto) {
                            labelDirecto.style.display = esCatalogo ? 'none' : '';
                        }
                        if (radioDirecto) {
                            radioDirecto.disabled = esCatalogo;
                        }
                        if (labelPorGramo) {
                            labelPorGramo.style.display = esCatalogo ? 'none' : '';
                        }
                        if (radioPorGramo) {
                            radioPorGramo.disabled = esCatalogo;
                        }

                        if (esCatalogo && radioUniforme && !radioUniforme.checked) {
                            radioUniforme.checked = true;
                            radioUniforme.dispatchEvent(new Event('change'));
                        } else if (esGrilla && radioUniforme && radioUniforme.checked && radioDirecto) {
                            radioDirecto.checked = true;
                            radioDirecto.dispatchEvent(new Event('change'));
                        }
                    }

                    radiosModoPrecio.forEach(function (r) {
                        r.addEventListener('change', function () {
                            if (modoPrecioActual() === 'grilla') {
                                deshabilitarCamposCosto();
                            } else {
                                restaurarCamposCosto();
                            }
                            actualizarOpcionCatalogoUniforme();
                        });
                    });

                    if (!btnAbrir || !modal || !form) { return; }

                    function enviarFormConCsrf(targetForm) {
                        if (typeof window.joyeriaEnviarFormConCsrf === 'function') {
                            window.joyeriaEnviarFormConCsrf(targetForm);
                            return;
                        }
                        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        var token = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
                        if (token !== '' && !targetForm.querySelector('input[name="_csrf_token"]')) {
                            var csrfField = document.createElement('input');
                            csrfField.type = 'hidden';
                            csrfField.name = '_csrf_token';
                            csrfField.value = token;
                            targetForm.appendChild(csrfField);
                        }
                        if (typeof targetForm.requestSubmit === 'function') {
                            targetForm.requestSubmit();
                        } else {
                            targetForm.submit();
                        }
                    }

                    function csrfToken() {
                        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        return csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
                    }

                    function grillaMetodoActual() {
                        for (var i = 0; i < radiosGrillaMetodo.length; i++) {
                            if (radiosGrillaMetodo[i].checked) { return radiosGrillaMetodo[i].value; }
                        }
                        return 'directo';
                    }

                    function aumentoPctParaCalculo() {
                        if (inputStockAumento) {
                            var modalAum = parseFloat(inputStockAumento.value);
                            if (isFinite(modalAum) && modalAum >= 0) {
                                return modalAum;
                            }
                        }
                        var aumEl = document.getElementById('aumento_pct');
                        var aum = aumEl ? parseFloat(aumEl.value) : 0;
                        if (!isFinite(aum) || aum < 0) { aum = 0; }
                        return aum;
                    }

                    function calcularPrecioVentaDesdeCosto(costoNum) {
                        var aum = aumentoPctParaCalculo();
                        if (!isFinite(costoNum) || costoNum <= 0) { return 0; }
                        var pv = costoNum * (1 + aum / 100);
                        if (pv > 0) { pv = Math.ceil(pv / 5) * 5; }
                        return pv;
                    }

                    function formatearAumentoPct(aum) {
                        if (!isFinite(aum) || aum < 0) { return '0'; }
                        return aum % 1 === 0 ? aum.toFixed(0) : aum.toFixed(2);
                    }

                    function calcularPrecioBrutoDesdeCosto(costoNum) {
                        var aum = aumentoPctParaCalculo();
                        if (!isFinite(costoNum) || costoNum <= 0) { return 0; }
                        return Math.round(costoNum * (1 + aum / 100) * 100) / 100;
                    }

                    function textoPreviewPrecioConMargen(costoNum) {
                        if (!isFinite(costoNum) || costoNum <= 0) { return '—'; }
                        var aum = formatearAumentoPct(aumentoPctParaCalculo());
                        var bruto = calcularPrecioBrutoDesdeCosto(costoNum);
                        var pv = calcularPrecioVentaDesdeCosto(costoNum);
                        if (Math.abs(bruto - pv) < 0.001) {
                            return '$' + costoNum.toFixed(2) + ' + ' + aum + '% → $' + pv.toFixed(2);
                        }
                        return '$' + costoNum.toFixed(2) + ' + ' + aum + '% → $' + bruto.toFixed(2) + ' → $' + pv.toFixed(2);
                    }

                    function textoPreviewPrecioFinal(valorNum) {
                        if (!isFinite(valorNum) || valorNum <= 0) { return '—'; }
                        return '$' + valorNum.toFixed(2) + ' (precio final, sin margen adicional)';
                    }

                    function sincronizarAumentoModalDesdeFormulario() {
                        var mainAum = document.getElementById('aumento_pct');
                        if (inputStockAumento && mainAum) {
                            inputStockAumento.value = mainAum.value;
                        }
                        actualizarResumenPrecioModal();
                    }

                    function actualizarResumenPrecioModal() {
                        var aum = aumentoPctParaCalculo();
                        var modalAumentoEl = document.getElementById('modal-aumento');
                        var modalPvEl = document.getElementById('modal-precio-venta');
                        var modalBrutoEl = document.getElementById('modal-precio-bruto');
                        var modalCostoEl = document.getElementById('modal-costo');
                        var modalGrillaAumLabel = document.getElementById('modal-grilla-aumento-label');
                        var costoInput = document.getElementById('costo');
                        var costoNum = costoInput ? parseFloat(costoInput.value) : 0;
                        if (!isFinite(costoNum) || costoNum < 0) { costoNum = 0; }

                        if (modalAumentoEl) {
                            modalAumentoEl.textContent = formatearAumentoPct(aum);
                        }
                        if (modalGrillaAumLabel) {
                            modalGrillaAumLabel.textContent = formatearAumentoPct(aum);
                        }
                        if (modalCostoEl) {
                            modalCostoEl.textContent = costoNum > 0 ? costoNum.toFixed(2) : '0.00';
                        }
                        if (modalBrutoEl) {
                            var bruto = calcularPrecioBrutoDesdeCosto(costoNum);
                            modalBrutoEl.textContent = bruto > 0 ? bruto.toFixed(2) : '0.00';
                        }
                        if (modalPvEl) {
                            var pv = calcularPrecioVentaDesdeCosto(costoNum);
                            modalPvEl.textContent = pv > 0 ? pv.toFixed(2) : '0.00';
                        }
                        actualizarTodasPreviewsGrilla();
                    }

                    function aplicarAumentoModalAlFormulario() {
                        var mainAum = document.getElementById('aumento_pct');
                        if (!mainAum || !inputStockAumento) { return; }
                        mainAum.readOnly = false;
                        mainAum.value = inputStockAumento.value;
                    }

                    function costoCatalogoActualNum() {
                        var costoInput = document.getElementById('costo');
                        var costoNum = costoInput ? parseFloat(costoInput.value) : 0;
                        if (!isFinite(costoNum) || costoNum <= 0) { return 0; }
                        return costoNum;
                    }

                    function actualizarPreciosCalculadosGrillaPorGramo() {
                        if (grillaMetodoActual() !== 'por_gramo' || !wrapMatriz) { return; }
                        var pg = inputGrillaPgr ? parseFloat(inputGrillaPgr.value) : 0;
                        wrapMatriz.querySelectorAll('tr[data-valor1]').forEach(function (tr) {
                            var inpPeso = tr.querySelector('.matriz-celda-peso');
                            var spanCalc = tr.querySelector('.matriz-precio-calculado');
                            if (!inpPeso || !spanCalc) { return; }
                            var p = parseFloat(inpPeso.value);
                            if (isFinite(pg) && pg > 0 && isFinite(p) && p > 0) {
                                spanCalc.textContent = textoPreviewPrecioConMargen(p * pg);
                            } else {
                                spanCalc.textContent = '—';
                            }
                        });
                    }

                    function actualizarPreciosCalculadosGrillaDirecto() {
                        if (grillaMetodoActual() !== 'directo' || !wrapMatriz) { return; }
                        var esGrillaPrecio = modoPrecioActual() === 'grilla';
                        wrapMatriz.querySelectorAll('.matriz-celda-precio').forEach(function (inp) {
                            var tr = inp.closest('tr');
                            if (!tr) { return; }
                            var spanCalc = tr.querySelector('.matriz-precio-calculado');
                            if (!spanCalc) { return; }
                            var c = parseFloat(inp.value);
                            if (esGrillaPrecio) {
                                spanCalc.textContent = textoPreviewPrecioFinal(c);
                            } else if (isFinite(c) && c > 0) {
                                spanCalc.textContent = textoPreviewPrecioConMargen(c);
                            } else {
                                spanCalc.textContent = '—';
                            }
                        });
                    }

                    function actualizarPreciosCatalogoUniformeGrilla() {
                        if (grillaMetodoActual() !== 'catalogo_uniforme' || !wrapMatriz) { return; }
                        var costoCat = costoCatalogoActualNum();
                        var texto = textoPreviewPrecioConMargen(costoCat);
                        wrapMatriz.querySelectorAll('.matriz-precio-catalogo').forEach(function (span) {
                            span.textContent = texto;
                        });
                        var thCat = wrapMatriz.querySelector('.matriz-th-catalogo-uniforme');
                        if (thCat) {
                            thCat.textContent = costoCat > 0 ? textoPreviewPrecioConMargen(costoCat) : 'Precio catálogo (completa costo y aumento arriba)';
                        }
                    }

                    function actualizarResumenGrillaModal() {
                        var resumen = document.getElementById('modal-grilla-resumen-precios');
                        if (!resumen || !wrapMatriz) { return; }
                        var metodo = grillaMetodoActual();
                        var filas = [];
                        wrapMatriz.querySelectorAll('tr[data-valor1]').forEach(function (tr) {
                            var labelEl = tr.querySelector('td strong');
                            var label = labelEl ? String(labelEl.textContent || '').trim() : 'Variante';
                            if (metodo === 'por_gramo') {
                                var inpPeso = tr.querySelector('.matriz-celda-peso');
                                var pg = inputGrillaPgr ? parseFloat(inputGrillaPgr.value) : 0;
                                var p = inpPeso ? parseFloat(inpPeso.value) : 0;
                                if (isFinite(pg) && pg > 0 && isFinite(p) && p > 0) {
                                    filas.push('<strong>' + label + ':</strong> ' + textoPreviewPrecioConMargen(p * pg));
                                }
                            } else if (metodo === 'directo') {
                                var inpPrecio = tr.querySelector('.matriz-celda-precio');
                                var c = inpPrecio ? parseFloat(inpPrecio.value) : 0;
                                if (isFinite(c) && c > 0) {
                                    if (modoPrecioActual() === 'grilla') {
                                        filas.push('<strong>' + label + ':</strong> ' + textoPreviewPrecioFinal(c));
                                    } else {
                                        filas.push('<strong>' + label + ':</strong> ' + textoPreviewPrecioConMargen(c));
                                    }
                                }
                            } else if (metodo === 'catalogo_uniforme') {
                                var costoCat = costoCatalogoActualNum();
                                if (costoCat > 0) {
                                    filas.push('<strong>' + label + ':</strong> ' + textoPreviewPrecioConMargen(costoCat));
                                }
                            }
                        });
                        if (filas.length === 0) {
                            if (metodo === 'catalogo_uniforme') {
                                resumen.textContent = 'Completa costo y aumento en el formulario; la grilla usará el mismo precio de venta para todas las medidas.';
                            } else {
                                resumen.textContent = 'Genera la grilla e indica costos, pesos o precios por fila para ver el desglose.';
                            }
                            return;
                        }
                        resumen.innerHTML = filas.join('<br>');
                    }

                    function actualizarTodasPreviewsGrilla() {
                        actualizarPreciosCatalogoUniformeGrilla();
                        actualizarPreciosCalculadosGrillaPorGramo();
                        actualizarPreciosCalculadosGrillaDirecto();
                        actualizarResumenGrillaModal();
                    }

                    window.joyeriaActualizarPreviewsGrillaPieza = actualizarTodasPreviewsGrilla;

                    function deshabilitarCamposCosto() {
                        var ids = ['peso_gr', 'precio_por_gramo', 'costo'];
                        ids.forEach(function (id) {
                            var el = document.getElementById(id);
                            if (el) { el.disabled = true; el.required = false; el.style.opacity = '0.45'; }
                        });
                        var aumEl = document.getElementById('aumento_pct');
                        if (aumEl) { aumEl.readOnly = true; aumEl.style.opacity = '0.75'; }
                        document.querySelectorAll('input[name="metodo_costo"]').forEach(function (r) { r.disabled = true; });
                        if (hiddenCostoDesdeGrilla) { hiddenCostoDesdeGrilla.value = '1'; }
                        if (avisoGrilla) { avisoGrilla.style.display = ''; }
                        if (previewPrecioWrap) { previewPrecioWrap.style.display = 'none'; }
                    }

                    function restaurarCamposCosto() {
                        var ids = ['peso_gr', 'precio_por_gramo', 'costo'];
                        ids.forEach(function (id) {
                            var el = document.getElementById(id);
                            if (el) { el.disabled = false; el.style.opacity = ''; }
                        });
                        var aumEl = document.getElementById('aumento_pct');
                        if (aumEl) { aumEl.readOnly = false; aumEl.style.opacity = ''; }
                        document.querySelectorAll('input[name="metodo_costo"]').forEach(function (r) { r.disabled = false; });
                        if (hiddenCostoDesdeGrilla) { hiddenCostoDesdeGrilla.value = '0'; }
                        if (hiddenGrillaMetodo) { hiddenGrillaMetodo.value = 'directo'; }
                        if (avisoGrilla) { avisoGrilla.style.display = 'none'; }
                        if (previewPrecioWrap) { previewPrecioWrap.style.display = ''; }
                        // Reapply metodo_costo to restore required attributes
                        var activeRadio = document.querySelector('input[name="metodo_costo"]:checked');
                        if (activeRadio) { activeRadio.dispatchEvent(new Event('change')); }
                    }

                    function actualizarInfoModalModo(esEjes) {
                        var metodo = grillaMetodoActual();
                        var usaGrilla = esEjes;
                        if (modalPrecioInfoNormal) { modalPrecioInfoNormal.style.display = usaGrilla ? 'none' : ''; }
                        if (modalPrecioInfoGrilla) { modalPrecioInfoGrilla.style.display = usaGrilla ? '' : 'none'; }
                    }

                    function familiaUsaTalla() {
                        if (!familiaSelect || familiaSelect.selectedIndex < 0) { return false; }
                        var opt = familiaSelect.options[familiaSelect.selectedIndex];
                        return opt && opt.value !== '' && opt.getAttribute('data-usa-talla') === '1';
                    }

                    function nombreFamiliaActual() {
                        if (!familiaSelect || familiaSelect.selectedIndex < 0) { return ''; }
                        var opt = familiaSelect.options[familiaSelect.selectedIndex];
                        return opt && opt.value !== '' ? String(opt.textContent || '').trim() : '';
                    }

                    function tiposDisponibles(excluirTipoId) {
                        var excluir = excluirTipoId ? parseInt(excluirTipoId, 10) : 0;
                        var usaTalla = familiaUsaTalla();
                        return (catalogo.tipos || []).filter(function (t) {
                            if (parseInt(t.es_talla, 10) === 1 && !usaTalla) {
                                return false;
                            }
                            if (excluir > 0 && parseInt(t.id_variante_tipo, 10) === excluir) {
                                return false;
                            }
                            return true;
                        });
                    }

                    function tipoEsTalla(idTipo) {
                        var t = tipoPorId(idTipo);
                        return !!(t && parseInt(t.es_talla, 10) === 1);
                    }

                    function validarFamiliaPermiteTalla(eje1Id, eje2Id) {
                        if (familiaUsaTalla()) { return true; }
                        if (tipoEsTalla(eje1Id) || (eje2Id && tipoEsTalla(eje2Id))) {
                            var nom = nombreFamiliaActual() || 'seleccionada';
                            alert(
                                'La familia "' + nom + '" no esta marcada como anillos (usa talla).\n\n'
                                + 'Ve a Catalogos > Familias, editala y activa "Esta familia usa talla (anillos)", '
                                + 'o elige otra familia antes de usar Talla en la grilla.'
                            );
                            return false;
                        }
                        return true;
                    }

                    function actualizarHintTalla() {
                        if (!hintTalla) { return; }
                        var esEjes = selModo && selModo.value === 'ejes';
                        if (!esEjes || familiaUsaTalla()) {
                            hintTalla.style.display = 'none';
                            return;
                        }
                        hintTalla.style.display = 'block';
                        hintTalla.innerHTML = '<i class="bi bi-info-circle"></i> Los tipos marcados como talla solo estan disponibles para familias de anillos.';
                    }

                    function tipoPorId(id) {
                        var tid = parseInt(id, 10);
                        return (catalogo.tipos || []).find(function (t) {
                            return parseInt(t.id_variante_tipo, 10) === tid;
                        }) || null;
                    }

                    function valorPorId(id) {
                        var vid = parseInt(id, 10);
                        var found = null;
                        (catalogo.tipos || []).some(function (t) {
                            return (t.valores || []).some(function (v) {
                                if (parseInt(v.id_variante_valor, 10) === vid) {
                                    found = Object.assign({}, v, {
                                        id_variante_tipo: t.id_variante_tipo,
                                        tipo_nombre: t.nombre
                                    });
                                    return true;
                                }
                                return false;
                            });
                        });
                        return found;
                    }

                    function poblarSelectTipos(selectEl, incluirVacio, excluirTipoId) {
                        if (!selectEl) { return; }
                        var prev = selectEl.value;
                        selectEl.innerHTML = incluirVacio ? '<option value="">-- Solo eje 1 --</option>' : '';
                        tiposDisponibles(excluirTipoId).forEach(function (t) {
                            var opt = document.createElement('option');
                            opt.value = String(t.id_variante_tipo);
                            opt.textContent = t.nombre;
                            opt.setAttribute('data-es-talla', String(t.es_talla || 0));
                            selectEl.appendChild(opt);
                        });
                        if (prev && selectEl.querySelector('option[value="' + prev + '"]')) {
                            selectEl.value = prev;
                        }
                    }

                    function sincronizarEjesSelects() {
                        if (!selEje1) { return; }
                        var eje1Prev = selEje1.value;
                        poblarSelectTipos(selEje1, false, 0);
                        if (eje1Prev && selEje1.querySelector('option[value="' + eje1Prev + '"]')) {
                            selEje1.value = eje1Prev;
                        } else if (selEje1.options.length) {
                            selEje1.selectedIndex = 0;
                        }
                        if (selEje2) {
                            var eje2Prev = selEje2.value;
                            poblarSelectTipos(selEje2, true, selEje1.value);
                            if (eje2Prev && selEje2.querySelector('option[value="' + eje2Prev + '"]')) {
                                selEje2.value = eje2Prev;
                            } else if (familiaUsaTalla()) {
                                var tallaTipo = (catalogo.tipos || []).find(function (t) {
                                    return parseInt(t.es_talla, 10) === 1
                                        && parseInt(t.id_variante_tipo, 10) !== parseInt(selEje1.value, 10);
                                });
                                if (tallaTipo) {
                                    selEje2.value = String(tallaTipo.id_variante_tipo);
                                }
                            }
                        }
                        renderChecklist(contEje1, selEje1.value);
                        renderChecklist(contEje2, selEje2 ? selEje2.value : '');
                    }

                    function compararTallaLabels(a, b) {
                        var va = String(a || '').trim();
                        var vb = String(b || '').trim();
                        var na = va !== '' && !isNaN(parseFloat(va)) ? parseFloat(va) : null;
                        var nb = vb !== '' && !isNaN(parseFloat(vb)) ? parseFloat(vb) : null;
                        if (na !== null && nb !== null) { return na - nb; }
                        return va.localeCompare(vb, undefined, { numeric: true, sensitivity: 'base' });
                    }

                    function ordenarValoresTallaEnTipo(tipo) {
                        if (!tipo || parseInt(tipo.es_talla, 10) !== 1 || !tipo.valores) { return; }
                        tipo.valores.sort(function (a, b) {
                            return compararTallaLabels(a.valor, b.valor);
                        });
                    }

                    function ordenarItemsTalla(items) {
                        return items.slice().sort(function (a, b) {
                            return compararTallaLabels(a.label, b.label);
                        });
                    }

                    function renderChecklist(contenedor, idTipo) {
                        if (!contenedor) { return; }
                        contenedor.innerHTML = '';
                        var tipo = tipoPorId(idTipo);
                        if (!tipo) { return; }
                        ordenarValoresTallaEnTipo(tipo);
                        (tipo.valores || []).forEach(function (v) {
                            var label = document.createElement('label');
                            label.className = 'variante-valor-check';
                            label.style.display = 'block';
                            label.style.marginBottom = '4px';
                            var chk = document.createElement('input');
                            chk.type = 'checkbox';
                            chk.className = 'variante-valor-id';
                            chk.value = String(v.id_variante_valor);
                            chk.setAttribute('data-label', v.valor);
                            label.appendChild(chk);
                            label.appendChild(document.createTextNode(' ' + v.valor));
                            contenedor.appendChild(label);
                        });
                    }

                    function valoresSeleccionados(contenedor) {
                        var items = [];
                        if (!contenedor) { return items; }
                        contenedor.querySelectorAll('.variante-valor-id:checked').forEach(function (el) {
                            items.push({
                                id: parseInt(el.value, 10),
                                label: el.getAttribute('data-label') || el.value
                            });
                        });
                        return items;
                    }

                    function recalcularTotalMatriz() {
                        var total = 0;
                        if (!wrapMatriz) { return total; }
                        wrapMatriz.querySelectorAll('.matriz-celda-cantidad').forEach(function (el) {
                            var n = parseInt(el.value, 10);
                            if (isFinite(n) && n > 0) { total += n; }
                        });
                        if (spanMatrizTotal) { spanMatrizTotal.textContent = String(total); }
                        return total;
                    }

                    function generarGrillaMatriz() {
                        if (!wrapMatriz || !selEje1) { return; }
                        var eje1Id = parseInt(selEje1.value, 10);
                        var eje2Id = selEje2 && selEje2.value ? parseInt(selEje2.value, 10) : 0;
                        if (!validarFamiliaPermiteTalla(eje1Id, eje2Id || null)) {
                            return;
                        }
                        var tipo1 = tipoPorId(eje1Id);
                        var tipo2 = eje2Id > 0 ? tipoPorId(eje2Id) : null;
                        if (!tipo1) {
                            alert('Selecciona el eje 1.');
                            return;
                        }
                        if (eje2Id > 0 && eje2Id === eje1Id) {
                            alert('El eje 2 debe ser distinto del eje 1.');
                            return;
                        }

                        var filas = valoresSeleccionados(contEje1);
                        var columnas = tipo2 ? valoresSeleccionados(contEje2) : [{ id: null, label: 'Cantidad' }];
                        if (parseInt(tipo1.es_talla, 10) === 1) {
                            filas = ordenarItemsTalla(filas);
                        }
                        if (tipo2 && parseInt(tipo2.es_talla, 10) === 1) {
                            columnas = ordenarItemsTalla(columnas);
                        }

                        if (filas.length === 0) {
                            alert('Selecciona al menos un valor para el eje 1.');
                            return;
                        }
                        if (tipo2 && columnas.length === 0) {
                            alert('Selecciona al menos un valor para el eje 2.');
                            return;
                        }

                        var prev = {};
                        var prevPrecios = {};
                        var prevPesos = {};
                        wrapMatriz.querySelectorAll('tr[data-valor1]').forEach(function (tr) {
                            var v1 = tr.getAttribute('data-valor1');
                            prev[v1] = prev[v1] || {};
                            tr.querySelectorAll('.matriz-celda-cantidad').forEach(function (inp) {
                                var v2 = inp.getAttribute('data-valor2') || '0';
                                var n = parseInt(inp.value, 10);
                                if (isFinite(n) && n > 0) {
                                    prev[v1][v2] = n;
                                }
                            });
                            var inpPrecio = tr.querySelector('.matriz-celda-precio');
                            if (inpPrecio) {
                                var p = parseFloat(inpPrecio.value);
                                if (isFinite(p) && p > 0) { prevPrecios[v1] = p; }
                            }
                            var inpPeso = tr.querySelector('.matriz-celda-peso');
                            if (inpPeso) {
                                var pg = parseFloat(inpPeso.value);
                                if (isFinite(pg) && pg > 0) { prevPesos[v1] = pg; }
                            }
                        });

                        var metodoGen = grillaMetodoActual();
                        var ppgGlobal = inputGrillaPgr ? parseFloat(inputGrillaPgr.value) : 0;

                        var table = document.createElement('table');
                        table.className = 'matriz-variantes-table';
                        var thead = document.createElement('thead');
                        var headRow = document.createElement('tr');
                        var thBlank = document.createElement('th');
                        thBlank.textContent = tipo1.nombre + (tipo2 ? ' \\ ' + tipo2.nombre : '');
                        headRow.appendChild(thBlank);
                        columnas.forEach(function (col) {
                            var th = document.createElement('th');
                            th.textContent = col.label;
                            headRow.appendChild(th);
                        });
                        var thPrecio = document.createElement('th');
                        if (metodoGen === 'por_gramo') {
                            thPrecio.textContent = 'Peso (gr)';
                            thPrecio.style.minWidth = '90px';
                            headRow.appendChild(thPrecio);
                            var thCalc = document.createElement('th');
                            thCalc.textContent = 'Precio con margen';
                            thCalc.style.minWidth = '220px';
                            thCalc.style.color = '#7a6523';
                            headRow.appendChild(thCalc);
                        } else if (metodoGen === 'catalogo_uniforme') {
                            var thInfo = document.createElement('th');
                            thInfo.className = 'matriz-th-catalogo-uniforme';
                            thInfo.style.color = '#1f7a4d';
                            thInfo.style.minWidth = '220px';
                            thInfo.textContent = 'Precio catálogo (completa costo y aumento arriba)';
                            headRow.appendChild(thInfo);
                        } else {
                            var esGrillaPrecio = modoPrecioActual() === 'grilla';
                            thPrecio.textContent = esGrillaPrecio ? 'Precio venta ($)' : 'Costo ($)';
                            thPrecio.style.minWidth = '90px';
                            headRow.appendChild(thPrecio);
                            var thCalcDirecto = document.createElement('th');
                            thCalcDirecto.textContent = esGrillaPrecio ? 'Vista previa' : 'Precio con margen';
                            thCalcDirecto.style.minWidth = '220px';
                            thCalcDirecto.style.color = '#7a6523';
                            headRow.appendChild(thCalcDirecto);
                        }
                        thead.appendChild(headRow);
                        table.appendChild(thead);

                        var tbody = document.createElement('tbody');
                        filas.forEach(function (fila) {
                            var tr = document.createElement('tr');
                            tr.setAttribute('data-valor1', String(fila.id));
                            var tdFila = document.createElement('td');
                            tdFila.innerHTML = '<strong>' + fila.label + '</strong>';
                            tr.appendChild(tdFila);
                            columnas.forEach(function (col) {
                                var td = document.createElement('td');
                                var inp = document.createElement('input');
                                inp.type = 'number';
                                inp.className = 'form-input matriz-celda-cantidad';
                                inp.min = '0';
                                inp.step = '1';
                                inp.setAttribute('data-valor1', String(fila.id));
                                inp.setAttribute('data-valor2', col.id !== null ? String(col.id) : '0');
                                var key2 = col.id !== null ? String(col.id) : '0';
                                inp.value = (prev[String(fila.id)] && prev[String(fila.id)][key2])
                                    ? String(prev[String(fila.id)][key2]) : '0';
                                inp.addEventListener('input', recalcularTotalMatriz);
                                td.appendChild(inp);
                                tr.appendChild(td);
                            });

                            if (metodoGen === 'por_gramo') {
                                // Peso (gr) column
                                var tdPeso = document.createElement('td');
                                var inpPeso = document.createElement('input');
                                inpPeso.type = 'number';
                                inpPeso.className = 'form-input matriz-celda-peso';
                                inpPeso.min = '0';
                                inpPeso.step = '0.01';
                                inpPeso.placeholder = 'gr';
                                inpPeso.setAttribute('data-valor1', String(fila.id));
                                inpPeso.value = prevPesos[String(fila.id)] ? String(prevPesos[String(fila.id)]) : '';
                                tdPeso.appendChild(inpPeso);
                                tr.appendChild(tdPeso);

                                // Precio calculado (readonly display)
                                var tdCalc = document.createElement('td');
                                var spanCalc = document.createElement('span');
                                spanCalc.className = 'matriz-precio-calculado';
                                var pesoInit = prevPesos[String(fila.id)] || 0;
                                if (pesoInit > 0 && isFinite(ppgGlobal) && ppgGlobal > 0) {
                                    spanCalc.textContent = textoPreviewPrecioConMargen(pesoInit * ppgGlobal);
                                } else {
                                    spanCalc.textContent = '—';
                                }
                                spanCalc.style.cssText = 'font-size:0.88rem;color:#7a6523;font-weight:600;';
                                inpPeso.addEventListener('input', (function (sp) {
                                    return function () {
                                        var pg = inputGrillaPgr ? parseFloat(inputGrillaPgr.value) : 0;
                                        var p = parseFloat(this.value);
                                        if (isFinite(pg) && pg > 0 && isFinite(p) && p > 0) {
                                            sp.textContent = textoPreviewPrecioConMargen(p * pg);
                                        } else {
                                            sp.textContent = '—';
                                        }
                                        actualizarResumenGrillaModal();
                                    };
                                })(spanCalc));
                                tdCalc.appendChild(spanCalc);
                                tr.appendChild(tdCalc);
                            } else if (metodoGen === 'catalogo_uniforme') {
                                var tdInfoPrecio = document.createElement('td');
                                var spanCat = document.createElement('span');
                                spanCat.className = 'matriz-precio-catalogo';
                                spanCat.style.cssText = 'font-size:0.88rem;color:#1f7a4d;font-weight:600;';
                                spanCat.textContent = '—';
                                tdInfoPrecio.appendChild(spanCat);
                                tr.appendChild(tdInfoPrecio);
                            } else {
                                // Costo por fila (catalogo) o precio venta final (modo grilla)
                                var esGrillaPrecio = modoPrecioActual() === 'grilla';
                                var tdPrecio = document.createElement('td');
                                var inpPrecio = document.createElement('input');
                                inpPrecio.type = 'number';
                                inpPrecio.className = 'form-input matriz-celda-precio';
                                inpPrecio.min = '0';
                                inpPrecio.step = '0.01';
                                inpPrecio.placeholder = esGrillaPrecio ? 'Precio venta' : 'Costo';
                                inpPrecio.setAttribute('data-valor1', String(fila.id));
                                inpPrecio.value = prevPrecios[String(fila.id)] ? String(prevPrecios[String(fila.id)]) : '';
                                tdPrecio.appendChild(inpPrecio);
                                tr.appendChild(tdPrecio);

                                var tdCalcDirecto = document.createElement('td');
                                var spanCalcDirecto = document.createElement('span');
                                spanCalcDirecto.className = 'matriz-precio-calculado';
                                var costoInit = prevPrecios[String(fila.id)] || 0;
                                if (costoInit > 0) {
                                    spanCalcDirecto.textContent = esGrillaPrecio
                                        ? textoPreviewPrecioFinal(costoInit)
                                        : textoPreviewPrecioConMargen(costoInit);
                                } else {
                                    spanCalcDirecto.textContent = '—';
                                }
                                spanCalcDirecto.style.cssText = 'font-size:0.88rem;color:#7a6523;font-weight:600;';
                                inpPrecio.addEventListener('input', (function (sp, esFinal) {
                                    return function () {
                                        var c = parseFloat(this.value);
                                        if (isFinite(c) && c > 0) {
                                            sp.textContent = esFinal ? textoPreviewPrecioFinal(c) : textoPreviewPrecioConMargen(c);
                                        } else {
                                            sp.textContent = '—';
                                        }
                                        actualizarResumenGrillaModal();
                                    };
                                })(spanCalcDirecto, esGrillaPrecio));
                                tdCalcDirecto.appendChild(spanCalcDirecto);
                                tr.appendChild(tdCalcDirecto);
                            }

                            tbody.appendChild(tr);
                        });
                        table.appendChild(tbody);
                        wrapMatriz.innerHTML = '';
                        wrapMatriz.appendChild(table);
                        recalcularTotalMatriz();
                        actualizarTodasPreviewsGrilla();
                    }

                    function recopilarMatriz() {
                        var matriz = [];
                        if (!wrapMatriz) { return matriz; }
                        var metodo = grillaMetodoActual();
                        var ppg = metodo === 'por_gramo' && inputGrillaPgr ? parseFloat(inputGrillaPgr.value) : 0;

                        if (metodo === 'por_gramo') {
                            var pesosPorValor1 = {};
                            wrapMatriz.querySelectorAll('.matriz-celda-peso').forEach(function (inp) {
                                var v1 = inp.getAttribute('data-valor1');
                                var p = parseFloat(inp.value);
                                if (v1 && isFinite(p) && p > 0) { pesosPorValor1[v1] = p; }
                            });
                            wrapMatriz.querySelectorAll('.matriz-celda-cantidad').forEach(function (inp) {
                                var v1 = parseInt(inp.getAttribute('data-valor1'), 10);
                                var v2raw = inp.getAttribute('data-valor2');
                                var v2 = v2raw && v2raw !== '0' ? parseInt(v2raw, 10) : null;
                                var cant = parseInt(inp.value, 10);
                                if (isFinite(v1) && v1 > 0 && isFinite(cant) && cant > 0) {
                                    var peso = pesosPorValor1[String(v1)] || 0;
                                    matriz.push({
                                        valor1_id: v1,
                                        valor2_id: v2,
                                        cantidad: cant,
                                        precio: 0,
                                        metodo_celda: 'por_gramo',
                                        peso_gr: peso,
                                        precio_por_gramo: isFinite(ppg) && ppg > 0 ? ppg : 0
                                    });
                                }
                            });
                        } else {
                            // Precios indexados por valor1 (precio por fila/eje1)
                            var preciosPorValor1 = {};
                            wrapMatriz.querySelectorAll('.matriz-celda-precio').forEach(function (inp) {
                                var v1 = inp.getAttribute('data-valor1');
                                var p = parseFloat(inp.value);
                                if (v1 && isFinite(p) && p > 0) { preciosPorValor1[v1] = p; }
                            });
                            wrapMatriz.querySelectorAll('.matriz-celda-cantidad').forEach(function (inp) {
                                var v1 = parseInt(inp.getAttribute('data-valor1'), 10);
                                var v2raw = inp.getAttribute('data-valor2');
                                var v2 = v2raw && v2raw !== '0' ? parseInt(v2raw, 10) : null;
                                var cant = parseInt(inp.value, 10);
                                if (isFinite(v1) && v1 > 0 && isFinite(cant) && cant > 0) {
                                    var precio = preciosPorValor1[String(v1)] || 0;
                                    matriz.push({
                                        valor1_id: v1,
                                        valor2_id: v2,
                                        cantidad: cant,
                                        precio: precio,
                                        metodo_celda: 'directo',
                                        precio_es_final: modoPrecioActual() === 'grilla'
                                    });
                                }
                            });
                        }
                        return matriz;
                    }

                    function altaRapidaValor(idTipo, callback) {
                        var valor = window.prompt('Nuevo valor para este eje:');
                        if (valor === null) { return; }
                        valor = (valor || '').trim();
                        if (valor === '') { return; }
                        fetch('api/variantes_quick.php?accion=crear_valor', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': csrfToken()
                            },
                            body: JSON.stringify({
                                id_variante_tipo: idTipo,
                                valor: valor
                            })
                        }).then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data.success) {
                                    alert(data.error || 'No se pudo crear el valor.');
                                    return;
                                }
                                var tipo = tipoPorId(idTipo);
                                if (tipo) {
                                    tipo.valores = tipo.valores || [];
                                    tipo.valores.push(data.valor);
                                    ordenarValoresTallaEnTipo(tipo);
                                }
                                if (typeof callback === 'function') { callback(data.valor); }
                            })
                            .catch(function () {
                                alert('Error de red al crear el valor.');
                            });
                    }

                    function actualizarVistaVariante() {
                        var modo = selModo ? selModo.value : 'ninguna';
                        var esEjes = (modo === 'ejes');
                        if (grpEjes) { grpEjes.style.display = esEjes ? 'block' : 'none'; }
                        if (grpCantidad) { grpCantidad.style.display = esEjes ? 'none' : 'block'; }
                        if (esEjes) {
                            sincronizarEjesSelects();
                        }
                        actualizarHintTalla();
                        actualizarInfoModalModo(esEjes);
                    }

                    function prepararModal() {
                        actualizarVistaVariante();
                        sincronizarAumentoModalDesdeFormulario();
                        // Sincronizar hint uniforme y grp precio/gramo con el radio seleccionado
                        var metodoInicial = grillaMetodoActual();
                        if (grpGrillaPgr) { grpGrillaPgr.style.display = metodoInicial === 'por_gramo' ? '' : 'none'; }
                        if (hintUniforme) { hintUniforme.style.display = metodoInicial === 'catalogo_uniforme' ? '' : 'none'; }
                    }

                    if (familiaSelect) {
                        familiaSelect.addEventListener('change', function () {
                            if (selModo && selModo.value === 'ejes') {
                                if (wrapMatriz) { wrapMatriz.innerHTML = ''; }
                                sincronizarEjesSelects();
                                actualizarHintTalla();
                            }
                        });
                    }

                    if (selModo) {
                        selModo.addEventListener('change', function () {
                            if (wrapMatriz) { wrapMatriz.innerHTML = ''; }
                            actualizarVistaVariante();
                        });
                    }
                    if (selEje1) {
                        selEje1.addEventListener('change', function () {
                            if (selEje2) {
                                var eje2Prev = selEje2.value;
                                poblarSelectTipos(selEje2, true, selEje1.value);
                                if (eje2Prev && selEje2.querySelector('option[value="' + eje2Prev + '"]')) {
                                    selEje2.value = eje2Prev;
                                } else {
                                    selEje2.value = '';
                                }
                            }
                            renderChecklist(contEje1, selEje1.value);
                            renderChecklist(contEje2, selEje2 ? selEje2.value : '');
                            if (wrapMatriz) { wrapMatriz.innerHTML = ''; }
                        });
                    }
                    if (selEje2) {
                        selEje2.addEventListener('change', function () {
                            renderChecklist(contEje2, selEje2.value);
                            if (wrapMatriz) { wrapMatriz.innerHTML = ''; }
                        });
                    }
                    if (btnGenerarMatriz) {
                        btnGenerarMatriz.addEventListener('click', generarGrillaMatriz);
                    }
                    if (btnAgregarValorEje1 && selEje1) {
                        btnAgregarValorEje1.addEventListener('click', function () {
                            var idTipo = parseInt(selEje1.value, 10);
                            if (!idTipo) { alert('Selecciona el eje 1.'); return; }
                            altaRapidaValor(idTipo, function () {
                                renderChecklist(contEje1, selEje1.value);
                            });
                        });
                    }
                    if (btnAgregarValorEje2 && selEje2) {
                        btnAgregarValorEje2.addEventListener('click', function () {
                            var idTipo = parseInt(selEje2.value, 10);
                            if (!idTipo) { alert('Selecciona el eje 2.'); return; }
                            altaRapidaValor(idTipo, function () {
                                renderChecklist(contEje2, selEje2.value);
                            });
                        });
                    }

                    btnAbrir.addEventListener('click', function () {
                        // Validate only non-cost required fields before opening the modal
                        var camposCriticos = ['desc_pieza', 'id_sub_familia_FK', 'id_metal_FK', 'id_tienda_FK'];
                        for (var ci = 0; ci < camposCriticos.length; ci++) {
                            var el = form.querySelector('[name="' + camposCriticos[ci] + '"]');
                            if (el && !el.checkValidity()) {
                                el.reportValidity();
                                return;
                            }
                        }
                        if (!catalogo.tipos || catalogo.tipos.length === 0) {
                            alert('No hay tipos de variante en el catalogo. Registralos en Catalogos > Variantes.');
                        }

                        // Si el modo es "precio por variante": forzar ejes y deshabilitar "Ninguna"
                        var esModoPrecioGrilla = modoPrecioActual() === 'grilla';
                        if (selModo) {
                            var optNinguna = selModo.querySelector('option[value="ninguna"]');
                            if (esModoPrecioGrilla) {
                                if (optNinguna) { optNinguna.disabled = true; }
                                selModo.value = 'ejes';
                                selModo.dispatchEvent(new Event('change'));
                            } else {
                                if (optNinguna) { optNinguna.disabled = false; }
                            }
                        }
                        // Ocultar la opción catalogo_uniforme si el modo es "grilla"
                        actualizarOpcionCatalogoUniforme();

                        prepararModal();
                        modal.style.display = 'flex';
                    });
                    btnCerrar.addEventListener('click', function () {
                        restaurarCamposCosto();
                        modal.style.display = 'none';
                    });
                    modal.addEventListener('click', function (e) {
                        if (e.target === modal) {
                            restaurarCamposCosto();
                            modal.style.display = 'none';
                        }
                    });
                    if (btnRestaurarCosto) {
                        btnRestaurarCosto.addEventListener('click', function () {
                            restaurarCamposCosto();
                        });
                    }

                    radiosGrillaMetodo.forEach(function (r) {
                        r.addEventListener('change', function () {
                            var metodo = grillaMetodoActual();
                            if (grpGrillaPgr) { grpGrillaPgr.style.display = metodo === 'por_gramo' ? '' : 'none'; }
                            if (hintUniforme) { hintUniforme.style.display = metodo === 'catalogo_uniforme' ? '' : 'none'; }
                            if (wrapMatriz) { wrapMatriz.innerHTML = ''; }
                            recalcularTotalMatriz();
                            actualizarResumenPrecioModal();
                        });
                    });

                    if (inputGrillaPgr) {
                        inputGrillaPgr.addEventListener('input', function () {
                            actualizarPreciosCalculadosGrillaPorGramo();
                            actualizarResumenGrillaModal();
                        });
                    }
                    if (inputStockAumento) {
                        ['input', 'change'].forEach(function (evt) {
                            inputStockAumento.addEventListener(evt, function () {
                                actualizarResumenPrecioModal();
                            });
                        });
                    }

                    ['costo', 'aumento_pct', 'peso_gr', 'precio_por_gramo'].forEach(function (fieldId) {
                        var fieldEl = document.getElementById(fieldId);
                        if (!fieldEl) { return; }
                        ['input', 'change'].forEach(function (evt) {
                            fieldEl.addEventListener(evt, function () {
                                actualizarResumenPrecioModal();
                            });
                        });
                    });

                    btnConfirmar.addEventListener('click', function () {
                        var modo = selModo ? selModo.value : 'ninguna';
                        var esEjes = (modo === 'ejes');
                        var totalUnidades = 0;
                        var matriz = [];

                        if (esEjes) {
                            var eje1Id = selEje1 ? parseInt(selEje1.value, 10) : 0;
                            var eje2Id = selEje2 && selEje2.value ? parseInt(selEje2.value, 10) : 0;
                            if (!eje1Id) {
                                alert('Selecciona el eje 1.');
                                return;
                            }
                            if (!validarFamiliaPermiteTalla(eje1Id, eje2Id || null)) {
                                return;
                            }
                            var metodoGrilla = grillaMetodoActual();
                            if (metodoGrilla === 'catalogo_uniforme') {
                                // Precio del catálogo: costo del formulario + aumento del modal
                                var pvCatalogo = calcularPrecioVentaDesdeCosto(costoCatalogoActualNum());
                                if (!isFinite(pvCatalogo) || pvCatalogo <= 0) {
                                    alert('El precio del catálogo no es válido. Completa el costo en el formulario y revisa el aumento antes de continuar.');
                                    return;
                                }
                                // Recopilar solo cantidades de la grilla
                                if (!wrapMatriz) { alert('Genera la grilla primero.'); return; }
                                wrapMatriz.querySelectorAll('tr[data-valor1]').forEach(function (tr) {
                                    var v1 = parseInt(tr.getAttribute('data-valor1'), 10);
                                    tr.querySelectorAll('.matriz-celda-cantidad').forEach(function (inp) {
                                        var v2raw = inp.getAttribute('data-valor2');
                                        var v2 = v2raw && v2raw !== '0' ? parseInt(v2raw, 10) : null;
                                        var cant = parseInt(inp.value, 10);
                                        if (isFinite(v1) && v1 > 0 && isFinite(cant) && cant > 0) {
                                            matriz.push({
                                                valor1_id: v1,
                                                valor2_id: v2,
                                                cantidad: cant,
                                                precio: pvCatalogo,
                                                metodo_celda: 'directo',
                                                precio_es_final: true
                                            });
                                        }
                                    });
                                });
                                if (matriz.length === 0) {
                                    alert('Genera la grilla e indica cantidades en al menos una celda.');
                                    return;
                                }
                                // En modo catálogo-uniforme el costo viene del formulario; no deshabilitar
                                if (hiddenGrillaMetodo) { hiddenGrillaMetodo.value = 'directo'; }
                            } else if (metodoGrilla === 'por_gramo') {
                                var ppgVal = inputGrillaPgr ? parseFloat(inputGrillaPgr.value) : 0;
                                if (!isFinite(ppgVal) || ppgVal <= 0) {
                                    alert('Indica el precio por gramo para calcular los precios de la grilla.');
                                    return;
                                }
                                matriz = recopilarMatriz();
                                if (matriz.length === 0) {
                                    alert('Genera la grilla e indica cantidades en al menos una celda.');
                                    return;
                                }
                                var sinPeso = matriz.filter(function (c) { return !(c.peso_gr > 0); });
                                if (sinPeso.length > 0) {
                                    alert('Todas las filas de la grilla deben tener un peso en gramos (columna "Peso gr").');
                                    return;
                                }
                                deshabilitarCamposCosto();
                                if (hiddenGrillaMetodo) { hiddenGrillaMetodo.value = metodoGrilla; }
                            } else {
                                // directo
                                matriz = recopilarMatriz();
                                if (matriz.length === 0) {
                                    alert('Genera la grilla e indica cantidades en al menos una celda.');
                                    return;
                                }
                                var sinPrecio = matriz.filter(function (c) { return !(c.precio > 0); });
                                if (sinPrecio.length > 0) {
                                    if (modoPrecioActual() === 'grilla') {
                                        alert('Todas las filas deben tener un precio mayor a $0 cuando el modo es "precio por variante".');
                                        return;
                                    } else {
                                        if (!confirm('Algunas filas no tienen costo.\n\nSe usará el precio calculado de la pieza (costo + aumento) para esas filas.\n\n¿Continuar?')) {
                                            return;
                                        }
                                    }
                                }
                                if (modoPrecioActual() === 'grilla') {
                                    deshabilitarCamposCosto();
                                }
                                if (hiddenGrillaMetodo) { hiddenGrillaMetodo.value = metodoGrilla; }
                            }
                            matriz.forEach(function (c) { totalUnidades += c.cantidad; });
                            hiddenEje1.value = String(eje1Id);
                            hiddenEje2.value = selEje2 && selEje2.value ? String(parseInt(selEje2.value, 10)) : '';
                        } else {
                            var cantidad = parseInt(inputCantidad.value, 10);
                            if (!isFinite(cantidad) || cantidad < 1) {
                                alert('Indica una cantidad valida (>= 1).');
                                return;
                            }
                            totalUnidades = cantidad;
                            hiddenEje1.value = '';
                            hiddenEje2.value = '';
                        }

                        if (totalUnidades > 500) {
                            alert('La cantidad maxima por operacion es 500.');
                            return;
                        }

                        aplicarAumentoModalAlFormulario();

                        hiddenAccion.value = 'guardar_y_stock';
                        hiddenCantidad.value = String(totalUnidades);
                        if (hiddenEncolar && chkEncolar) {
                            hiddenEncolar.value = chkEncolar.checked ? '1' : '';
                        }
                        hiddenModo.value = esEjes ? 'ejes' : 'ninguna';
                        if (hiddenMatriz) {
                            hiddenMatriz.value = esEjes ? JSON.stringify(matriz) : '';
                        }
                        enviarFormConCsrf(form);
                    });
                })();
            </script>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró la pieza. <a href="pieza.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/../partials/modal_imprimir_etiquetas.php'; ?>
    <script src="js/etiquetas-print.js"></script>
</div>
