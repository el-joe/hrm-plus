/**
 * Recruitment pipeline board (admin/recruitment/vacancies/_board.blade.php).
 *
 * - Drag a card ([data-application-card][draggable]) onto another column's list.
 * - Keyboard / no-JS alternative: the stage select + "Move" button on each card is a
 *   plain PATCH form; with JavaScript it is sent with fetch and the card moves.
 *
 * The server is the source of truth: the card moves optimistically and goes back to
 * where it was when the request fails (validation message shown as a toast).
 */
import { http } from '../lib/http';
import { toast } from '../ui/toast';

function updateCounts(board) {
    board.querySelectorAll('[data-stage-column]').forEach((column) => {
        const count = column.querySelector('[data-stage-count]');

        if (count) {
            count.textContent = String(column.querySelectorAll('[data-application-card]').length);
        }
    });
}

function listFor(board, stageId) {
    return board.querySelector(`[data-stage-list][data-stage-id="${stageId}"]`);
}

async function moveCard(board, card, stageId) {
    const origin = card.parentElement;
    const nextSibling = card.nextElementSibling;
    const target = listFor(board, stageId);

    if (!target || origin === target) {
        return;
    }

    target.appendChild(card);
    updateCounts(board);
    card.setAttribute('aria-busy', 'true');

    try {
        const body = await http.patch(card.dataset.moveUrl, { stage_id: Number(stageId) });
        const select = card.querySelector('[data-move-select]');

        if (select) {
            select.value = String(body.application.stage_id);
        }

        if (body.application.status !== 'active') {
            card.removeAttribute('draggable');
            card.querySelector('[data-move-form]')?.remove();
        }

        toast(body.message, { tone: 'success' });
    } catch (error) {
        origin.insertBefore(card, nextSibling);
        updateCounts(board);
        const select = card.querySelector('[data-move-select]');

        if (select) {
            select.value = origin.dataset.stageId;
        }

        const message = Object.values(error.errors ?? {})[0]?.[0] ?? error.message;
        toast(message, { tone: 'danger' });
    } finally {
        card.removeAttribute('aria-busy');
        card.querySelector('[data-move-select]')?.focus({ preventScroll: true });
    }
}

export function initPipelineBoard() {
    document.querySelectorAll('[data-pipeline-board]').forEach((board) => {
        let dragged = null;

        board.addEventListener('dragstart', (event) => {
            const card = event.target.closest('[data-application-card][draggable="true"]');

            if (!card) {
                return;
            }

            dragged = card;
            card.classList.add('opacity-50');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.applicationId);
        });

        board.addEventListener('dragend', () => {
            dragged?.classList.remove('opacity-50');
            dragged = null;
            board.querySelectorAll('[data-stage-list]').forEach((list) => list.classList.remove('bg-action-primary-subtle'));
        });

        board.addEventListener('dragover', (event) => {
            const list = event.target.closest('[data-stage-list]');

            if (dragged && list) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                board.querySelectorAll('[data-stage-list]').forEach((item) => item.classList.toggle('bg-action-primary-subtle', item === list));
            }
        });

        board.addEventListener('drop', (event) => {
            const list = event.target.closest('[data-stage-list]');

            if (!dragged || !list) {
                return;
            }

            event.preventDefault();
            list.classList.remove('bg-action-primary-subtle');
            moveCard(board, dragged, list.dataset.stageId);
        });

        board.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-move-form]');
            const card = form?.closest('[data-application-card]');

            if (!form || !card) {
                return;
            }

            event.preventDefault();
            moveCard(board, card, form.querySelector('[data-move-select]').value);
        });
    });
}
