<div class="admin-modules">
    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/../partials/ventas_list_toolbar.php'; ?>

    <?php if (!empty($ventas)): ?>
        <p class="text-muted" style="margin:0 0 0.75rem;font-size:0.9rem;">
            <?php echo count($ventas); ?> venta<?php echo count($ventas) === 1 ? '' : 's'; ?> encontrada<?php echo count($ventas) === 1 ? '' : 's'; ?>.
        </p>
        <div class="admin-table-wrapper">
            <table id="tabla-ventas" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Apartado</th>
                        <th>Cliente</th>
                        <th>Empleado</th>
                        <th>Impuesto</th>
                        <th>Total cobrado</th>
                        <th>Canje</th>
                        <th>Impuesto Monto</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $ventaItem): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((string) (int) $ventaItem['id_venta'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <?php
                                $idAp = isset($ventaItem['id_apartado_FK']) ? (int) $ventaItem['id_apartado_FK'] : 0;
                                if ($idAp > 0) {
                                    echo '<span class="text-muted" title="Liquidacion de apartado en punto de venta">#';
                                    echo (int) $idAp;
                                    echo ' <small>(liq.)</small></span>';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) $ventaItem['cliente_nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) $ventaItem['empleado_nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) $ventaItem['tipo_impuesto']); ?> (<?php echo htmlspecialchars((string) $ventaItem['impuesto_porcentaje']); ?>%)</td>
                            <td>$<?php echo htmlspecialchars(number_format((float) $ventaItem['total'], 2, '.', '')); ?></td>
                            <td>
                                <?php
                                $montoCanje = (float) ($ventaItem['monto_canje_aplicado'] ?? 0);
                                echo $montoCanje > 0.009
                                    ? '$' . htmlspecialchars(number_format($montoCanje, 2, '.', ''))
                                    : '—';
                                ?>
                            </td>
                            <td>$<?php echo htmlspecialchars(number_format((float) $ventaItem['impuesto_monto'], 2, '.', '')); ?></td>
                            <td><?php echo htmlspecialchars((string) $ventaItem['estado']); ?></td>
                            <td><?php echo htmlspecialchars((string) $ventaItem['fecha_venta']); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="ventas.php?accion=ver&id=<?php echo (int) $ventaItem['id_venta']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-list-ul"></i> Lineas
                                    </a>
                                    <a href="ventas.php?accion=actualizar&id=<?php echo (int) $ventaItem['id_venta']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="ventas.php?accion=borrar&id=<?php echo (int) $ventaItem['id_venta']; ?>" class="btn-action-danger" onclick="return confirm('¿Cancelar esta venta?');">
                                        <i class="bi bi-x-circle"></i> Cancelar
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
                <p><i class="bi bi-info-circle"></i>
                    <?php
                    require_once __DIR__ . '/../../includes/list_filters.php';
                    $sinResultadosFiltro = (isset($filtrosVentas) && joyeria_ventas_filtros_activos($filtrosVentas))
                        || (isset($busqueda) && trim((string) $busqueda) !== '');
                    echo $sinResultadosFiltro
                        ? 'No hay ventas que coincidan con los filtros aplicados.'
                        : 'No hay ventas registradas.';
                    ?>
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>
