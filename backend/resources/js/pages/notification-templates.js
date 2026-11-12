/**
 * Notification template editor (admin/notifications/templates/edit): clicking a placeholder chip
 * ([data-placeholder]) inserts "{{name}}" at the cursor of the last focused field (subject or body).
 * Without JS the chips are plain text to copy.
 */
export function initNotificationTemplates() {
    const form = document.querySelector('[data-template-editor]');

    if (!form) {
        return;
    }

    let target = form.querySelector('[name="body"]');

    form.querySelectorAll('[name="subject"], [name="body"]').forEach((field) => {
        field.addEventListener('focus', () => {
            target = field;
        });
    });

    document.querySelectorAll('[data-placeholder]').forEach((chip) => {
        chip.addEventListener('click', (event) => {
            event.preventDefault();

            if (!target || target.disabled) {
                return;
            }

            const text = `{{${chip.dataset.placeholder}}}`;
            const start = target.selectionStart ?? target.value.length;
            const end = target.selectionEnd ?? target.value.length;
            target.setRangeText(text, start, end, 'end');
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.focus();
        });
    });
}
