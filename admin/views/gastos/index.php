<div class="admin-modules">
    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <?php
        $tipoAlerta = $mensajeTipo ?? 'success';
        if (!in_array($tipoAlerta, ['success', 'error', 'info'], true)) {
            $tipoAlerta = 'success';
        }
        $iconoAlerta = $tipoAlerta === 'error' ? 'bi-exclamation-triangle' : ($tipoAlerta === 'info' ? 'bi-info-circle' : 'bi-check-circle');
        ?>
        <div class="alert-message <?php echo htmlspecialchars($tipoAlerta); ?>">
            <p><i class="bi <?php echo htmlspecialchars($iconoAlerta); ?>"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="gastos.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Gasto
        </a>
    </div>
    <?php
    $listSearchAction = 'gastos.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por concepto, categoria, empleado o monto...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($gastos)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-gastos" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Concepto</th>
                        <th>Categoria</th>
                        <th>Empleado</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                        <th>Forma Pago</th>
                        <th>Caja</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gastos as $gastoItem): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((string) (int) $gastoItem['id_gasto'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $gastoItem['concepto']); ?></td>
                            <td><?php echo htmlspecialchars((string) $gastoItem['categoria_nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) $gastoItem['empleado_nombre']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format((float) $gastoItem['monto'], 2, '.', '')); ?></td>
                            <td><?php echo htmlspecialchars((string) $gastoItem['fecha_gasto']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($gastoItem['forma_pago'] ?? 'N/A')); ?></td>
                            <td><?php echo ((int) ($gastoItem['afecta_caja'] ?? 0) === 1) ? 'Si' : 'No'; ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="gastos.php?accion=actualizar&id=<?php echo (int) $gastoItem['id_gasto']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="gastos.php?accion=borrar&id=<?php echo (int) $gastoItem['id_gasto']; ?>" class="btn-action-danger" onclick="return confirm('¿Eliminar este gasto?');">
                                        <i class="bi bi-trash"></i> Eliminar
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
                <p><i class="bi bi-info-circle"></i> No hay gastos registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
