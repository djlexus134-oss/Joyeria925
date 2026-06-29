<?php
/** @var array $lista */
/** @var array $catalogoClientes */
/** @var array $formasPago */
/** @var int|null $idEmpleadoSesion */
/** @var int $idApartadoUrl */
$idApartadoUrl = isset($idApartadoUrl) ? (int) $idApartadoUrl : 0;
$puedeAccionAbonar = auth_has_permission('APARTADO_GESTION_ACTUALIZAR') && $idEmpleadoSesion !== null;
$puedeAccionCambio = auth_has_permission('APARTADO_GESTION_QUITAR_PIEZA')
    || auth_has_permission('APARTADO_GESTION_AGREGAR_PIEZA');
?>
<div class="admin-modules">
    <div class="form-section">
        <h3><i class="bi bi-cash-coin"></i> Abono a apartado activo</h3>
        <p class="text-muted">Registra pagos sobre apartados en estado <strong>activo</strong>. Para nuevos apartados usa <a href="apartados_alta.php?accion=leer">Apartados alta</a>.</p>
        <?php if (!auth_has_permission('APARTADO_GESTION_LEER')): ?>
            <div class="alert-message error"><p>Sin permiso de lectura del módulo.</p></div>
        <?php elseif (!auth_has_permission('APARTADO_GESTION_ACTUALIZAR')): ?>
            <p class="text-muted">Sin permiso para registrar abonos.</p>
        <?php elseif ($idEmpleadoSesion === null): ?>
            <p class="text-muted">Empleado no vinculado.</p>
        <?php else: ?>
            <form id="form_apartado_abono" class="form-card form-card--sm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="ac_ab_id">ID apartado</label>
                        <input type="number" min="1" id="ac_ab_id" name="id_apartado_FK" class="form-input" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="ac_ab_monto">Monto</label>
                        <input type="number" step="0.01" min="0.01" id="ac_ab_monto" name="monto" class="form-input" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="ac_ab_fp">Forma de pago</label>
                        <select id="ac_ab_fp" name="id_forma_pago_FK" class="form-input" required>
                        <?php foreach ($formasPago as $fp): ?>
                            <option value="<?php echo (int) $fp['id_forma_pago']; ?>"<?php echo (!empty($idFormaPagoDefault) && (int) $fp['id_forma_pago'] === (int) $idFormaPagoDefault) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $fp['forma_pago']); ?></option>
                        <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-action-primary" id="btn_ac_abono"><i class="bi bi-check2-circle"></i> Registrar abono</button>
                </div>
            </form>
            <div id="ac_result_abono" class="alert-message info" style="display:none; margin-top:1rem;"></div>
        <?php endif; ?>
    </div>

    <?php if (auth_has_permission('APARTADO_GESTION_LEER')): ?>
        <?php
        $aa_context = 'consulta';
        $aa_rows = $lista;
        $aa_catalogoClientes = $catalogoClientes;
        $aa_heading = 'Apartados activos';
        $aa_intro = 'Solo se listan apartados pendientes de liquidar. Las liquidaciones no aparecen aquí. Desde aquí puedes <strong>abonar</strong> o ir a <strong>quitar / agregar pieza</strong>.';
        $aa_puede_abonar = $puedeAccionAbonar;
        $aa_puede_cambio_link = $puedeAccionCambio;
        $aa_puede_ver_abonos = true;
        require __DIR__ . '/../partials/apartados_activos_tabla.php';
        ?>
    <?php endif; ?>
</div>

<?php if (auth_has_permission('APARTADO_GESTION_LEER')): ?>
<script>
window.JOYERIA_APARTADOS_ACTIVOS = <?php echo json_encode([
    'context' => 'consulta',
    'puedeAbonar' => !empty($puedeAccionAbonar),
    'puedeCambioLink' => !empty($puedeAccionCambio),
    'puedeVerAbonos' => true,
    'linkAbonarConsulta' => false,
    'usarCambio' => false,
    'idApartadoUrl' => (int) $idApartadoUrl,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="js/fk-autocomplete.js"></script>
<script src="js/apartados-activos-tabla.js"></script>
<?php endif; ?>

<?php if (auth_has_permission('APARTADO_GESTION_LEER') && auth_has_permission('APARTADO_GESTION_ACTUALIZAR') && $idEmpleadoSesion !== null): ?>
<script>
(function () {
    var formAbono = document.getElementById('form_apartado_abono');
    if (formAbono) {
        formAbono.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(formAbono);
            var btn = document.getElementById('btn_ac_abono');
            if (btn) btn.disabled = true;
            fetch('api/apartados_gestion.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tipo: 'abono',
                    id_apartado_FK: parseInt(fd.get('id_apartado_FK') || '0', 10),
                    monto: fd.get('monto'),
                    id_forma_pago_FK: parseInt(fd.get('id_forma_pago_FK') || '0', 10)
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.success) throw new Error((res && res.error) || 'Error');
                    var d = res.data || {};
                    var extraTicket = '';
                    if (d.impresion_encolada) {
                        extraTicket = '<p style="margin-top:0.75rem;"><i class="bi bi-printer"></i> <strong>Ticket encolado</strong> para impresión en caja';
                        if (d.id_cola_impresion) {
                            extraTicket += ' <span class="text-muted">(cola # ' + parseInt(d.id_cola_impresion, 10) + ')</span>';
                        }
                        extraTicket += '.</p>';
                    }
                    var o = document.getElementById('ac_result_abono');
                    o.className = 'alert-message info';
                    o.innerHTML = '<p>' + (res.message || '') + '</p><p>Nuevo saldo: <strong>$' + (d.saldo_pendiente || '') + '</strong></p>' + extraTicket;
                    o.style.display = 'block';
                    formAbono.reset();
                    if (typeof window.joyeriaApartadosActivosRecargarTabla === 'function') {
                        window.joyeriaApartadosActivosRecargarTabla();
                    }
                })
                .catch(function (err) { alert(err.message || 'Error'); })
                .finally(function () { if (btn) btn.disabled = false; });
        });
    }
})();
</script>
<?php endif; ?>
