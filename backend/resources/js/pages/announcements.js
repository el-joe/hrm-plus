/**
 * Announcement form (Phase 8b): a minimal formatting toolbar for the body textareas.
 *
 * [data-format-toolbar="field"] holds buttons with data-format="strong|em|ul|a". A button wraps the
 * selected text of the textarea in the allowed tag (a list turns each selected line into an item; a
 * link asks for the address). The server sanitises the HTML anyway (allow-list), so this is only a
 * convenience: without JavaScript the toolbar stays hidden and the textarea accepts the same tags.
 */
function wrap(textarea, before, after, transform = (text) => text) {
    const { selectionStart: start, selectionEnd: end, value } = textarea;
    const selected = value.slice(start, end);
    const replacement = before + transform(selected) + after;

    textarea.setRangeText(replacement, start, end, 'end');
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function escapeAttribute(value) {
    return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

export function initAnnouncementForm() {
    document.querySelectorAll('[data-format-toolbar]').forEach((toolbar) => {
        const textarea = document.getElementById(toolbar.dataset.formatToolbar);

        if (!textarea) {
            return;
        }

        toolbar.addEventListener('click', (event) => {
            const button = event.target.closest('[data-format]');

            if (!button) {
                return;
            }

            event.preventDefault();
            const format = button.dataset.format;

            if (format === 'ul') {
                wrap(textarea, '<ul>\n', '\n</ul>', (text) => (text || '…')
                    .split('\n')
                    .filter((line) => line.trim() !== '')
                    .map((line) => `<li>${line.trim()}</li>`)
                    .join('\n'));

                return;
            }

            if (format === 'a') {
                const url = window.prompt(button.dataset.linkPrompt || 'https://', 'https://');

                if (!url || !/^(https?:\/\/|mailto:)/i.test(url.trim())) {
                    return;
                }

                wrap(textarea, `<a href="${escapeAttribute(url.trim())}">`, '</a>', (text) => text || url.trim());

                return;
            }

            wrap(textarea, `<${format}>`, `</${format}>`);
        });
    });
}
