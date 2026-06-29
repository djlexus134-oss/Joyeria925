<?php
/**
 * Galeria de imagenes con acciones Principal / Eliminar.
 *
 * Variables requeridas:
 * - $pieza (array): debe incluir id_pieza.
 * - $imagenesPieza (array)
 *
 * Variables opcionales:
 * - $piezaGaleriaOrigenFoto (bool): si true, enlaces conservan origen=foto.
 */
$piezaGaleriaOrigenFoto = !empty($piezaGaleriaOrigenFoto);
$piezaGaleriaQueryExtra = $piezaGaleriaOrigenFoto ? '&origen=foto' : '';
$idPiezaGaleria = (int) ($pieza['id_pieza'] ?? 0);
?>
<?php if (!empty($imagenesPieza)): ?>
    <div style="display:flex;flex-wrap:wrap;gap:12px;">
        <?php foreach ($imagenesPieza as $imagen): ?>
            <div style="width:160px;border:1px solid #ddd;border-radius:8px;padding:8px;">
                <img src="<?php echo htmlspecialchars((string) $imagen['url_imagen']); ?>" alt="Imagen pieza" style="width:100%;height:110px;object-fit:cover;border-radius:6px;">

                <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ((int) ($imagen['es_principal'] ?? 0) === 1): ?>
                        <span class="btn-action-secondary" style="pointer-events:none;opacity:0.85;">
                            <i class="bi bi-star-fill"></i> Principal
                        </span>
                    <?php else: ?>
                        <a class="btn-action-secondary"
                           href="pieza.php?accion=establecer_principal_imagen&id=<?php echo $idPiezaGaleria; ?>&id_imagen=<?php echo (int) $imagen['id_imagen']; ?><?php echo $piezaGaleriaQueryExtra; ?>"
                           onclick="return confirm('Marcar esta imagen como principal?');">
                            <i class="bi bi-star"></i> Principal
                        </a>
                    <?php endif; ?>

                    <a class="btn-action-danger"
                       href="pieza.php?accion=eliminar_imagen&id=<?php echo $idPiezaGaleria; ?>&id_imagen=<?php echo (int) $imagen['id_imagen']; ?><?php echo $piezaGaleriaQueryExtra; ?>"
                       onclick="return confirm('Eliminar esta imagen?');">
                        <i class="bi bi-trash"></i> Eliminar
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <small><em>No hay imagenes registradas para esta pieza.</em></small>
<?php endif; ?>
