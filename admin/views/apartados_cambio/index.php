<?php

/** @var array $listaApartadosActivos */
/** @var array $catalogoClientes */
/** @var int $idApartadoUrl */
$listaApartadosActivos = isset($listaApartadosActivos) && is_array($listaApartadosActivos) ? $listaApartadosActivos : [];
$idApartadoUrl = isset($idApartadoUrl) ? (int) $idApartadoUrl : 0;
$puedeAbonarEnConsulta = auth_has_permission('APARTADO_GESTION_LEER') && auth_has_permission('APARTADO_GESTION_ACTUALIZAR') && $idEmpleadoSesion !== null;

?>

<div class="admin-modules">

    <div class="form-section">

        <h3><i class="bi bi-arrow-left-right"></i> Cambiar pieza del apartado</h3>

        <p class="text-muted">

            <strong>Una pieza en el apartado:</strong> el dinero abonado en tienda se traslada como <strong>crédito por cambio</strong> a un apartado nuevo (contrato nuevo).

            <strong>Varias piezas:</strong> eliges la línea a sustituir; se reemplaza en el <strong>mismo apartado</strong> y se recalculan total y saldo (sin apartado destino).

            La pieza nueva debe estar <strong>disponible</strong> y en la <strong>misma tienda</strong>.

        </p>



        <?php if (!auth_has_permission('APARTADO_CAMBIO_LEER')): ?>

            <div class="alert-message error"><p>No tienes permiso para usar esta pantalla.</p></div>

        <?php elseif ($idEmpleadoSesion === null): ?>

            <div class="alert-message error">

                <p>Tu usuario no tiene un empleado activo vinculado; no puedes registrar el cambio desde aquí.</p>

            </div>

        <?php else: ?>

            <form id="form_apartado_cambio" class="form-card form-card--md">

                <input type="hidden" name="id_empleado_FK" value="<?php echo (int) $idEmpleadoSesion; ?>">

                <div class="form-row">

                    <div class="form-group">

                        <label for="ac_id_apartado">ID apartado (opcional si usas código)</label>

                        <input type="number" min="1" id="ac_id_apartado" name="id_apartado" class="form-input" placeholder="Ej. 42">

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label for="ac_codigo_actual">Código pieza actual (apartada)</label>

                        <div style="display:flex; gap:8px; align-items:stretch; flex-wrap:wrap;">
                            <input type="text" id="ac_codigo_actual" name="codigo_apartado" class="form-input joyeria-barcode-input" style="flex:1 1 200px; min-width:0;" autocomplete="off" placeholder="Barras o auxiliar (ej. 28488/97)">
                            <button type="button" class="btn-action-secondary" id="btn_ac_escanear_actual" title="Escanear con cámara"><i class="bi bi-camera"></i></button>
                        </div>

                    </div>

                </div>

                <div class="form-actions" style="margin-bottom: 1rem;">

                    <button type="button" class="btn-action-primary" id="btn_ac_preview">

                        <i class="bi bi-search"></i> Vista previa

                    </button>

                </div>

                <div id="ac_preview" class="alert-message info" style="display:none;"></div>



                <div class="form-row" id="ac_row_detalle" style="display:none;">

                    <div class="form-group">

                        <label for="ac_id_detalle">Línea a reemplazar</label>

                        <select id="ac_id_detalle" name="id_apartado_detalle" class="form-input">

                            <option value="">— Selecciona linea —</option>

                        </select>

                    </div>

                </div>



                <hr style="margin: 1.25rem 0;">



                <div class="form-row">

                    <div class="form-group">

                        <label for="ac_codigo_nueva">Código pieza nueva (disponible)</label>

                        <div style="display:flex; gap:8px; align-items:stretch; flex-wrap:wrap;">
                            <input type="text" id="ac_codigo_nueva" name="codigo_pieza_nueva" class="form-input joyeria-barcode-input" style="flex:1 1 200px; min-width:0;" autocomplete="off" placeholder="Barras o auxiliar (ej. 28488/97)">
                            <button type="button" class="btn-action-secondary" id="btn_ac_escanear_nueva" title="Escanear con cámara"><i class="bi bi-camera"></i></button>
                        </div>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label for="ac_obs">Observaciones (opcional)</label>

                        <textarea id="ac_obs" name="observaciones" class="form-input" rows="2" placeholder="Notas internas"></textarea>

                    </div>

                </div>

                <?php if (auth_has_permission('APARTADO_CAMBIO_CREAR')): ?>

                    <div class="form-actions">

                        <button type="submit" class="btn-action-primary" id="btn_ac_enviar">

                            <i class="bi bi-check2-circle"></i> Confirmar cambio

                        </button>

                    </div>

                <?php else: ?>

                    <div class="alert-message error"><p>No tienes permiso para ejecutar el cambio (solo lectura).</p></div>

                <?php endif; ?>

            </form>

            <div id="ac_resultado" class="alert-message info" style="display:none; margin-top:1rem;"></div>

        <?php endif; ?>

    </div>

    <?php if (auth_has_permission('APARTADO_CAMBIO_LEER')): ?>
        <?php
        $aa_context = 'cambio';
        $aa_rows = $listaApartadosActivos;
        $aa_catalogoClientes = $catalogoClientes;
        $aa_heading = 'Apartados activos';
        $aa_intro = 'Filtra por cliente: <strong>Abonar</strong> abre la consulta con ese apartado; <strong>Usar en cambio</strong> rellena el formulario de arriba.';
        $aa_link_abonar = $puedeAbonarEnConsulta;
        $aa_usar_cambio = $idEmpleadoSesion !== null;
        require __DIR__ . '/../partials/apartados_activos_tabla.php';
        ?>
    <?php endif; ?>



    <div class="form-section" style="margin-top:2rem;">

        <h3><i class="bi bi-list-ul"></i> Ultimos cambios registrados</h3>

        <div class="admin-table-wrapper">

            <table class="admin-table">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Fecha</th>

                        <th>Tipo</th>

                        <th>Apart. origen</th>

                        <th>Apart. destino</th>

                        <th>Pieza ant.</th>

                        <th>Pieza nueva</th>

                        <th class="text-right">Credito</th>

                        <th>Empleado</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($lista)): ?>

                        <tr><td colspan="9">Sin registros.</td></tr>

                    <?php else: ?>

                        <?php foreach ($lista as $r): ?>

                            <?php

                            $tipoOp = (string) ($r['tipo_operacion'] ?? 'nuevo_apartado');

                            $tipoLabel = $tipoOp === 'reemplazo_mismo' ? 'Mismo apartado' : 'Nuevo apartado';

                            ?>

                            <tr>

                                <td><?php echo (int) ($r['id_apartado_cambio'] ?? 0); ?></td>

                                <td><?php echo htmlspecialchars((string) ($r['fecha_registro'] ?? '')); ?></td>

                                <td><?php echo htmlspecialchars($tipoLabel); ?></td>

                                <td><?php echo (int) ($r['id_apartado_origen_FK'] ?? 0); ?></td>

                                <td><?php echo (int) ($r['id_apartado_destino_FK'] ?? 0); ?></td>

                                <td><?php echo (int) ($r['id_pieza_stock_origen_FK'] ?? 0); ?></td>

                                <td><?php echo (int) ($r['id_pieza_stock_destino_FK'] ?? 0); ?></td>

                                <td class="text-right">$<?php echo htmlspecialchars(number_format((float) ($r['monto_credito_aplicado'] ?? 0), 2, '.', '')); ?></td>

                                <td><?php echo htmlspecialchars((string) ($r['empleado_nombre'] ?? '')); ?></td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>



<?php if (auth_has_permission('APARTADO_CAMBIO_LEER')): ?>

<script>

window.JOYERIA_APARTADOS_ACTIVOS = <?php echo json_encode([
    'context' => 'cambio',
    'puedeAbonar' => false,
    'puedeCambioLink' => false,
    'linkAbonarConsulta' => !empty($puedeAbonarEnConsulta),
    'usarCambio' => $idEmpleadoSesion !== null,
    'idApartadoUrl' => (int) $idApartadoUrl,
], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="js/fk-autocomplete.js"></script>

<script src="js/apartados-activos-tabla.js"></script>

<?php endif; ?>


<?php if (auth_has_permission('APARTADO_CAMBIO_LEER') && $idEmpleadoSesion !== null): ?>

<script>

(function () {

    var idApartadoActual = 0;

    var lineasCount = 0;

    var preview = document.getElementById('ac_preview');

    var resultado = document.getElementById('ac_resultado');

    var btnPrev = document.getElementById('btn_ac_preview');

    var form = document.getElementById('form_apartado_cambio');

    var rowDet = document.getElementById('ac_row_detalle');

    var selDet = document.getElementById('ac_id_detalle');



    function fmtMoney(v) {

        var n = parseFloat(v);

        if (isNaN(n)) return v;

        return n.toFixed(2);

    }



    function renderPreview(data) {

        var a = data.apartado || {};

        var dets = data.detalles || [];

        idApartadoActual = parseInt(a.id_apartado, 10) || 0;

        lineasCount = parseInt(data.lineas_count, 10) || dets.length || 0;

        var html = '<p><strong>Apartado #' + idApartadoActual + '</strong> — Cliente: ' + (a.cliente_nombre || '') + '</p>';

        html += '<p>Estado: <strong>' + (a.estado || '') + '</strong> | Total: $' + fmtMoney(a.total_apartado) + ' | Saldo pendiente: $' + fmtMoney(a.saldo_pendiente) + '</p>';

        html += '<p>Abonos en tienda (cobro_tienda): <strong>$' + fmtMoney(data.abonos_cobro_tienda) + '</strong></p>';

        if (dets.length) {

            html += '<p><strong>Lineas (' + dets.length + ')</strong></p><ul style="margin:0 0 0 1.1rem;">';

            for (var i = 0; i < dets.length; i++) {

                var det = dets[i];

                html += '<li>#' + det.id_apartado_detalle + ' — Stock ' + det.id_pieza_stock_FK + ' — ' + (det.desc_pieza || '') + ' — Barras: ' + (det.codigo_barras || '') + '</li>';

            }

            html += '</ul>';

        }

        if (lineasCount > 1) {

            html += '<p class="text-muted">Varias piezas: elige la línea a reemplazar y confirma con la pieza nueva (mismo apartado).</p>';

        } else {

            html += '<p class="text-muted">Una sola pieza: al confirmar se crea un apartado nuevo con el credito de tienda.</p>';

        }

        preview.innerHTML = html;

        preview.style.display = 'block';



        if (selDet) {

            selDet.innerHTML = '<option value="">— Selecciona linea —</option>';

            for (var j = 0; j < dets.length; j++) {

                var d = dets[j];

                var opt = document.createElement('option');

                opt.value = String(d.id_apartado_detalle);

                opt.textContent = '#' + d.id_apartado_detalle + ' — ' + (d.desc_pieza || '') + ' (stock ' + d.id_pieza_stock_FK + ')';

                selDet.appendChild(opt);

            }

        }

        if (rowDet) {

            rowDet.style.display = lineasCount > 1 ? 'block' : 'none';

        }

        if (lineasCount === 1 && selDet && dets[0]) {

            selDet.value = String(dets[0].id_apartado_detalle);

        }

    }



    if (btnPrev) {

        btnPrev.addEventListener('click', function () {

            var id = parseInt(document.getElementById('ac_id_apartado').value || '0', 10);

            var cod = (document.getElementById('ac_codigo_actual').value || '').trim();

            preview.style.display = 'none';

            resultado.style.display = 'none';

            var url = 'api/apartados_cambio.php?';

            if (id > 0) {

                url += 'id_apartado=' + encodeURIComponent(id);

            } else if (cod) {

                url += 'codigo_apartado=' + encodeURIComponent(cod);

            } else {

                alert('Indica ID de apartado o código de la pieza apartada.');

                return;

            }

            btnPrev.disabled = true;

            fetch(url, { credentials: 'same-origin' })

                .then(function (r) { return r.json(); })

                .then(function (res) {

                    if (!res || !res.success) throw new Error((res && res.error) || 'Error');

                    renderPreview(res.data);

                })

                .catch(function (e) {

                    alert(e.message || 'Error');

                })

                .finally(function () { btnPrev.disabled = false; });

        });

    }



    if (form) {

        form.addEventListener('submit', function (e) {

            e.preventDefault();

            resultado.style.display = 'none';

            if (idApartadoActual <= 0) {

                alert('Primero obtiene la vista previa del apartado.');

                return;

            }

            var codN = (document.getElementById('ac_codigo_nueva').value || '').trim();

            if (!codN) {

                alert('Indica el código de la pieza nueva (disponible).');

                return;

            }

            var idDet = 0;

            if (lineasCount > 1) {

                idDet = parseInt((selDet && selDet.value) || '0', 10);

                if (idDet <= 0) {

                    alert('Selecciona la línea (detalle) a reemplazar.');

                    return;

                }

            } else if (selDet && selDet.value) {

                idDet = parseInt(selDet.value, 10);

            }

            var btn = document.getElementById('btn_ac_enviar');

            if (btn) btn.disabled = true;

            var fd = new FormData(form);

            var payload = {

                id_apartado_origen: idApartadoActual,

                codigo_pieza_nueva: codN,

                id_empleado_FK: parseInt(fd.get('id_empleado_FK') || '0', 10),

                observaciones: (fd.get('observaciones') || '').toString()

            };

            if (idDet > 0) {

                payload.id_apartado_detalle = idDet;

            }

            fetch('api/apartados_cambio.php', {

                method: 'POST',

                credentials: 'same-origin',

                headers: { 'Content-Type': 'application/json' },

                body: JSON.stringify(payload)

            })

                .then(function (r) { return r.json(); })

                .then(function (res) {

                    if (!res || !res.success) throw new Error((res && res.error) || 'Error');

                    resultado.className = 'alert-message info';

                    var d = res.data || {};

                    var mismo = d.total_apartado !== undefined && parseInt(d.id_apartado, 10) === parseInt(d.id_apartado_destino, 10);

                    var extra = '';

                    if (mismo) {

                        extra = '<p>Mismo apartado <strong>#' + d.id_apartado + '</strong> — Total: $' + fmtMoney(d.total_apartado) + ' | Saldo: $' + fmtMoney(d.saldo_pendiente_destino) + '</p>';

                    } else {

                        extra = '<p>Nuevo apartado: <strong>#' + d.id_apartado_destino + '</strong> | Crédito aplicado: $' +

                            fmtMoney(d.monto_credito_aplicado) + ' | Saldo pendiente: $' + fmtMoney(d.saldo_pendiente_destino) + '</p>';

                    }

                    resultado.innerHTML = '<p>' + (res.message || 'OK') + '</p>' + extra;

                    resultado.style.display = 'block';

                    if (typeof window.joyeriaApartadosActivosRecargarTabla === 'function') {

                        window.joyeriaApartadosActivosRecargarTabla();

                    }

                    idApartadoActual = 0;

                    lineasCount = 0;

                    preview.style.display = 'none';

                    if (rowDet) rowDet.style.display = 'none';

                })

                .catch(function (err) {

                    alert(err.message || 'Error');

                })

                .finally(function () {

                    if (btn) btn.disabled = false;

                });

                });

    }

})();

</script>
<script src="js/barcode-camera.js"></script>
<script src="js/joyeria-barcode-field.js"></script>
<script>
(function () {
    if (window.JoyeriaBarcodeField) {
        JoyeriaBarcodeField.bind('ac_codigo_actual', 'btn_ac_escanear_actual');
        JoyeriaBarcodeField.bind('ac_codigo_nueva', 'btn_ac_escanear_nueva');
    }
})();
</script>

<?php endif; ?>

