/**
 * Client-side tabs (x-tabs with items carrying 'id'). Implements the WAI-ARIA
 * tabs pattern: click, Arrow keys (mirrored in RTL), Home / End. A URL hash
 * equal to a panel id selects that tab on load. Without JS all panels show.
 */
import { enhance, isRtl } from '../lib/dom';

const ACTIVE = ['border-action-primary-default', 'text-text-link'];
const INACTIVE = ['border-transparent', 'text-text-secondary', 'hover:text-text-primary', 'hover:border-border-strong'];

function select(tabs, tab, focus = false) {
    tabs.forEach((other) => {
        const active = other === tab;
        other.setAttribute('aria-selected', String(active));
        other.tabIndex = active ? 0 : -1;
        other.classList.remove(...(active ? INACTIVE : ACTIVE));
        other.classList.add(...(active ? ACTIVE : INACTIVE));

        const panel = document.getElementById(other.getAttribute('aria-controls'));

        if (panel) {
            panel.hidden = !active;
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', other.id);
            panel.tabIndex = 0;
        }
    });

    if (focus) {
        tab.focus();
    }
}

function initTabList(container) {
    const tabs = [...container.querySelectorAll('[data-tab]')];

    if (tabs.length === 0) {
        return;
    }

    const fromHash = tabs.find((tab) => `#${tab.getAttribute('aria-controls')}` === window.location.hash);
    select(tabs, fromHash ?? tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') ?? tabs[0]);

    container.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-tab]');

        if (tab) {
            select(tabs, tab);
        }
    });

    container.addEventListener('keydown', (event) => {
        const index = tabs.indexOf(document.activeElement);

        if (index === -1) {
            return;
        }

        const forward = isRtl() ? 'ArrowLeft' : 'ArrowRight';
        const backward = isRtl() ? 'ArrowRight' : 'ArrowLeft';
        const targets = {
            [forward]: tabs[(index + 1) % tabs.length],
            [backward]: tabs[(index - 1 + tabs.length) % tabs.length],
            Home: tabs[0],
            End: tabs[tabs.length - 1],
        };

        if (targets[event.key]) {
            event.preventDefault();
            select(tabs, targets[event.key], true);
        }
    });
}

export function initTabs(root = document) {
    enhance('[data-tabs]', initTabList, root);
}
