/**
 * Light / dark theme.
 *
 * html[data-theme] is set before first paint by partials/head.blade.php from
 * localStorage('hrm-theme') or, when nothing is stored, prefers-color-scheme.
 * [data-theme-toggle] buttons switch and remember the choice. While nothing is
 * stored, the page follows OS changes live.
 */
import { on, storage } from '../lib/dom';

const root = document.documentElement;
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

export const currentTheme = () => (root.dataset.theme === 'dark' ? 'dark' : 'light');

/**
 * @param {'light'|'dark'} theme
 * @param {boolean} remember
 */
export function setTheme(theme, remember = true) {
    root.dataset.theme = theme;

    if (remember) {
        storage.set('hrm-theme', theme);
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.setAttribute('aria-pressed', String(theme === 'dark')));
    document.dispatchEvent(new CustomEvent('theme:change', { detail: { theme } }));
}

export function initTheme() {
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.setAttribute('aria-pressed', String(currentTheme() === 'dark')));

    on('click', '[data-theme-toggle]', () => setTheme(currentTheme() === 'dark' ? 'light' : 'dark'));

    prefersDark.addEventListener('change', (event) => {
        if (storage.get('hrm-theme') === null) {
            setTheme(event.matches ? 'dark' : 'light', false);
        }
    });
}
