/**
 * Dropdown menus on <details data-dropdown> (x-dropdown). Native open/close
 * works without JS; this adds: one menu open at a time, close on outside click,
 * on Escape (focus back to the trigger) and after choosing an item, and arrow-key
 * movement between items.
 */
import { on } from '../lib/dom';

const openMenus = () => [...document.querySelectorAll('details[data-dropdown][open]')];

function close(details, restoreFocus = false) {
    details.open = false;

    if (restoreFocus) {
        details.querySelector('summary')?.focus();
    }
}

export function initDropdowns() {
    document.addEventListener('toggle', (event) => {
        const details = event.target;

        if (details instanceof HTMLDetailsElement && details.matches('[data-dropdown]') && details.open) {
            openMenus().filter((other) => other !== details).forEach((other) => close(other));
        }
    }, true);

    document.addEventListener('click', (event) => {
        openMenus().forEach((details) => {
            if (!details.contains(event.target)) {
                close(details);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        const details = event.target instanceof Element ? event.target.closest('details[data-dropdown][open]') : null;

        if (!details) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close(details, true);

            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const items = [...details.querySelectorAll('[role="menuitem"]')];
            const index = items.indexOf(document.activeElement);
            const next = event.key === 'ArrowDown' ? index + 1 : index - 1;
            items[(next + items.length) % items.length]?.focus();
        }
    });

    on('click', 'details[data-dropdown] a[role="menuitem"]', (event, item) => close(item.closest('details')));
}
