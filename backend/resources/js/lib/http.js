/**
 * fetch() wrapper for same-origin calls to the web routes and /api/v1.
 *
 * - Sends the CSRF token (meta[name=csrf-token]) and asks for JSON.
 * - Serialises plain objects as JSON; FormData is sent as multipart.
 * - Resolves with the parsed body (the API envelope's `data` is NOT unwrapped,
 *   so callers can read `meta` / `message`).
 * - Rejects with an HttpError that normalises both error shapes:
 *     API envelope:      {success: false, message, code, errors?}
 *     Laravel web JSON:  {message, errors?}  (422 validation)
 *
 * Usage:
 *   import { http, HttpError } from './lib/http';
 *   const body = await http.post('/admin/things', { name: 'x' });
 */

import { t } from './i18n';

export class HttpError extends Error {
    /**
     * @param {string} message
     * @param {{status: number, code?: string, errors?: Record<string, string[]>, body?: unknown, response?: Response}} details
     */
    constructor(message, { status, code = null, errors = {}, body = null, response = null }) {
        super(message);
        this.name = 'HttpError';
        this.status = status;
        this.code = code;
        this.errors = errors ?? {};
        this.body = body;
        this.response = response;
    }

    /** True for a 422 carrying field errors. */
    get isValidation() {
        return this.status === 422;
    }
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';


async function parseBody(response) {
    if (response.status === 204) {
        return null;
    }

    const type = response.headers.get('content-type') ?? '';

    return type.includes('application/json') ? response.json() : response.text();
}

/**
 * @param {string} url
 * @param {{method?: string, data?: unknown, headers?: Record<string, string>, signal?: AbortSignal}} options
 */
export async function request(url, { method = 'GET', data = undefined, headers = {}, signal } = {}) {
    const upper = method.toUpperCase();
    const init = {
        method: upper,
        credentials: 'same-origin',
        signal,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...headers,
        },
    };

    if (upper !== 'GET' && upper !== 'HEAD') {
        init.headers['X-CSRF-TOKEN'] = csrfToken();
    }

    if (data instanceof FormData || data instanceof URLSearchParams) {
        init.body = data;
    } else if (data !== undefined) {
        init.headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(data);
    }

    let response;

    try {
        response = await fetch(url, init);
    } catch (error) {
        if (error.name === 'AbortError') {
            throw error;
        }

        throw new HttpError(t('network_error'), { status: 0, code: 'network_error' });
    }

    const body = await parseBody(response);

    if (!response.ok || (body && typeof body === 'object' && body.success === false)) {
        const isObject = body && typeof body === 'object';

        throw new HttpError(
            (isObject && body.message) || t('request_failed'),
            {
                status: response.status,
                code: isObject ? body.code ?? null : null,
                errors: isObject ? body.errors ?? {} : {},
                body,
                response,
            },
        );
    }

    return { body, response };
}

export const http = {
    get: (url, options) => request(url, { ...options, method: 'GET' }).then((result) => result.body),
    post: (url, data, options) => request(url, { ...options, method: 'POST', data }).then((result) => result.body),
    put: (url, data, options) => request(url, { ...options, method: 'PUT', data }).then((result) => result.body),
    patch: (url, data, options) => request(url, { ...options, method: 'PATCH', data }).then((result) => result.body),
    delete: (url, data, options) => request(url, { ...options, method: 'DELETE', data }).then((result) => result.body),
};
