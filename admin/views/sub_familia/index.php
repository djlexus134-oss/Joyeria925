<div class="admin-modules">

    <?php if(isset($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="module-actions-row">
    <div class="module-actions">
        <a href="sub_familia.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nueva Subfamilia
        </a>
    </div>
    <?php
    $listSearchAction = 'sub_familia.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por subfamilia o familia...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>
    
    <?php if(!empty($subfamilias)): ?>
        <div class="admin-table-wrapper">
        <table id="tabla-subfamilias" class="admin-table">
            <thead>
                <tr>
                    <th class="id-col">ID</th>
                    <th class="name-col">Nombre de Subfamilia</th>
                    <th class="related-col">Familia</th>
                    <th class="actions-col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subfamilias as $subfamilia): ?>
                    <tr>
                        <td><strong><?php echo str_pad(htmlspecialchars($subfamilia['id_sub_familia']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                        <td><?php echo htmlspecialchars($subfamilia['nom_sub_familia']); ?></td>
                        <td><span class="table-accent-text"><?php echo htmlspecialchars($subfamilia['nom_familia']); ?></span></td>
                                <td class="actions-cell">
                                     <div class="actions-stack">
                                          <a href="sub_familia.php?accion=actualizar&id=<?php echo $subfamilia['id_sub_familia']; ?>" 
                                              class="btn-action-secondary" title="Editar subfamilia">
                                              <i class="bi bi-pencil"></i> Editar
                                          </a>
                                          <a href="sub_familia.php?accion=borrar&id=<?php echo $subfamilia['id_sub_familia']; ?>" 
                                              class="btn-action-danger" 
                                              title="Eliminar subfamilia"
                                              onclick="return confirm('¿Estás seguro de que deseas eliminar esta subfamilia?');">
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
                <p><i class="bi bi-info-circle"></i> No hay subfamilias registradas.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
