<div class="admin-modules">

    <?php if (isset($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="proveedores.php?accion=contacto_crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Contacto
        </a>
        <a href="proveedores.php?accion=leer" class="btn-action-secondary">
            <i class="bi bi-arrow-left"></i> Volver a Proveedores
        </a>
    </div>
    <?php
    $listSearchAction = 'proveedores.php';
    $listSearchHidden = ['accion' => 'contactos'];
    $listSearchPlaceholder = 'Buscar por nombre, correo, telefono o proveedor...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($contactos)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Proveedor</th>
                        <th>Nombre</th>
                        <th>Telefono</th>
                        <th>Correo</th>
                        <th>Puesto</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contactos as $contacto): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((string) (int) $contacto['id_contacto'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $contacto['razon_social']); ?></td>
                            <td><?php echo htmlspecialchars((string) $contacto['nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($contacto['telefono'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($contacto['correo'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($contacto['puesto'] ?? '')); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="proveedores.php?accion=contacto_actualizar&id=<?php echo (int) $contacto['id_contacto']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="proveedores.php?accion=contacto_borrar&id=<?php echo (int) $contacto['id_contacto']; ?>"
                                       class="btn-action-danger"
                                       onclick="return confirm('Deseas dar de baja este contacto?');">
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
            <p><i class="bi bi-info-circle"></i> No hay contactos de proveedor registrados.</p>
        </div>
    <?php endif; ?>
</div>
