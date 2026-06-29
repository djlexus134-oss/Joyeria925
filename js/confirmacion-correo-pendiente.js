(() => {
    'use strict';

    const API_URL = 'tienda_auth_api.php';
    const RESEND_DELAY_MS = 30000;
    const RESEND_COOLDOWN_MS = 10000;
    const ts = () => window.joyeriaTurnstile || {};

    const section = document.getElementById('resendVerificationSection');
    const btnResend = document.getElementById('btnResendVerificationPending');
    const banner = document.getElementById('resendBanner');

    const showBanner = (msg, type = 'info') => {
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

    if (section) {
        setTimeout(() => {
            section.hidden = false;
            const wrap = section.querySelector('.joyeria-turnstile-wrap');
            if (wrap && ts().renderIn) {
                ts().renderIn(wrap);
            }
        }, RESEND_DELAY_MS);
    }

    btnResend?.addEventListener('click', async () => {
        const correo = String(section?.dataset?.correo || '').trim();
        if (!correo) {
            showBanner('No pudimos determinar tu correo. Vuelve a registrarte.', 'error');
            return;
        }

        if (ts().requireToken && !ts().requireToken(section)) {
            showBanner('Completa la verificacion de seguridad antes de continuar.', 'error');
            return;
        }

        btnResend.disabled = true;
        showBanner('');

        const payload = { correo };
        const token = ts().getToken ? ts().getToken(section) : '';
        if (token) {
            payload.turnstile_token = token;
        }

        try {
            const data = await apiCall('resend_verification', payload);
            if (data.ok) {
                showBanner(data.message || 'Revisa tu correo.', 'info');
                const wrap = section?.querySelector('.joyeria-turnstile-wrap');
                if (wrap && ts().renderIn) {
                    wrap.dataset.rendered = '0';
                    wrap.innerHTML = '';
                    ts().renderIn(wrap);
                }
            } else {
                showBanner(data.error || 'No se pudo reenviar el correo.', 'error');
            }
        } catch (e) {
            showBanner('No se pudo conectar con el servidor.', 'error');
        } finally {
            setTimeout(() => {
                btnResend.disabled = false;
            }, RESEND_COOLDOWN_MS);
        }
    });
})();
