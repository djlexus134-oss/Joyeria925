<?php
/** @var array<int, array<string, mixed>> $piezas */
/** @var string $busqueda */
/** @var int $dias */
/** @var int $stockMax */
/** @var int $idTienda */
/** @var array<int, array<string, mixed>> $tiendasActivas */
/** @var string $tiendaNombreFiltro */
/** @var int $totalPiezas */
/** @var int $totalUnidadesStock */
/** @var int $totalVentasPeriodo */
/** @var int $totalSugeridoComprar */
/** @var string $queryPdf */
?>

<div class="admin-modules">
    <section class="admin-card" style="margin-bottom: 1rem;">
        <h3>Filtros de sugerencia de compra</h3>
        <p class="form-hint" style="max-width: 960px;">
            Lista piezas con ventas en el periodo indicado y stock bajo o agotado.
            Prioriza demanda reciente frente a pocas unidades disponibles para armar tu pedido al proveedor.
        </p>

        <form method="get" action="piezas_vendidas.php" class="form-stack" style="max-width: 960px;">
            <input type="hidden" name="accion" value="leer">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;">
                <div>
                    <label for="q"><i class="bi bi-search"></i> Buscar</label>
                    <input class="form-input" type="text" name="q" id="q" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="ID, descripcion, metal, proveedor o tienda">
                </div>
                <div>
                    <label for="dias"><i class="bi bi-calendar-range"></i> Periodo (dias)</label>
                    <input class="form-input" type="number" name="dias" id="dias" min="1" max="365" value="<?php echo (int) $dias; ?>">
                    <p class="form-hint" style="margin-top:0.35rem;">Ventas contadas en los ultimos N dias (default 90).</p>
                </div>
                <div>
                    <label for="stock_max"><i class="bi bi-box"></i> Stock maximo en lista</label>
                    <input class="form-input" type="number" name="stock_max" id="stock_max" min="0" max="99" value="<?php echo (int) $stockMax; ?>">
                    <p class="form-hint" style="margin-top:0.35rem;">Incluye piezas con hasta esta cantidad disponible (mas agotadas).</p>
                </div>
                <div>
                    <label for="id_tienda"><i class="bi bi-shop"></i> Tienda</label>
                    <select class="form-input" name="id_tienda" id="id_tienda">
                        <option value="0"<?php echo $idTienda <= 0 ? ' selected' : ''; ?>>Todas</option>
                        <?php foreach ($tiendasActivas as $tiendaRow): ?>
                            <?php $tid = (int) ($tiendaRow['id_tienda'] ?? 0); ?>
                            <option value="<?php echo $tid; ?>"<?php echo $idTienda === $tid ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($tiendaRow['nom_tienda'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions" style="margin:0;">
                    <button type="submit" class="btn-action-primary"><i class="bi bi-arrow-repeat"></i> Aplicar filtro</button>
                    <a class="btn-action-secondary" href="piezas_vendidas.php?<?php echo htmlspecialchars($queryPdf); ?>"><i class="bi bi-filetype-pdf"></i> Exportar PDF</a>
                </div>
            </div>
        </form>
    </section>

    <section class="admin-card" style="margin-bottom: 1rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;">
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo (int) $totalPiezas; ?></strong><br>Piezas sugeridas</p>
            </div>
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo number_format($totalUnidadesStock, 0, '.', ','); ?></strong><br>Stock disponible</p>
            </div>
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo number_format($totalVentasPeriodo, 0, '.', ','); ?></strong><br>Ventas en periodo</p>
            </div>
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo number_format($totalSugeridoComprar, 0, '.', ','); ?></strong><br>Unidades sugeridas</p>
            </div>
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo (int) $dias; ?></strong><br>Dias de analisis</p>
            </div>
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo htmlspecialchars($tiendaNombreFiltro); ?></strong><br>Tienda</p>
            </div>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-table-wrapper">
            <?php if (empty($piezas)): ?>
                <p class="text-muted" style="margin:0;">No hay piezas que cumplan los criterios de resurtido para el filtro actual.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="text-right">ID</th>
                            <th>Descripción</th>
                            <th>Subfamilia</th>
                            <th>Metal</th>
                            <th>Proveedor</th>
                            <th>Tienda</th>
                            <th class="text-right">Ventas (<?php echo (int) $dias; ?>d)</th>
                            <th class="text-right">Stock</th>
                            <th class="text-right">Sugerido</th>
                            <th>Ultima venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($piezas as $pieza): ?>
                            <?php
                            $idPieza = (int) ($pieza['id_pieza'] ?? 0);
                            $stockActual = (int) ($pieza['stock_actual'] ?? 0);
                            $ventasPeriodo = (int) ($pieza['ventas_periodo'] ?? 0);
                            $sugerido = (int) ($pieza['sugerido_comprar'] ?? 0);
                            $ultimaVenta = $pieza['ultima_venta'] ?? null;
                            $ultimaVentaTxt = '—';
                            if ($ultimaVenta !== null && $ultimaVenta !== '') {
                                $ts = strtotime((string) $ultimaVenta);
                                $ultimaVentaTxt = $ts !== false ? date('d/m/Y', $ts) : (string) $ultimaVenta;
                            }
                            $rowClass = $stockActual === 0 ? ' style="background:#fef2f2;"' : '';
                            ?>
                            <tr<?php echo $rowClass; ?>>
                                <td class="text-right"><strong><?php echo $idPieza; ?></strong></td>
                                <td><?php echo htmlspecialchars((string) ($pieza['desc_pieza'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pieza['nom_sub_familia'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pieza['nom_metal'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pieza['razon_social'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pieza['nom_tienda'] ?? '')); ?></td>
                                <td class="text-right"><?php echo $ventasPeriodo; ?></td>
                                <td class="text-right"><?php echo $stockActual; ?></td>
                                <td class="text-right"><strong><?php echo $sugerido; ?></strong></td>
                                <td><?php echo htmlspecialchars($ultimaVentaTxt); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</div>
