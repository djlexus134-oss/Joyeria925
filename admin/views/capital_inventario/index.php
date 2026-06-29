<?php
/** @var array<int, array<string, mixed>> $filas */
/** @var array<string, mixed> $resumen */
/** @var array<int, array{id_tienda: int, nom_tienda: string}> $tiendasActivas */
/** @var array<int, array{id_familia: int, nom_familia: string}> $familiasActivas */
/** @var int $idTienda */
/** @var int $idFamilia */
/** @var string $tiendaNombreFiltro */
/** @var string $familiaNombreFiltro */
/** @var bool $mostrarSubtotalesFamilia */
/** @var string $queryPdf */

$fmtMoney = static function (float $monto): string {
    return '$' . number_format($monto, 2, '.', ',');
};
?>

<div class="admin-modules">
    <section class="admin-card" style="margin-bottom: 1rem;">
        <h3>Filtros</h3>
        <p class="form-hint" style="max-width: 900px;">
            Muestra el capital invertido en inventario disponible: costo de compra de cada pieza de catálogo
            multiplicado por sus unidades en stock con estado <strong>disponible</strong>.
            No incluye efectivo de caja ni piezas apartadas o vendidas.
        </p>

        <form method="get" action="capital_inventario.php" class="form-stack" style="max-width: 900px;">
            <input type="hidden" name="accion" value="leer">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;align-items:end;">
                <div>
                    <label for="id_tienda"><i class="bi bi-shop"></i> Tienda</label>
                    <select class="form-input" name="id_tienda" id="id_tienda">
                        <option value="0"<?php echo $idTienda === 0 ? ' selected' : ''; ?>>— Todas las tiendas —</option>
                        <?php foreach ($tiendasActivas as $t): ?>
                            <?php $tid = (int) ($t['id_tienda'] ?? 0); ?>
                            <option value="<?php echo $tid; ?>"<?php echo $idTienda === $tid ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($t['nom_tienda'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="id_familia"><i class="bi bi-diagram-3"></i> Familia</label>
                    <select class="form-input" name="id_familia" id="id_familia">
                        <option value="0"<?php echo $idFamilia === 0 ? ' selected' : ''; ?>>— Todas las familias —</option>
                        <?php foreach ($familiasActivas as $f): ?>
                            <?php $fid = (int) ($f['id_familia'] ?? 0); ?>
                            <option value="<?php echo $fid; ?>"<?php echo $idFamilia === $fid ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($f['nom_familia'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions" style="margin:0;">
                    <button type="submit" class="btn-action-primary"><i class="bi bi-arrow-repeat"></i> Aplicar filtro</button>
                    <a class="btn-action-secondary" href="capital_inventario.php?<?php echo htmlspecialchars($queryPdf); ?>"><i class="bi bi-filetype-pdf"></i> Exportar PDF</a>
                </div>
            </div>
        </form>
        <p class="form-hint" style="margin-top:0.75rem;">
            <strong>Filtro activo:</strong>
            Tienda <?php echo htmlspecialchars($tiendaNombreFiltro); ?>
            &nbsp;|&nbsp;
            Familia <?php echo htmlspecialchars($familiaNombreFiltro); ?>
        </p>
    </section>

    <section class="admin-card" style="margin-bottom: 1rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo htmlspecialchars($fmtMoney((float) ($resumen['total_costo'] ?? 0))); ?></strong><br>Costo total en inventario</p>
            </div>
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo number_format((int) ($resumen['total_unidades'] ?? 0), 0, '.', ','); ?></strong><br>Unidades disponibles</p>
            </div>
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo (int) ($resumen['num_piezas'] ?? 0); ?></strong><br>Piezas de catálogo</p>
            </div>
            <div class="alert-message info" style="margin:0;">
                <p><strong><?php echo (int) ($resumen['num_familias'] ?? 0); ?></strong><br>Familias con stock</p>
            </div>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-table-wrapper">
            <?php if (empty($filas)): ?>
                <p class="text-muted" style="margin:0;">No hay inventario disponible para el filtro actual.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Familia</th>
                            <th>Subfamilia</th>
                            <th class="text-right">ID</th>
                            <th>Descripción</th>
                            <th>Metal</th>
                            <th>Tienda</th>
                            <th class="text-right">Unidades</th>
                            <th class="text-right">Costo unit.</th>
                            <th class="text-right">Costo total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $familiaAnterior = null;
                        $subUnidades = 0;
                        $subCosto = 0.0;
                        $subNombre = '';

                        $emitirSubtotal = static function () use (
                            &$subUnidades,
                            &$subCosto,
                            &$subNombre,
                            $fmtMoney,
                            $mostrarSubtotalesFamilia
                        ): void {
                            if (!$mostrarSubtotalesFamilia || $subNombre === '') {
                                return;
                            }
                            ?>
                            <tr class="capital-inventario-subtotal">
                                <td colspan="6"><strong>Subtotal <?php echo htmlspecialchars($subNombre); ?></strong></td>
                                <td class="text-right"><strong><?php echo number_format($subUnidades, 0, '.', ','); ?></strong></td>
                                <td></td>
                                <td class="text-right"><strong><?php echo htmlspecialchars($fmtMoney($subCosto)); ?></strong></td>
                            </tr>
                            <?php
                        };

                        foreach ($filas as $fila):
                            $idFamiliaFila = (int) ($fila['id_familia'] ?? 0);
                            if ($mostrarSubtotalesFamilia && $familiaAnterior !== null && $idFamiliaFila !== $familiaAnterior) {
                                $emitirSubtotal();
                                $subUnidades = 0;
                                $subCosto = 0.0;
                                $subNombre = '';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($fila['nom_familia'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($fila['nom_sub_familia'] ?? '')); ?></td>
                                <td class="text-right"><strong><?php echo (int) ($fila['id_pieza'] ?? 0); ?></strong></td>
                                <td><?php echo htmlspecialchars((string) ($fila['desc_pieza'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($fila['nom_metal'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($fila['nom_tienda'] ?? '')); ?></td>
                                <td class="text-right"><?php echo (int) ($fila['unidades'] ?? 0); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($fmtMoney((float) ($fila['costo_unitario'] ?? 0))); ?></td>
                                <td class="text-right"><strong><?php echo htmlspecialchars($fmtMoney((float) ($fila['costo_total'] ?? 0))); ?></strong></td>
                            </tr>
                            <?php
                            if ($mostrarSubtotalesFamilia) {
                                $subUnidades += (int) ($fila['unidades'] ?? 0);
                                $subCosto += (float) ($fila['costo_total'] ?? 0);
                                $subNombre = (string) ($fila['nom_familia'] ?? '');
                                $familiaAnterior = $idFamiliaFila;
                            }
                        endforeach;

                        if ($mostrarSubtotalesFamilia && $subNombre !== '') {
                            $emitirSubtotal();
                        }
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</div>

<style>
    .capital-inventario-subtotal td {
        background: #f3f4f6;
        border-top: 2px solid #d1d5db;
    }
</style>
