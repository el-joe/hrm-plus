/**
 * Modals on native <dialog data-modal> (x-modal).
 *
 *   <x-button type="button" data-modal-open="invite-modal">…</x-button>
 *   <x-modal id="invite-modal" :title="…">…</x-modal>
 *
 * Closing: [data-modal-close], Escape (native), a click on the backdrop, or a
 * <form method="dialog">. Focus returns to the element that opened it.
 * Programmatic: openModal('invite-modal'), closeModal('invite-modal').
 */
import { on } from '../lib/dom';

const openers = new WeakMap();

/**
 * @param {string|HTMLDialogElement} target
 * @param {Element|null} opener
 */
export function openModal(target, opener = document.activeElement) {
    const dialog = typeof target === 'string' ? document.getElementById(target) : target;

    if (!(dialog instanceof HTMLDialogElement) || dialog.open) {
        return dialog;
    }

    openers.set(dialog, opener);
    dialog.showModal();
    dialog.querySelector('[autofocus]')?.focus();

    return dialog;
}

export function closeModal(target, returnValue = '') {
    const dialog = typeof target === 'string' ? document.getElementById(target) : target;

    if (dialog instanceof HTMLDialogElement && dialog.open) {
        dialog.close(returnValue);
    }
}

function wire(dialog) {
    if (dialog.dataset.modalWired !== undefined) {
        return;
    }

    dialog.dataset.modalWired = '';

    dialog.addEventListener('close', () => {
        const opener = openers.get(dialog);

        if (opener instanceof HTMLElement && document.contains(opener)) {
            opener.focus();
        }
    });

    // A click whose target is the <dialog> itself landed on the backdrop.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close('cancel');
        }
    });
}

export function initModals() {
    document.querySelectorAll('dialog[data-modal]').forEach(wire);

    on('click', '[data-modal-open]', (event, trigger) => {
        event.preventDefault();
        const dialog = document.getElementById(trigger.dataset.modalOpen);

        if (dialog) {
            wire(dialog);
            openModal(dialog, trigger);
        }
    });

    on('click', '[data-modal-close]', (event, trigger) => {
        event.preventDefault();
        trigger.closest('dialog')?.close('cancel');
    });
}
