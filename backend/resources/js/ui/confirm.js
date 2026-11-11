/**
 * Confirmation before destructive actions (x-confirm-dialog in every layout).
 *
 * Declarative — on a <form>, a submit <button>, or a link:
 *   <form method="POST" action="…" data-confirm="Delete this role?"
 *         data-confirm-title="Delete: Recruiter" data-confirm-label="Delete" data-confirm-tone="danger">
 *
 * Programmatic:
 *   if (await confirmDialog({ message: '…', tone: 'warning' })) { … }
 *
 * Without JavaScript the form simply submits; the server must still authorise
 * and validate every destructive request.
 */
import { on } from '../lib/dom';
import { t } from '../lib/i18n';
import { openModal } from './modal';

const TONES = {
    danger: { wrap: 'bg-feedback-danger-bg text-feedback-danger-solid', icon: 'alert-triangle' },
    warning: { wrap: 'bg-feedback-warning-bg text-feedback-warning-solid', icon: 'alert-triangle' },
    info: { wrap: 'bg-feedback-info-bg text-feedback-info-solid', icon: 'info' },
};

const ACCEPT_CLASSES = {
    danger: ['bg-action-danger-default', 'hover:bg-action-danger-hover', 'active:bg-action-danger-pressed'],
    other: ['bg-action-primary-default', 'hover:bg-action-primary-hover', 'active:bg-action-primary-pressed'],
};

/**
 * @param {{message: string, title?: string, confirmLabel?: string, tone?: 'danger'|'warning'|'info'}} options
 * @returns {Promise<boolean>}
 */
export function confirmDialog({ message, title, confirmLabel, tone = 'danger' }) {
    const dialog = document.querySelector('[data-confirm-dialog]');

    if (!(dialog instanceof HTMLDialogElement)) {
        return Promise.resolve(window.confirm(message));
    }

    const titleElement = dialog.querySelector('[data-confirm-title]');
    const accept = dialog.querySelector('[data-confirm-accept]');
    const acceptLabel = accept.querySelector('span:last-of-type');
    const wrap = dialog.querySelector('[data-confirm-icon-wrap]');
    const toneConfig = TONES[tone] ?? TONES.danger;

    titleElement.dataset.default ??= titleElement.textContent;
    acceptLabel.dataset.default ??= acceptLabel.textContent;

    titleElement.textContent = title || titleElement.dataset.default;
    acceptLabel.textContent = confirmLabel || acceptLabel.dataset.default || t('confirm');
    dialog.querySelector('[data-confirm-message]').textContent = message;

    wrap.className = wrap.className.replace(/\b(bg|text)-feedback-\S+/g, '').trim();
    wrap.classList.add(...toneConfig.wrap.split(' '));

    accept.classList.remove(...ACCEPT_CLASSES.danger, ...ACCEPT_CLASSES.other);
    accept.classList.add(...(tone === 'danger' ? ACCEPT_CLASSES.danger : ACCEPT_CLASSES.other));

    return new Promise((resolve) => {
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
        dialog.returnValue = '';
        openModal(dialog);
        dialog.querySelector('[data-confirm-cancel]')?.focus();
    });
}

const optionsFrom = (element) => ({
    message: element.dataset.confirm,
    title: element.dataset.confirmTitle,
    confirmLabel: element.dataset.confirmLabel,
    tone: element.dataset.confirmTone || 'danger',
});

export function initConfirm() {
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        const submitter = event.submitter;
        const source = submitter?.dataset.confirm ? submitter : form.dataset.confirm ? form : null;

        if (!source || form.dataset.confirmed !== undefined) {
            delete form.dataset.confirmed;

            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        if (await confirmDialog(optionsFrom(source))) {
            form.dataset.confirmed = '';
            form.requestSubmit(submitter ?? undefined);
        }
    }, true);

    on('click', 'a[data-confirm]', async (event, link) => {
        event.preventDefault();

        if (await confirmDialog(optionsFrom(link))) {
            window.location.assign(link.href);
        }
    });
}
