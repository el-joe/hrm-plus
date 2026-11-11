/**
 * Strings rendered by partials/head.blade.php into <script id="app-i18n">.
 * Never hard-code user-visible text in JS: add the key there (and to lang/ar + lang/en).
 */
let cache = null;

function strings() {
    if (cache === null) {
        try {
            cache = JSON.parse(document.getElementById('app-i18n')?.textContent ?? '{}');
        } catch {
            cache = {};
        }
    }

    return cache;
}

/**
 * @param {string} key  Dot path, e.g. 'validation.required'.
 * @param {Record<string, string|number>} replace  Laravel-style :placeholders.
 */
export function t(key, replace = {}) {
    const value = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), strings());

    if (typeof value !== 'string') {
        return key;
    }

    return Object.entries(replace).reduce((text, [name, replacement]) => text.replaceAll(`:${name}`, String(replacement)), value);
}
