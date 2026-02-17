/**
 * Safe API fetch: always reads response as text first, checks Content-Type,
 * then parses JSON. Prevents "JSON.parse: unexpected character" when server
 * returns HTML/redirect/error body.
 */
(function() {
    'use strict';

    function getBase() {
        return (typeof window.__OZON_BASE__ !== 'undefined' && window.__OZON_BASE__ !== null)
            ? window.__OZON_BASE__
            : (window.location.pathname.replace(/\/[^/]*$/, '') || '');
    }

    /**
     * Build URL to API entrypoint using clean, prefix-based path:
     *     /<base>/api/<route>
     *
     * Example (base="/frontend", route="/me"):
     *     "/frontend/api/me"
     *
     * nginx is responsible for mapping /frontend/api/* to the real PHP entrypoint.
     *
     * @param {string} route - logical route, e.g. "/me" or "me/ozon-credentials"
     * @param {string} [baseOverride] - optional base path like "/frontend"
     * @returns {string} full URL like "/frontend/api/me"
     */
    function buildApiUrlR(route, baseOverride) {
        var r = route || "";
        if (r && r[0] !== "/") r = "/" + r;
        var base = (typeof baseOverride === 'string' && baseOverride.length)
            ? baseOverride
            : getBase();
        return (base || "") + "/api" + r;
    }
    window.buildApiUrlR = buildApiUrlR;

    function logBadResponse(url, status, contentType, bodySnippet) {
        var msg = '[API] Non-JSON or error response: url=' + url + ' status=' + status + ' contentType=' + (contentType || '') + ' body=' + (bodySnippet || '').substring(0, 300);
        if (typeof console !== 'undefined' && console.warn) {
            console.warn(msg);
        }
    }

    /**
     * @param {string} path - Path (e.g. '/api/drafts' or '/auth/login')
     * @param {Object} options - fetch options (method, headers, body). body can be object (will be JSON.stringify'd).
     * @returns {Promise<{ok: boolean, data?: *, error?: {code, message, details}}>} Parsed envelope.
     */
    window.apiFetchJson = function(path, options) {
        options = options || {};
        var base = getBase();
        // If caller already provided a path that includes the base prefix (e.g. '/frontend/auth/login'),
        // do NOT prepend base again. This avoids paths like '/frontend/frontend/auth/login'.
        var isAbs = (path.indexOf('http://') === 0 || path.indexOf('https://') === 0);
        var url;
        if (isAbs) {
            url = path;
        } else {
            if (base && (path === base || path.indexOf(base + '/') === 0)) {
                url = path;
            } else {
                url = (base ? base : '') + path;
            }
        }

        var headers = options.headers || {};
        if (!headers['Accept']) {
            headers['Accept'] = 'application/json';
        }

        // Forward current UI locale to backend without relying on query args,
        // which might be rewritten by nginx.
        var lang = (typeof window.__OZON_LANG__ === 'string' && window.__OZON_LANG__)
            ? window.__OZON_LANG__
            : null;
        if (lang && !headers['X-Lang']) {
            headers['X-Lang'] = lang;
        }
        var body = options.body;
        if (body !== undefined && typeof body !== 'string' && !(body instanceof FormData)) {
            body = JSON.stringify(body);
            if (!headers['Content-Type']) {
                headers['Content-Type'] = 'application/json';
            }
        }

        return fetch(url, {
            method: options.method || 'GET',
            headers: headers,
            body: body,
            credentials: options.credentials !== undefined ? options.credentials : 'same-origin'
        }).then(function(res) {
            var contentType = (res.headers && res.headers.get ? res.headers.get('Content-Type') : '') || '';
            return res.text().then(function(text) {
                var isJson = contentType.indexOf('application/json') !== -1;

                if (!isJson) {
                    logBadResponse(url, res.status, contentType, text);
                    var err = new Error('Server returned non-JSON (status ' + res.status + '). Check console for details.');
                    err.status = res.status;
                    err.bodySnippet = text ? text.substring(0, 300) : '';
                    throw err;
                }

                var data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    logBadResponse(url, res.status, contentType, text);
                    var parseErr = new Error('Invalid JSON from server: ' + (text ? text.substring(0, 100) : 'empty'));
                    parseErr.status = res.status;
                    parseErr.bodySnippet = text ? text.substring(0, 300) : '';
                    throw parseErr;
                }

                if (res.status < 200 || res.status >= 300) {
                    logBadResponse(url, res.status, contentType, text);
                }

                return data;
            });
        });
    };
})();
