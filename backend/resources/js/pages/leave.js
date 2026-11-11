/**
 * Leave screens.
 *
 * form[data-leave-form] (admin "request on behalf", portal "My leaves"): the day-part
 * select only offers the parts the chosen leave type allows (options carry
 * data-units), the end date is hidden for partial days and the hours field
 * only shows for hourly leave. Without JavaScript every field stays visible
 * and the server validates the combination.
 *
 * [data-leave-reject] (admin requests queue): opens the shared reject modal for the
 * row instead of following the link to the request page.
 */
import { openModal } from '../ui/modal';

function syncLeaveForm(form) {
    const type = form.querySelector('[name="leave_type_id"]');
    const part = form.querySelector('[name="day_part"]');

    if (!type || !part) {
        return;
    }

    const unit = type.selectedOptions[0]?.dataset.unit || 'day';

    part.querySelectorAll('option').forEach((option) => {
        const allowed = (option.dataset.units || '').split(',').includes(unit);
        option.hidden = !allowed;
        option.disabled = !allowed;
    });

    if (part.selectedOptions[0]?.disabled) {
        part.value = 'full';
    }

    const value = part.value || 'full';
    const endField = form.querySelector('[data-leave-end]');
    const hoursField = form.querySelector('[data-leave-hours]');

    if (endField) {
        endField.hidden = value !== 'full';
    }

    if (hoursField) {
        hoursField.hidden = value !== 'hours';
    }

    const attachmentHint = form.querySelector('[data-leave-attachment-hint]');

    if (attachmentHint) {
        attachmentHint.textContent = type.selectedOptions[0]?.dataset.attachmentHint || '';
    }
}

export function initLeaveForms() {
    document.querySelectorAll('form[data-leave-form]').forEach((form) => {
        form.addEventListener('change', (event) => {
            if (event.target.matches('[name="leave_type_id"], [name="day_part"]')) {
                syncLeaveForm(form);
            }
        });
        syncLeaveForm(form);
    });
}

export function initLeaveReject() {
    const modal = document.getElementById('leave-reject-modal');

    if (!modal) {
        return;
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-leave-reject]');

        if (!trigger || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        modal.querySelector('form').action = trigger.dataset.leaveReject;
        modal.querySelector('[data-leave-reject-subject]').textContent = trigger.dataset.subject || '';
        openModal('leave-reject-modal', trigger);
    });
}
