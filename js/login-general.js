(() => {
    'use strict';

    const API_URL = 'tienda_auth_api.php';
    const ts = () => window.joyeriaTurnstile || {};

    const panes = document.querySelectorAll('.general-pane');
    const buttons = document.querySelectorAll('.switch-btn');
    const paneLinks = document.querySelectorAll('[data-pane-target]');
    const banner = document.getElementById('generalBanner');
    const loginCorreoInput = document.getElementById('correo_login');

    const renderTurnstileForPane = (paneId) => {
        const pane = document.getElementById(paneId);
        const form = pane?.querySelector('form');
        if (form && ts().renderForm) {
            ts().renderForm(form);
        }
    };

    const showPane = (id) => {
        panes.forEach((p) => p.classList.toggle('is-active', p.id === id));
        buttons.forEach((b) => b.classList.toggle('is-active', b.dataset.pane === id));
        renderTurnstileForPane(id);
    };

    const showBanner = (msg, type = 'error') => {
        if (!banner) return;
        banner.textContent = msg || '';
        banner.classList.remove('error', 'success', 'info');
        if (!msg) {
            banner.hidden = true;
            return;
        }
        banner.hidden = false;
        banner.classList.add(type);
    };

    const esIdentificadorValido = (valor) => {
        const v = String(valor || '').trim();
        if (!v) return false;
        if (v.includes('@')) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
        }
        return v.replace(/\D/g, '').length >= 10;
    };

    const apiCall = async (action, body) => {
        const payload = Object.assign({ action }, body || {});
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json;charset=UTF-8' },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        return res.json();
    };

    const applyRedirect = (data) => {
        if (!data || typeof data.redirect !== 'string' || data.redirect === '') return false;
        window.location.assign(new URL(data.redirect, window.location.href).href);
        return true;
    };

    const turnstilePayload = (form) => {
        const token = ts().getToken ? ts().getToken(form) : '';
        return token ? { turnstile_token: token } : {};
    };

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            showBanner('');
            showPane(btn.dataset.pane);
        });
    });

    paneLinks.forEach((lnk) => {
        lnk.addEventListener('click', (ev) => {
            ev.preventDefault();
            showBanner('');
            showPane(lnk.dataset.paneTarget);
        });
    });

    document.getElementById('formGeneralLogin')?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        showBanner('');
        const form = ev.currentTarget;
        if (ts().requireToken && !ts().requireToken(form)) {
            showBanner('Completa la verificacion de seguridad antes de continuar.', 'error');
            return;
        }
        const fd = new FormData(form);
        const identificador = String(fd.get('correo') || '').trim();
        if (!esIdentificadorValido(identificador)) {
            showBanner('Introduce un correo válido o un teléfono de al menos 10 dígitos.', 'error');
            return;
        }
        try {
            const data = await apiCall('login', Object.assign({
                correo: identificador,
                contrasena: fd.get('contrasena'),
            }, turnstilePayload(form)));
            if (data.ok) {
                if (!applyRedirect(data)) {
                    showBanner('Sesión iniciada.', 'success');
                }
            } else {
                if (applyRedirect(data)) {
                    return;
                }
                let msg = data.error || 'No se pudo iniciar sesión.';
                if (data.detail) {
                    msg += ' ' + data.detail;
                }
                if (Array.isArray(data.hints) && data.hints.length) {
                    msg += ' ' + data.hints.join(' ');
                }
                showBanner(msg, 'error');
                ts().resetForm?.(form);
            }
        } catch (e) {
            showBanner('No se pudo conectar con el servidor.', 'error');
            ts().resetForm?.(form);
        }
    });

    document.getElementById('formGeneralRegister')?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        showBanner('');
        const form = ev.currentTarget;
        if (ts().requireToken && !ts().requireToken(form)) {
            showBanner('Completa la verificacion de seguridad antes de continuar.', 'error');
            return;
        }
        const fd = new FormData(form);
        const c = String(fd.get('contrasena') || '');
        const c2 = String(fd.get('contrasena_confirm') || '');
        const correo = String(fd.get('correo') || '').trim();
        const telefono = String(fd.get('telefono') || '').trim();
        if (!correo) {
            showBanner('El correo es obligatorio.', 'error');
            return;
        }
        if (telefono.replace(/\D/g, '').length < 10) {
            showBanner('El teléfono debe tener al menos 10 dígitos.', 'error');
            return;
        }
        if (c.length < 8) {
            showBanner('La contraseña debe tener al menos 8 caracteres.', 'error');
            return;
        }
        if (c !== c2) {
            showBanner('Las contraseñas no coinciden.', 'error');
            return;
        }
        try {
            const data = await apiCall('register', Object.assign({
                nombre: fd.get('nombre'),
                primer_apellido: fd.get('primer_apellido'),
                segundo_apellido: fd.get('segundo_apellido') || '',
                correo: fd.get('correo'),
                telefono: fd.get('telefono'),
                contrasena: c,
                contrasena_confirm: c2,
            }, turnstilePayload(form)));
            if (data.ok) {
                if (applyRedirect(data)) {
                    return;
                }
                showBanner(data.message || 'Cuenta creada.', 'success');
                showPane('pane-login');
                if (loginCorreoInput && correo) {
                    loginCorreoInput.value = correo;
                }
                form.reset();
                ts().resetForm?.(form);
            } else {
                showBanner(data.error || 'No se pudo crear la cuenta.', 'error');
                ts().resetForm?.(form);
            }
        } catch (e) {
            showBanner('No se pudo conectar con el servidor.', 'error');
            ts().resetForm?.(form);
        }
    });

    document.getElementById('formGeneralForgot')?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        showBanner('');
        const form = ev.currentTarget;
        if (ts().requireToken && !ts().requireToken(form)) {
            showBanner('Completa la verificacion de seguridad antes de continuar.', 'error');
            return;
        }
        const fd = new FormData(form);
        try {
            const data = await apiCall('forgot_password', Object.assign({
                correo: fd.get('correo'),
            }, turnstilePayload(form)));
            if (data.ok) {
                showBanner(data.message || 'Revisa tu correo.', 'info');
                ts().resetForm?.(form);
            } else {
                showBanner(data.error || 'No se pudo enviar el enlace.', 'error');
                ts().resetForm?.(form);
            }
        } catch (e) {
            showBanner('No se pudo conectar con el servidor.', 'error');
            ts().resetForm?.(form);
        }
    });

    renderTurnstileForPane('pane-login');

    const initialError = (banner?.dataset?.initialError || '').trim();
    if (initialError) {
        showBanner(initialError, 'error');
    } else {
        showBanner('');
    }
})();
