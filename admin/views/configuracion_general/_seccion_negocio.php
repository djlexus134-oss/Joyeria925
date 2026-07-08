<?php
/** @var array $valores */
/** @var array $catalogos */
/** @var array $opcionesBarcode */
?>
<section class="config-hub-card" id="panel-negocio">
    <h3><i class="bi bi-shop"></i> Operación y valores por defecto</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="tipo_codigo_barras_default">Código de barras en piezas</label>
            <select class="form-input" name="tipo_codigo_barras_default" id="tipo_codigo_barras_default">
                <?php foreach ($opcionesBarcode as $code => $label): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($valores['tipo_codigo_barras_default'] === $code) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="id_tienda_default">Tienda por defecto (nuevas piezas)</label>
            <select class="form-input" name="id_tienda_default" id="id_tienda_default">
                <option value="0">— Sin preferencia —</option>
                <?php foreach ((array) ($catalogos['tiendas'] ?? []) as $tienda): ?>
                    <option value="<?php echo (int) $tienda['id_tienda']; ?>" <?php echo ((int) $valores['id_tienda_default'] === (int) $tienda['id_tienda']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) ($tienda['nom_tienda'] ?? ('Tienda #' . $tienda['id_tienda']))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="markup_pct_default">Margen al generar stock (%)</label>
            <input class="form-input" type="number" step="0.01" min="0" max="9999" name="markup_pct_default" id="markup_pct_default"
                   value="<?php echo htmlspecialchars((string) $valores['markup_pct_default']); ?>">
            <small class="form-hint">Porcentaje aplicado al costo para calcular precio inicial.</small>
        </div>
        <div class="form-group">
            <label for="descuento_general_mostrador">Descuento piezas en mostrador (%)</label>
            <input class="form-input" type="number" step="0.01" min="0" max="100" name="descuento_general_mostrador" id="descuento_general_mostrador"
                   value="<?php echo htmlspecialchars((string) $valores['descuento_general_mostrador']); ?>">
            <small class="form-hint">Piezas/joyas cuando el metal no tiene % propio o el cliente no tiene descuento.</small>
        </div>
        <div class="form-group">
            <label for="descuento_insumos_mostrador">Descuento insumos en mostrador (%)</label>
            <input class="form-input" type="number" step="0.01" min="0" max="100" name="descuento_insumos_mostrador" id="descuento_insumos_mostrador"
                   value="<?php echo htmlspecialchars((string) ($valores['descuento_insumos_mostrador'] ?? '0.00')); ?>">
            <small class="form-hint">Siempre para insumos en POS; no usa el % del cliente.</small>
        </div>
    </div>

    <h4 style="margin-top:1.25rem;"><i class="bi bi-percent"></i> Descuento mayoreo (joyas)</h4>
    <p class="text-muted" style="margin:0 0 0.75rem 0;font-size:0.9rem;">
        Si el cliente alcanza el subtotal de joyas en plata (metales con mayoreo activo) a precio lista en una compra pagada,
        se le asigna el descuento indicado en su ficha (sin bajar un % mayor existente). Aplica en catálogo web y POS.
    </p>
    <div class="form-row">
        <div class="form-group">
            <label for="mayoreo_umbral_mxn">Umbral subtotal joyas (MXN)</label>
            <input class="form-input" type="number" step="0.01" min="0" name="mayoreo_umbral_mxn" id="mayoreo_umbral_mxn"
                   value="<?php echo htmlspecialchars((string) ($valores['mayoreo_umbral_mxn'] ?? '6000.00')); ?>">
            <small class="form-hint">Suma de precios de lista de joyas en el ticket o carrito.</small>
        </div>
        <div class="form-group">
            <label for="mayoreo_descuento_pct">Descuento mayoreo (%)</label>
            <input class="form-input" type="number" step="0.01" min="0" max="100" name="mayoreo_descuento_pct" id="mayoreo_descuento_pct"
                   value="<?php echo htmlspecialchars((string) ($valores['mayoreo_descuento_pct'] ?? '50.00')); ?>">
            <small class="form-hint">Se compara con promoción vigente y % de ficha; se aplica el mejor.</small>
        </div>
    </div>

    <h4 style="margin-top:1.25rem;"><i class="bi bi-ui-checks"></i> Formularios de captura</h4>
    <div class="form-row">
        <div class="form-group">
            <label for="id_forma_pago_default">Forma de pago inicial</label>
            <select class="form-input" name="id_forma_pago_default" id="id_forma_pago_default">
                <option value="0">— Primera activa —</option>
                <?php foreach ((array) ($catalogos['formas_pago'] ?? []) as $fp): ?>
                    <option value="<?php echo (int) $fp['id_forma_pago']; ?>" <?php echo ((int) $valores['id_forma_pago_default'] === (int) $fp['id_forma_pago']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) ($fp['forma_pago'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="id_impuesto_default">Impuesto inicial</label>
            <select class="form-input" name="id_impuesto_default" id="id_impuesto_default">
                <option value="0">— Primero en catalogo —</option>
                <?php foreach ((array) ($catalogos['impuestos'] ?? []) as $imp): ?>
                    <?php
                    $etiquetaImp = trim((string) ($imp['tipo_impuesto'] ?? 'Impuesto'))
                        . ' (' . number_format((float) ($imp['porcentaje'] ?? 0), 2) . '%)';
                    ?>
                    <option value="<?php echo (int) $imp['id_impuesto']; ?>" <?php echo ((int) $valores['id_impuesto_default'] === (int) $imp['id_impuesto']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($etiquetaImp); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <h4 style="margin-top:1.25rem;"><i class="bi bi-bank"></i> Depósito SPEI / transferencia</h4>
    <p class="text-muted" style="margin:0 0 0.75rem 0;font-size:0.9rem;">
        Datos bancarios para mostrar un QR informativo en punto de venta. El cliente copia la CLABE en su app bancaria; no abre el banco automaticamente.
    </p>
    <div class="form-row">
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="spei_deposito_habilitado" value="1" <?php echo !empty($valores['spei_deposito_habilitado']) ? 'checked' : ''; ?>>
                <span><i class="bi bi-qr-code"></i> Mostrar QR de transferencia en POS</span>
            </label>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="spei_beneficiario">Beneficiario (titular)</label>
            <input class="form-input" type="text" name="spei_beneficiario" id="spei_beneficiario" maxlength="255"
                   value="<?php echo htmlspecialchars((string) ($valores['spei_beneficiario'] ?? '')); ?>"
                   placeholder="Nombre del titular de la cuenta">
        </div>
        <div class="form-group">
            <label for="spei_banco">Banco</label>
            <input class="form-input" type="text" name="spei_banco" id="spei_banco" maxlength="255"
                   value="<?php echo htmlspecialchars((string) ($valores['spei_banco'] ?? '')); ?>"
                   placeholder="Ej. BBVA Mexico">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="spei_clabe">CLABE interbancaria</label>
            <input class="form-input" type="text" name="spei_clabe" id="spei_clabe" maxlength="18" inputmode="numeric" pattern="[0-9]{18}"
                   value="<?php echo htmlspecialchars((string) ($valores['spei_clabe'] ?? '')); ?>"
                   placeholder="18 digitos">
            <small class="form-hint">Se valida el digito verificador al guardar.</small>
        </div>
        <div class="form-group">
            <label for="spei_referencia_prefijo">Prefijo de concepto</label>
            <input class="form-input" type="text" name="spei_referencia_prefijo" id="spei_referencia_prefijo" maxlength="32"
                   value="<?php echo htmlspecialchars((string) ($valores['spei_referencia_prefijo'] ?? 'VENTA')); ?>"
                   placeholder="VENTA">
            <small class="form-hint">Se concatena con fecha/hora en el QR (ej. VENTA-20250605-143022).</small>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group" style="flex:1 1 100%;">
            <label for="spei_instrucciones">Instrucciones para el cliente (opcional)</label>
            <input class="form-input" type="text" name="spei_instrucciones" id="spei_instrucciones" maxlength="255"
                   value="<?php echo htmlspecialchars((string) ($valores['spei_instrucciones'] ?? '')); ?>"
                   placeholder="Ej. Incluye el concepto al transferir y muestra comprobante al cajero">
        </div>
    </div>
</section>
