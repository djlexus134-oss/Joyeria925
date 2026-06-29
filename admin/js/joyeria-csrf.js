/**
 * Token CSRF admin: helpers para fetch y FormData (meta csrf-token en header.php).
 */
(function () {
    'use strict';

    function readToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '').trim() : '';
    }

    function appendToFormData(fd) {
        if (!fd || typeof fd.append !== 'function') {
            return fd;
        }
        var token = readToken();
        if (token === '') {
            return fd;
        }
        try {
            if (typeof fd.set === 'function') {
                fd.set('_csrf_token', token);
            } else {
                fd.append('_csrf_token', token);
            }
        } catch (e) {
            fd.append('_csrf_token', token);
        }
        return fd;
    }

    function mergeHeaders(extra) {
        var headers = new Headers(extra || undefined);
        var token = readToken();
        if (token !== '') {
            headers.set('X-CSRF-Token', token);
        }
        return headers;
    }

    function prepareFetchInit(init) {
        init = init || {};
        var method = String(init.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) === -1) {
            return init;
        }
        init.headers = mergeHeaders(init.headers);
        if (init.body instanceof FormData) {
            appendToFormData(init.body);
        } else if (typeof init.body === 'string' && init.body.trim() !== '') {
            var headers = init.headers;
            var ct = headers instanceof Headers ? (headers.get('Content-Type') || '') : '';
            if (ct.indexOf('application/json') !== -1) {
                try {
                    var parsed = JSON.parse(init.body);
                    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                        var t = readToken();
                        if (t !== '' && !parsed._csrf_token) {
                            parsed._csrf_token = t;
                            init.body = JSON.stringify(parsed);
                        }
                    }
                } catch (eJson) {
                    /* body JSON invalido: solo cabecera */
                }
            }
        }
        return init;
    }

    function isCsrfErrorMessage(msg) {
        var s = String(msg || '');
        return s.indexOf('Token de seguridad') !== -1 || s.indexOf('token de seguridad') !== -1;
    }

    window.joyeriaCsrfToken = readToken;
    window.joyeriaAppendCsrfToFormData = appendToFormData;
    window.joyeriaCsrfMergeHeaders = mergeHeaders;
    window.joyeriaPrepareFetchCsrf = prepareFetchInit;
    window.joyeriaIsCsrfErrorMessage = isCsrfErrorMessage;
})();
