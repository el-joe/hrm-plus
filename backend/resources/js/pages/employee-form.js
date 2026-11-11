/**
 * Employee placement fields (admin/employees/_placement-fields.blade.php).
 *
 * Selects with data-depends-on="<field>" only offer the options whose
 * data-parent matches the value of that field (options without a parent, such
 * as company-wide departments, stay available). A selected option that no
 * longer fits is cleared. The server validates the combination anyway; this
 * only keeps the lists short.
 */
function syncDependants(form, changedName) {
    form.querySelectorAll(`select[data-depends-on="${changedName}"]`).forEach((select) => {
        const parent = form.querySelector(`[name="${changedName}"]`)?.value ?? '';

        [...select.options].forEach((option) => {
            if (!('parent' in option.dataset)) {
                return;
            }

            const fits = parent === '' || option.dataset.parent === '' || option.dataset.parent === parent;
            option.hidden = !fits;
            option.disabled = !fits;

            if (!fits && option.selected) {
                select.value = '';
            }
        });

        syncDependants(form, select.name);
    });
}

export function initEmployeeForm() {
    document.querySelectorAll('form[data-employee-form]').forEach((form) => {
        ['branch_id', 'department_id'].forEach((name) => syncDependants(form, name));

        form.addEventListener('change', (event) => {
            if (event.target instanceof HTMLSelectElement && form.querySelector(`[data-depends-on="${event.target.name}"]`)) {
                syncDependants(form, event.target.name);
            }
        });
    });
}
