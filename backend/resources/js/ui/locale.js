/**
 * Language switch (partials/locale-switcher.blade.php).
 *
 * The switch is a normal POST form to locale.update and works without JS. This
 * module only adds: a <select> inside [data-locale-switcher] submits on change,
 * and the submit button shows a busy state while the page reloads.
 */
import { on } from '../lib/dom';

export function initLocale() {
    on('change', '[data-locale-switcher] select', (event, select) => select.form?.requestSubmit());

    on('submit', '[data-locale-switcher]', (event, form) => {
        form.querySelector('button')?.setAttribute('aria-busy', 'true');
    });
}
