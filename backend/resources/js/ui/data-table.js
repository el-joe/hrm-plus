/**
 * Data tables (x-table => [data-table]).
 *
 * Sorting and pagination are server-side and plain links (x-table.th sort="…",
 * x-pagination), so they work without JS. This module adds:
 *
 * - Row links: <tr data-href="…"> navigates on click (ignored when the click is
 *   on a link, button, input or inside a form) and on Enter.
 * - Row selection for bulk actions:
 *     <input type="checkbox" data-table-select-all>       in the header
 *     <input type="checkbox" data-table-select value="id"> in each row
 *   keeps aria-selected on the rows and dispatches "table:selection" on the
 *   [data-table] element with {detail: {ids}}. Only render these when a bulk
 *   action actually exists.
 */
import { on } from '../lib/dom';

const INTERACTIVE = 'a, button, input, select, textarea, label, form, summary';

function selection(table) {
    const boxes = [...table.querySelectorAll('[data-table-select]')];
    const ids = boxes.filter((box) => box.checked).map((box) => box.value);
    const master = table.querySelector('[data-table-select-all]');

    boxes.forEach((box) => box.closest('tr')?.setAttribute('aria-selected', String(box.checked)));

    if (master) {
        master.checked = boxes.length > 0 && ids.length === boxes.length;
        master.indeterminate = ids.length > 0 && ids.length < boxes.length;
    }

    table.dispatchEvent(new CustomEvent('table:selection', { bubbles: true, detail: { ids } }));
}

export function initDataTables() {
    on('click', '[data-table] tr[data-href]', (event, row) => {
        if (event.target.closest(INTERACTIVE) || window.getSelection()?.toString()) {
            return;
        }

        if (event.metaKey || event.ctrlKey) {
            window.open(row.dataset.href, '_blank', 'noopener');
        } else {
            window.location.assign(row.dataset.href);
        }
    });

    on('keydown', '[data-table] tr[data-href]', (event, row) => {
        if (event.key === 'Enter' && event.target === row) {
            window.location.assign(row.dataset.href);
        }
    });

    document.querySelectorAll('[data-table] tr[data-href]').forEach((row) => {
        row.tabIndex = 0;
        row.classList.add('cursor-pointer');
    });

    on('change', '[data-table-select-all]', (event, master) => {
        const table = master.closest('[data-table]');
        table.querySelectorAll('[data-table-select]').forEach((box) => {
            box.checked = master.checked;
        });
        selection(table);
    });

    on('change', '[data-table-select]', (event, box) => selection(box.closest('[data-table]')));
}
