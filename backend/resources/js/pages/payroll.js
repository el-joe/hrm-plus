/**
 * Payroll forms (Phase 7a).
 *
 * select[data-switch="group"] shows only the elements of the same form whose
 * data-show-for lists the selected value (data-switch-group="group"; an empty
 * data-switch is the default group). data-hide-when="group:value" hides an
 * element whatever its own group says (e.g. amount for instalment deductions).
 * Hidden fields are disabled so they are not submitted. Without JavaScript
 * every field stays visible and the server keeps only what the choice uses.
 *
 * [data-repeater] (structure lines, tax brackets): "Add" clones the
 * <template data-repeater-template> with a fresh index, "Remove" deletes a row.
 * The server renders a few empty rows, so the form also works without JS.
 */
import { on } from '../lib/dom';

// Blade components render a bare attribute as data-switch="data-switch": that is the default group.
const groupOf = (value) => (!value || value === 'data-switch' ? '' : value);

function groupValues(form) {
    const values = {};

    form.querySelectorAll('select[data-switch]').forEach((select) => {
        values[groupOf(select.dataset.switch)] = select.value;
    });

    return values;
}

function syncSwitches(form) {
    const values = groupValues(form);

    form.querySelectorAll('[data-show-for]').forEach((element) => {
        const group = element.dataset.switchGroup || '';

        if (!(group in values)) {
            return;
        }

        let visible = element.dataset.showFor.split(' ').includes(values[group]);
        const hideWhen = element.dataset.hideWhen;

        if (visible && hideWhen) {
            const [hideGroup, hideValue] = hideWhen.split(':');
            visible = values[hideGroup] !== hideValue;
        }

        element.hidden = !visible;
        element.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !visible;
        });
    });
}

function addRow(repeater) {
    const template = repeater.querySelector('template[data-repeater-template]');
    const rows = repeater.querySelector('[data-repeater-rows]');

    if (!template || !rows) {
        return;
    }

    const next = Number(repeater.dataset.repeaterNext || rows.children.length);
    repeater.dataset.repeaterNext = String(next + 1);
    rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(next)));
    rows.lastElementChild?.querySelectorAll('[data-js-only]').forEach((element) => {
        element.hidden = false;
    });
    rows.lastElementChild?.querySelector('select, input')?.focus();
}

export function initPayrollForms() {
    const forms = new Set([...document.querySelectorAll('select[data-switch]')].map((select) => select.form).filter(Boolean));
    forms.forEach(syncSwitches);

    on('change', 'select[data-switch]', (event, select) => {
        if (select.form) {
            syncSwitches(select.form);
        }
    });

    document.querySelectorAll('[data-repeater]').forEach((repeater) => {
        const rows = repeater.querySelector('[data-repeater-rows]');
        repeater.dataset.repeaterNext = String(rows ? rows.children.length : 0);
    });

    on('click', '[data-repeater-add]', (event, button) => {
        const repeater = button.closest('[data-repeater]');

        if (repeater) {
            addRow(repeater);
        }
    });

    on('click', '[data-repeater-remove]', (event, button) => {
        const row = button.closest('[data-repeater-row]');
        const rows = row?.parentElement;

        if (row && rows && rows.children.length > 1) {
            row.remove();
        } else if (row) {
            row.querySelectorAll('input, select').forEach((field) => {
                field.value = '';
            });
        }
    });
}
