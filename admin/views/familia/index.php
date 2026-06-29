<div class="admin-modules">

    <?php if(isset($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="module-actions-row">
    <div class="module-actions">
        <a href="familia.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nueva Familia
        </a>
    </div>
    <?php
    $listSearchAction = 'familia.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre de familia...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>
    
    <?php if(!empty($familias)): ?>
        <div class="admin-table-wrapper">
        <table id="tabla-familias" class="admin-table">
            <thead>
                <tr>
                    <th class="id-col">ID</th>
                    <th class="name-col">Nombre de Familia</th>
                    <th>Usa talla</th>
                    <th class="actions-col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($familias as $familia): ?>
                    <tr>
                        <td><strong>#<?php echo str_pad(htmlspecialchars($familia['id_familia']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                        <td><?php echo htmlspecialchars($familia['nom_familia']); ?></td>
                        <td>
                            <?php if ((int) ($familia['usa_talla'] ?? 0) === 1): ?>
                                <span class="badge badge-success"><i class="bi bi-check-lg"></i> Si</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">No</span>
                            <?php endif; ?>
                        </td>
                                <td class="actions-cell">
                                     <div class="actions-stack">
                                          <a href="familia.php?accion=actualizar&id=<?php echo $familia['id_familia']; ?>" 
                                              class="btn-action-secondary" title="Editar familia">
                                              <i class="bi bi-pencil"></i> Editar
                                          </a>
                                          <a href="familia.php?accion=borrar&id=<?php echo $familia['id_familia']; ?>" 
                                              class="btn-action-danger" 
                                              title="Eliminar familia"
                                              onclick="return confirm('¿Estás seguro de que deseas eliminar esta familia?');">
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
                <p><i class="bi bi-info-circle"></i> No hay familias registradas.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
