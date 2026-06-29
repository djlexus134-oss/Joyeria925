<?php
/**
 * Tabla unificada: apartados activos + buscar cliente + modal piezas.
 * Variables previas al include:
 * @var string $aa_context 'consulta' | 'cambio' | 'unificado'
 * @var array  $aa_rows
 * @var array  $aa_catalogoClientes
 * @var string $aa_heading
 * @var string $aa_intro
 * consulta: $aa_puede_abonar, $aa_puede_cambio_link
 * unificado: $aa_puede_abonar, $aa_puede_quitar_pieza, $aa_puede_agregar_pieza
 * $aa_puede_ver_abonos: mostrar boton para consultar historial de abonos (lectura del modulo)
 */
$aa_context = isset($aa_context) ? (string) $aa_context : 'consulta';
$aa_puede_ver_abonos = !isset($aa_puede_ver_abonos) ? true : !empty($aa_puede_ver_abonos);
$aa_rows = isset($aa_rows) && is_array($aa_rows) ? $aa_rows : [];
$aa_catalogoClientes = isset($aa_catalogoClientes) && is_array($aa_catalogoClientes) ? $aa_catalogoClientes : [];
$aa_heading = isset($aa_heading) ? (string) $aa_heading : 'Apartados activos';
$aa_intro = isset($aa_intro) ? (string) $aa_intro : '';
?>
<div class="form-section" style="margin-top:2rem;">
    <h3><i class="bi bi-grid-3x2-gap"></i> <?php echo htmlspecialchars($aa_heading); ?></h3>
    <?php if ($aa_intro !== ''): ?>
        <p class="text-muted" style="margin-bottom:1rem;"><?php echo $aa_intro; ?></p>
    <?php endif; ?>
    <div class="form-row" style="max-width: 520px; margin-bottom: 1rem;">
        <div class="form-group">
            <label for="aa_filtro_cliente"><i class="bi bi-person-lines-fill"></i> Buscar cliente</label>
            <select id="aa_filtro_cliente" class="form-input" title="Buscar por nombre, apellido, correo o teléfono">
            <?php
            $clientes = $aa_catalogoClientes;
            $selectedId = '';
            $emptyLabel = 'Todos los clientes';
            $emptyValue = '';
            require __DIR__ . '/cliente_select_options.php';
            ?>
            </select>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Estado</th>
                    <th>Cliente</th>
                    <th class="text-right">Lineas</th>
                    <th>Código pieza</th>
                    <th>Vencimiento</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Saldo</th>
                    <th>Piezas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="aa_tabla_apartados_body">
                <?php if (empty($aa_rows)): ?>
                    <tr><td colspan="10">Sin registros.</td></tr>
                <?php else: ?>
                    <?php foreach ($aa_rows as $r): ?>
                        <tr>
                            <td><?php echo (int) ($r['id_apartado'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['estado'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['cliente_nombre'] ?? '')); ?></td>
                            <td class="text-right"><?php echo (int) ($r['lineas_count'] ?? 1); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['codigo_pieza'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['fecha_vencimiento'] ?? '')); ?></td>
                            <td class="text-right">$<?php echo htmlspecialchars(number_format((float) ($r['total_apartado'] ?? 0), 2, '.', '')); ?></td>
                            <td class="text-right">$<?php echo htmlspecialchars(number_format((float) ($r['saldo_pendiente'] ?? 0), 2, '.', '')); ?></td>
                            <td>
                                <button type="button" class="btn-action-secondary aa-btn-piezas" data-id-apartado="<?php echo (int) ($r['id_apartado'] ?? 0); ?>">
                                    <i class="bi bi-box-seam"></i> Ver
                                </button>
                            </td>
                            <td class="aa-td-acciones" style="white-space:nowrap;">
                                <?php if ($aa_puede_ver_abonos): ?>
                                    <button type="button" class="btn-action-secondary aa-btn-ver-abonos" data-id-apartado="<?php echo (int) ($r['id_apartado'] ?? 0); ?>" title="Ver abonos registrados">
                                        <i class="bi bi-receipt"></i> Abonos
                                    </button>
                                <?php endif; ?>
                                <?php if ($aa_context === 'unificado'): ?>
                                    <?php if (!empty($aa_puede_abonar)): ?>
                                        <button type="button" class="btn-action-primary aa-btn-abonar" data-id-apartado="<?php echo (int) ($r['id_apartado'] ?? 0); ?>">
                                            <i class="bi bi-cash-coin"></i> Abonar
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($aa_puede_quitar_pieza)): ?>
                                        <button type="button" class="btn-action-secondary aa-btn-quitar-pieza" data-id-apartado="<?php echo (int) ($r['id_apartado'] ?? 0); ?>" style="margin-left:4px;">
                                            <i class="bi bi-dash-square"></i> Quitar pieza
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($aa_puede_agregar_pieza)): ?>
                                        <button type="button" class="btn-action-secondary aa-btn-agregar-pieza" data-id-apartado="<?php echo (int) ($r['id_apartado'] ?? 0); ?>" style="margin-left:4px;">
                                            <i class="bi bi-plus-square"></i> Agregar pieza
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($aa_context === 'consulta'): ?>
                                    <?php if (!empty($aa_puede_abonar)): ?>
                                        <button type="button" class="btn-action-primary aa-btn-abonar" data-id-apartado="<?php echo (int) ($r['id_apartado'] ?? 0); ?>">
                                            <i class="bi bi-cash-coin"></i> Abonar
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($aa_puede_cambio_link)): ?>
                                        <a href="apartados_operaciones.php?accion=leer&amp;destino=quitar&amp;id_apartado=<?php echo (int) ($r['id_apartado'] ?? 0); ?>" class="btn-action-secondary aa-link-cambio" style="display:inline-block;margin-left:4px;">
                                            <i class="bi bi-dash-square"></i> Quitar pieza
                                        </a>
                                        <a href="apartados_operaciones.php?accion=leer&amp;destino=agregar&amp;id_apartado=<?php echo (int) ($r['id_apartado'] ?? 0); ?>" class="btn-action-secondary aa-link-agregar" style="display:inline-block;margin-left:4px;">
                                            <i class="bi bi-plus-square"></i> Agregar pieza
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (!empty($aa_link_abonar)): ?>
                                        <a href="apartados_operaciones.php?accion=leer&amp;destino=abono&amp;id_apartado=<?php echo (int) ($r['id_apartado'] ?? 0); ?>" class="btn-action-primary aa-link-abonar" style="display:inline-block;">
                                            <i class="bi bi-cash-coin"></i> Abonar
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($aa_usar_cambio)): ?>
                                        <button type="button" class="btn-action-secondary aa-btn-usar-cambio" data-id-apartado="<?php echo (int) ($r['id_apartado'] ?? 0); ?>" style="margin-left:4px;">
                                            <i class="bi bi-arrow-left-right"></i> Usar en cambio
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <dialog id="aa_modal_piezas" class="admin-dialog admin-dialog--wide">
        <div class="ja-modal-card">
            <h3 id="aa_modal_piezas_title" style="margin-top:0;">Piezas del apartado</h3>
            <p class="text-muted" style="font-size:0.9rem;margin:0 0 12px 0;" id="aa_modal_piezas_sub"></p>
            <div class="admin-table-wrapper" style="max-height:50vh;overflow:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Estado stock</th>
                            <th class="text-right">Precio apartado</th>
                        </tr>
                    </thead>
                    <tbody id="aa_modal_piezas_body"></tbody>
                </table>
            </div>
        </div>
        <div class="ja-modal-footer">
            <button type="button" class="btn-action-primary" id="aa_modal_piezas_cerrar">Cerrar</button>
        </div>
    </dialog>

    <dialog id="aa_modal_abonos" class="admin-dialog admin-dialog--wide">
        <div class="ja-modal-card">
            <h3 id="aa_modal_abonos_title" style="margin-top:0;">Abonos del apartado</h3>
            <p class="text-muted" style="font-size:0.9rem;margin:0 0 12px 0;" id="aa_modal_abonos_sub"></p>
            <div class="admin-table-wrapper" style="max-height:50vh;overflow:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th class="text-right">Monto</th>
                            <th>Forma de pago</th>
                            <th>Origen</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="aa_modal_abonos_body"></tbody>
                </table>
            </div>
            <p id="aa_modal_abonos_total" class="text-muted" style="font-size:0.9rem;margin:12px 0 0 0;"></p>
        </div>
        <div class="ja-modal-footer">
            <button type="button" class="btn-action-primary" id="aa_modal_abonos_cerrar">Cerrar</button>
        </div>
    </dialog>
</div>
