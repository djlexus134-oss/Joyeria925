<?php
/** @var array<int, array<string, mixed>> $ventas */
/** @var string $busqueda */
/** @var array<string, mixed> $filtros */
/** @var array<int, array<string, mixed>> $tiendasActivas */

function joyeria_vol_label_entrega(string $estado): array
{
    switch ($estado) {
        case 'lista_recoger':
            return ['Lista para recoger', 'success'];
        case 'entregada':
            return ['Entregada', 'info'];
        case 'cancelada':
            return ['Cancelada', 'error'];
        default:
            return ['Pendiente', 'warning'];
    }
}

function joyeria_vol_label_pago(string $estado): array
{
    switch ($estado) {
        case 'pagado':
            return ['Pagado', 'success'];
        case 'rechazado':
            return ['Rechazado', 'error'];
        case 'reembolsado':
            return ['Reembolsado', 'warning'];
        default:
            return ['Pendiente', 'warning'];
    }
}
?>

<div class="admin-modules">
    <section class="admin-card" style="margin-bottom: 1rem;">
        <h3>Bandeja de surtido (ventas en linea)</h3>
        <p class="form-hint">
            Estas son las ventas pagadas por clientes a traves de la tienda en linea. La entrega es <strong>en sucursal</strong>:
            aparta la pieza del anaquel y marca el pedido como "Lista para recoger" para avisar al cliente.
        </p>

        <form method="get" action="ventas_online.php" class="form-stack">
            <input type="hidden" name="accion" value="leer">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;">
                <div>
                    <label for="q"><i class="bi bi-search"></i> Buscar</label>
                    <input class="form-input" type="text" name="q" id="q" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Orden, cliente, correo">
                </div>
                <div>
                    <label for="ep">Pago</label>
                    <select class="form-input" name="ep" id="ep">
                        <option value="">Todos</option>
                        <?php foreach (['pendiente'=>'Pendiente','pagado'=>'Pagado','rechazado'=>'Rechazado','reembolsado'=>'Reembolsado'] as $v=>$lbl): ?>
                            <option value="<?php echo $v; ?>"<?php echo ($filtros['estado_pago'] ?? '') === $v ? ' selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ee">Entrega</label>
                    <select class="form-input" name="ee" id="ee">
                        <option value="">Todas</option>
                        <?php foreach (['pendiente'=>'Pendiente','lista_recoger'=>'Lista para recoger','entregada'=>'Entregada','cancelada'=>'Cancelada'] as $v=>$lbl): ?>
                            <option value="<?php echo $v; ?>"<?php echo ($filtros['estado_entrega'] ?? '') === $v ? ' selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="id_tienda"><i class="bi bi-shop"></i> Tienda</label>
                    <select class="form-input" name="id_tienda" id="id_tienda">
                        <option value="0">Todas</option>
                        <?php foreach ($tiendasActivas as $tnd): ?>
                            <option value="<?php echo (int) $tnd['id_tienda']; ?>"<?php echo ((int) ($filtros['id_tienda'] ?? 0)) === (int) $tnd['id_tienda'] ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $tnd['nom_tienda']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fd">Desde</label>
                    <input class="form-input" type="date" name="fd" id="fd" value="<?php echo htmlspecialchars((string) ($filtros['fecha_desde'] ?? '')); ?>">
                </div>
                <div>
                    <label for="fh">Hasta</label>
                    <input class="form-input" type="date" name="fh" id="fh" value="<?php echo htmlspecialchars((string) ($filtros['fecha_hasta'] ?? '')); ?>">
                </div>
                <div class="form-actions" style="margin:0;">
                    <button type="submit" class="btn-action-primary"><i class="bi bi-arrow-repeat"></i> Aplicar</button>
                    <a class="btn-action-secondary" href="ventas_online.php">Limpiar</a>
                </div>
            </div>
        </form>
    </section>

    <section class="admin-card">
        <div class="admin-table-wrapper">
            <?php if (empty($ventas)): ?>
                <p class="text-muted" style="margin:0;">No hay ventas en linea con esos filtros.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Tienda</th>
                            <th class="text-right">Items</th>
                            <th class="text-right">Total</th>
                            <th>Pago</th>
                            <th>Entrega</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $v): ?>
                            <?php
                            [$lblEnt, $clsEnt] = joyeria_vol_label_entrega((string) ($v['estado_entrega'] ?? ''));
                            [$lblPago, $clsPago] = joyeria_vol_label_pago((string) ($v['estado_pago'] ?? ''));
                            ?>
                            <tr>
                                <td><strong>#<?php echo (int) $v['id_venta']; ?></strong></td>
                                <td><?php echo htmlspecialchars((string) ($v['fecha_venta'] ?? '')); ?></td>
                                <td>
                                    <?php echo htmlspecialchars((string) ($v['cliente_nombre'] ?? 'Cliente')); ?>
                                    <?php if (!empty($v['cliente_correo'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars((string) $v['cliente_correo']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($v['nom_tienda'] ?? '—')); ?></td>
                                <td class="text-right"><?php echo (int) ($v['items_count'] ?? 0); ?></td>
                                <td class="text-right">$<?php echo number_format((float) ($v['total'] ?? 0), 2, '.', ','); ?></td>
                                <td><span class="alert-message <?php echo $clsPago; ?>" style="display:inline-block;padding:2px 8px;margin:0;"><?php echo htmlspecialchars($lblPago); ?></span></td>
                                <td><span class="alert-message <?php echo $clsEnt; ?>" style="display:inline-block;padding:2px 8px;margin:0;"><?php echo htmlspecialchars($lblEnt); ?></span></td>
                                <td>
                                    <a class="btn-action-secondary" href="ventas_online.php?accion=ver&id=<?php echo (int) $v['id_venta']; ?>">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</div>
