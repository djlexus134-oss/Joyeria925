<div class="admin-modules">
    <style>
        .proveedor-detalle-row td {
            background: #fbfbfd;
            padding: 0.75rem 1rem;
        }
        .proveedor-detalle-box {
            border: 1px solid #e4e7ee;
            border-radius: 8px;
            background: #fff;
            padding: 0.85rem;
        }
        .proveedor-detalle-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }
        .proveedor-detalle-title {
            margin: 0;
            font-size: 0.95rem;
            color: #2f3542;
        }
        .proveedor-contacto-form {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px dashed #d9dee8;
        }
        .proveedor-contacto-form .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
        }
        .proveedor-contacto-form .form-actions {
            margin-top: 0.75rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .contacto-actions-inline {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .contacto-actions-inline .btn-action-secondary,
        .contacto-actions-inline .btn-action-danger {
            padding: 0.35rem 0.55rem;
            font-size: 0.78rem;
        }
    </style>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="proveedores.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Proveedor
        </a>
        <a href="proveedores.php?accion=contactos" class="btn-action-secondary">
            <i class="bi bi-person-lines-fill"></i> Contactos
        </a>
    </div>
    <?php
    $listSearchAction = 'proveedores.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por razon social, RFC, direccion o CP...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($proveedores)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-proveedores" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Razon Social</th>
                        <th class="related-col">Nombre Comercial</th>
                        <th class="related-col">RFC</th>
                        <th class="related-col">Tipo Persona</th>
                        <th class="related-col">Direccion</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proveedores as $proveedor): ?>
                        <?php
                        $idProveedorActual = (int) $proveedor['id_proveedor'];
                        $idFilaContactos = 'contactos-proveedor-' . $idProveedorActual;
                        $idFormContacto = 'contacto-form-' . $idProveedorActual;
                        $listaContactos = $contactosPorProveedor[$idProveedorActual] ?? [];
                        $mostrarFormContacto = isset($abrirFormContactoProveedorId) && (int) $abrirFormContactoProveedorId === $idProveedorActual;
                        ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($proveedor['id_proveedor']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($proveedor['razon_social']); ?></td>
                            <td><?php echo htmlspecialchars($proveedor['nom_comercial'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($proveedor['rfc'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($proveedor['tipo_persona'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                $direccionTexto = ($proveedor['nom_calle'] ?? '') . ' #' . ($proveedor['num_exterior'] ?? '');
                                if (!empty($proveedor['num_interior'])) {
                                    $direccionTexto .= ' Int. ' . $proveedor['num_interior'];
                                }
                                $direccionTexto .= ', ' . ($proveedor['nom_colonia'] ?? '') . ', CP ' . ($proveedor['codigo_postal'] ?? '');
                                $direccionTexto = trim($direccionTexto, " \t\n\r\0\x0B,");
                                echo htmlspecialchars(empty($proveedor['id_direccion_FK']) ? 'Sin direccion' : ($direccionTexto !== '' ? $direccionTexto : 'Sin direccion'));
                                ?>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="proveedores.php?accion=actualizar&id=<?php echo $proveedor['id_proveedor']; ?>" class="btn-action-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <button type="button"
                                            class="btn-action-secondary js-toggle-contactos"
                                            data-target="<?php echo htmlspecialchars($idFilaContactos); ?>"
                                            aria-expanded="false">
                                        <i class="bi bi-people"></i> Ver contactos
                                    </button>
                                    <a href="proveedores.php?accion=borrar&id=<?php echo $proveedor['id_proveedor']; ?>" class="btn-action-danger" title="Eliminar" onclick="return confirm('Estas seguro de dar de baja este proveedor?');">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr id="<?php echo htmlspecialchars($idFilaContactos); ?>" class="proveedor-detalle-row" style="display:none;">
                            <td colspan="7">
                                <div class="proveedor-detalle-box">
                                    <div class="proveedor-detalle-head">
                                        <h4 class="proveedor-detalle-title"><i class="bi bi-person-lines-fill"></i> Contactos del proveedor</h4>
                                        <button type="button"
                                                class="btn-action-secondary js-toggle-form-contacto"
                                                data-target="<?php echo htmlspecialchars($idFormContacto); ?>">
                                            <i class="bi bi-plus-circle"></i> Agregar contacto
                                        </button>
                                    </div>
                                    <?php if (!empty($listaContactos)): ?>
                                        <table class="admin-table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
                                                    <th>Teléfono</th>
                                                    <th>Correo</th>
                                                    <th>Puesto</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($listaContactos as $contacto): ?>
                                                    <?php
                                                    $idContacto = (int) ($contacto['id_contacto'] ?? 0);
                                                    $idFormEditContacto = 'form-editar-contacto-' . $idContacto;
                                                    $modoEditarContacto = isset($editarContactoId) && (int) $editarContactoId === $idContacto;
                                                    ?>
                                                    <tr>
                                                        <td><strong>#<?php echo str_pad((string) $idContacto, 3, '0', STR_PAD_LEFT); ?></strong></td>
                                                        <td><?php echo htmlspecialchars((string) ($contacto['nombre'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($contacto['telefono'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($contacto['correo'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($contacto['puesto'] ?? '')); ?></td>
                                                        <td>
                                                            <div class="contacto-actions-inline">
                                                                <button type="button"
                                                                        class="btn-action-secondary js-toggle-form-contacto"
                                                                        data-target="<?php echo htmlspecialchars($idFormEditContacto); ?>">
                                                                    <i class="bi bi-pencil"></i> Editar
                                                                </button>
                                                                <a href="proveedores.php?accion=borrar_contacto&id=<?php echo $idContacto; ?>"
                                                                   class="btn-action-danger"
                                                                   onclick="return confirm('¿Deseas eliminar este contacto?');">
                                                                    <i class="bi bi-trash"></i> Eliminar
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr id="<?php echo htmlspecialchars($idFormEditContacto); ?>" style="display:<?php echo $modoEditarContacto ? 'table-row' : 'none'; ?>;">
                                                        <td colspan="6">
                                                            <form action="proveedores.php?accion=actualizar_contacto&id=<?php echo $idContacto; ?>" method="POST" class="proveedor-contacto-form" style="display:block;margin-top:0;padding-top:0;border-top:none;">
                                                                <input type="hidden" name="id_proveedor_FK" value="<?php echo $idProveedorActual; ?>">
                                                                <div class="form-row">
                                                                    <div class="form-group">
                                                                        <label for="nombre_contacto_edit_<?php echo $idContacto; ?>">Nombre</label>
                                                                        <input id="nombre_contacto_edit_<?php echo $idContacto; ?>" class="form-input" type="text" name="nombre" maxlength="100" required value="<?php echo htmlspecialchars((string) ($contacto['nombre'] ?? '')); ?>">
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="telefono_contacto_edit_<?php echo $idContacto; ?>">Telefono</label>
                                                                        <input id="telefono_contacto_edit_<?php echo $idContacto; ?>" class="form-input" type="text" name="telefono" maxlength="20" value="<?php echo htmlspecialchars((string) ($contacto['telefono'] ?? '')); ?>">
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="correo_contacto_edit_<?php echo $idContacto; ?>">Correo</label>
                                                                        <input id="correo_contacto_edit_<?php echo $idContacto; ?>" class="form-input" type="email" name="correo" maxlength="80" value="<?php echo htmlspecialchars((string) ($contacto['correo'] ?? '')); ?>">
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="puesto_contacto_edit_<?php echo $idContacto; ?>">Puesto</label>
                                                                        <input id="puesto_contacto_edit_<?php echo $idContacto; ?>" class="form-input" type="text" name="puesto" maxlength="50" value="<?php echo htmlspecialchars((string) ($contacto['puesto'] ?? '')); ?>">
                                                                    </div>
                                                                </div>
                                                                <div class="form-actions">
                                                                    <button type="submit" class="btn-action-primary"><i class="bi bi-check-lg"></i> Guardar cambios</button>
                                                                    <button type="button"
                                                                            class="btn-action-secondary js-toggle-form-contacto"
                                                                            data-target="<?php echo htmlspecialchars($idFormEditContacto); ?>">
                                                                        <i class="bi bi-x-lg"></i> Cancelar
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="alert-message info" style="margin:0;">
                                            <p><i class="bi bi-info-circle"></i> Este proveedor no tiene contactos registrados.</p>
                                        </div>
                                    <?php endif; ?>

                                    <form id="<?php echo htmlspecialchars($idFormContacto); ?>"
                                          action="proveedores.php?accion=crear_contacto"
                                          method="POST"
                                          class="proveedor-contacto-form"
                                          style="display:<?php echo $mostrarFormContacto ? 'block' : 'none'; ?>;">
                                        <input type="hidden" name="id_proveedor_FK" value="<?php echo $idProveedorActual; ?>">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="nombre_contacto_<?php echo $idProveedorActual; ?>"><i class="bi bi-person"></i> Nombre</label>
                                                <input id="nombre_contacto_<?php echo $idProveedorActual; ?>" class="form-input" type="text" name="nombre" maxlength="100" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="telefono_contacto_<?php echo $idProveedorActual; ?>"><i class="bi bi-telephone"></i> Telefono</label>
                                                <input id="telefono_contacto_<?php echo $idProveedorActual; ?>" class="form-input" type="text" name="telefono" maxlength="20">
                                            </div>
                                            <div class="form-group">
                                                <label for="correo_contacto_<?php echo $idProveedorActual; ?>"><i class="bi bi-envelope"></i> Correo</label>
                                                <input id="correo_contacto_<?php echo $idProveedorActual; ?>" class="form-input" type="email" name="correo" maxlength="80">
                                            </div>
                                            <div class="form-group">
                                                <label for="puesto_contacto_<?php echo $idProveedorActual; ?>"><i class="bi bi-briefcase"></i> Puesto</label>
                                                <input id="puesto_contacto_<?php echo $idProveedorActual; ?>" class="form-input" type="text" name="puesto" maxlength="50">
                                            </div>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" class="btn-action-primary"><i class="bi bi-check-lg"></i> Guardar contacto</button>
                                            <button type="button" class="btn-action-secondary js-toggle-form-contacto" data-target="<?php echo htmlspecialchars($idFormContacto); ?>">
                                                <i class="bi bi-x-lg"></i> Cancelar
                                            </button>
                                        </div>
                                    </form>
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
                <p><i class="bi bi-info-circle"></i> No hay proveedores registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-toggle-contactos').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var targetId = boton.getAttribute('data-target');
            if (!targetId) {
                return;
            }
            var fila = document.getElementById(targetId);
            if (!fila) {
                return;
            }
            var expandido = fila.style.display !== 'none';
            fila.style.display = expandido ? 'none' : '';
            boton.setAttribute('aria-expanded', expandido ? 'false' : 'true');
            boton.innerHTML = expandido
                ? '<i class="bi bi-people"></i> Ver contactos'
                : '<i class="bi bi-people-fill"></i> Ocultar contactos';
        });
    });

    document.querySelectorAll('.js-toggle-form-contacto').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var targetId = boton.getAttribute('data-target');
            if (!targetId) {
                return;
            }
            var form = document.getElementById(targetId);
            if (!form) {
                return;
            }
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
    });

    var formAbierto = document.querySelector('.proveedor-contacto-form[id^="contacto-form-"][style*="display:block"]');
    if (formAbierto) {
        var filaPadre = formAbierto.closest('.proveedor-detalle-row');
        if (filaPadre) {
            filaPadre.style.display = '';
            var idFila = filaPadre.getAttribute('id');
            if (idFila) {
                var botonRelacion = document.querySelector('.js-toggle-contactos[data-target="' + idFila + '"]');
                if (botonRelacion) {
                    botonRelacion.setAttribute('aria-expanded', 'true');
                    botonRelacion.innerHTML = '<i class="bi bi-people-fill"></i> Ocultar contactos';
                }
            }
        }
    }

    var filaEdicionAbierta = document.querySelector('tr[id^="form-editar-contacto-"][style*="display:table-row"]');
    if (filaEdicionAbierta) {
        var contenedor = filaEdicionAbierta.closest('.proveedor-detalle-row');
        if (contenedor) {
            contenedor.style.display = '';
            var idFilaCont = contenedor.getAttribute('id');
            if (idFilaCont) {
                var boton = document.querySelector('.js-toggle-contactos[data-target="' + idFilaCont + '"]');
                if (boton) {
                    boton.setAttribute('aria-expanded', 'true');
                    boton.innerHTML = '<i class="bi bi-people-fill"></i> Ocultar contactos';
                }
            }
        }
    }
});
</script>
