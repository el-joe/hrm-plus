/**
 * Toasts (x-toast-region / x-toast).
 *
 *   import { toast } from './ui/toast';
 *   toast('Saved.', { tone: 'success' });
 *
 * Server flashes (session 'status' / 'error') are already rendered as toasts;
 * this module adds the close button and auto-dismiss to them.
 * Declarative trigger: <button data-toast-trigger="success" data-toast-message="...">.
 */
import { on } from '../lib/dom';

const TONE_COLORS = {
    success: 'text-feedback-success-solid',
    warning: 'text-feedback-warning-solid',
    danger: 'text-feedback-danger-solid',
    info: 'text-feedback-info-solid',
};

const DEFAULT_TIMEOUT = 5000;

function region() {
    return document.querySelector('[data-toast-region]');
}

export function dismissToast(element) {
    if (!element || element.dataset.leaving !== undefined) {
        return;
    }

    element.dataset.leaving = '';
    setTimeout(() => element.remove(), 160);
}

function arm(element, timeout) {
    const close = element.querySelector('[data-toast-close]');

    if (close) {
        close.hidden = false;
    }

    // Danger toasts stay until dismissed so they are not missed.
    if (timeout > 0 && element.dataset.tone !== 'danger') {
        let timer = setTimeout(() => dismissToast(element), timeout);

        element.addEventListener('mouseenter', () => clearTimeout(timer));
        element.addEventListener('mouseleave', () => {
            timer = setTimeout(() => dismissToast(element), timeout / 2);
        });
    }
}

/**
 * @param {string} message
 * @param {{tone?: 'success'|'warning'|'danger'|'info', title?: string|null, timeout?: number}} options
 * @returns {HTMLElement|null}
 */
export function toast(message, { tone = 'info', title = null, timeout = DEFAULT_TIMEOUT } = {}) {
    const template = document.querySelector('[data-toast-template]');
    const target = region();

    if (!template || !target) {
        return null;
    }

    const element = template.content.firstElementChild.cloneNode(true);
    const icon = element.querySelector('[data-toast-icon]');

    element.dataset.tone = tone;
    element.setAttribute('role', tone === 'danger' ? 'alert' : 'status');
    icon.classList.remove(...Object.values(TONE_COLORS));
    icon.classList.add(TONE_COLORS[tone] ?? TONE_COLORS.info);
    icon.querySelectorAll('[data-toast-icon-tone]').forEach((svg) => {
        svg.toggleAttribute('hidden', svg.dataset.toastIconTone !== tone);
    });

    const titleElement = element.querySelector('[data-toast-title]');
    titleElement.textContent = title ?? '';
    titleElement.hidden = !title;
    element.querySelector('[data-toast-message]').textContent = message;

    target.append(element);
    arm(element, timeout);

    return element;
}

export function initToasts() {
    region()?.querySelectorAll('[data-toast]').forEach((element) => arm(element, DEFAULT_TIMEOUT));

    on('click', '[data-toast-close]', (event, button) => dismissToast(button.closest('[data-toast]')));

    on('click', '[data-toast-trigger]', (event, trigger) => {
        toast(trigger.dataset.toastMessage ?? '', { tone: trigger.dataset.toastTrigger || 'info', title: trigger.dataset.toastTitle ?? null });
    });
}
