<?php
require_once(__DIR__ . "/../header.php");

$piezaParaRecalculo = $piezaParaRecalculo ?? null;
$calculos = $calculos ?? [];
$modoRecalculo = $modoRecalculo ?? 'directo';
$markupPctDefault = $markupPctDefault ?? 10;
$costoDirecto = $costoDirecto ?? 0;
$pesoGramos = $pesoGramos ?? 0;
$precioPorGramo = $precioPorGramo ?? 0;
?>

<header class="admin-header">
    <h2>Recalcular Precios de Stock</h2>
</header>

<div class="admin-main">
    <?php if (!empty($piezaParaRecalculo) && !empty($calculos)): ?>
        <div class="recalculo-container">
            <h3><?php echo htmlspecialchars((string) ($piezaParaRecalculo['desc_pieza'] ?? '')); ?></h3>
            <p class="recalculo-intro">
                Elige cómo quieres recalcular el precio de venta. Solo se muestran los campos que corresponden al modo seleccionado.
            </p>

            <form action="pieza.php?accion=aplicar_recalculo&id=<?php echo (int) $piezaParaRecalculo['id_pieza']; ?>" method="POST" id="formRecalculo">
                <input type="hidden" name="aplicar_recalculo" value="1">
                <input type="hidden" name="id_pieza" value="<?php echo (int) $piezaParaRecalculo['id_pieza']; ?>">

                <div class="recalculo-mode-card">
                    <label for="modo_recalculo">Modo de recálculo</label>
                    <select id="modo_recalculo" name="modo_recalculo" class="recalculo-select">
                        <option value="directo" <?php echo $modoRecalculo === 'directo' ? 'selected' : ''; ?>>Costo directo</option>
                        <option value="gramo" <?php echo $modoRecalculo === 'gramo' ? 'selected' : ''; ?>>Precio por gramo</option>
                    </select>
                    <small>Si cambias el modo, se mostrarán solo los campos relacionados con ese cálculo y se guardarán en la pieza.</small>
                </div>

                <div class="recalculo-grid">
                    <div class="recalculo-field recalculo-field-directo">
                        <label for="costo_directo">Costo directo</label>
                        <input type="number" id="costo_directo" name="costo_directo" step="0.01" min="0" value="<?php echo htmlspecialchars(number_format((float) $costoDirecto, 2, '.', '')); ?>">
                        <small>Se guarda en BD como el nuevo costo de la pieza cuando recalculas por costo directo.</small>
                    </div>

                    <div class="recalculo-field recalculo-field-gramo">
                        <label for="precio_por_gramo">Precio por gramo</label>
                        <input type="number" id="precio_por_gramo" name="precio_por_gramo" step="0.0001" min="0" value="<?php echo htmlspecialchars($precioPorGramo > 0 ? number_format((float) $precioPorGramo, 4, '.', '') : ''); ?>">
                        <small>Se guarda en BD como precio por gramo para recalcular y dejar trazabilidad.</small>
                    </div>

                    <div class="recalculo-field recalculo-field-gramo">
                        <label for="gramos">Gramos</label>
                        <input type="number" id="gramos" name="gramos" step="0.01" min="0" value="<?php echo htmlspecialchars(number_format((float) $pesoGramos, 2, '.', '')); ?>">
                        <small>Se guarda en BD junto con el precio por gramo.</small>
                    </div>

                    <div class="recalculo-field">
                        <label for="markup_pct">Porcentaje de aumento (%)</label>
                        <input type="number" id="markup_pct" name="markup_pct" step="0.01" min="0" value="<?php echo htmlspecialchars(number_format((float) $markupPctDefault, 2, '.', '')); ?>">
                        <small>Margen que se aplicará sobre el costo recalculado.</small>
                    </div>
                </div>

                <div class="recalculo-summary">
                    <div>
                        <strong>Piezas stock:</strong> <?php echo (int) count($calculos); ?>
                    </div>
                    <div>
                        <strong>Modo activo:</strong> <span id="modoActivoTexto"><?php echo $modoRecalculo === 'gramo' ? 'Precio por gramo' : 'Costo directo'; ?></span>
                    </div>
                </div>

                <table class="recalculo-preview">
                    <thead>
                        <tr>
                            <th>Código auxiliar</th>
                            <th>Precio actual</th>
                            <th>Precio nuevo</th>
                            <th>Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calculos as $calculo): ?>
                            <tr data-id="<?php echo (int) $calculo['id_pieza_stock']; ?>" data-actual="<?php echo htmlspecialchars((string) $calculo['precio_venta_anterior']); ?>">
                                <td><?php echo htmlspecialchars((string) $calculo['codigo_auxiliar']); ?></td>
                                <td class="text-right">$<?php echo htmlspecialchars((string) $calculo['precio_venta_anterior']); ?></td>
                                <td class="text-right">
                                    $<span class="precio-nuevo" id="precio_nuevo_<?php echo (int) $calculo['id_pieza_stock']; ?>"><?php echo htmlspecialchars((string) $calculo['precio_venta_nuevo']); ?></span>
                                </td>
                                <td class="text-right diferencia-celda <?php echo ((float) $calculo['diferencia'] >= 0) ? 'diff-positiva' : 'diff-negativa'; ?>" id="diff_<?php echo (int) $calculo['id_pieza_stock']; ?>">
                                    <?php echo ((float) $calculo['diferencia'] >= 0) ? '+' : ''; ?><?php echo htmlspecialchars((string) $calculo['diferencia']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="recalculo-buttons">
                    <button type="submit" class="btn-action-primary">
                        <i class="bi bi-check-lg"></i> Aplicar Cambios
                    </button>
                    <a href="pieza.php?accion=leer" class="btn-action-secondary">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <style>
            .recalculo-container {
                max-width: 980px;
                margin: 20px auto;
                padding: 22px;
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
            }

            .recalculo-container h3 {
                margin: 0 0 8px 0;
            }

            .recalculo-intro {
                margin: 0 0 18px 0;
                color: #5b6472;
            }

            .recalculo-mode-card {
                padding: 14px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: #f8fafc;
                margin-bottom: 16px;
            }

            .recalculo-mode-card label,
            .recalculo-field label {
                display: block;
                margin-bottom: 6px;
                font-weight: 600;
                color: #1f2937;
            }

            .recalculo-select,
            .recalculo-field input {
                width: 100%;
                max-width: 320px;
                padding: 10px 12px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                background: #fff;
                font-size: 14px;
            }

            .recalculo-mode-card small,
            .recalculo-field small {
                display: block;
                margin-top: 6px;
                color: #6b7280;
                font-size: 12px;
            }

            .recalculo-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px;
                margin-bottom: 16px;
            }

            .recalculo-field {
                padding: 14px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: #fff;
            }

            .recalculo-summary {
                display: flex;
                gap: 18px;
                flex-wrap: wrap;
                margin: 12px 0 16px;
                padding: 12px 14px;
                border-radius: 10px;
                background: #f8fafc;
                border: 1px solid #e5e7eb;
                color: #334155;
            }

            .recalculo-preview {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0 18px;
                overflow: hidden;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
            }

            .recalculo-preview thead {
                background: #111827;
                color: white;
            }

            .recalculo-preview th,
            .recalculo-preview td {
                padding: 12px;
                border-bottom: 1px solid #e5e7eb;
            }

            .recalculo-preview th {
                text-align: left;
            }

            .text-right {
                text-align: right;
            }

            .precio-nuevo {
                font-weight: 700;
                color: #0f766e;
            }

            .diff-positiva {
                color: #16a34a;
                font-weight: 600;
            }

            .diff-negativa {
                color: #dc2626;
                font-weight: 600;
            }

            .recalculo-buttons {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            .btn-action-primary,
            .btn-action-secondary {
                padding: 10px 16px;
                border: none;
                border-radius: 8px;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                transition: transform 0.15s ease, background-color 0.15s ease;
            }

            .btn-action-primary {
                background: #0f766e;
                color: white;
            }

            .btn-action-primary:hover,
            .btn-action-secondary:hover {
                transform: translateY(-1px);
            }

            .btn-action-secondary {
                background: #6b7280;
                color: white;
            }

            .is-hidden {
                display: none !important;
            }

            @media (max-width: 720px) {
                .recalculo-grid {
                    grid-template-columns: 1fr;
                }

                .recalculo-select,
                .recalculo-field input {
                    max-width: 100%;
                }
            }
        </style>

        <script>
            (function () {
                const modo = document.getElementById('modo_recalculo');
                const costoDirecto = document.getElementById('costo_directo');
                const precioPorGramo = document.getElementById('precio_por_gramo');
                const gramos = document.getElementById('gramos');
                const markupPct = document.getElementById('markup_pct');
                const modoActivoTexto = document.getElementById('modoActivoTexto');
                const filas = Array.from(document.querySelectorAll('.recalculo-preview tbody tr'));

                const formatear = (valor) => Number.isFinite(valor) ? valor.toFixed(2) : '0.00';

                function obtenerBase() {
                    const modoActual = modo.value;
                    const markup = Math.max(0, parseFloat(markupPct.value) || 0);

                    if (modoActual === 'gramo') {
                        const precio = parseFloat(precioPorGramo.value) || 0;
                        const peso = parseFloat(gramos.value) || 0;
                        return {
                            base: precio * peso,
                            markup,
                            texto: 'Precio por gramo'
                        };
                    }

                    const costo = parseFloat(costoDirecto.value) || 0;
                    return {
                        base: costo,
                        markup,
                        texto: 'Costo directo'
                    };
                }

                function alternarCampos() {
                    const usarGramo = modo.value === 'gramo';
                    document.querySelectorAll('.recalculo-field-directo').forEach((el) => {
                        el.classList.toggle('is-hidden', usarGramo);
                    });
                    document.querySelectorAll('.recalculo-field-gramo').forEach((el) => {
                        el.classList.toggle('is-hidden', !usarGramo);
                    });
                    modoActivoTexto.textContent = usarGramo ? 'Precio por gramo' : 'Costo directo';
                }

                function actualizarVistaPrevia() {
                    const datos = obtenerBase();
                    const precioNuevo = datos.base * (1 + datos.markup / 100);

                    filas.forEach((fila) => {
                        const actual = parseFloat(fila.dataset.actual) || 0;
                        const nuevo = precioNuevo;
                        const diferencia = nuevo - actual;
                        const nuevoNodo = fila.querySelector('.precio-nuevo');
                        const diffNodo = fila.querySelector('.diferencia-celda');

                        nuevoNodo.textContent = formatear(nuevo);
                        diffNodo.textContent = `${diferencia >= 0 ? '+' : ''}${formatear(diferencia)}`;
                        diffNodo.classList.toggle('diff-positiva', diferencia >= 0);
                        diffNodo.classList.toggle('diff-negativa', diferencia < 0);
                    });
                }

                modo.addEventListener('change', function () {
                    alternarCampos();
                    actualizarVistaPrevia();
                });

                [costoDirecto, precioPorGramo, gramos, markupPct].forEach((campo) => {
                    if (campo) {
                        campo.addEventListener('input', actualizarVistaPrevia);
                    }
                });

                alternarCampos();
                actualizarVistaPrevia();
            })();
        </script>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No hay piezas de stock asociadas a esta pieza. <a href="pieza.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>

<?php require_once(__DIR__ . "/../footer.php"); ?>