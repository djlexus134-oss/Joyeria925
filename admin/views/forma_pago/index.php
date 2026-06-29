<div class="admin-modules">

    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="forma_pago.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nueva Forma de Pago
        </a>
    </div>
    <?php
    $listSearchAction = 'forma_pago.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por forma de pago...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if(!empty($formasPago)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-forma-pago" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Forma de Pago</th>
                        <?php if (!empty($mostrarEsEfectivoCaja)): ?>
                            <th>Efectivo caja</th>
                        <?php endif; ?>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formasPago as $forma): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($forma['id_forma_pago']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($forma['forma_pago']); ?></td>
                            <?php if (!empty($mostrarEsEfectivoCaja)): ?>
                                <td><?php echo !empty($forma['es_efectivo']) ? 'Si' : 'No'; ?></td>
                            <?php endif; ?>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="forma_pago.php?accion=actualizar&id=<?php echo $forma['id_forma_pago']; ?>" class="btn-action-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="forma_pago.php?accion=borrar&id=<?php echo $forma['id_forma_pago']; ?>" class="btn-action-danger" title="Eliminar" onclick="return confirm('¿Estas seguro de dar de baja esta forma de pago?');">
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
                <p><i class="bi bi-info-circle"></i> No hay formas de pago registradas.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
