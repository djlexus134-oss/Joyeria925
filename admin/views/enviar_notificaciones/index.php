<?php
/**
 * Pantalla para enviar notificaciones especiales a clientes, empleados o usuarios
 * por WhatsApp, correo y/o campana interna del panel.
 */
?>
<div class="form-section" id="envio-notif-wrap">
    <div class="alert-message info" id="envio-notif-alert" style="display:none;"></div>

    <div class="form-row">
        <div class="form-group">
            <label for="envio_grupo">Audiencia</label>
            <select class="form-input" id="envio_grupo">
                <option value="clientes">Clientes</option>
                <option value="empleados">Empleados</option>
                <option value="usuarios">Usuarios</option>
            </select>
            <small class="form-hint">Define a quien va dirigida la notificacion.</small>
        </div>
        <div class="form-group">
            <label>Destinatarios</label>
            <label style="display:flex;align-items:center;gap:.5rem;font-weight:400;">
                <input type="checkbox" id="envio_todos" checked>
                Enviar a todos del grupo
            </label>
            <small class="form-hint" id="envio_total_hint">Cargando destinatarios...</small>
        </div>
    </div>

    <div class="form-group" id="envio_lista_wrap" style="display:none;">
        <label for="envio_lista">Selecciona destinatarios</label>
        <select class="form-input" id="envio_lista" multiple size="8" style="min-height:160px;"></select>
        <small class="form-hint">Manten Ctrl (o Cmd) para seleccionar varios.</small>
    </div>

    <fieldset style="border:1px solid #e2e2e2;border-radius:8px;padding:1rem;margin:1rem 0;">
        <legend style="font-weight:600;padding:0 .5rem;">Canales de envio</legend>
        <div class="form-row">
            <label style="display:flex;align-items:center;gap:.5rem;font-weight:400;">
                <input type="checkbox" id="canal_whatsapp" checked> <i class="bi bi-whatsapp"></i> WhatsApp
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;font-weight:400;">
                <input type="checkbox" id="canal_correo"> <i class="bi bi-envelope"></i> Correo
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;font-weight:400;">
                <input type="checkbox" id="canal_interna"> <i class="bi bi-bell"></i> Campana interna
            </label>
        </div>
        <small class="form-hint" id="envio_interna_hint" style="display:none;">
            La campana interna no aplica para clientes (no tienen panel); ese canal se omitira.
        </small>
    </fieldset>

    <div class="form-group" id="envio_asunto_wrap">
        <label for="envio_asunto">Asunto (solo correo)</label>
        <input class="form-input" type="text" id="envio_asunto" maxlength="150" placeholder="Asunto del correo">
    </div>

    <div class="form-group">
        <label for="envio_mensaje">Mensaje</label>
        <textarea class="form-input" id="envio_mensaje" rows="5" maxlength="1000" placeholder="Escribe el mensaje a enviar..."></textarea>
        <small class="form-hint">Para WhatsApp se usa la plantilla de notificacion configurada (1 variable = este mensaje).</small>
    </div>

    <div class="form-actions">
        <button type="button" class="btn-action-primary" id="envio_enviar">
            <i class="bi bi-send"></i> Enviar notificacion
        </button>
    </div>

    <div id="envio_resultado" style="margin-top:1rem;"></div>
</div>

<script>
(function () {
    'use strict';

    var grupoSel = document.getElementById('envio_grupo');
    var todosChk = document.getElementById('envio_todos');
    var listaWrap = document.getElementById('envio_lista_wrap');
    var lista = document.getElementById('envio_lista');
    var totalHint = document.getElementById('envio_total_hint');
    var canalWa = document.getElementById('canal_whatsapp');
    var canalCorreo = document.getElementById('canal_correo');
    var canalInterna = document.getElementById('canal_interna');
    var internaHint = document.getElementById('envio_interna_hint');
    var asunto = document.getElementById('envio_asunto');
    var mensaje = document.getElementById('envio_mensaje');
    var btn = document.getElementById('envio_enviar');
    var alertBox = document.getElementById('envio-notif-alert');
    var resultado = document.getElementById('envio_resultado');

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function showAlert(msg, tipo) {
        alertBox.className = 'alert-message ' + (tipo || 'info');
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
    }
    function hideAlert() { alertBox.style.display = 'none'; }

    function actualizarHintInterna() {
        internaHint.style.display = (grupoSel.value === 'clientes') ? 'block' : 'none';
    }

    function cargarDestinatarios() {
        totalHint.textContent = 'Cargando destinatarios...';
        lista.innerHTML = '';
        fetch('api/enviar_notificaciones.php?grupo=' + encodeURIComponent(grupoSel.value), {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    totalHint.textContent = (data && data.error) || 'No se pudieron cargar los destinatarios.';
                    return;
                }
                var items = data.data || [];
                totalHint.textContent = items.length + ' destinatario(s) en el grupo.';
                var html = '';
                items.forEach(function (it) {
                    var extra = [];
                    if (it.telefono) extra.push('tel: ' + it.telefono);
                    if (it.correo) extra.push(it.correo);
                    var detalle = extra.length ? ' (' + extra.join(' · ') + ')' : '';
                    html += '<option value="' + parseInt(it.id_usuario, 10) + '">'
                        + escHtml(it.nombre) + escHtml(detalle) + '</option>';
                });
                lista.innerHTML = html;
            })
            .catch(function () {
                totalHint.textContent = 'Error de red al cargar destinatarios.';
            });
    }

    function toggleLista() {
        listaWrap.style.display = todosChk.checked ? 'none' : 'block';
    }

    grupoSel.addEventListener('change', function () {
        actualizarHintInterna();
        cargarDestinatarios();
    });
    todosChk.addEventListener('change', toggleLista);

    btn.addEventListener('click', function () {
        hideAlert();
        resultado.innerHTML = '';

        var canales = {
            whatsapp: canalWa.checked,
            correo: canalCorreo.checked,
            interna: canalInterna.checked
        };
        if (!canales.whatsapp && !canales.correo && !canales.interna) {
            showAlert('Selecciona al menos un canal de envio.', 'error');
            return;
        }
        if (mensaje.value.trim() === '') {
            showAlert('El mensaje es obligatorio.', 'error');
            return;
        }

        var ids = [];
        if (!todosChk.checked) {
            for (var i = 0; i < lista.options.length; i++) {
                if (lista.options[i].selected) ids.push(parseInt(lista.options[i].value, 10));
            }
            if (ids.length === 0) {
                showAlert('Selecciona al menos un destinatario o marca "Enviar a todos".', 'error');
                return;
            }
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';

        fetch('api/enviar_notificaciones.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                grupo: grupoSel.value,
                ids: ids,
                canales: canales,
                asunto: asunto.value,
                mensaje: mensaje.value
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send"></i> Enviar notificacion';
                if (!data || !data.success) {
                    showAlert((data && data.error) || 'No se pudo enviar la notificacion.', 'error');
                    return;
                }
                showAlert('Envio procesado correctamente.', 'success');
                var r = data.resumen || {};
                function fila(nombre, c) {
                    if (!c) return '';
                    return '<tr><td style="padding:6px;border-bottom:1px solid #eee;">' + nombre + '</td>'
                        + '<td style="padding:6px;border-bottom:1px solid #eee;text-align:center;">' + (c.enviados || 0) + '</td>'
                        + '<td style="padding:6px;border-bottom:1px solid #eee;text-align:center;">' + (c.omitidos || 0) + '</td>'
                        + '<td style="padding:6px;border-bottom:1px solid #eee;text-align:center;">' + (c.errores || 0) + '</td></tr>';
                }
                var html = '<table style="width:100%;border-collapse:collapse;margin-top:8px;">'
                    + '<tr style="background:#1a1a1a;color:#f4d03f;">'
                    + '<th style="padding:6px;text-align:left;">Canal</th>'
                    + '<th style="padding:6px;">Enviados</th>'
                    + '<th style="padding:6px;">Omitidos</th>'
                    + '<th style="padding:6px;">Errores</th></tr>'
                    + fila('WhatsApp', r.whatsapp)
                    + fila('Correo', r.correo)
                    + fila('Campana interna', r.interna)
                    + '</table>'
                    + '<p style="margin-top:6px;font-size:13px;color:#666;">Destinatarios procesados: ' + (r.destinatarios || 0) + '</p>';
                if (data.aviso) {
                    html += '<p style="font-size:13px;color:#5b7384;">' + escHtml(data.aviso) + '</p>';
                }
                if (data.errores && data.errores.length) {
                    html += '<ul style="font-size:13px;color:#a33;">';
                    data.errores.forEach(function (e) { html += '<li>' + escHtml(e) + '</li>'; });
                    html += '</ul>';
                }
                resultado.innerHTML = html;
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send"></i> Enviar notificacion';
                showAlert('Error de red al enviar la notificacion.', 'error');
            });
    });

    actualizarHintInterna();
    toggleLista();
    cargarDestinatarios();
})();
</script>
