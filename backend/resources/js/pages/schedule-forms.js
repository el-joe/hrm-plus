/**
 * Attendance configuration forms (admin/scheduling/*). Every form also works
 * without JavaScript: all optional parts are rendered and the server only
 * reads the ones that apply.
 *
 * - form[data-shift-form]: flexible-hours section only for flexible shifts;
 *   break rows: empty rows beyond the first are hidden and revealed by "Add break".
 * - form[data-work-schedule-form]: only the pattern grid of the chosen type, only
 *   as many rotating rows as the cycle length; "Rest" label when a day has no shift.
 * - form[data-scope-form]: only the record select of the chosen "applies to" type.
 * - form[data-schedule-change-form]: shift field for a change, employee field for a swap.
 */
function toggle(element, visible) {
    element.hidden = !visible;
    element.querySelectorAll('input, select, textarea').forEach((control) => {
        control.disabled = !visible;
    });
}

function initShiftForm(form) {
    const type = form.querySelector('[data-shift-type]');
    const flexible = form.querySelector('[data-flexible-fields]');
    const sync = () => toggle(flexible, type.value === 'flexible');
    type.addEventListener('change', sync);
    sync();

    const rows = [...form.querySelectorAll('[data-break-row]')];
    const add = form.querySelector('[data-add-break]');
    const syncAdd = () => {
        add.disabled = rows.every((row) => !row.hidden);
    };

    rows.filter((row) => 'emptyRow' in row.dataset).forEach((row) => {
        row.hidden = true;
    });

    add?.addEventListener('click', () => {
        const next = rows.find((row) => row.hidden);

        if (next) {
            next.hidden = false;
            next.querySelector('input')?.focus();
        }

        syncAdd();
    });

    form.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-break]');

        if (!remove) {
            return;
        }

        const row = remove.closest('[data-break-row]');
        row.querySelectorAll('input:not([type="hidden"])').forEach((input) => {
            if (input.type === 'checkbox') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        row.hidden = rows.filter((candidate) => !candidate.hidden).length > 1;
        syncAdd();
    });

    syncAdd();
}

function initWorkScheduleForm(form) {
    const type = form.querySelector('[data-schedule-type]');
    const length = form.querySelector('[data-cycle-length]');
    const rotatingFields = form.querySelectorAll('[data-rotating-field]');

    const sync = () => {
        form.querySelectorAll('[data-pattern-grid]').forEach((grid) => toggle(grid, grid.dataset.patternGrid === type.value));
        rotatingFields.forEach((field) => {
            const wrapper = field.closest('div:not(.relative)') ?? field;
            wrapper.hidden = type.value !== 'rotating';
            field.disabled = type.value !== 'rotating';
        });

        const cycle = Math.max(2, Number.parseInt(length.value, 10) || 7);
        form.querySelectorAll('[data-pattern-grid="rotating"] [data-pattern-row]').forEach((row) => {
            const visible = type.value === 'rotating' && Number(row.dataset.rowIndex) < cycle;
            toggle(row, visible);
        });
    };

    form.addEventListener('change', (event) => {
        const row = event.target.closest('[data-pattern-row]');

        if (row) {
            row.querySelector('[data-rest-label]').hidden = row.querySelector('input:checked') !== null;
        }
    });

    type.addEventListener('change', sync);
    length.addEventListener('input', sync);
    sync();
}

function initScopeForm(form) {
    const type = form.querySelector('[data-scope-type]');
    const sync = () => form.querySelectorAll('[data-scope-target]').forEach((target) => toggle(target, target.dataset.scopeTarget === type.value));
    type.addEventListener('change', sync);
    sync();
}

function initScheduleChangeForm(form) {
    const type = form.querySelector('[data-change-type]');
    const sync = () => form.querySelectorAll('[data-change-field]').forEach((field) => toggle(field, field.dataset.changeField === type.value));
    type.addEventListener('change', sync);
    sync();
}

export function initScheduleForms() {
    document.querySelectorAll('form[data-shift-form]').forEach(initShiftForm);
    document.querySelectorAll('form[data-work-schedule-form]').forEach(initWorkScheduleForm);
    document.querySelectorAll('form[data-scope-form]').forEach(initScopeForm);
    document.querySelectorAll('form[data-schedule-change-form]').forEach(initScheduleChangeForm);
}
