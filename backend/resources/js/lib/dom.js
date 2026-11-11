/**
 * Small DOM helpers shared by the UI modules.
 */

/** @returns {boolean} true when the document is right-to-left. */
export const isRtl = () => document.documentElement.dir === 'rtl';

/**
 * Delegated event listener.
 *
 * @param {string} type        Event type, e.g. 'click'.
 * @param {string} selector    CSS selector matched with closest().
 * @param {(event: Event, element: Element) => void} handler
 * @param {Document|Element} root
 */
export function on(type, selector, handler, root = document) {
    root.addEventListener(type, (event) => {
        const element = event.target instanceof Element ? event.target.closest(selector) : null;

        if (element && root.contains(element)) {
            handler(event, element);
        }
    });
}

const enhanced = new WeakMap();

/**
 * Run `init` once for every element matching `selector` (safe to call again
 * after inserting new markup).
 *
 * @param {string} selector
 * @param {(element: HTMLElement) => void} init
 * @param {ParentNode} root
 */
export function enhance(selector, init, root = document) {
    if (!enhanced.has(init)) {
        enhanced.set(init, new WeakSet());
    }

    const done = enhanced.get(init);

    root.querySelectorAll(selector).forEach((element) => {
        if (!done.has(element)) {
            done.add(element);
            init(element);
        }
    });
}

/**
 * @param {Function} callback
 * @param {number} wait
 */
export function debounce(callback, wait = 300) {
    let timer;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => callback(...args), wait);
    };
}

/** Elements that can take keyboard focus inside `container`. */
export function focusable(container) {
    return [...container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
        .filter((element) => !element.closest('[hidden]') && element.getClientRects().length > 0);
}

/**
 * Persist a small preference; storage may be unavailable (private mode).
 */
export const storage = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            if (value === null) {
                window.localStorage.removeItem(key);
            } else {
                window.localStorage.setItem(key, value);
            }
        } catch {
            // Preference simply is not remembered.
        }
    },
};
