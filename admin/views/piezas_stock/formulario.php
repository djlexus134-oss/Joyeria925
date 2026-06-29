<?php
$stockBaseScript = $stockBaseScript ?? 'pieza.php';
$stockAccionLeer = $stockAccionLeer ?? 'stock';
$stockAccionCrear = $stockAccionCrear ?? 'stock_crear';
$stockAccionActualizar = $stockAccionActualizar ?? 'stock_actualizar';
$esEdicion = isset($piezaStock) && !empty($piezaStock);
$titulo = $esEdicion ? 'Editar Stock de Pieza' : 'Nuevo Stock de Pieza';
$idPiezaPadre = isset($idPiezaFiltro) && (int) $idPiezaFiltro > 0 ? (int) $idPiezaFiltro : null;
$accionForm = $esEdicion
    ? $stockBaseScript . '?accion=' . rawurlencode($stockAccionActualizar) . '&id=' . urlencode((string) $piezaStock['id_pieza_stock']) . ($idPiezaPadre !== null ? '&id_pieza=' . $idPiezaPadre : '')
    : $stockBaseScript . '?accion=' . rawurlencode($stockAccionCrear) . ($idPiezaPadre !== null ? '&id_pieza=' . $idPiezaPadre : '');

$idPieza = $_POST['id_pieza_FK'] ?? ($esEdicion ? ($piezaStock['id_pieza_FK'] ?? '') : ($idPiezaPadre !== null ? (string) $idPiezaPadre : ''));
$precioVenta = $_POST['precio_venta'] ?? ($esEdicion ? ($piezaStock['precio_venta'] ?? '') : ($precioVentaHermano ?? ''));
$estado = $_POST['estado'] ?? ($esEdicion ? ($piezaStock['estado'] ?? '') : 'disponible');
$tipoCodigoDefault = (isset($tipoCodigoBarrasDefault) && in_array((string) $tipoCodigoBarrasDefault, ['EAN13', 'CODE128', 'QR'], true))
    ? (string) $tipoCodigoBarrasDefault
    : 'CODE128';
$tipoCodigo = $_POST['tipo_codigo'] ?? ($esEdicion ? ($piezaStock['tipo_codigo'] ?? '') : $tipoCodigoDefault);
$varianteValor1Id = $_POST['variante_valor1_id'] ?? ($esEdicion ? ($piezaStock['variante_valor1_id'] ?? '') : '');
$varianteValor2Id = $_POST['variante_valor2_id'] ?? ($esEdicion ? ($piezaStock['variante_valor2_id'] ?? '') : '');
$tiposVariante = $catalogos['variantes'] ?? [];
$usaCatalogoVariantes = !empty($tiposVariante);
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($piezaStock)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="id_pieza_FK"><i class="bi bi-gem"></i> Pieza:<span class="required">*</span></label>
                    <select class="form-input" name="id_pieza_FK" id="id_pieza_FK" required>
                        <option value="">-- Selecciona una pieza --</option>
                        <?php foreach (($catalogos['piezas'] ?? []) as $pieza): ?>
                            <option value="<?php echo (int) $pieza['id_pieza']; ?>"
                                data-usa-talla="<?php echo (int) ($pieza['usa_talla'] ?? 0); ?>"
                                <?php echo ((string) $idPieza === (string) $pieza['id_pieza']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $pieza['desc_pieza'] . ' - ' . $pieza['nom_sub_familia'] . ' (' . $pieza['nom_metal'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="precio_venta"><i class="bi bi-cash-coin"></i> Precio de Venta:<span class="required">*</span></label>
                    <input type="number" class="form-input" name="precio_venta" id="precio_venta" step="0.01" min="0"
                           value="<?php echo htmlspecialchars((string) $precioVenta); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="estado"><i class="bi bi-info-circle"></i> Estado:<span class="required">*</span></label>
                    <select class="form-input" name="estado" id="estado" required>
                        <option value="">-- Selecciona un estado --</option>
                        <?php foreach (($catalogos['estados'] ?? []) as $est): ?>
                            <option value="<?php echo htmlspecialchars((string) $est); ?>"
                                <?php echo ((string) $estado === (string) $est) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ucfirst(str_replace('_', ' ', $est))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
            <input type="hidden" name="tipo_codigo" value="<?php echo htmlspecialchars((string) $tipoCodigo); ?>">

            <?php if ($usaCatalogoVariantes): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="variante_valor1_id"><i class="bi bi-tags"></i> Variante eje 1:</label>
                        <select class="form-input" name="variante_valor1_id" id="variante_valor1_id">
                            <option value="">-- Ninguna --</option>
                            <?php foreach ($tiposVariante as $tipoVar): ?>
                                <?php if ((int) ($tipoVar['es_talla'] ?? 0) === 1): ?>
                                    <optgroup label="<?php echo htmlspecialchars((string) $tipoVar['nombre']); ?>" data-es-talla="1">
                                <?php else: ?>
                                    <optgroup label="<?php echo htmlspecialchars((string) $tipoVar['nombre']); ?>" data-es-talla="0">
                                <?php endif; ?>
                                <?php foreach (($tipoVar['valores'] ?? []) as $valorVar): ?>
                                    <option value="<?php echo (int) $valorVar['id_variante_valor']; ?>"
                                        data-tipo-id="<?php echo (int) $tipoVar['id_variante_tipo']; ?>"
                                        data-es-talla="<?php echo (int) ($tipoVar['es_talla'] ?? 0); ?>"
                                        <?php echo ((string) $varianteValor1Id === (string) $valorVar['id_variante_valor']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) $valorVar['valor']); ?>
                                    </option>
                                <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="variante_valor2_id"><i class="bi bi-tags"></i> Variante eje 2 (opcional):</label>
                        <select class="form-input" name="variante_valor2_id" id="variante_valor2_id">
                            <option value="">-- Ninguna --</option>
                            <?php foreach ($tiposVariante as $tipoVar): ?>
                                <optgroup label="<?php echo htmlspecialchars((string) $tipoVar['nombre']); ?>" data-es-talla="<?php echo (int) ($tipoVar['es_talla'] ?? 0); ?>">
                                <?php foreach (($tipoVar['valores'] ?? []) as $valorVar): ?>
                                    <option value="<?php echo (int) $valorVar['id_variante_valor']; ?>"
                                        data-tipo-id="<?php echo (int) $tipoVar['id_variante_tipo']; ?>"
                                        data-es-talla="<?php echo (int) ($tipoVar['es_talla'] ?? 0); ?>"
                                        <?php echo ((string) $varianteValor2Id === (string) $valorVar['id_variante_valor']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) $valorVar['valor']); ?>
                                    </option>
                                <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="form-hint" id="hint-variante-talla-stock">
                    <i class="bi bi-info-circle"></i>
                    Los valores de talla solo estan disponibles si la pieza pertenece a una familia de anillos.
                    Administra tipos en <a href="variantes.php?accion=leer">Catalogos &gt; Variantes</a>.
                </p>
            <?php else: ?>
                <input type="hidden" name="variante_tipo" value="ninguna">
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="<?php echo htmlspecialchars($stockBaseScript); ?>?accion=<?php echo htmlspecialchars($stockAccionLeer); ?><?php echo $idPiezaPadre !== null ? '&id_pieza=' . $idPiezaPadre : ''; ?>" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el stock de pieza. <a href="<?php echo htmlspecialchars($stockBaseScript); ?>?accion=<?php echo htmlspecialchars($stockAccionLeer); ?>">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.JoyeriaFkAutocomplete) {
        JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_pieza_FK', allowEmpty: false, placeholder: 'Buscar pieza...' });
    }

    var selPieza = document.getElementById('id_pieza_FK');
    var selValor1 = document.getElementById('variante_valor1_id');
    var selValor2 = document.getElementById('variante_valor2_id');

    function piezaUsaTalla() {
        if (!selPieza || selPieza.selectedIndex < 0) { return false; }
        var opt = selPieza.options[selPieza.selectedIndex];
        return opt && opt.getAttribute('data-usa-talla') === '1';
    }

    function filtrarTallasEnSelect(selectEl) {
        if (!selectEl) { return; }
        var usaTalla = piezaUsaTalla();
        selectEl.querySelectorAll('optgroup[data-es-talla="1"]').forEach(function (og) {
            og.disabled = !usaTalla;
            og.hidden = !usaTalla;
        });
        selectEl.querySelectorAll('option[data-es-talla="1"]').forEach(function (opt) {
            opt.disabled = !usaTalla;
            opt.hidden = !usaTalla;
            if (!usaTalla && opt.selected) {
                selectEl.value = '';
            }
        });
    }

    function evitarMismoValor() {
        if (!selValor1 || !selValor2) { return; }
        if (selValor1.value !== '' && selValor1.value === selValor2.value) {
            selValor2.value = '';
        }
    }

    if (selPieza) {
        selPieza.addEventListener('change', function () {
            filtrarTallasEnSelect(selValor1);
            filtrarTallasEnSelect(selValor2);
        });
    }
    if (selValor1) { selValor1.addEventListener('change', evitarMismoValor); }
    if (selValor2) { selValor2.addEventListener('change', evitarMismoValor); }

    filtrarTallasEnSelect(selValor1);
    filtrarTallasEnSelect(selValor2);
});
</script>
