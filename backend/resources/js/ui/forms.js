/**
 * Form helpers.
 *
 * form[data-validate]
 *   Client-side checks before submit, shown in each field's error slot
 *   (x-form.field renders <p id="{id}-error" data-field-error>). The checks
 *   only mirror the HTML attributes (required, type=email, min/max,
 *   minlength/maxlength, data-match="other_field"); the server stays the
 *   authority. Server errors already on the page are left alone until the
 *   user edits that field. The submit button gets a loading state and double
 *   submits are blocked.
 *
 * form[data-async]
 *   Submits with fetch (lib/http.js). 422 errors are shown at the fields; on
 *   success the browser follows the final URL (controllers keep returning
 *   redirects), or a "form:success" event is dispatched when data-async="event".
 *
 * Exports showErrors(form, errors) / clearErrors(form) for custom flows.
 */
import { t } from '../lib/i18n';
import { request } from '../lib/http';
import { toast } from './toast';

const FIELD_SELECTOR = 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea';

/** Laravel error key ("scopes.branch.0") -> candidate input names ("scopes[branch]", ...). */
function namesForKey(key) {
    const parts = key.split('.');
    const names = [];

    for (let length = parts.length; length > 0; length -= 1) {
        const [head, ...rest] = parts.slice(0, length);
        const name = head + rest.map((part) => `[${part}]`).join('');
        names.push(name, `${name}[]`);
    }

    return names;
}

function errorSlot(field) {
    return (field.id && document.getElementById(`${field.id}-error`)) || field.closest('[data-field]')?.querySelector('[data-field-error]') || null;
}

function labelText(field) {
    const label = field.id ? document.querySelector(`label[for="${CSS.escape(field.id)}"]`) : null;

    return (label?.textContent ?? field.getAttribute('aria-label') ?? field.name ?? '').replace('*', '').trim();
}

function setFieldError(field, message) {
    const slot = errorSlot(field);

    if (message) {
        field.setAttribute('aria-invalid', 'true');
    } else {
        field.removeAttribute('aria-invalid');
    }

    if (slot) {
        slot.textContent = message ?? '';
        slot.hidden = !message;
    }
}

/** @returns {string|null} the first failing rule's message. */
export function validateField(field) {
    if (field.disabled || field.closest('fieldset[disabled]')) {
        return null;
    }

    const attribute = labelText(field);
    const value = field.type === 'checkbox' || field.type === 'radio' ? (field.checked ? field.value : '') : field.value.trim();
    const { validity } = field;

    if (validity.valueMissing || (field.required && value === '')) {
        return t('validation.required', { attribute });
    }

    if (value === '') {
        return null;
    }

    if (validity.typeMismatch && field.type === 'email') {
        return t('validation.email', { attribute });
    }

    if (validity.badInput) {
        return t(field.type === 'date' ? 'validation.date' : 'validation.integer', { attribute });
    }

    if (validity.rangeUnderflow) {
        return t('validation.min_numeric', { attribute, min: field.min });
    }

    if (validity.rangeOverflow) {
        return t('validation.max_numeric', { attribute, max: field.max });
    }

    if (validity.stepMismatch) {
        return t('validation.integer', { attribute });
    }

    if (field.minLength > 0 && value.length < field.minLength) {
        return t('validation.min_string', { attribute, min: field.minLength });
    }

    if (field.maxLength > 0 && value.length > field.maxLength) {
        return t('validation.max_string', { attribute, max: field.maxLength });
    }

    if (field.dataset.match) {
        const other = field.form?.elements.namedItem(field.dataset.match);

        if (other instanceof HTMLInputElement && other.value !== field.value) {
            return t('validation.confirmed', { attribute: labelText(other) });
        }
    }

    return null;
}

/** Runs every check; shows messages; focuses the first invalid field. */
export function validateForm(form) {
    let firstInvalid = null;

    form.querySelectorAll(FIELD_SELECTOR).forEach((field) => {
        const message = validateField(field);

        if (message || field.dataset.clientError !== undefined) {
            setFieldError(field, message);
        }

        if (message) {
            field.dataset.clientError = '';
            firstInvalid ??= field;
        } else {
            delete field.dataset.clientError;
        }
    });

    firstInvalid?.focus();

    return firstInvalid === null;
}

/**
 * @param {HTMLFormElement} form
 * @param {Record<string, string[]>} errors  Laravel / API "errors" object.
 */
export function showErrors(form, errors) {
    let first = null;

    Object.entries(errors).forEach(([key, messages]) => {
        const field = namesForKey(key).map((name) => form.querySelector(`[name="${CSS.escape(name)}"]`)).find(Boolean);

        if (field) {
            setFieldError(field, Array.isArray(messages) ? messages[0] : String(messages));
            first ??= field;
        }
    });

    first?.focus();
}

export function clearErrors(form) {
    form.querySelectorAll('[aria-invalid="true"]').forEach((field) => setFieldError(field, null));
}

function setBusy(form, busy, submitter = null) {
    const button = submitter ?? form.querySelector('button[type="submit"], button:not([type])');

    form.toggleAttribute('data-submitting', busy);

    if (button) {
        button.toggleAttribute('data-loading', busy);
        button.setAttribute('aria-busy', String(busy));
    }
}

async function submitAsync(form, submitter) {
    const data = new FormData(form, submitter ?? undefined);
    const method = (data.get('_method') ?? form.getAttribute('method') ?? 'POST').toString().toUpperCase();

    clearErrors(form);
    setBusy(form, true, submitter);

    try {
        const { body, response } = await request(form.action, { method: method === 'GET' ? 'GET' : 'POST', data });

        if (form.dataset.async === 'event') {
            form.dispatchEvent(new CustomEvent('form:success', { bubbles: true, detail: { body, response } }));
            setBusy(form, false, submitter);
        } else {
            window.location.assign(response.url);
        }
    } catch (error) {
        setBusy(form, false, submitter);

        if (error.isValidation) {
            showErrors(form, error.errors);
        } else {
            toast(error.message, { tone: 'danger' });
        }
    }
}

export function initForms() {
    document.querySelectorAll('form[data-validate], form[data-async]').forEach((form) => {
        form.noValidate = true;
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !(form.matches('[data-validate]') || form.matches('[data-async]'))) {
            return;
        }

        if (form.dataset.submitting !== undefined) {
            event.preventDefault();

            return;
        }

        if (!validateForm(form)) {
            event.preventDefault();

            return;
        }

        if (form.matches('[data-async]')) {
            event.preventDefault();
            submitAsync(form, event.submitter);

            return;
        }

        setBusy(form, true, event.submitter);
    });

    // Re-check a field the user is fixing; a server error clears once edited.
    const recheck = (event) => {
        const field = event.target;

        if (!(field instanceof Element) || !field.matches(FIELD_SELECTOR) || !field.form?.matches('[data-validate], [data-async]')) {
            return;
        }

        if (field.getAttribute('aria-invalid') === 'true') {
            const message = validateField(field);
            setFieldError(field, message);
            field.toggleAttribute('data-client-error', Boolean(message));
        }
    };

    document.addEventListener('input', recheck);
    document.addEventListener('change', recheck);

    // Back/forward cache: never restore a form stuck in its loading state.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting]').forEach((form) => setBusy(form, false));
    });
}
