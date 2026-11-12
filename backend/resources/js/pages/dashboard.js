/**
 * "Customise dashboard" (admin/dashboard-customise, form[data-dashboard-layout]).
 *
 * Without JavaScript the admin types a position number per widget. With it, the up / down buttons
 * move a row and renumber every position input, so the saved order matches what is on screen.
 */
import { on } from '../lib/dom';

function renumber(list) {
    list.querySelectorAll('[data-layout-item]').forEach((item, index) => {
        const input = item.querySelector('[data-layout-position]');

        if (input) {
            input.value = String(index + 1);
        }
    });
}

export function initDashboardLayout() {
    on('click', '[data-dashboard-layout] [data-layout-move]', (event, button) => {
        const item = button.closest('[data-layout-item]');
        const list = item?.closest('[data-layout-list]');

        if (!item || !list) {
            return;
        }

        if (button.dataset.layoutMove === 'up' && item.previousElementSibling) {
            item.previousElementSibling.before(item);
        } else if (button.dataset.layoutMove === 'down' && item.nextElementSibling) {
            item.nextElementSibling.after(item);
        }

        renumber(list);
        button.focus();
    });
}
