<div class="admin-modules">
    <div class="module-actions">
        <a href="empleado.php?accion=actualizar&id=<?php echo $empleado['id_empleado']; ?>" class="btn-action-secondary">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="empleado.php?accion=borrar&id=<?php echo $empleado['id_empleado']; ?>" class="btn-action-danger"
            onclick="return confirm('¿Estás seguro de que deseas dar de baja este empleado?');">
            <i class="bi bi-trash"></i> Dar de baja
        </a>
        <a href="empleado.php?accion=leer" class="btn-action-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="detail-card">
        <div class="detail-header">
            <h3><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($empleado['nombre']); ?> <?php echo htmlspecialchars($empleado['primer_apellido']); ?> <?php echo htmlspecialchars($empleado['segundo_apellido']); ?></h3>
            <span class="detail-id">ID: #<?php echo str_pad(htmlspecialchars($empleado['id_empleado']), 4, '0', STR_PAD_LEFT); ?></span>
        </div>

        <div class="detail-content">
            <div class="detail-section">
                <h4><i class="bi bi-briefcase"></i> Información Laboral</h4>
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Puesto:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['nombre_puesto']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Salario:</span>
                        <span class="detail-value">$<?php echo number_format($empleado['salario'], 2, '.', ''); ?></span>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="bi bi-file-earmark-text"></i> Documentos</h4>
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">CURP:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['curp']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">RFC:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['rfc']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">NSS:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['nss']); ?></span>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="bi bi-telephone"></i> Contacto</h4>
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Correo:</span>
                        <span class="detail-value"><a href="mailto:<?php echo htmlspecialchars($empleado['correo']); ?>"><?php echo htmlspecialchars($empleado['correo']); ?></a></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Teléfono:</span>
                        <span class="detail-value"><a href="tel:<?php echo htmlspecialchars($empleado['telefono']); ?>"><?php echo htmlspecialchars($empleado['telefono']); ?></a></span>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="bi bi-geo-alt"></i> Dirección Completa</h4>
                <?php if (empty($empleado['id_direccion']) && empty($empleado['id_direccion_FK'])): ?>
                    <p class="detail-value text-muted"><?php echo htmlspecialchars('Sin direccion registrada'); ?></p>
                <?php else: ?>
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Calle:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['nom_calle'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Número:</span>
                        <span class="detail-value">#<?php echo htmlspecialchars(($empleado['num_exterior'] ?? 'N/A')); ?><?php if($empleado['num_interior'] ?? null): ?> Int. <?php echo htmlspecialchars($empleado['num_interior']); ?><?php endif; ?></span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Colonia:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['nom_colonia'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Código Postal:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['codigo_postal'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Localidad:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['nom_localidad'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Municipio:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['nom_municipio'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Estado:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['nom_estado'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">País:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($empleado['nom_pais'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
