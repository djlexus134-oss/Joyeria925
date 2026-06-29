<?php
require_once __DIR__ . '/../../includes/form_defaults.php';
require_once __DIR__ . '/../../includes/cliente_select.php';

$esEdicion = isset($orden) && !empty($orden);
$titulo = $esEdicion ? 'Orden ' . htmlspecialchars((string) ($orden['folio'] ?? '')) : 'Nueva Orden de Taller';
$accionForm = $esEdicion
    ? 'ordenes_taller.php?accion=actualizar&id=' . urlencode((string) $orden['id_orden_taller'])
    : 'ordenes_taller.php?accion=crear';

$origen = $_POST['origen'] ?? ($esEdicion ? ($orden['origen'] ?? 'cliente') : 'cliente');
$tipo = $_POST['tipo'] ?? ($esEdicion ? ($orden['tipo'] ?? 'reparacion') : 'reparacion');
$id_pieza_stock_FK = $_POST['id_pieza_stock_FK'] ?? ($esEdicion ? ($orden['id_pieza_stock_FK'] ?? '') : '');
$codigo_busqueda = $_POST['codigo_busqueda'] ?? ($esEdicion && !empty($orden['codigo_barras']) ? $orden['codigo_barras'] : ($esEdicion && !empty($orden['codigo_auxiliar']) ? $orden['codigo_auxiliar'] : ''));
$pieza_descripcion = $_POST['pieza_descripcion'] ?? ($esEdicion ? ($orden['pieza_descripcion'] ?? '') : '');
$id_cliente_FK = $_POST['id_cliente_FK'] ?? ($esEdicion ? ($orden['id_cliente_FK'] ?? '') : '');
$id_taller_FK = $_POST['id_taller_FK'] ?? ($esEdicion ? ($orden['id_taller_FK'] ?? '') : '');
$descripcion_problema = $_POST['descripcion_problema'] ?? ($esEdicion ? ($orden['descripcion_problema'] ?? '') : '');
$costo_total = $_POST['costo_total'] ?? ($esEdicion ? ($orden['costo_total'] ?? '') : '');
$costo_taller = $_POST['costo_taller'] ?? ($esEdicion ? ($orden['costo_taller'] ?? '') : '');
$fecha_compromiso = joyeria_form_date_value(
    isset($_POST['fecha_compromiso']) ? (string) $_POST['fecha_compromiso'] : null,
    $esEdicion ? (string) ($orden['fecha_compromiso'] ?? '') : null,
    $esEdicion
);
$observaciones = $_POST['observaciones'] ?? ($esEdicion ? ($orden['observaciones'] ?? '') : '');
$anticipo_monto = $_POST['anticipo_monto'] ?? '';
$id_forma_pago_anticipo = $_POST['id_forma_pago_anticipo'] ?? (!empty($idFormaPagoDefault) ? (string) (int) $idFormaPagoDefault : '');

$ordenCerrada = $esEdicion && in_array((string) ($orden['estado'] ?? ''), ['entregada', 'cancelada'], true);
$clientes = $catalogos['clientes'] ?? [];
$stockInfo = null;
if ($esEdicion && !empty($orden['id_pieza_stock_FK'])) {
    $stockInfo = [
        'codigo_auxiliar' => $orden['codigo_auxiliar'] ?? '',
        'codigo_barras' => $orden['codigo_barras'] ?? '',
        'desc_pieza' => $orden['desc_pieza'] ?? '',
        'estado' => $orden['stock_estado'] ?? '',
    ];
}
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-clipboard-check' : 'bi-plus-circle'; ?>"></i>
        <?php echo $titulo; ?>
    </h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <?php
        $tipoAlertaForm = $mensajeTipo ?? 'error';
        if (!in_array($tipoAlertaForm, ['success', 'error', 'info'], true)) {
            $tipoAlertaForm = 'error';
        }
        $iconoAlertaForm = $tipoAlertaForm === 'success' ? 'bi-check-circle' : ($tipoAlertaForm === 'info' ? 'bi-info-circle' : 'bi-exclamation-triangle');
        ?>
        <div class="alert-message <?php echo htmlspecialchars($tipoAlertaForm); ?>">
            <p><i class="bi <?php echo htmlspecialchars($iconoAlertaForm); ?>"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($esEdicion): ?>
        <div class="module-actions" style="margin-bottom:16px;">
            <a href="ordenes_taller.php?accion=imprimir&id=<?php echo (int) $orden['id_orden_taller']; ?>" class="btn-action-secondary" target="_blank">
                <i class="bi bi-printer"></i> Imprimir comprobante
            </a>
            <a href="ordenes_taller.php?accion=leer" class="btn-action-secondary">
                <i class="bi bi-arrow-left"></i> Volver al listado
            </a>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form" id="form-orden-taller">
        <?php if (!$esEdicion): ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="origen"><i class="bi bi-box-seam"></i> Origen de la pieza:</label>
                    <select class="form-input" name="origen" id="origen" required>
                        <option value="cliente" <?php echo $origen === 'cliente' ? 'selected' : ''; ?>>Pieza del cliente</option>
                        <option value="inventario" <?php echo $origen === 'inventario' ? 'selected' : ''; ?>>Pieza de inventario</option>
                    </select>
                </div>
            </div>
        <?php else: ?>
            <input type="hidden" name="origen" value="<?php echo htmlspecialchars((string) $origen); ?>">
        <?php endif; ?>

        <div id="bloque-inventario" class="form-row" style="<?php echo $origen === 'inventario' ? '' : 'display:none;'; ?>">
            <div class="form-group" style="flex:2;">
                <label for="codigo_busqueda"><i class="bi bi-upc-scan"></i> Codigo de barras / auxiliar:</label>
                <div style="display:flex;gap:10px;">
                    <input type="text" class="form-input" name="codigo_busqueda" id="codigo_busqueda"
                           value="<?php echo htmlspecialchars((string) $codigo_busqueda); ?>"
                           placeholder="Escanee o escriba el codigo" <?php echo $esEdicion ? 'readonly' : ''; ?>>
                    <?php if (!$esEdicion): ?>
                        <button type="button" class="btn-action-secondary" id="btn-buscar-stock">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="id_pieza_stock_FK" id="id_pieza_stock_FK" value="<?php echo htmlspecialchars((string) $id_pieza_stock_FK); ?>">
                <div id="stock-resumen" class="form-hint" style="margin-top:8px;">
                    <?php if ($stockInfo): ?>
                        <i class="bi bi-gem"></i>
                        <?php echo htmlspecialchars((string) $stockInfo['desc_pieza']); ?>
                        — <?php echo htmlspecialchars((string) ($stockInfo['codigo_auxiliar'] ?: $stockInfo['codigo_barras'])); ?>
                        (<?php echo htmlspecialchars((string) $stockInfo['estado']); ?>)
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="bloque-cliente-pieza" class="form-row" style="<?php echo $origen === 'cliente' || $esEdicion ? '' : 'display:none;'; ?>">
            <div class="form-group" style="flex:2;">
                <label for="pieza_descripcion"><i class="bi bi-gem"></i> Descripcion de la pieza:</label>
                <input type="text" class="form-input" name="pieza_descripcion" id="pieza_descripcion" maxlength="255"
                       value="<?php echo htmlspecialchars((string) $pieza_descripcion); ?>"
                       placeholder="Ej. Anillo de oro 14k con piedra"
                       <?php echo ($origen === 'inventario' && !$esEdicion) ? 'readonly' : ''; ?>
                       <?php echo ($origen === 'cliente' || ($esEdicion && $origen === 'cliente')) ? 'required' : ''; ?>>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="id_cliente_FK"><i class="bi bi-person"></i> Cliente:</label>
                <select class="form-input fk-autocomplete" name="id_cliente_FK" id="id_cliente_FK" data-placeholder="Buscar cliente...">
                    <?php
                    $selectedId = $id_cliente_FK;
                    $emptyLabel = '-- Sin cliente --';
                    $emptyValue = '';
                    require __DIR__ . '/../partials/cliente_select_options.php';
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_taller_FK"><i class="bi bi-tools"></i> Taller externo:</label>
                <select class="form-input" name="id_taller_FK" id="id_taller_FK">
                    <option value="">-- Sin asignar --</option>
                    <?php foreach (($catalogos['talleres'] ?? []) as $taller): ?>
                        <option value="<?php echo (int) $taller['id_taller']; ?>" <?php echo (string) $id_taller_FK === (string) $taller['id_taller'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $taller['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="tipo"><i class="bi bi-wrench"></i> Tipo de trabajo:</label>
                <select class="form-input" name="tipo" id="tipo" required <?php echo $ordenCerrada ? 'disabled' : ''; ?>>
                    <option value="reparacion" <?php echo $tipo === 'reparacion' ? 'selected' : ''; ?>>Reparacion</option>
                    <option value="modificacion" <?php echo $tipo === 'modificacion' ? 'selected' : ''; ?>>Modificacion</option>
                </select>
                <?php if ($ordenCerrada): ?>
                    <input type="hidden" name="tipo" value="<?php echo htmlspecialchars((string) $tipo); ?>">
                <?php endif; ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex:2;">
                <label for="descripcion_problema"><i class="bi bi-card-text"></i> Defecto / parte a modificar:</label>
                <textarea class="form-input" name="descripcion_problema" id="descripcion_problema" rows="3" required <?php echo $ordenCerrada ? 'readonly' : ''; ?>><?php echo htmlspecialchars((string) $descripcion_problema); ?></textarea>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="costo_total"><i class="bi bi-currency-dollar"></i> Costo total al cliente:</label>
                <input type="number" class="form-input" name="costo_total" id="costo_total" step="0.01" min="0"
                       value="<?php echo htmlspecialchars((string) $costo_total); ?>" required <?php echo $ordenCerrada ? 'readonly' : ''; ?>>
            </div>

            <div class="form-group">
                <label for="costo_taller"><i class="bi bi-cash"></i> Costo taller (interno):</label>
                <input type="number" class="form-input" name="costo_taller" id="costo_taller" step="0.01" min="0"
                       value="<?php echo htmlspecialchars((string) $costo_taller); ?>" <?php echo $ordenCerrada ? 'readonly' : ''; ?>>
            </div>

            <div class="form-group">
                <label for="fecha_compromiso"><i class="bi bi-calendar-event"></i> Fecha compromiso:</label>
                <input type="date" class="form-input" name="fecha_compromiso" id="fecha_compromiso"
                       value="<?php echo htmlspecialchars((string) $fecha_compromiso); ?>" <?php echo $ordenCerrada ? 'readonly' : ''; ?>>
            </div>
        </div>

        <?php if (!$esEdicion): ?>
            <div class="form-section" id="bloque-anticipo" style="border:1px dashed var(--border-color,#ddd);padding:16px;border-radius:8px;margin:12px 0;">
                <h4 style="margin:0 0 12px;"><i class="bi bi-wallet2"></i> Anticipo (opcional)</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="anticipo_monto"><i class="bi bi-currency-dollar"></i> Monto anticipo:</label>
                        <input type="number" class="form-input" name="anticipo_monto" id="anticipo_monto" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $anticipo_monto); ?>" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="id_forma_pago_anticipo"><i class="bi bi-credit-card"></i> Forma de pago:</label>
                        <select class="form-input" name="id_forma_pago_anticipo" id="id_forma_pago_anticipo">
                            <option value="">-- Seleccione --</option>
                            <?php foreach (($catalogos['formasPago'] ?? []) as $fp): ?>
                                <option value="<?php echo (int) $fp['id_forma_pago']; ?>" <?php echo (string) $id_forma_pago_anticipo === (string) $fp['id_forma_pago'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $fp['forma_pago']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <small class="form-hint"><i class="bi bi-info-circle"></i> Si captura un anticipo, la forma de pago es obligatoria.</small>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group" style="flex:2;">
                <label for="observaciones"><i class="bi bi-chat-left-text"></i> Observaciones:</label>
                <input type="text" class="form-input" name="observaciones" id="observaciones" maxlength="500"
                       value="<?php echo htmlspecialchars((string) $observaciones); ?>" <?php echo $ordenCerrada ? 'readonly' : ''; ?>>
            </div>
        </div>

        <?php if (!$ordenCerrada): ?>
            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Crear Orden'; ?>
                </button>
                <a href="ordenes_taller.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($esEdicion && !empty($orden)): ?>
    <?php require __DIR__ . '/seguimiento.php'; ?>
<?php endif; ?>

<script src="js/fk-autocomplete.js"></script>
<?php if (!$esEdicion): ?>
    <script src="js/ordenes-taller-form.js"></script>
<?php endif; ?>
