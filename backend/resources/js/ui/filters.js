/**
 * Filter bars (x-filter-bar => form[data-filters], method GET).
 *
 * - Selects, dates and checkboxes submit the form on change.
 * - Inputs with data-filter-debounce submit 450 ms after typing stops.
 * - Empty parameters are left out of the URL; the page parameter is dropped
 *   so a new filter starts at page 1. Sort parameters stay (hidden inputs).
 * The visible Filter button keeps everything working without JavaScript.
 */
import { debounce, on } from '../lib/dom';

function submit(form) {
    form.requestSubmit(form.querySelector('[data-filters-submit]') ?? undefined);
}

export function initFilters() {
    on('change', 'form[data-filters] select, form[data-filters] input[type="date"], form[data-filters] input[type="checkbox"], form[data-filters] input[type="radio"]', (event, field) => submit(field.form));

    const debounced = new WeakMap();

    on('input', 'form[data-filters] [data-filter-debounce]', (event, field) => {
        if (!debounced.has(field)) {
            debounced.set(field, debounce(() => submit(field.form), 450));
        }

        debounced.get(field)();
    });

    on('submit', 'form[data-filters]', (event, form) => {
        // Disabled controls are not submitted: hide empty ones from the query string.
        form.querySelectorAll('input, select').forEach((field) => {
            if (field.name && field.value === '' && !field.disabled) {
                field.disabled = true;
                field.dataset.filterEmpty = '';
            }
        });

        form.querySelector('[data-filters-submit]')?.setAttribute('data-loading', '');
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-filter-empty]').forEach((field) => {
            field.disabled = false;
            delete field.dataset.filterEmpty;
        });
        document.querySelectorAll('form[data-filters] [data-filters-submit]').forEach((button) => button.removeAttribute('data-loading'));
    });
}
