(() => {
    'use strict';

    const cfg = window.JOYERIA_TURNSTILE || {};
    const enabled = cfg.enabled === true;
    const siteKey = String(cfg.siteKey || '');

    const getToken = (form) => {
        if (!enabled) {
            return '';
        }
        const root = form && form.nodeType === 1 ? form : document;
        const input = root.querySelector('[name="cf-turnstile-response"]');
        return String(input?.value || '').trim();
    };

    const resetForm = (form) => {
        if (!enabled || !window.turnstile || !form) {
            return;
        }
        const widget = form.querySelector('.joyeria-turnstile-wrap');
        if (!widget) {
            return;
        }
        const widgetId = widget.dataset.turnstileWidgetId;
        if (widgetId) {
            window.turnstile.reset(widgetId);
        }
    };

    const renderIn = (container) => {
        if (!enabled || !container || container.dataset.rendered === '1' || siteKey === '') {
            return;
        }

        const tryRender = () => {
            if (!window.turnstile) {
                window.setTimeout(tryRender, 120);
                return;
            }
            const widgetId = window.turnstile.render(container, {
                sitekey: siteKey,
                theme: 'light',
            });
            container.dataset.rendered = '1';
            container.dataset.turnstileWidgetId = String(widgetId);
        };

        tryRender();
    };

    const renderForm = (form) => {
        if (!form) {
            return;
        }
        const container = form.querySelector('.joyeria-turnstile-wrap');
        if (container) {
            renderIn(container);
        }
    };

    const requireToken = (form) => {
        if (!enabled) {
            return true;
        }
        return getToken(form) !== '';
    };

    window.joyeriaTurnstile = {
        enabled,
        siteKey,
        getToken,
        resetForm,
        renderIn,
        renderForm,
        requireToken,
    };
})();
