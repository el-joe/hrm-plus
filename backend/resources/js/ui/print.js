/**
 * [data-print] buttons open the browser's print dialog (offer letters, …).
 */
export function initPrint() {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-print]')) {
            window.print();
        }
    });
}
