<?php require_once __DIR__ . '/../../../includes/promociones_tienda_publica.php'; ?>
<div class="admin-modules">

    <?php if (isset($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="promociones.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nueva Promoción
        </a>
    </div>
    <?php
    $listSearchAction = 'promociones.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre, pieza, familia o subfamilia...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($promociones)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Nombre</th>
                        <th>Descuento</th>
                        <th>Vigencia</th>
                        <th>Se Aplica A</th>
                        <th>Estado</th>
                        <th>Tienda en línea</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promociones as $promo): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((string) (int) $promo['id_promocion'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $promo['nombre']); ?></td>
                            <td><?php echo number_format((float) $promo['porcentaje_descuento'], 2); ?>%</td>
                            <td>
                                <small>
                                    <?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $promo['fecha_inicio']))); ?>
                                    a
                                    <?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $promo['fecha_fin']))); ?>
                                </small>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars(joyeria_promocion_texto_alcance($promo), ENT_QUOTES, 'UTF-8'); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo (int) $promo['activa'] === 1 ? 'success' : 'danger'; ?>">
                                    <?php echo (int) $promo['activa'] === 1 ? 'Activa' : 'Inactiva'; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $estadoTienda = joyeria_promocion_estado_tienda($promo);
                                $badgeTienda = match ($estadoTienda) {
                                    'Vigente' => 'success',
                                    'Programada' => 'info',
                                    'Vencida' => 'secondary',
                                    default => 'danger',
                                };
                                ?>
                                <span class="badge badge-<?php echo $badgeTienda; ?>">
                                    <?php echo htmlspecialchars($estadoTienda, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="promociones.php?accion=actualizar&id=<?php echo (int) $promo['id_promocion']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="promociones.php?accion=borrar&id=<?php echo (int) $promo['id_promocion']; ?>"
                                       class="btn-action-danger"
                                       onclick="return confirm('¿Deseas desactivar esta promoción?');">
                                        <i class="bi bi-trash"></i> Desactivar
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
            <p><i class="bi bi-info-circle"></i> No hay promociones activas registradas.</p>
        </div>
    <?php endif; ?>
</div>
