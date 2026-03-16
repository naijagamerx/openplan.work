const App = (function(existing) {
    'use strict';

    if (existing && existing.api) {
        return existing;
    }

    function buildUrl(endpoint) {
        const cleanEndpoint = endpoint.startsWith('/') ? endpoint.substring(1) : endpoint;
        const baseUrl = typeof APP_URL !== 'undefined' ? APP_URL : '';
        return baseUrl ? `${baseUrl}/${cleanEndpoint}` : cleanEndpoint;
    }

    async function request(endpoint, options = {}) {
        const headers = {
            'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
            ...options.headers
        };

        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = headers['Content-Type'] || 'application/json';
        }

        const response = await fetch(buildUrl(endpoint), {
            credentials: 'same-origin',
            ...options,
            headers
        });

        const contentType = response.headers.get('content-type') || '';
        const isJson = contentType.includes('application/json');
        const payload = isJson ? await response.json().catch(() => ({})) : await response.text().catch(() => '');

        if (!response.ok) {
            const error = new Error(`HTTP error! status: ${response.status}`);
            error.status = response.status;
            error.response = payload;
            throw error;
        }

        if (!isJson) {
            throw new Error('Server returned non-JSON response');
        }

        return payload;
    }

    return {
        ...existing,
        api: {
            request,
            get(endpoint) {
                return request(endpoint);
            },
            post(endpoint, data) {
                return request(endpoint, { method: 'POST', body: JSON.stringify(data) });
            },
            put(endpoint, data) {
                return request(endpoint, { method: 'PUT', body: JSON.stringify(data) });
            },
            delete(endpoint) {
                return request(endpoint, { method: 'DELETE' });
            }
        }
    };
})(typeof App !== 'undefined' ? App : undefined);

