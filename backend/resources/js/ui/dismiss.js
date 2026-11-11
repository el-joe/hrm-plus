/**
 * [data-dismiss="<selector>"] removes its closest ancestor matching the selector
 * (e.g. the close button of a dismissible x-alert).
 */
import { on } from '../lib/dom';

export function initDismiss() {
    on('click', '[data-dismiss]', (event, button) => {
        button.closest(button.dataset.dismiss)?.remove();
    });
}
