<?php /** @var array $valores */ ?>
<section class="config-hub-card">
    <h3><i class="bi bi-file-earmark-text"></i> Contratos laborales (PDF)</h3>
    <div class="form-group">
        <label for="contrato_nombre_patron">Nombre del patron</label>
        <input class="form-input" type="text" name="contrato_nombre_patron" id="contrato_nombre_patron" maxlength="255" required
               value="<?php echo htmlspecialchars((string) $valores['contrato_nombre_patron']); ?>">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="contrato_ciudad">Ciudad de firma</label>
            <input class="form-input" type="text" name="contrato_ciudad" id="contrato_ciudad" maxlength="255" required
                   value="<?php echo htmlspecialchars((string) $valores['contrato_ciudad']); ?>">
        </div>
        <div class="form-group">
            <label for="contrato_tribunal_ciudad">Tribunales laborales (clausula 14)</label>
            <input class="form-input" type="text" name="contrato_tribunal_ciudad" id="contrato_tribunal_ciudad" maxlength="255" required
                   value="<?php echo htmlspecialchars((string) $valores['contrato_tribunal_ciudad']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label for="contrato_domicilio_fuente_trabajo">Domicilio de la fuente de trabajo</label>
        <input class="form-input" type="text" name="contrato_domicilio_fuente_trabajo" id="contrato_domicilio_fuente_trabajo" maxlength="255" required
               value="<?php echo htmlspecialchars((string) $valores['contrato_domicilio_fuente_trabajo']); ?>">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="contrato_jornada_horas_semanales">Jornada semanal (horas)</label>
            <input class="form-input" type="number" min="1" max="72" name="contrato_jornada_horas_semanales" id="contrato_jornada_horas_semanales" required
                   value="<?php echo (int) $valores['contrato_jornada_horas_semanales']; ?>">
        </div>
        <div class="form-group">
            <label for="contrato_nacionalidad_default">Nacionalidad por defecto</label>
            <input class="form-input" type="text" name="contrato_nacionalidad_default" id="contrato_nacionalidad_default" maxlength="80" required
                   value="<?php echo htmlspecialchars((string) $valores['contrato_nacionalidad_default']); ?>">
        </div>
    </div>
    <p class="form-hint" style="margin-top:1rem;">
        <a href="contratos_empleados.php?accion=listar"><i class="bi bi-box-arrow-up-right"></i> Ir a contratos de empleados</a>
    </p>
</section>
