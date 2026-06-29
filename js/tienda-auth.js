(function () {
    'use strict';

    var API_URL = 'tienda_auth_api.php';

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }

    function showBanner(el, text, variant) {
        if (!el) {
            return;
        }
        el.textContent = text || '';
        el.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-info');
        if (!text) {
            el.classList.add('d-none');
            return;
        }
        el.classList.add(
            variant === 'success'
                ? 'alert-success'
                : variant === 'info'
                  ? 'alert-info'
                  : 'alert-danger'
        );
    }

    async function apiCall(action, body) {
        var payload = Object.assign({ action: action }, body || {});
        var res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json;charset=UTF-8' },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        return res.json();
    }

    /** Sigue redirect relativo desde la página actual (mismo directorio virtual que index). */
    function goIfRedirect(data) {
        if (!data || !data.redirect || typeof data.redirect !== 'string') {
            return;
        }
        window.location.assign(new URL(data.redirect, window.location.href).href);
    }

    function updateHeaderUi(user) {
        var btn = $('#btnTiendaAccount');
        var sess = $('.tienda-header-session');
        var nameEl = $('.tienda-header-name');
        if (!btn || !sess) {
            return;
        }
        if (user && (user.nombre_completo || user.correo)) {
            sess.classList.remove('d-none');
            sess.classList.add('d-flex');
            if (nameEl) {
                var nc = (user.nombre_completo && String(user.nombre_completo).trim()) || '';
                nameEl.textContent = nc || user.correo || '';
            }
            btn.setAttribute('aria-label', 'Cuenta: ' + (user.correo || ''));
        } else {
            sess.classList.add('d-none');
            sess.classList.remove('d-flex');
            if (nameEl) {
                nameEl.textContent = '';
            }
            btn.setAttribute('aria-label', 'Iniciar sesión o registrarse');
        }
    }

    async function refreshSession() {
        try {
            var data = await apiCall('session', {});
            if (data.ok) {
                updateHeaderUi(data.user);
            }
        } catch (e) {
            /* silenciar si no hay servidor PHP */
        }
    }

    function resetForgotPanel(modalEl, showForgot) {
        var paneLogin = $('.tienda-pane-login', modalEl);
        var paneForgot = $('.tienda-pane-forgot', modalEl);
        var tabs = modalEl.querySelector('.nav-tabs');
        if (!paneLogin || !paneForgot) {
            return;
        }
        if (showForgot) {
            if (tabs) {
                tabs.classList.add('d-none');
            }
            paneLogin.classList.add('d-none');
            paneForgot.classList.remove('d-none');
        } else {
            paneForgot.classList.add('d-none');
            if (tabs) {
                tabs.classList.remove('d-none');
            }
            paneLogin.classList.remove('d-none');
        }
    }

    function esIdentificadorValido(valor) {
        var v = String(valor || '').trim();
        if (!v) {
            return false;
        }
        if (v.indexOf('@') !== -1) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
        }
        return v.replace(/\D/g, '').length >= 10;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = $('#modalTiendaAuth');
        if (!modalEl) {
            return;
        }

        var bannerLogin = $('.tienda-auth-banner-login', modalEl);
        var bannerReg = $('.tienda-auth-banner-registro', modalEl);
        var bannerForgot = $('.tienda-auth-banner-forgot', modalEl);

        document.getElementById('btnTiendaLogout')?.addEventListener('click', async function (ev) {
            ev.preventDefault();
            try {
                await apiCall('logout', {});
                updateHeaderUi(null);
            } catch (e) {}
        });

        document.getElementById('btnShowForgot')?.addEventListener('click', function (ev) {
            ev.preventDefault();
            resetForgotPanel(modalEl, true);
            showBanner(bannerForgot, '', '');
        });

        document.getElementById('btnBackToLogin')?.addEventListener('click', function (ev) {
            ev.preventDefault();
            resetForgotPanel(modalEl, false);
            showBanner(bannerForgot, '', '');
        });

        modalEl.addEventListener('shown.bs.modal', function () {
            refreshSession();
            resetForgotPanel(modalEl, false);
            showBanner(bannerLogin, '', '');
            showBanner(bannerReg, '', '');
            showBanner(bannerForgot, '', '');
        });

        modalEl.querySelector('[data-bs-target="#paneTiendaRegistro"]')?.addEventListener('click', function () {
            resetForgotPanel(modalEl, false);
        });
        modalEl.querySelector('[data-bs-target="#paneTiendaLogin"]')?.addEventListener('click', function () {
            resetForgotPanel(modalEl, false);
        });

        document.getElementById('formTiendaLogin')?.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            var fd = new FormData(ev.target);
            showBanner(bannerLogin, '', '');
            var identificador = String(fd.get('correo') || '').trim();
            if (!esIdentificadorValido(identificador)) {
                showBanner(bannerLogin, 'Introduce un correo válido o un teléfono de al menos 10 dígitos.', 'danger');
                return;
            }
            try {
                var data = await apiCall('login', {
                    correo: identificador,
                    contrasena: fd.get('contrasena'),
                });
                if (data.ok) {
                    if (data.redirect) {
                        goIfRedirect(data);
                        return;
                    }
                    updateHeaderUi(data.user);
                    showBanner(bannerLogin, 'Sesión iniciada correctamente.', 'success');
                    ev.target.reset();
                    setTimeout(function () {
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    }, 600);
                } else if (data.email_not_verified) {
                    showBanner(
                        bannerLogin,
                        data.error || 'Debes confirmar tu correo antes de iniciar sesión.',
                        'info'
                    );
                } else {
                    showBanner(bannerLogin, data.error || 'No se pudo iniciar sesión.', 'danger');
                }
            } catch (e) {
                showBanner(
                    bannerLogin,
                    'No se pudo conectar con el servidor. Comprueba que el sitio se sirva con PHP.',
                    'danger'
                );
            }
        });

        document.getElementById('formTiendaRegistro')?.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            var fd = new FormData(ev.target);
            showBanner(bannerReg, '', '');
            var c = fd.get('contrasena');
            var c2 = fd.get('contrasena_confirm');
            var correo = String(fd.get('correo') || '').trim();
            var telefono = String(fd.get('telefono') || '').trim();
            if (!correo) {
                showBanner(bannerReg, 'El correo es obligatorio.', 'danger');
                return;
            }
            if (telefono.replace(/\D/g, '').length < 10) {
                showBanner(bannerReg, 'El teléfono debe tener al menos 10 dígitos.', 'danger');
                return;
            }
            if (String(c || '').length < 8) {
                showBanner(bannerReg, 'La contraseña debe tener al menos 8 caracteres.', 'danger');
                return;
            }
            if (c !== c2) {
                showBanner(bannerReg, 'Las contraseñas no coinciden.', 'danger');
                return;
            }
            try {
                var data = await apiCall('register', {
                    nombre: fd.get('nombre'),
                    primer_apellido: fd.get('primer_apellido'),
                    segundo_apellido: fd.get('segundo_apellido') || '',
                    correo: fd.get('correo'),
                    telefono: fd.get('telefono'),
                    contrasena: c,
                    contrasena_confirm: c2,
                });
                if (data.ok && data.verification_required) {
                    showBanner(
                        bannerReg,
                        data.message || 'Revisa tu correo para confirmar tu cuenta antes de iniciar sesión.',
                        'info'
                    );
                    ev.target.reset();
                    var lt = modalEl.querySelector('#tabTiendaLogin');
                    if (lt && window.bootstrap && window.bootstrap.Tab) {
                        bootstrap.Tab.getOrCreateInstance(lt).show();
                    }
                } else if (data.ok && data.user) {
                    if (data.redirect) {
                        goIfRedirect(data);
                        return;
                    }
                    updateHeaderUi(data.user);
                    showBanner(bannerReg, '¡Bienvenido! Tu cuenta fue creada.', 'success');
                    ev.target.reset();
                    setTimeout(function () {
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    }, 650);
                } else if (data.ok && data.message) {
                    showBanner(bannerReg, data.message, 'info');
                } else if (data.ok && data.registered && !data.user) {
                    showBanner(bannerReg, data.message || 'Cuenta creada. Inicia sesión.', 'success');
                    ev.target.reset();
                    var lt = modalEl.querySelector('#tabTiendaLogin');
                    if (lt && window.bootstrap && window.bootstrap.Tab) {
                        bootstrap.Tab.getOrCreateInstance(lt).show();
                    }
                } else {
                    showBanner(bannerReg, data.error || 'No se pudo registrar.', 'danger');
                }
            } catch (e) {
                showBanner(
                    bannerReg,
                    'No se pudo conectar con el servidor. Comprueba que el sitio se sirva con PHP.',
                    'danger'
                );
            }
        });

        document.getElementById('formTiendaForgot')?.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            var fd = new FormData(ev.target);
            showBanner(bannerForgot, '', '');
            try {
                var data = await apiCall('forgot_password', { correo: fd.get('correo') });
                if (data.ok) {
                    showBanner(bannerForgot, data.message || 'Revisa tu correo.', 'success');
                    ev.target.reset();
                } else {
                    showBanner(bannerForgot, data.error || 'No se pudo enviar la solicitud.', 'danger');
                }
            } catch (e) {
                showBanner(
                    bannerForgot,
                    'No se pudo conectar con el servidor. Comprueba que el sitio se sirva con PHP.',
                    'danger'
                );
            }
        });

        refreshSession();
    });
})();
