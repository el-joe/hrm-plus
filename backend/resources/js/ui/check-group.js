/**
 * "Select all" checkboxes and live counters.
 *
 *   <input type="checkbox" data-check-all="module-payroll">          controls …
 *   <input type="checkbox" data-check-group="module-payroll" …>       … these.
 *   <p data-check-count="permissions" data-template=":count selected"></p>
 *   <input type="checkbox" data-check-counted="permissions" …>        counted.
 *
 * The master shows the indeterminate state when only some are checked.
 * Masters and counters should be rendered hidden with data-js-only.
 */
import { on } from '../lib/dom';

const members = (group) => [...document.querySelectorAll(`[data-check-group="${CSS.escape(group)}"]`)].filter((box) => !box.disabled);

function syncMaster(master) {
    const boxes = members(master.dataset.checkAll);
    const checked = boxes.filter((box) => box.checked).length;

    master.checked = boxes.length > 0 && checked === boxes.length;
    master.indeterminate = checked > 0 && checked < boxes.length;
    master.disabled = boxes.length === 0;
}

function syncCounters() {
    document.querySelectorAll('[data-check-count]').forEach((counter) => {
        const name = CSS.escape(counter.dataset.checkCount);
        const count = document.querySelectorAll(`[data-check-counted="${name}"]:checked`).length;
        counter.textContent = (counter.dataset.template ?? ':count').replace(':count', String(count));
    });
}

export function initCheckGroups() {
    document.querySelectorAll('[data-check-all]').forEach(syncMaster);
    syncCounters();

    on('change', '[data-check-all]', (event, master) => {
        members(master.dataset.checkAll).forEach((box) => {
            box.checked = master.checked;
        });
        master.indeterminate = false;
        syncCounters();
    });

    on('change', '[data-check-group], [data-check-counted]', (event, box) => {
        if (box.dataset.checkGroup) {
            document.querySelectorAll(`[data-check-all="${CSS.escape(box.dataset.checkGroup)}"]`).forEach(syncMaster);
        }
        syncCounters();
    });
}
