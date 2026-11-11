/**
 * Admin sidebar (layouts/admin.blade.php).
 *
 * - Desktop (lg+): [data-sidebar-collapse] toggles the 80px rail; the choice is
 *   stored in localStorage('hrm-sidebar') and applied before first paint by
 *   partials/head.blade.php (html[data-sidebar="collapsed"]).
 * - Below lg: [data-sidebar-open] opens the off-canvas drawer
 *   (html[data-sidebar-open]); the scrim, [data-sidebar-close] and Escape close it,
 *   and Tab is kept inside the drawer. Without JS the open link targets
 *   #admin-sidebar and CSS :target shows the drawer.
 */
import { focusable, on, storage } from '../lib/dom';

const root = document.documentElement;
const desktop = window.matchMedia('(min-width: 1024px)');

function sidebar() {
    return document.querySelector('[data-admin-sidebar]');
}

function setExpanded(value) {
    document.querySelectorAll('[data-sidebar-open]').forEach((trigger) => trigger.setAttribute('aria-expanded', String(value)));
}

export function openDrawer() {
    root.dataset.sidebarOpen = '';
    setExpanded(true);
    focusable(sidebar())[0]?.focus();
}

export function closeDrawer({ restoreFocus = true } = {}) {
    if (root.dataset.sidebarOpen === undefined) {
        return;
    }

    delete root.dataset.sidebarOpen;
    setExpanded(false);

    if (restoreFocus) {
        document.querySelector('[data-sidebar-open]')?.focus();
    }
}

export function toggleCollapsed() {
    const collapsed = root.dataset.sidebar !== 'collapsed';

    if (collapsed) {
        root.dataset.sidebar = 'collapsed';
    } else {
        delete root.dataset.sidebar;
    }

    storage.set('hrm-sidebar', collapsed ? 'collapsed' : null);
    document.querySelectorAll('[data-sidebar-collapse]').forEach((button) => button.setAttribute('aria-expanded', String(!collapsed)));
}

export function initSidebar() {
    if (!sidebar()) {
        return;
    }

    // Drop a no-JS "#admin-sidebar" target so :target does not keep it open.
    if (window.location.hash === '#admin-sidebar') {
        history.replaceState(null, '', window.location.pathname + window.location.search);
    }

    document.querySelectorAll('[data-sidebar-collapse]').forEach((button) => button.setAttribute('aria-expanded', String(root.dataset.sidebar !== 'collapsed')));

    on('click', '[data-sidebar-collapse]', () => toggleCollapsed());

    on('click', '[data-sidebar-open]', (event) => {
        event.preventDefault();
        openDrawer();
    });

    on('click', '[data-sidebar-close], [data-sidebar-scrim]', (event) => {
        event.preventDefault();
        closeDrawer();
    });

    document.addEventListener('keydown', (event) => {
        if (root.dataset.sidebarOpen === undefined) {
            return;
        }

        if (event.key === 'Escape') {
            closeDrawer();

            return;
        }

        if (event.key === 'Tab') {
            const items = focusable(sidebar());
            const first = items[0];
            const last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    });

    desktop.addEventListener('change', (event) => {
        if (event.matches) {
            closeDrawer({ restoreFocus: false });
        }
    });
}
