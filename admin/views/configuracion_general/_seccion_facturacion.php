<?php
/** @var array $valores */
?>
<section class="config-hub-card" id="panel-facturacion">
    <h3><i class="bi bi-receipt-cutoff"></i> Facturacion CFDI (Facturama)</h3>
    <p class="form-hint" style="margin-bottom:1rem;">
        Las credenciales de Facturama van en <code>config.php</code> como <code>JOYERIA_FACTURAMA_USUARIO</code> y <code>JOYERIA_FACTURAMA_PASSWORD</code>.
        Activa la emision automatica cuando el emisor este dado de alta en Facturama.
    </p>

    <div class="form-row">
        <div class="form-group">
            <label>
                <input type="hidden" name="facturacion_habilitada" value="0">
                <input type="checkbox" name="facturacion_habilitada" value="1"
                    <?php echo !empty($valores['facturacion_habilitada']) ? 'checked' : ''; ?>>
                Emitir CFDI automaticamente al completar ventas
            </label>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="cfdi_rfc_emisor">RFC emisor</label>
            <input class="form-input" type="text" name="cfdi_rfc_emisor" id="cfdi_rfc_emisor" maxlength="13"
                   value="<?php echo htmlspecialchars((string) ($valores['cfdi_rfc_emisor'] ?? '')); ?>">
        </div>
        <div class="form-group">
            <label for="cfdi_nombre_emisor">Nombre / razon social emisor</label>
            <input class="form-input" type="text" name="cfdi_nombre_emisor" id="cfdi_nombre_emisor" maxlength="254"
                   value="<?php echo htmlspecialchars((string) ($valores['cfdi_nombre_emisor'] ?? '')); ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="cfdi_regimen_fiscal">Regimen fiscal emisor</label>
            <input class="form-input" type="text" name="cfdi_regimen_fiscal" id="cfdi_regimen_fiscal" maxlength="3"
                   value="<?php echo htmlspecialchars((string) ($valores['cfdi_regimen_fiscal'] ?? '601')); ?>">
        </div>
        <div class="form-group">
            <label for="cfdi_lugar_expedicion">CP expedicion</label>
            <input class="form-input" type="text" name="cfdi_lugar_expedicion" id="cfdi_lugar_expedicion" maxlength="5"
                   value="<?php echo htmlspecialchars((string) ($valores['cfdi_lugar_expedicion'] ?? '')); ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="cfdi_serie">Serie</label>
            <input class="form-input" type="text" name="cfdi_serie" id="cfdi_serie" maxlength="10"
                   value="<?php echo htmlspecialchars((string) ($valores['cfdi_serie'] ?? 'A')); ?>">
        </div>
        <div class="form-group">
            <label for="cfdi_siguiente_folio">Siguiente folio</label>
            <input class="form-input" type="number" min="1" name="cfdi_siguiente_folio" id="cfdi_siguiente_folio"
                   value="<?php echo htmlspecialchars((string) ($valores['cfdi_siguiente_folio'] ?? '1')); ?>">
        </div>
        <div class="form-group">
            <label for="facturama_modo">Modo Facturama</label>
            <select class="form-input" name="facturama_modo" id="facturama_modo">
                <?php $modo = (string) ($valores['facturama_modo'] ?? 'sandbox'); ?>
                <option value="sandbox" <?php echo $modo === 'sandbox' ? 'selected' : ''; ?>>Sandbox (pruebas)</option>
                <option value="produccion" <?php echo $modo === 'produccion' ? 'selected' : ''; ?>>Produccion</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="cfdi_forma_pago_online_default">Forma pago SAT ventas online</label>
            <input class="form-input" type="text" name="cfdi_forma_pago_online_default" maxlength="2"
                   value="<?php echo htmlspecialchars((string) ($valores['cfdi_forma_pago_online_default'] ?? '03')); ?>">
            <small class="form-hint">03 = transferencia</small>
        </div>
        <div class="form-group">
            <label for="whatsapp_template_factura">Plantilla WhatsApp factura (opcional)</label>
            <input class="form-input" type="text" name="whatsapp_template_factura" maxlength="80"
                   value="<?php echo htmlspecialchars((string) ($valores['whatsapp_template_factura'] ?? '')); ?>">
        </div>
    </div>
</section>
