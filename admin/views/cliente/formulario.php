<?php
$esEdicion = isset($cliente) && !empty($cliente);
$titulo = $esEdicion ? 'Editar Cliente' : 'Nuevo Cliente';
$accionForm = $esEdicion
    ? 'cliente.php?accion=actualizar&id=' . urlencode((string) $cliente['id_cliente'])
    : 'cliente.php?accion=crear';

$nombre = $_POST['nombre'] ?? ($esEdicion ? ($cliente['nombre'] ?? '') : '');
$primer_apellido = $_POST['primer_apellido'] ?? ($esEdicion ? ($cliente['primer_apellido'] ?? '') : '');
$segundo_apellido = $_POST['segundo_apellido'] ?? ($esEdicion ? ($cliente['segundo_apellido'] ?? '') : '');
$correo = $_POST['correo'] ?? ($esEdicion ? ($cliente['correo'] ?? '') : '');
$telefono = $_POST['telefono'] ?? ($esEdicion ? ($cliente['telefono'] ?? '') : '');
$descuento_porcentaje = $_POST['descuento_porcentaje'] ?? ($esEdicion ? ($cliente['descuento_porcentaje'] ?? '') : '');
$rfc = $_POST['rfc'] ?? ($esEdicion ? ($cliente['rfc'] ?? '') : '');
$razon_social = $_POST['razon_social'] ?? ($esEdicion ? ($cliente['razon_social'] ?? '') : '');
$regimen_fiscal = $_POST['regimen_fiscal'] ?? ($esEdicion ? ($cliente['regimen_fiscal'] ?? '') : '');
$uso_cfdi = $_POST['uso_cfdi'] ?? ($esEdicion ? ($cliente['uso_cfdi'] ?? 'G03') : 'G03');
$codigo_postal_fiscal = $_POST['codigo_postal_fiscal'] ?? ($esEdicion ? ($cliente['codigo_postal_fiscal'] ?? '') : '');

$postDir = static function (string $key, $default = '') use ($esEdicion, $cliente) {
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if ($esEdicion && is_array($cliente) && array_key_exists($key, $cliente)) {
        return $cliente[$key];
    }
    return $default;
};

$dirCliente = [
    'id_pais_FK' => $postDir('id_pais_FK'),
    'id_estado_FK' => $postDir('id_estado_FK'),
    'id_municipio_FK' => $postDir('id_municipio_FK'),
    'id_localidad_FK' => $postDir('id_localidad_FK'),
    'id_codigo_postal_FK' => $postDir('id_codigo_postal_FK'),
    'codigo_postal' => $postDir('codigo_postal'),
    'id_colonia_FK' => $postDir('id_colonia_FK'),
    'nom_colonia' => $postDir('nom_colonia'),
    'id_calle_FK' => $postDir('id_calle_FK'),
    'nom_calle' => $postDir('nom_calle'),
    'num_exterior' => $postDir('num_exterior'),
    'num_interior' => $postDir('num_interior'),
    'nom_pais' => $postDir('nom_pais'),
    'nom_estado' => $postDir('nom_estado'),
    'nom_municipio' => $postDir('nom_municipio'),
    'nom_localidad' => $postDir('nom_localidad'),
];

$dirOptsCliente = [
    'prefix' => '',
    'root_id' => 'joyeria_dir_cliente',
    'feedback_id' => 'cliente_dir_feedback',
    'omit_fieldset' => true,
    'api_prefix' => './api/',
    'data_dir_req' => true,
    'num_exterior_id' => 'num_exterior',
    'num_interior_id' => 'num_interior',
];

$forzarDireccion = $esEdicion && !empty($cliente['id_direccion_FK']);
$incluir_direccion_val = $_POST['incluir_direccion'] ?? ($forzarDireccion ? '1' : '0');
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($cliente)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <fieldset class="form-fieldset">
                <legend><i class="bi bi-person"></i> Información personal</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre"><i class="bi bi-person-badge"></i> Nombre:</label>
                        <input type="text" class="form-input" name="nombre" id="nombre" maxlength="50" value="<?php echo htmlspecialchars($nombre); ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="primer_apellido"><i class="bi bi-person-badge"></i> Primer Apellido:</label>
                        <input type="text" class="form-input" name="primer_apellido" id="primer_apellido" maxlength="25" value="<?php echo htmlspecialchars($primer_apellido); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="segundo_apellido"><i class="bi bi-person-badge"></i> Segundo Apellido:</label>
                        <input type="text" class="form-input" name="segundo_apellido" id="segundo_apellido" maxlength="25" value="<?php echo htmlspecialchars($segundo_apellido); ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-fieldset">
                <legend><i class="bi bi-telephone"></i> Contacto</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="correo"><i class="bi bi-envelope"></i> Correo (opcional):</label>
                        <input type="email" class="form-input" name="correo" id="correo" maxlength="80" value="<?php echo htmlspecialchars($correo); ?>" inputmode="email" autocomplete="email">
                        <small class="form-hint">Sin correo el cliente no podra iniciar sesion en la tienda en linea.</small>
                    </div>
                    <div class="form-group">
                        <label for="telefono"><i class="bi bi-telephone"></i> Teléfono:</label>
                        <input type="text" class="form-input" name="telefono" id="telefono" maxlength="15" value="<?php echo htmlspecialchars($telefono); ?>" required>
                    </div>
                    <?php if ($esEdicion): ?>
                    <div class="form-group">
                        <label for="contrasena"><i class="bi bi-lock"></i> Contrasena (opcional):</label>
                        <input type="password" class="form-input" name="contrasena" id="contrasena" maxlength="255">
                        <small class="form-hint">Dejar vacio para no cambiar. Si agregas correo por primera vez, se puede generar una temporal y enviarla por email.</small>
                    </div>
                    <?php else: ?>
                    <p class="form-hint" style="margin:0 0 12px;"><i class="bi bi-lock"></i> La contrasena de acceso se genera automaticamente en el sistema.</p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <fieldset class="form-fieldset">
                <legend><i class="bi bi-receipt-cutoff"></i> Datos fiscales (CFDI)</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="rfc">RFC</label>
                        <input type="text" class="form-input" name="rfc" id="rfc" maxlength="13" value="<?php echo htmlspecialchars($rfc); ?>" placeholder="Opcional para factura nominativa">
                    </div>
                    <div class="form-group">
                        <label for="razon_social">Razon social</label>
                        <input type="text" class="form-input" name="razon_social" id="razon_social" maxlength="254" value="<?php echo htmlspecialchars($razon_social); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="regimen_fiscal">Regimen fiscal</label>
                        <input type="text" class="form-input" name="regimen_fiscal" id="regimen_fiscal" maxlength="3" value="<?php echo htmlspecialchars($regimen_fiscal); ?>" placeholder="Ej. 616, 601">
                    </div>
                    <div class="form-group">
                        <label for="uso_cfdi">Uso CFDI</label>
                        <input type="text" class="form-input" name="uso_cfdi" id="uso_cfdi" maxlength="5" value="<?php echo htmlspecialchars($uso_cfdi); ?>" placeholder="G03, S01...">
                    </div>
                    <div class="form-group">
                        <label for="codigo_postal_fiscal">CP fiscal (override)</label>
                        <input type="text" class="form-input" name="codigo_postal_fiscal" id="codigo_postal_fiscal" maxlength="5" value="<?php echo htmlspecialchars($codigo_postal_fiscal); ?>">
                        <small class="form-hint">Si vacio, usa el CP de la direccion del cliente.</small>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-fieldset">
                <legend><i class="bi bi-percent"></i> Configuración comercial</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="descuento_porcentaje"><i class="bi bi-tag"></i> Descuento (%):</label>
                        <input type="number" class="form-input" name="descuento_porcentaje" id="descuento_porcentaje" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string) $descuento_porcentaje); ?>" placeholder="Ej. 10">
                        <small class="form-text text-muted">Hasta 2 decimales (ej. 50 o 25.5).</small>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-fieldset" style="border: 2px solid #c9a962; padding: 1rem 1.25rem;">
                <legend><i class="bi bi-geo-alt"></i> Dirección (opcional)</legend>
                <?php if ($forzarDireccion): ?>
                    <input type="hidden" name="incluir_direccion" value="1">
                    <p class="form-hint" style="font-weight:600;"><i class="bi bi-info-circle"></i> Este cliente ya tiene dirección; completa todos los campos de domicilio.</p>
                <?php else: ?>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="incluir_direccion_select" style="font-weight:600; display:block; margin-bottom:8px;">
                            <i class="bi bi-question-circle"></i> Desea agregar dirección de contacto?
                        </label>
                        <select class="form-input" id="incluir_direccion_select" name="incluir_direccion" style="max-width:320px;" onchange="sincronizarRequeridosDireccionCliente()">
                            <option value="0" <?php echo ((string) $incluir_direccion_val !== '1') ? 'selected' : ''; ?>>No</option>
                            <option value="1" <?php echo ((string) $incluir_direccion_val === '1') ? 'selected' : ''; ?>>Sí, registrar dirección</option>
                        </select>
                        <small class="form-hint">Si elige No, solo se guardan los datos personales y de contacto.</small>
                    </div>
                <?php endif; ?>

                <div id="bloque_direccion_cliente" class="direccion-bloque">
                    <?php
                    $dir = $dirCliente;
                    $dirOpts = $dirOptsCliente;
                    require __DIR__ . '/../partials/direccion_form.php';
                    ?>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="cliente.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>

        <?php
        $puedeVerCreditos = function_exists('auth_has_permission') && (
            auth_has_permission('CLIENTE_CREDITO_LEER')
            || auth_has_permission('CLIENTE_CREDITO_APLICAR')
            || auth_has_permission('CLIENTE_CREDITO_AJUSTAR')
        );
        ?>
        <?php if ($esEdicion && !empty($cliente) && $puedeVerCreditos): ?>
            <fieldset class="form-fieldset" style="margin-top:1.5rem;">
                <legend><i class="bi bi-wallet2"></i> Crédito a favor (monedero)</legend>
                <div id="cli_cred_box" data-id-cliente="<?php echo (int) ($cliente['id_cliente'] ?? 0); ?>">
                    <p style="margin:0;">Saldo disponible: <strong>$<span id="cli_cred_saldo">0.00</span></strong></p>
                    <div id="cli_cred_loading" class="text-muted" style="font-size:0.9rem; margin-top:0.5rem;">Cargando movimientos...</div>

                    <h4 style="margin-top:1rem;">Origenes del credito</h4>
                    <div class="admin-table-wrapper">
                        <table class="admin-table" id="cli_cred_origenes_tbl">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th class="text-right">Monto</th>
                                    <th class="text-right">Disponible</th>
                                    <th>Apartado origen</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="cli_cred_origenes_body">
                                <tr><td colspan="7" class="text-muted">Sin movimientos.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h4 style="margin-top:1rem;">Consumos del credito</h4>
                    <div class="admin-table-wrapper">
                        <table class="admin-table" id="cli_cred_consumos_tbl">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tipo uso</th>
                                    <th class="text-right">Monto</th>
                                    <th>Apartado</th>
                                    <th>Venta</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="cli_cred_consumos_body">
                                <tr><td colspan="6" class="text-muted">Sin consumos.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </fieldset>
            <script>
            (function () {
                var box = document.getElementById('cli_cred_box');
                if (!box) return;
                var idCli = parseInt(box.getAttribute('data-id-cliente') || '0', 10);
                if (!idCli) return;
                var lblSaldo = document.getElementById('cli_cred_saldo');
                var loadingEl = document.getElementById('cli_cred_loading');
                var origenesBody = document.getElementById('cli_cred_origenes_body');
                var consumosBody = document.getElementById('cli_cred_consumos_body');

                function escapeHtml(s) {
                    if (s === null || s === undefined) return '';
                    var t = document.createElement('div');
                    t.textContent = String(s);
                    return t.innerHTML;
                }
                function fmtMoney(v) {
                    var n = parseFloat(v);
                    if (!isFinite(n)) return v;
                    return n.toFixed(2);
                }
                function badgeEstado(estado) {
                    var color = '#6c757d';
                    if (estado === 'disponible') color = '#198754';
                    if (estado === 'consumido') color = '#0d6efd';
                    if (estado === 'anulado') color = '#dc3545';
                    return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;color:#fff;background:' + color + ';font-size:0.8rem;">' + escapeHtml(estado) + '</span>';
                }

                fetch('api/clientes_creditos.php?id_cliente=' + encodeURIComponent(idCli) + '&estado=todos&incluir_consumos=1', {
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.success || !res.data) throw new Error((res && res.error) || 'No se pudo cargar el monedero.');
                        var d = res.data;
                        if (lblSaldo) lblSaldo.textContent = fmtMoney(d.total_disponible || 0);
                        var creditos = Array.isArray(d.creditos) ? d.creditos : [];
                        if (creditos.length === 0) {
                            origenesBody.innerHTML = '<tr><td colspan="7" class="text-muted">Sin movimientos.</td></tr>';
                        } else {
                            var rows = creditos.map(function (c) {
                                return '<tr>'
                                    + '<td>#' + parseInt(c.id_credito, 10) + '</td>'
                                    + '<td>' + escapeHtml(c.tipo || '') + '</td>'
                                    + '<td>' + badgeEstado(c.estado || '') + '</td>'
                                    + '<td class="text-right">$' + fmtMoney(c.monto || 0) + '</td>'
                                    + '<td class="text-right">$' + fmtMoney(c.monto_disponible || 0) + '</td>'
                                    + '<td>' + (c.id_apartado_origen_FK ? '#' + parseInt(c.id_apartado_origen_FK, 10) : '<span class="text-muted">—</span>') + '</td>'
                                    + '<td>' + escapeHtml(c.fecha_registro || '') + '</td>'
                                    + '</tr>';
                            });
                            origenesBody.innerHTML = rows.join('');
                        }
                        var consumos = Array.isArray(d.consumos) ? d.consumos : [];
                        if (consumos.length === 0) {
                            consumosBody.innerHTML = '<tr><td colspan="6" class="text-muted">Sin consumos.</td></tr>';
                        } else {
                            var rows2 = consumos.map(function (c) {
                                return '<tr>'
                                    + '<td>#' + parseInt(c.id_consumo, 10) + '</td>'
                                    + '<td>' + escapeHtml(c.tipo_uso || '') + '</td>'
                                    + '<td class="text-right">$' + fmtMoney(c.monto || 0) + '</td>'
                                    + '<td>' + (c.id_apartado_FK ? '#' + parseInt(c.id_apartado_FK, 10) : '<span class="text-muted">—</span>') + '</td>'
                                    + '<td>' + (c.id_venta_FK ? '#' + parseInt(c.id_venta_FK, 10) : '<span class="text-muted">—</span>') + '</td>'
                                    + '<td>' + escapeHtml(c.fecha_registro || '') + '</td>'
                                    + '</tr>';
                            });
                            consumosBody.innerHTML = rows2.join('');
                        }
                        if (loadingEl) loadingEl.style.display = 'none';
                    })
                    .catch(function (err) {
                        if (loadingEl) {
                            loadingEl.textContent = err.message || 'No se pudo cargar el monedero.';
                        }
                    });
            })();
            </script>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el cliente. <a href="cliente.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>

<script>
function clienteDireccionObligatoria() {
    var hidden = document.querySelector('input[type="hidden"][name="incluir_direccion"]');
    if (hidden && hidden.value === '1') {
        return true;
    }
    var sel = document.getElementById('incluir_direccion_select');
    if (sel) {
        return String(sel.value) === '1';
    }
    return true;
}

function sincronizarRequeridosDireccionCliente() {
    var on = clienteDireccionObligatoria();
    var bloque = document.getElementById('bloque_direccion_cliente');
    if (bloque) {
        bloque.style.opacity = on ? '1' : '0.55';
        bloque.style.pointerEvents = on ? '' : 'none';
    }
    document.querySelectorAll('#bloque_direccion_cliente [data-dir-req]').forEach(function(el) {
        if (on) {
            el.removeAttribute('disabled');
            el.setAttribute('required', 'required');
        } else {
            el.removeAttribute('required');
            el.setAttribute('disabled', 'disabled');
        }
    });
}

var selCliDir = document.getElementById('incluir_direccion_select');
if (selCliDir) {
    selCliDir.addEventListener('change', sincronizarRequeridosDireccionCliente);
}
sincronizarRequeridosDireccionCliente();
</script>
