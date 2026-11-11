/**
 * Payroll run page (admin/payroll/runs/show): while a queued calculation runs
 * ([data-payroll-progress] = progress URL) poll it every 3 seconds, update the
 * bar and reload the page when the run leaves "calculating". Without JS the
 * card offers a reload link.
 */
import { http } from '../lib/http';
import { t } from '../lib/i18n';

export function initPayrollRunProgress() {
    const card = document.querySelector('[data-payroll-progress]');

    if (!card) {
        return;
    }

    const url = card.getAttribute('data-payroll-progress');
    const bar = card.querySelector('[data-progress-bar]');
    const fill = card.querySelector('[data-progress-fill]');
    const text = card.querySelector('[data-progress-text]');

    const poll = async () => {
        try {
            const progress = await http.get(url);

            if (progress.status !== 'calculating') {
                window.location.reload();

                return;
            }

            fill.style.inlineSize = `${progress.percent}%`;
            bar.setAttribute('aria-valuenow', String(progress.percent));
            text.textContent = t('payroll.progress', { done: progress.done, total: progress.total });
        } catch {
            // Keep polling; a transient error must not stop the progress display.
        }

        window.setTimeout(poll, 3000);
    };

    window.setTimeout(poll, 3000);
}
