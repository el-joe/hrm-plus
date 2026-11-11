/**
 * Global search shell in the admin top bar (partials/global-search.blade.php).
 *
 * Matches the typed text against the pages in the admin's own sidebar (so it
 * never offers a page the admin cannot open) and, when the form carries
 * data-search-action, offers a full search there (currently: admin users).
 * Keyboard: "/" or Ctrl/Cmd+K focuses the field, Arrow keys move, Enter opens,
 * Escape closes.
 */
import { on } from '../lib/dom';

const normalise = (text) => text.toLocaleLowerCase().normalize('NFKD').replace(/[ً-ٰٟ]/g, '').replace(/[أإآ]/g, 'ا').trim();

function pages() {
    return [...document.querySelectorAll('[data-admin-sidebar] [data-nav-item]')].map((link) => ({
        label: link.querySelector('[data-sidebar-label]')?.textContent.trim() ?? link.textContent.trim(),
        url: link.href,
        icon: link.querySelector('svg')?.outerHTML ?? '',
    }));
}

function initSearch(form) {
    const input = form.querySelector('[data-global-search-input]');
    const list = form.querySelector('[data-global-search-results]');
    const strings = form.querySelector('[data-global-search-strings]')?.dataset ?? {};
    const action = form.dataset.searchAction;
    let options = [];
    let active = -1;

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };

    const highlight = (index) => {
        const items = [...list.querySelectorAll('[role="option"]')];
        active = items.length ? (index + items.length) % items.length : -1;
        items.forEach((item, position) => {
            const selected = position === active;
            item.setAttribute('aria-selected', String(selected));
            item.classList.toggle('bg-action-neutral-hover', selected);
        });

        if (active >= 0) {
            input.setAttribute('aria-activedescendant', items[active].id);
            items[active].scrollIntoView({ block: 'nearest' });
        }
    };

    const render = () => {
        const term = input.value.trim();

        if (term === '') {
            close();

            return;
        }

        const needle = normalise(term);
        options = pages().filter((page) => normalise(page.label).includes(needle));

        if (action) {
            const url = new URL(action, window.location.href);
            url.searchParams.set('search', term);
            options.push({ label: (strings.searchAdmins ?? '').replace(':term', term), url: url.toString(), icon: '' });
        }

        list.replaceChildren(...(options.length ? options : [{ label: strings.noResults ?? '', url: null }]).map((option, index) => {
            const item = document.createElement('li');

            if (!option.url) {
                item.className = 'px-3 py-2 text-body-sm text-text-tertiary';
                item.textContent = option.label;

                return item;
            }

            item.id = `global-search-option-${index}`;
            item.setAttribute('role', 'option');
            item.className = 'flex cursor-pointer items-center gap-2.5 rounded-md px-3 py-2 text-body-md text-text-primary hover:bg-action-neutral-hover';
            item.dataset.url = option.url;
            item.innerHTML = option.icon;
            item.append(document.createTextNode(option.label));
            item.querySelector('svg')?.classList.add('text-text-tertiary');

            return item;
        }));

        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        highlight(0);
    };

    input.addEventListener('input', render);
    input.addEventListener('focus', render);

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (list.hidden) {
                render();
            } else {
                highlight(active + (event.key === 'ArrowDown' ? 1 : -1));
            }
        } else if (event.key === 'Escape') {
            close();
        }
    });

    form.addEventListener('submit', (event) => {
        const chosen = list.querySelectorAll('[role="option"]')[active];

        if (chosen) {
            event.preventDefault();
            window.location.assign(chosen.dataset.url);
        } else if (!action) {
            event.preventDefault();
        }
    });

    list.addEventListener('mousedown', (event) => event.preventDefault());
    on('click', '[role="option"]', (event, item) => window.location.assign(item.dataset.url), list);

    input.addEventListener('blur', () => setTimeout(close, 100));

    document.addEventListener('keydown', (event) => {
        const typing = event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable="true"]');

        if ((event.key === '/' && !typing) || ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k')) {
            if (form.offsetParent !== null) {
                event.preventDefault();
                input.focus();
            }
        }
    });
}

export function initGlobalSearch() {
    document.querySelectorAll('[data-global-search]').forEach(initSearch);
}
