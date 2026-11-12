/**
 * Client-side mirror of App\Support\Formatter (Phase 8c). Reads the display settings the
 * layouts put on <html>: data-digits ("arab" = Arabic-Indic digits, only set while the
 * interface is Arabic and the setting is on) and data-date-format (PHP format: Y-m-d,
 * d/m/Y, d-m-Y, m/d/Y).
 */
const root = document.documentElement;
const ARABIC_INDIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

export const usesArabicDigits = () => root.dataset.digits === 'arab';

/**
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function formatDigits(value) {
    if (value === null || value === undefined) {
        return '';
    }

    const text = String(value);

    return usesArabicDigits() ? text.replace(/[0-9]/g, (digit) => ARABIC_INDIC[Number(digit)]) : text;
}

/**
 * Grouped number with a fixed number of decimals (display only).
 *
 * @param {string|number|null|undefined} value
 * @param {number} decimals
 */
export function formatNumber(value, decimals = 0) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const number = Number(value);

    if (Number.isNaN(number)) {
        return formatDigits(value);
    }

    const text = number.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

    return formatDigits(text);
}

/**
 * An ISO date (YYYY-MM-DD…) in the configured date format.
 *
 * @param {string|null|undefined} iso
 */
export function formatDate(iso) {
    if (!iso) {
        return '';
    }

    const [year, month, day] = String(iso).slice(0, 10).split('-');

    if (!year || !month || !day) {
        return formatDigits(iso);
    }

    const pattern = root.dataset.dateFormat || 'Y-m-d';
    const text = pattern.replace(/[Ymd]/g, (token) => ({ Y: year, m: month, d: day })[token]);

    return formatDigits(text);
}
