/**
 * "Mark all present except..." list (admin/attendance/bulk.blade.php).
 * Client-side search, select all / none of the visible rows, applying one
 * exception to every selected row, and showing the time inputs only for
 * the actions that use them (late: check-in; custom times: both). Without
 * JavaScript every control is visible and the form still submits; the
 * server validates everything again.
 */
const TIME_ACTIONS = { late: ['in'], custom_times: ['in', 'out'] };

function syncTimes(row) {
    const action = row.querySelector('[data-bulk-action]')?.value ?? 'present';
    const needs = TIME_ACTIONS[action] ?? [];

    row.querySelectorAll('[data-bulk-in], [data-bulk-out]').forEach((input) => {
        const kind = input.hasAttribute('data-bulk-in') ? 'in' : 'out';
        const field = input.closest('[data-field]') ?? input.parentElement;
        field.hidden = !needs.includes(kind);
        input.required = needs.includes(kind);
    });
}

function changed(box) {
    box.dispatchEvent(new Event('change', { bubbles: true }));
}

export function initAttendanceBulk() {
    const form = document.querySelector('[data-bulk-attendance]');

    if (!form) {
        return;
    }

    const rows = [...form.querySelectorAll('[data-bulk-row]')];
    const visibleRows = () => rows.filter((row) => !row.hidden);
    const checkbox = (row) => row.querySelector('[data-check-group]');

    rows.forEach(syncTimes);

    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-bulk-action]')) {
            syncTimes(event.target.closest('[data-bulk-row]'));
        }
    });

    form.querySelector('[data-bulk-search] input, input[data-bulk-search]')?.addEventListener('input', (event) => {
        const term = event.target.value.trim().toLowerCase();
        rows.forEach((row) => {
            row.hidden = term !== '' && !row.dataset.search.includes(term);
        });
    });

    form.querySelectorAll('[data-bulk-select]').forEach((button) => {
        button.addEventListener('click', () => {
            const value = button.dataset.bulkSelect === 'all';
            visibleRows().forEach((row) => {
                const box = checkbox(row);
                box.checked = value;
                changed(box);
            });
        });
    });

    form.querySelector('[data-bulk-apply]')?.addEventListener('click', () => {
        const select = form.querySelector('[data-bulk-apply-action] select, select[data-bulk-apply-action]');

        visibleRows().filter((row) => checkbox(row).checked).forEach((row) => {
            const action = row.querySelector('[data-bulk-action]');
            action.value = select.value;
            syncTimes(row);
        });
    });
}
