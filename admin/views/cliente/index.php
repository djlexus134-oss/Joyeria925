<?php
require_once __DIR__ . '/../../includes/cliente_correo.php';
?>
<div class="admin-modules">

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="cliente.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Cliente
        </a>
    </div>
    <?php
    $listSearchAction = 'cliente.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre completo, correo, teléfono o dirección...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($clientes)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-clientes" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Cliente</th>
                        <th class="related-col">Correo</th>
                        <th class="related-col">Telefono</th>
                        <th class="related-col">Descuento</th>
                        <th class="col-direccion">Direccion</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($cliente['id_cliente']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td class="col-nombre">
                                <?php
                                $nombreCompleto = trim((string) ($cliente['nombre'] . ' ' . $cliente['primer_apellido'] . ' ' . ($cliente['segundo_apellido'] ?? '')));
                                echo htmlspecialchars($nombreCompleto);
                                ?>
                            </td>
                            <td><?php
                                $correoLista = (string) ($cliente['correo'] ?? '');
                                echo joyeria_cliente_correo_es_sintetico($correoLista)
                                    ? '<span class="text-muted">Sin correo</span>'
                                    : htmlspecialchars($correoLista);
                                ?></td>
                            <td><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                            <td><?php echo number_format((float) ($cliente['descuento_porcentaje'] ?? 0), 2); ?>%</td>
                            <td class="col-direccion">
                                <?php
                                $direccion = trim((string) (($cliente['nom_calle'] ?? '') . ' #' . ($cliente['num_exterior'] ?? '') . ' Col. ' . ($cliente['nom_colonia'] ?? '') . ' CP ' . ($cliente['codigo_postal'] ?? '')));
                                echo htmlspecialchars($direccion);
                                ?>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="cliente.php?accion=actualizar&id=<?php echo $cliente['id_cliente']; ?>" class="btn-action-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="cliente.php?accion=borrar&id=<?php echo $cliente['id_cliente']; ?>" class="btn-action-danger" title="Borrar" onclick="return confirm('¿Estas seguro de dar de baja este cliente?');">
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
                <p><i class="bi bi-info-circle"></i> No hay clientes registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
