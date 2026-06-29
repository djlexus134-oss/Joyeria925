<?php
/** @var array $valores */
?>
<section class="config-hub-card" id="panel-mensajeria">
    <h3><i class="bi bi-whatsapp"></i> WhatsApp (Meta Cloud API)</h3>
    <p class="form-hint" style="margin-bottom:1rem;">
        El <strong>token</strong> de acceso se define en <code>config.php</code> (constante <code>JOYERIA_WHATSAPP_TOKEN</code>), no aqui.
        Los mensajes de bienvenida y las notificaciones requieren <strong>plantillas aprobadas</strong> en Meta Business Manager;
        escribe abajo el nombre exacto de cada plantilla.
    </p>

    <div class="form-row">
        <div class="form-group">
            <label for="whatsapp_habilitado">Envio por WhatsApp</label>
            <select class="form-input" name="whatsapp_habilitado" id="whatsapp_habilitado">
                <option value="0" <?php echo empty($valores['whatsapp_habilitado']) ? 'selected' : ''; ?>>Desactivado</option>
                <option value="1" <?php echo !empty($valores['whatsapp_habilitado']) ? 'selected' : ''; ?>>Activado</option>
            </select>
            <small class="form-hint">Si esta desactivado, no se envia ningun WhatsApp.</small>
        </div>
        <div class="form-group">
            <label for="whatsapp_phone_number_id">Phone Number ID</label>
            <input class="form-input" type="text" name="whatsapp_phone_number_id" id="whatsapp_phone_number_id"
                   value="<?php echo htmlspecialchars((string) ($valores['whatsapp_phone_number_id'] ?? '')); ?>"
                   placeholder="Ej. 123456789012345">
            <small class="form-hint">Identificador del numero en WhatsApp Cloud API.</small>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="whatsapp_api_version">Version de Graph API</label>
            <input class="form-input" type="text" name="whatsapp_api_version" id="whatsapp_api_version"
                   value="<?php echo htmlspecialchars((string) ($valores['whatsapp_api_version'] ?? 'v20.0')); ?>"
                   placeholder="v20.0">
        </div>
        <div class="form-group">
            <label for="whatsapp_codigo_pais_default">Lada por defecto</label>
            <input class="form-input" type="text" name="whatsapp_codigo_pais_default" id="whatsapp_codigo_pais_default"
                   value="<?php echo htmlspecialchars((string) ($valores['whatsapp_codigo_pais_default'] ?? '52')); ?>"
                   placeholder="52">
            <small class="form-hint">Se antepone si el telefono no trae lada (52 = Mexico).</small>
        </div>
        <div class="form-group">
            <label for="whatsapp_template_idioma">Idioma de plantillas</label>
            <input class="form-input" type="text" name="whatsapp_template_idioma" id="whatsapp_template_idioma"
                   value="<?php echo htmlspecialchars((string) ($valores['whatsapp_template_idioma'] ?? 'es_MX')); ?>"
                   placeholder="es_MX">
            <small class="form-hint">Codigo de idioma configurado en Meta (ej. es_MX, es).</small>
        </div>
    </div>

    <h4 style="margin-top:1.25rem;"><i class="bi bi-chat-square-text"></i> Plantillas aprobadas</h4>
    <div class="form-row">
        <div class="form-group">
            <label for="whatsapp_template_bienvenida_cliente">Bienvenida cliente</label>
            <input class="form-input" type="text" name="whatsapp_template_bienvenida_cliente" id="whatsapp_template_bienvenida_cliente"
                   value="<?php echo htmlspecialchars((string) ($valores['whatsapp_template_bienvenida_cliente'] ?? '')); ?>"
                   placeholder="nombre_plantilla_cliente">
            <small class="form-hint">1 variable de cuerpo: nombre del cliente.</small>
        </div>
        <div class="form-group">
            <label for="whatsapp_template_bienvenida_empleado">Bienvenida empleado</label>
            <input class="form-input" type="text" name="whatsapp_template_bienvenida_empleado" id="whatsapp_template_bienvenida_empleado"
                   value="<?php echo htmlspecialchars((string) ($valores['whatsapp_template_bienvenida_empleado'] ?? '')); ?>"
                   placeholder="nombre_plantilla_empleado">
            <small class="form-hint">1 variable de cuerpo: nombre del empleado.</small>
        </div>
        <div class="form-group">
            <label for="whatsapp_template_notificacion">Notificacion especial</label>
            <input class="form-input" type="text" name="whatsapp_template_notificacion" id="whatsapp_template_notificacion"
                   value="<?php echo htmlspecialchars((string) ($valores['whatsapp_template_notificacion'] ?? '')); ?>"
                   placeholder="nombre_plantilla_notificacion">
            <small class="form-hint">1 variable de cuerpo: el mensaje a enviar.</small>
        </div>
    </div>
</section>
