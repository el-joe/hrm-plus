/**
 * Notification bell (partials/notification-bell): refreshes the unread badge every minute while the
 * tab is visible, from the area's JSON endpoint (data-count-url). The bell itself works without JS.
 */
import { http } from '../lib/http';

const INTERVAL = 60_000;

export function initNotificationBell() {
    const bell = document.querySelector('[data-notification-bell]');
    const badge = bell?.querySelector('[data-bell-count]');

    if (!bell || !badge || !bell.dataset.countUrl) {
        return;
    }

    const refresh = async () => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        try {
            const { count } = await http.get(bell.dataset.countUrl);
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.toggle('hidden', count === 0);
        } catch {
            // A failed poll keeps the last known count.
        }
    };

    window.setInterval(refresh, INTERVAL);
    document.addEventListener('visibilitychange', refresh);
}
