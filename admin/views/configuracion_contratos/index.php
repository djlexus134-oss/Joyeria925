<?php
/** @var array $valores */
/** @var string|null $mensaje */
/** @var string $tipoMensaje */
?>

<div class="admin-modules">
    <?php if (!empty($mensaje)): ?>
        <div class="alert-message <?php echo htmlspecialchars($tipoMensaje ?? 'success'); ?>">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <p class="contracts-subtitle" style="margin-bottom:1rem;">
        Esta pantalla se movio al panel unificado.
        <a href="configuracion_general.php?seccion=contratos">Abrir configuracion de contratos</a>.
    </p>

    <form method="post" action="configuracion_contratos.php?accion=guardar" class="form-section">
        <h3><i class="bi bi-file-earmark-text"></i> Patrón y fuente de trabajo</h3>

        <div class="form-group">
            <label for="contrato_nombre_patron">Nombre del patrón</label>
            <input class="form-input" type="text" name="contrato_nombre_patron" id="contrato_nombre_patron" maxlength="255" required
                   value="<?php echo htmlspecialchars((string) ($valores['nombre_patron'] ?? '')); ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="contrato_ciudad">Ciudad de firma</label>
                <input class="form-input" type="text" name="contrato_ciudad" id="contrato_ciudad" maxlength="255" required
                       value="<?php echo htmlspecialchars((string) ($valores['ciudad'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="contrato_tribunal_ciudad">Tribunales laborales (cláusula 14)</label>
                <input class="form-input" type="text" name="contrato_tribunal_ciudad" id="contrato_tribunal_ciudad" maxlength="255" required
                       value="<?php echo htmlspecialchars((string) ($valores['tribunal_ciudad'] ?? '')); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="contrato_domicilio_fuente_trabajo">Domicilio de la fuente de trabajo</label>
            <input class="form-input" type="text" name="contrato_domicilio_fuente_trabajo" id="contrato_domicilio_fuente_trabajo" maxlength="255" required
                   value="<?php echo htmlspecialchars((string) ($valores['domicilio_fuente_trabajo'] ?? '')); ?>">
        </div>

        <h3 style="margin-top:1.5rem;"><i class="bi bi-clock"></i> Jornada y nacionalidad</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="contrato_jornada_horas_semanales">Jornada semanal (horas)</label>
                <input class="form-input" type="number" min="1" max="72" name="contrato_jornada_horas_semanales" id="contrato_jornada_horas_semanales" required
                       value="<?php echo (int) ($valores['jornada_horas_semanales'] ?? 48); ?>">
            </div>
            <div class="form-group">
                <label for="contrato_nacionalidad_default">Nacionalidad por defecto</label>
                <input class="form-input" type="text" name="contrato_nacionalidad_default" id="contrato_nacionalidad_default" maxlength="80" required
                       value="<?php echo htmlspecialchars((string) ($valores['nacionalidad_default'] ?? 'Mexicana')); ?>">
            </div>
        </div>

        <div class="form-actions" style="margin-top:1.5rem;">
            <button type="submit" class="btn-action-primary"><i class="bi bi-check-lg"></i> Guardar</button>
            <a href="contratos_empleados.php?accion=listar" class="btn-action-secondary"><i class="bi bi-arrow-left"></i> Volver a contratos</a>
        </div>
    </form>
</div>
