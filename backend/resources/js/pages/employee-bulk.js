/**
 * Bulk bar of the employee directory (admin/employees/_bulk-bar.blade.php).
 * Shows how many rows are ticked (from the data-table "table:selection"
 * event) and only the target field the chosen action needs. The form itself
 * works without JavaScript.
 */
export function initEmployeeBulk() {
    const form = document.querySelector('[data-bulk-form]');

    if (!form) {
        return;
    }

    const count = form.querySelector('[data-bulk-count]');
    const action = form.querySelector('[data-bulk-action]');
    const targets = [...form.querySelectorAll('[data-bulk-target]')];

    const showTarget = () => {
        targets.forEach((target) => {
            target.hidden = !target.dataset.bulkTarget.split(' ').includes(action.value);
        });
    };

    document.addEventListener('table:selection', (event) => {
        if (count) {
            count.textContent = String(event.detail.ids.length);
        }
    });

    action.addEventListener('change', showTarget);
    showTarget();
}
