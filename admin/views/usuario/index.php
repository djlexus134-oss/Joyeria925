<div class="admin-modules">

    <?php if (isset($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="usuario.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Usuario
        </a>
    </div>
    <?php
    $listSearchAction = 'usuario.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre, correo o direccion...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($usuarios)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-usuarios" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Nombre Completo</th>
                        <th class="col-correo">Correo</th>
                        <th class="col-telefono">Teléfono</th>
                        <th class="col-direccion">Dirección</th>
                        <th class="col-estado">Estado</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((string) (int) $usuario['id_usuario'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td class="col-nombre">
                                <?php echo htmlspecialchars((string) $usuario['nombre'] . ' ' . $usuario['primer_apellido']); ?>
                                <?php if (!empty($usuario['segundo_apellido'])): ?>
                                    <?php echo htmlspecialchars((string) ' ' . $usuario['segundo_apellido']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="col-correo"><?php echo htmlspecialchars((string) $usuario['correo']); ?></td>
                            <td class="col-telefono"><?php echo htmlspecialchars((string) $usuario['telefono']); ?></td>
                            <td class="col-direccion">
                                <?php if (!empty($usuario['nom_calle'])): ?>
                                    <small>
                                        <?php echo htmlspecialchars((string) $usuario['nom_calle'] . ' ' . $usuario['num_exterior']); ?>
                                        <?php if (!empty($usuario['num_interior'])): ?>
                                            <?php echo htmlspecialchars(' apt. ' . (string) $usuario['num_interior']); ?>
                                        <?php endif; ?>
                                        - <?php echo htmlspecialchars((string) $usuario['nom_colonia']); ?>
                                    </small>
                                <?php else: ?>
                                    <small><em>Sin registrar</em></small>
                                <?php endif; ?>
                            </td>
                            <td class="col-estado">
                                <span class="badge badge-<?php echo (int) $usuario['activo'] === 1 ? 'success' : 'danger'; ?>">
                                    <?php echo (int) $usuario['activo'] === 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="usuario.php?accion=actualizar&id=<?php echo (int) $usuario['id_usuario']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="usuario.php?accion=borrar&id=<?php echo (int) $usuario['id_usuario']; ?>"
                                       class="btn-action-danger"
                                       onclick="return confirm('¿Deseas dar de baja este usuario?');">
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
            <p><i class="bi bi-info-circle"></i> No hay usuarios registrados.</p>
        </div>
    <?php endif; ?>
</div>
