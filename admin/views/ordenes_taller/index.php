<?php
$estadoBadgeClass = [
    'recibida' => 'info',
    'en_taller' => 'warning',
    'lista' => 'success',
    'entregada' => 'success',
    'cancelada' => 'error',
];
?>
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
            <a href="ordenes_taller.php?accion=crear" class="btn-action-primary">
                <i class="bi bi-plus-lg"></i> Nueva Orden
            </a>
        </div>
        <?php
        $listSearchAction = 'ordenes_taller.php';
        $listSearchHidden = ['accion' => 'leer'];
        $listSearchPlaceholder = 'Buscar por folio, pieza, cliente, taller o estado...';
        require __DIR__ . '/../partials/list_search_bar.php';
        ?>
    </div>

    <?php if (!empty($ordenes)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-ordenes-taller" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">Folio</th>
                        <th>Pieza</th>
                        <th>Cliente</th>
                        <th>Taller</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>Saldo</th>
                        <th>Compromiso</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordenes as $orden): ?>
                        <?php
                        $estado = (string) ($orden['estado'] ?? '');
                        $badge = $estadoBadgeClass[$estado] ?? 'info';
                        $piezaLabel = (string) ($orden['pieza_descripcion'] ?? '');
                        if ($piezaLabel === '' && !empty($orden['desc_pieza'])) {
                            $piezaLabel = (string) $orden['desc_pieza'];
                        }
                        if (!empty($orden['codigo_auxiliar'])) {
                            $piezaLabel .= ' (' . $orden['codigo_auxiliar'] . ')';
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars((string) $orden['folio']); ?></strong></td>
                            <td><?php echo htmlspecialchars($piezaLabel !== '' ? $piezaLabel : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($orden['cliente_nombre'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($orden['taller_nombre'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst((string) ($orden['tipo'] ?? ''))); ?></td>
                            <td>
                                <span class="alert-message <?php echo htmlspecialchars($badge); ?>" style="display:inline-block;padding:2px 8px;margin:0;">
                                    <?php echo htmlspecialchars($app->etiquetaEstado($estado)); ?>
                                </span>
                            </td>
                            <td>$<?php echo htmlspecialchars(number_format((float) ($orden['costo_total'] ?? 0), 2)); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format((float) ($orden['saldo_pendiente'] ?? 0), 2)); ?></td>
                            <td><?php echo !empty($orden['fecha_compromiso']) ? htmlspecialchars((string) $orden['fecha_compromiso']) : '—'; ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="ordenes_taller.php?accion=actualizar&id=<?php echo (int) $orden['id_orden_taller']; ?>" class="btn-action-secondary" title="Ver / Editar">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <a href="ordenes_taller.php?accion=imprimir&id=<?php echo (int) $orden['id_orden_taller']; ?>&retorno=leer" class="btn-action-secondary" title="Imprimir ticket en caja">
                                        <i class="bi bi-printer"></i> Imprimir
                                    </a>
                                    <a href="ordenes_taller.php?accion=borrar&id=<?php echo (int) $orden['id_orden_taller']; ?>" class="btn-action-danger" title="Eliminar" onclick="return confirm('¿Dar de baja esta orden de taller?');">
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
            <p><i class="bi bi-info-circle"></i> No hay ordenes de taller registradas.</p>
        </div>
    <?php endif; ?>
</div>
