<?php
/** @var array $catalogoClientes */
/** @var ?int $idEmpleadoSesion */
/** @var bool $puedeCrear */
/** @var bool $puedeLeer */
?>

<div class="form-section">
    <?php if ($idEmpleadoSesion === null): ?>
        <div class="alert-message error"><p>Tu usuario no está vinculado a un empleado activo.</p></div>
    <?php endif; ?>

    <?php if (!$puedeCrear && !$puedeLeer): ?>
        <div class="alert-message error"><p>No tienes permisos sobre devoluciones.</p></div>
    <?php else: ?>

        <p class="text-muted" style="max-width:720px; line-height:1.45;">
            Registra la devolución de una pieza vendida y acredita su valor al <strong>monedero</strong> del cliente.
            El saldo se puede usar despues en <strong>Punto de venta</strong> o en <strong>Apartados activos</strong>.
            Tambien puedes usar canje inmediato en POS si el cliente compra el mismo dia.
        </p>

        <?php if ($puedeCrear && $idEmpleadoSesion !== null): ?>
            <form id="form_dev_credito" class="form-card form-card--md">
                <div class="form-row">
                    <div class="form-group">
                        <label for="dc_cliente">Cliente *</label>
                        <select class="form-input" id="dc_cliente" name="id_cliente_FK" required>
                            <?php
                            $clientes = $catalogoClientes;
                            $selectedId = '';
                            $emptyLabel = '— Selecciona —';
                            $emptyValue = '';
                            require __DIR__ . '/../partials/cliente_select_options.php';
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dc_venta">Venta # (opcional)</label>
                        <input type="number" min="1" class="form-input" id="dc_venta" placeholder="Se infiere por la pieza si se omite">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dc_codigo">Código pieza *</label>
                        <input type="text" class="form-input joyeria-barcode-input" id="dc_codigo" autocomplete="off" required placeholder="Barras o auxiliar">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dc_motivo">Motivo</label>
                        <input type="text" class="form-input" id="dc_motivo" maxlength="500">
                    </div>
                </div>
                <div id="dc_preview" class="alert-message info" style="display:none; margin-top:0.75rem;"></div>
                <div class="form-actions" style="margin-top:1rem;">
                    <button type="button" class="btn-action-secondary" id="btn_dc_preview"><i class="bi bi-eye"></i> Vista previa</button>
                    <button type="submit" class="btn-action-primary" id="btn_dc_enviar"><i class="bi bi-check2-circle"></i> Registrar y acreditar</button>
                </div>
            </form>
            <div id="dc_mensaje" style="margin-top:0.75rem;"></div>
        <?php else: ?>
            <p class="text-muted">Necesitas permisos <code>DEVOLUCION_CREAR</code> y <code>DEVOLUCION_CREDITO_MONEDERO</code>.</p>
        <?php endif; ?>

        <?php if ($puedeLeer): ?>
            <h3 style="margin-top:2rem;"><i class="bi bi-clock-history"></i> Ultimas devoluciones</h3>
            <div id="dc_recientes" class="admin-table-wrapper"><p class="text-muted">Cargando...</p></div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php if ($puedeCrear && $idEmpleadoSesion !== null): ?>
<script src="js/fk-autocomplete.js"></script>
<script>
(function () {
    function el(id) { return document.getElementById(id); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;');
    }
    if (window.JoyeriaFkAutocomplete) {
        JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'dc_cliente', allowEmpty: false, placeholder: 'Nombre, apellido, correo o teléfono...' });
    }
    function apiPost(body) {
        return fetch('api/devoluciones.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().then(function (res) {
                if (!res || !res.success) throw new Error((res && res.error) || 'Error');
                return res;
            });
        });
    }
    function payloadBase() {
        return {
            id_cliente_FK: parseInt(el('dc_cliente').value || '0', 10),
            id_venta_FK: parseInt(el('dc_venta').value || '0', 10) || 0,
            codigo: (el('dc_codigo').value || '').trim(),
            motivo: (el('dc_motivo').value || '').trim()
        };
    }
    el('btn_dc_preview').addEventListener('click', function () {
        var p = payloadBase();
        if (p.id_cliente_FK <= 0 || !p.codigo) {
            alert('Cliente y código son obligatorios.');
            return;
        }
        apiPost(Object.assign({ tipo: 'preview_monedero' }, p)).then(function (res) {
            var d = res.data || {};
            var box = el('dc_preview');
            box.style.display = '';
            box.innerHTML = '<p style="margin:0;">' + esc(d.descripcion) + ' · Venta #' + esc(d.id_venta_origen)
                + ' · Credito <strong>$' + parseFloat(d.monto_credito || 0).toFixed(2) + '</strong>'
                + ' · Monedero: $' + parseFloat(d.monedero_saldo_actual || 0).toFixed(2)
                + ' → <strong>$' + parseFloat(d.monedero_saldo_tras || 0).toFixed(2) + '</strong></p>';
        }).catch(function (err) { alert(err.message || 'Error'); });
    });
    el('form_dev_credito').addEventListener('submit', function (e) {
        e.preventDefault();
        var p = payloadBase();
        if (p.id_cliente_FK <= 0 || !p.codigo) {
            alert('Cliente y código son obligatorios.');
            return;
        }
        if (!window.confirm('Registrar devolución y acreditar el monedero?')) return;
        var btn = el('btn_dc_enviar');
        btn.disabled = true;
        apiPost(Object.assign({ tipo: 'monedero' }, p)).then(function (res) {
            el('dc_mensaje').innerHTML = '<div class="alert-message success"><p>' + esc(res.message || 'Listo') + '</p></div>';
            el('dc_codigo').value = '';
            el('dc_preview').style.display = 'none';
            cargarRecientes();
        }).catch(function (err) {
            el('dc_mensaje').innerHTML = '<div class="alert-message error"><p>' + esc(err.message) + '</p></div>';
        }).finally(function () { btn.disabled = false; });
    });
    function cargarRecientes() {
        var box = el('dc_recientes');
        if (!box) return;
        fetch('api/devoluciones.php?limit=25', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    box.innerHTML = '<p class="text-muted">Sin datos.</p>';
                    return;
                }
                var rows = res.data.map(function (d) {
                    return '<tr><td>' + esc(d.fecha_devolucion) + '</td><td>' + esc(d.tipo_origen) + '</td>'
                        + '<td>' + (d.id_venta_FK || '—') + '</td><td>' + esc(d.pieza_codigo_auxiliar || '') + '</td>'
                        + '<td class="text-right">$' + parseFloat(d.monto_reembolso || 0).toFixed(2) + '</td>'
                        + '<td>' + (d.credito_id ? ('#' + d.credito_id) : '—') + '</td></tr>';
                }).join('');
                box.innerHTML = '<table class="admin-table"><thead><tr><th>Fecha</th><th>Origen</th><th>Venta</th><th>Código</th><th>Monto</th><th>Crédito</th></tr></thead><tbody>'
                    + (rows || '<tr><td colspan="6">Sin registros</td></tr>') + '</tbody></table>';
            });
    }
    cargarRecientes();
})();
</script>
<?php endif; ?>
