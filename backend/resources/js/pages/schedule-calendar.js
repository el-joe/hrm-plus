/**
 * Schedule calendar (admin/scheduling/calendar/index): a cell link opens the
 * "change this date" modal instead of the form page, pre-filled with the
 * employee, the date and the shifts in force. Without JavaScript the link
 * leads to the same form as a page.
 */
import { openModal } from '../ui/modal';

export function initScheduleCalendar() {
    const modal = document.getElementById('calendar-day-modal');

    if (!modal) {
        return;
    }

    document.querySelector('[data-schedule-calendar]')?.addEventListener('click', (event) => {
        const cell = event.target.closest('[data-calendar-cell]');

        if (!cell || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        const shiftIds = (cell.dataset.shiftIds || '').split(',').filter(Boolean);

        modal.querySelectorAll('[data-calendar-field="employee_id"]').forEach((input) => {
            input.value = cell.dataset.employeeId;
        });
        modal.querySelectorAll('[data-calendar-field="date"]').forEach((input) => {
            input.value = cell.dataset.date;
        });
        modal.querySelectorAll('[data-calendar-shift]').forEach((checkbox) => {
            checkbox.checked = shiftIds.includes(checkbox.value);
        });
        modal.querySelector('[data-calendar-modal-subject]').textContent = `${cell.dataset.employeeName} · ${cell.dataset.date}`;
        modal.querySelector('[data-calendar-clear-button]').hidden = cell.dataset.override !== '1';

        openModal('calendar-day-modal', cell);
    });
}
