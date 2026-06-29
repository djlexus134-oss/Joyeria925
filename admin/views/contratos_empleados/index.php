<div class="admin-modules">

    <?php if (!empty($mensaje)): ?>
        <?php $alertClass = ($tipo_mensaje === 'error') ? 'error' : (($tipo_mensaje === 'success') ? 'success' : 'info'); ?>
        <div class="alert-message <?php echo $alertClass; ?>">
            <p><?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="contracts-header">
        <div>
            <h3 class="contracts-title">Contratos de Empleados</h3>
            <p class="contracts-subtitle">Gestion de contratos laborales, generacion de PDFs y auditoria</p>
        </div>
        <?php if (auth_can_module_action('contratos', 'crear')): ?>
            <a href="contratos_empleados.php?accion=crear" class="btn-action-primary">Nuevo Contrato</a>
        <?php endif; ?>
    </div>

    <?php if ($accion === 'listar' || !$accion): ?>
        <?php
        $listSearchAction = 'contratos_empleados.php';
        $listSearchHidden = ['accion' => 'listar'];
        $listSearchPlaceholder = 'Buscar por empleado, tipo de contrato u observaciones...';
        require __DIR__ . '/../partials/list_search_bar.php';
        ?>
        <?php if (!empty($contratos)): ?>
            <div class="admin-table-wrapper">
                <table id="tabla-contratos" class="admin-table">
                    <thead>
                        <tr>
                            <th class="name-col">Empleado</th>
                            <th class="related-col">Correo</th>
                            <th class="related-col">Tipo Contrato</th>
                            <th class="related-col">Vigencia</th>
                            <th class="related-col">Documento</th>
                            <th class="actions-col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratos as $contrato): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php
                                        $nombreCompleto = trim(
                                            ($contrato['empleado_nombre'] ?? '') . ' ' .
                                            ($contrato['empleado_primer_apellido'] ?? '') . ' ' .
                                            ($contrato['empleado_segundo_apellido'] ?? '')
                                        );
                                        echo htmlspecialchars($nombreCompleto !== '' ? $nombreCompleto : 'Sin nombre');
                                        ?>
                                    </strong>
                                </td>
                                <td><?php echo htmlspecialchars($contrato['empleado_correo'] ?? 'Sin correo'); ?></td>
                                <td>
                                    <span class="badge-active contract-type-badge"><?php echo htmlspecialchars($contrato['tipo_contrato']); ?></span>
                                </td>
                                <td>
                                    <div class="contract-meta">
                                        <small><strong>Inicio:</strong> <?php echo date('d/m/Y', strtotime($contrato['fecha_inicio'])); ?></small>
                                        <small>
                                            <strong>Fin:</strong>
                                            <?php echo !empty($contrato['fecha_fin']) ? date('d/m/Y', strtotime($contrato['fecha_fin'])) : 'Indeterminado'; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($contrato['ruta_archivo'])): ?>
                                        <a href="visualizar_pdf.php?file=<?php echo urlencode(basename($contrato['ruta_archivo'])); ?>" class="btn-action-secondary" target="_blank" title="Ver PDF en el navegador">Ver PDF</a>
                                    <?php else: ?>
                                        <span class="text-muted">Sin PDF</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <div class="btn-group-compact">
                                        <a href="contratos_empleados.php?accion=ver&id=<?php echo $contrato['id_contrato']; ?>" class="btn-icon" title="Ver detalles"><i class="bi bi-eye"></i></a>
                                        <?php if (auth_can_module_action('contratos', 'actualizar')): ?>
                                            <a href="contratos_empleados.php?accion=actualizar&id=<?php echo $contrato['id_contrato']; ?>" class="btn-icon" title="Editar contrato"><i class="bi bi-pencil"></i></a>
                                        <?php endif; ?>
                                        <?php if (!empty($contrato['ruta_archivo']) && auth_can_module_action('contratos', 'actualizar')): ?>
                                            <form method="POST" style="display:inline;" title="Regenerar PDF">
                                                <input type="hidden" name="accion" value="regenerar_pdf">
                                                <input type="hidden" name="id_contrato" value="<?php echo $contrato['id_contrato']; ?>">
                                                <button type="submit" class="btn-icon" title="Regenerar PDF"><i class="bi bi-file-pdf"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (auth_can_module_action('contratos', 'borrar')): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro de eliminar este contrato? Se marcará como inactivo.');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_contrato" value="<?php echo $contrato['id_contrato']; ?>">
                                                <button type="submit" class="btn-icon danger" title="Eliminar contrato"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert-message info">
                <div class="alert-content">
                    <p>No hay contratos registrados aun.</p>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($accion === 'crear' || ($accion === 'actualizar' && $contratoActual)): ?>
        <div class="form-section contracts-form-section">
            <h3><?php echo $accion === 'crear' ? 'Nuevo Contrato de Empleado' : 'Editar Contrato'; ?></h3>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="<?php echo $accion; ?>">
                <?php if ($accion === 'actualizar' && $contratoActual): ?>
                    <input type="hidden" name="id_contrato" value="<?php echo $contratoActual['id_contrato']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="id_empleado_FK">Empleado *</label>
                    <select class="form-select" id="id_empleado_FK" name="id_empleado_FK" required <?php echo $accion === 'actualizar' ? 'disabled' : ''; ?>>
                        <option value="">Seleccione un empleado</option>
                        <?php foreach ($empleados as $emp): ?>
                            <option value="<?php echo $emp['id_empleado']; ?>" <?php echo ($accion === 'actualizar' && $contratoActual['id_empleado_FK'] == $emp['id_empleado']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($emp['nombre'] ?? '') . ' ' . ($emp['primer_apellido'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($accion === 'actualizar' && $contratoActual): ?>
                        <input type="hidden" name="id_empleado_FK" value="<?php echo $contratoActual['id_empleado_FK']; ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="tipo_contrato">Tipo de Contrato *</label>
                    <select class="form-select" id="tipo_contrato" name="tipo_contrato" required>
                        <option value="">Seleccione un tipo</option>
                        <?php foreach (ContratoEmpleado::TYPES as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo ($accion === 'actualizar' && $contratoActual['tipo_contrato'] === $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fecha_inicio">Fecha de Inicio *</label>
                    <?php
                    require_once __DIR__ . '/../../includes/form_defaults.php';
                    $fechaInicioContrato = ($accion === 'actualizar' && $contratoActual)
                        ? joyeria_form_date_value(
                            isset($_POST['fecha_inicio']) ? (string) $_POST['fecha_inicio'] : null,
                            (string) ($contratoActual['fecha_inicio'] ?? ''),
                            true
                        )
                        : joyeria_form_date_value(
                            isset($_POST['fecha_inicio']) ? (string) $_POST['fecha_inicio'] : null,
                            null,
                            false
                        );
                    ?>
                    <input type="date" class="form-input" id="fecha_inicio" name="fecha_inicio" required value="<?php echo htmlspecialchars($fechaInicioContrato, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="form-group">
                    <label for="fecha_fin">Fecha de Termino</label>
                    <input type="date" class="form-input" id="fecha_fin" name="fecha_fin" value="<?php echo ($accion === 'actualizar' && $contratoActual && $contratoActual['fecha_fin']) ? $contratoActual['fecha_fin'] : ''; ?>">
                    <small class="form-hint">Dejar vacío si es contrato indeterminado.</small>
                </div>

                <div class="form-group">
                    <label for="observaciones">Observaciones</label>
                    <textarea class="form-input" id="observaciones" name="observaciones" rows="4"><?php
                        echo ($accion === 'actualizar' && $contratoActual)
                            ? htmlspecialchars($contratoActual['observaciones'] ?? '')
                            : '';
                    ?></textarea>
                    <small class="form-hint">Notas adicionales o clausulas especiales.</small>
                </div>

                <?php if ($accion === 'actualizar' && $contratoActual && !empty($contratoActual['ruta_archivo'])): ?>
                    <div class="alert-message info contracts-pdf-info">
                        <p>
                            Documento actual:
                            <a href="visualizar_pdf.php?file=<?php echo urlencode(basename($contratoActual['ruta_archivo'])); ?>" target="_blank" title="Ver PDF en el navegador">Ver PDF</a>
                            | Generado: <?php echo date('d/m/Y H:i', strtotime($contratoActual['fecha_registro'])); ?>
                        </p>
                    </div>
                    <div class="form-actions" style="margin-top: 0;">
                        <button type="submit" name="accion" value="regenerar_pdf" class="btn-action-secondary">Regenerar PDF</button>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn-action-primary"><?php echo $accion === 'crear' ? 'Crear Contrato' : 'Guardar Cambios'; ?></button>
                    <a href="contratos_empleados.php?accion=listar" class="btn-action-secondary">Cancelar</a>
                </div>
            </form>
        </div>

    <?php elseif ($accion === 'ver' && $contratoActual): ?>
        <div class="form-section contracts-detail-section">
            <h3>Detalles del Contrato</h3>

            <div class="contract-detail-grid">
                <div class="detail-card">
                    <h4>Empleado</h4>
                    <p>
                        <strong>
                            <?php
                            $nombreDetalle = trim(
                                ($contratoActual['empleado_nombre'] ?? '') . ' ' .
                                ($contratoActual['empleado_primer_apellido'] ?? '') . ' ' .
                                ($contratoActual['empleado_segundo_apellido'] ?? '')
                            );
                            echo htmlspecialchars($nombreDetalle !== '' ? $nombreDetalle : 'Sin nombre');
                            ?>
                        </strong>
                    </p>
                    <p><strong>Correo:</strong> <?php echo htmlspecialchars($contratoActual['empleado_correo'] ?? 'Sin correo'); ?></p>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($contratoActual['empleado_telefono'] ?? 'Sin teléfono'); ?></p>
                </div>

                <div class="detail-card">
                    <h4>Contrato</h4>
                    <p><strong>Tipo:</strong> <?php echo htmlspecialchars($contratoActual['tipo_contrato']); ?></p>
                    <p><strong>Inicio:</strong> <?php echo date('d/m/Y', strtotime($contratoActual['fecha_inicio'])); ?></p>
                    <p><strong>Fin:</strong> <?php echo !empty($contratoActual['fecha_fin']) ? date('d/m/Y', strtotime($contratoActual['fecha_fin'])) : 'Indeterminado'; ?></p>
                    <p><strong>Estado:</strong> <?php echo $contratoActual['activo'] ? 'Activo' : 'Inactivo'; ?></p>
                </div>
            </div>

            <?php if (!empty($contratoActual['observaciones'])): ?>
                <div class="detail-card contracts-observaciones">
                    <h4>Observaciones</h4>
                    <p><?php echo nl2br(htmlspecialchars($contratoActual['observaciones'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="detail-card contracts-audit-card">
                <p><strong>Creado:</strong> <?php echo date('d/m/Y H:i', strtotime($contratoActual['fecha_registro'])); ?></p>
                <?php if (!$contratoActual['activo'] && !empty($contratoActual['fecha_baja'])): ?>
                    <p>
                        <strong>Eliminado por:</strong>
                        <?php echo htmlspecialchars(($contratoActual['usuario_baja_nombre'] ?? '') . ' ' . ($contratoActual['usuario_baja_primer_apellido'] ?? '')); ?>
                    </p>
                    <p><strong>Fecha de baja:</strong> <?php echo date('d/m/Y H:i', strtotime($contratoActual['fecha_baja'])); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <?php if (!empty($contratoActual['ruta_archivo'])): ?>
                    <a href="visualizar_pdf.php?file=<?php echo urlencode(basename($contratoActual['ruta_archivo'])); ?>" target="_blank" class="btn-action-secondary" title="Ver PDF en el navegador">Ver PDF</a>
                <?php endif; ?>
                <?php if (auth_can_module_action('contratos', 'actualizar')): ?>
                    <a href="contratos_empleados.php?accion=actualizar&id=<?php echo $contratoActual['id_contrato']; ?>" class="btn-action-secondary">Editar</a>
                <?php endif; ?>
                <a href="contratos_empleados.php?accion=listar" class="btn-action-primary">Volver</a>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_empleado_FK', allowEmpty: false, placeholder: 'Buscar empleado...' });
});
</script>
