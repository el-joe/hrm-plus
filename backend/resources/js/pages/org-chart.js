/**
 * Organisation chart (admin/structure/org-chart): collapse / expand branches,
 * zoom, and find a node by code or name. The chart is fully rendered by the
 * server and readable without JavaScript; these controls are data-js-only.
 */
const ZOOM_STEP = 0.1;
const ZOOM_MIN = 0.4;
const ZOOM_MAX = 1.5;

function setCollapsed(node, collapsed) {
    node.toggleAttribute('data-collapsed', collapsed);
    const toggle = node.querySelector(':scope > [data-org-card] [data-org-toggle]');
    toggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
}

function initChart(chart) {
    const canvas = chart.querySelector('[data-org-canvas]');

    if (!canvas) {
        return;
    }

    const nodes = [...canvas.querySelectorAll('[data-org-node]')];
    const viewport = chart.querySelector('[data-org-viewport]');
    let zoom = 1;

    // Start with the first top-level node in the middle of the viewport (wide trees overflow).
    const firstCard = canvas.querySelector('[data-org-card]');
    if (viewport && firstCard) {
        const cardBox = firstCard.getBoundingClientRect();
        const viewportBox = viewport.getBoundingClientRect();
        viewport.scrollLeft += cardBox.left + cardBox.width / 2 - (viewportBox.left + viewportBox.width / 2);
    }

    canvas.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-org-toggle]');

        if (toggle) {
            const node = toggle.closest('[data-org-node]');
            setCollapsed(node, !node.hasAttribute('data-collapsed'));
        }
    });

    chart.querySelector('[data-org-expand]')?.addEventListener('click', () => {
        nodes.forEach((node) => setCollapsed(node, false));
    });

    chart.querySelector('[data-org-collapse]')?.addEventListener('click', () => {
        // Keep the first two levels visible: the roots stay open, everything below them closes.
        nodes.forEach((node) => setCollapsed(node, node.parentElement.closest('[data-org-node]') !== null));
    });

    chart.querySelectorAll('[data-org-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.orgZoom;
            zoom = action === 'reset' ? 1 : zoom + (action === 'in' ? ZOOM_STEP : -ZOOM_STEP);
            zoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round(zoom * 10) / 10));
            canvas.style.setProperty('--org-zoom', String(zoom));
        });
    });

    const search = chart.querySelector('[data-org-search]');
    let timer;

    search?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const term = search.value.trim().toLowerCase();
            let first = null;

            nodes.forEach((node) => {
                const matches = term !== '' && node.dataset.orgSearch.includes(term);
                node.toggleAttribute('data-org-match', matches);

                if (matches) {
                    first ??= node;
                    // Reveal the match: expand every ancestor.
                    let ancestor = node.parentElement.closest('[data-org-node]');
                    while (ancestor) {
                        setCollapsed(ancestor, false);
                        ancestor = ancestor.parentElement.closest('[data-org-node]');
                    }
                }
            });

            first?.querySelector('[data-org-card]')?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        }, 200);
    });
}

export function initOrgChart() {
    document.querySelectorAll('[data-org-chart]').forEach(initChart);
}
