<div class="admin-modules">

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="empleado.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Empleado
        </a>
    </div>
    <?php
    $listSearchAction = 'empleado.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre, puesto, correo o ubicacion...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($empleados)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-empleado" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Nombre Completo</th>
                        <th class="related-col">Puesto</th>
                        <th class="related-col">Salario</th>
                        <th class="related-col">Contacto</th>
                        <th class="related-col">Ubicación</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleados as $empleado): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($empleado['id_empleado']), 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <div class="employee-name">
                                    <strong><?php echo htmlspecialchars($empleado['nombre']); ?></strong>
                                    <span class="text-muted"><?php echo htmlspecialchars($empleado['primer_apellido'] . ' ' . $empleado['segundo_apellido']); ?></span>
                                </div>
                            </td>
                            <td><span class="table-accent-text"><?php echo htmlspecialchars($empleado['nombre_puesto']); ?></span></td>
                            <td><?php echo number_format($empleado['salario'], 2, '.', ''); ?></td>
                            <td>
                                <div class="contact-info">
                                    <small><?php echo htmlspecialchars($empleado['correo']); ?></small>
                                    <small><?php echo htmlspecialchars($empleado['telefono']); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="location-info">
                                    <small><strong><?php echo htmlspecialchars($empleado['nom_municipio'] ?? 'N/A'); ?></strong></small>
                                    <small><?php echo htmlspecialchars($empleado['nom_estado'] ?? 'N/A'); ?></small>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="empleado.php?accion=ver&id=<?php echo $empleado['id_empleado']; ?>"
                                        class="btn-action-secondary" title="Ver detalles">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <a href="empleado.php?accion=actualizar&id=<?php echo $empleado['id_empleado']; ?>"
                                        class="btn-action-secondary" title="Editar empleado">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="empleado.php?accion=borrar&id=<?php echo $empleado['id_empleado']; ?>"
                                        class="btn-action-danger"
                                        title="Dar de baja empleado"
                                        onclick="return confirm('¿Estás seguro de que deseas dar de baja este empleado?');">
                                        <i class="bi bi-trash"></i> Dar de baja
                                    </a>
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
                <p><i class="bi bi-info-circle"></i> No hay empleados registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
