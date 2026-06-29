<div class="admin-modules">
    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success"><p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p></div>
    <?php endif; ?>

    <div class="module-actions">
        <a href="impuestos_historico.php?accion=crear" class="btn-action-primary"><i class="bi bi-plus-lg"></i> Nuevo Historico</a>
        <a href="impuestos.php?accion=leer" class="btn-action-secondary"><i class="bi bi-arrow-left"></i> Volver a Impuestos</a>
    </div>

    <?php if(!empty($historicos)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Impuesto</th>
                        <th>Porcentaje</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historicos as $item): ?>
                        <tr>
                            <td>#<?php echo str_pad(htmlspecialchars($item['id_impuesto_historico']), 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($item['tipo_impuesto']); ?></td>
                            <td><?php echo number_format((float)$item['porcentaje'], 2, '.', ''); ?>%</td>
                            <td><?php echo htmlspecialchars($item['fecha_inicio']); ?></td>
                            <td><?php echo htmlspecialchars($item['fecha_fin'] ?? 'N/A'); ?></td>
                            <td><?php echo intval($item['activo']) === 1 ? 'Si' : 'No'; ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="impuestos_historico.php?accion=actualizar&id=<?php echo $item['id_impuesto_historico']; ?>" class="btn-action-secondary"><i class="bi bi-pencil"></i> Editar</a>
                                    <a href="impuestos_historico.php?accion=borrar&id=<?php echo $item['id_impuesto_historico']; ?>" class="btn-action-danger" onclick="return confirm('¿Eliminar este registro historico?');"><i class="bi bi-trash"></i> Eliminar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> No hay historico de impuestos registrado.</p></div>
    <?php endif; ?>
</div>
