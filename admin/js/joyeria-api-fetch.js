/**
 * Fetch JSON para APIs admin: CSRF, credentials, parseo seguro y errores HTTP.
 */
(function () {
    'use strict';

    function parseJsonBody(text, httpStatus) {
        if (text == null || String(text).trim() === '') {
            return null;
        }
        var raw = String(text);
        try {
            return JSON.parse(raw);
        } catch (e) {
            var trimmed = raw.trim();
            var jsonStart = trimmed.indexOf('{');
            if (jsonStart > 0) {
                try {
                    return JSON.parse(trimmed.slice(jsonStart));
                } catch (e2) {
                    /* continuar */
                }
            }
            if (httpStatus === 401) {
                var err401 = new Error('Sesion no valida. Inicia sesion nuevamente.');
                err401.status = 401;
                throw err401;
            }
            var err = new Error('El servidor devolvio una respuesta invalida. Recarga la pagina e intenta de nuevo.');
            err.invalidJson = true;
            err.cause = e;
            err.rawPreview = raw.slice(0, 400);
            throw err;
        }
    }

    function messageFromPayload(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }
        if (typeof data.error === 'string' && data.error.trim() !== '') {
            return data.error;
        }
        if (typeof data.mensaje === 'string' && data.mensaje.trim() !== '') {
            return data.mensaje;
        }
        if (typeof data.message === 'string' && data.message.trim() !== '') {
            return data.message;
        }
        return fallback;
    }

    function isSameOriginUrl(url) {
        if (!url || String(url).indexOf('http') !== 0) {
            return true;
        }
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch (e) {
            return true;
        }
    }

    function prepareInit(url, init) {
        init = Object.assign({}, init || {});
        if (!init.credentials && isSameOriginUrl(url)) {
            init.credentials = 'same-origin';
        }
        if (window.joyeriaPrepareFetchCsrf && isSameOriginUrl(url)) {
            return window.joyeriaPrepareFetchCsrf(init);
        }
        return init;
    }

    async function joyeriaApiFetch(url, init) {
        init = prepareInit(url, init);
        var res = await fetch(url, init);
        var text = await res.text();
        var data = parseJsonBody(text, res.status);

        if (res.status === 401) {
            var err401 = new Error(messageFromPayload(data, 'Sesion no valida. Inicia sesion nuevamente.'));
            err401.status = 401;
            err401.data = data;
            throw err401;
        }

        if (res.status === 403) {
            var msg403 = messageFromPayload(data, 'Token de seguridad invalido. Recarga la pagina.');
            var err403 = new Error(msg403);
            err403.status = 403;
            err403.data = data;
            throw err403;
        }

        if (!res.ok) {
            var errHttp = new Error(messageFromPayload(data, 'Error del servidor (' + res.status + ').'));
            errHttp.status = res.status;
            errHttp.data = data;
            throw errHttp;
        }

        if (data && data.success === false) {
            var errApi = new Error(messageFromPayload(data, 'Operacion no completada.'));
            errApi.status = res.status;
            errApi.data = data;
            throw errApi;
        }

        return { response: res, data: data };
    }

    window.joyeriaApiFetch = joyeriaApiFetch;
    window.joyeriaApiMessageFromPayload = messageFromPayload;
})();
