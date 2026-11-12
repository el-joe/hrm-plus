/**
 * Charts (Phase 8c) — a thin wrapper around Chart.js (npm, bundled by Vite; no CDN).
 *
 * Markup (rendered by the x-chart Blade component):
 *
 *   <figure data-chart>
 *     <canvas role="img" aria-label="…"></canvas>
 *     <script type="application/json" data-chart-config>{…}</script>
 *     <details>…accessible data table…</details>
 *   </figure>
 *
 * Config (built in PHP by App\Support\Charts\ChartData):
 *   { type: 'bar'|'line'|'doughnut'|'pie', labels: [...],
 *     datasets: [{ label, data: [...], color: 'chart-1'…'chart-6' }],
 *     options: { stacked, horizontal, percent, decimals } }
 *
 * - Colours come from the design tokens (CSS variables --color-chart-1…6, text and border
 *   tokens), so light / dark both work; charts are rebuilt on the `theme:change` event.
 * - RTL: legends and tooltips run right-to-left and the category axis is reversed when
 *   <html dir="rtl">. Font: the page font (Cairo).
 * - Numbers on axes and tooltips go through lib/format.js (Arabic-Indic digits setting).
 *
 * Token classes used through CSS variables (kept here so Tailwind emits them):
 * bg-chart-1 bg-chart-2 bg-chart-3 bg-chart-4 bg-chart-5 bg-chart-6 text-text-secondary border-border-subtle
 */
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PieController,
    PointElement,
    Tooltip,
} from 'chart.js';
import { formatNumber } from '../lib/format';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, Legend, LinearScale, LineController, LineElement, PieController, PointElement, Tooltip);

const instances = new Map();
const PALETTE = ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6'];

function token(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(`--color-${name}`).trim();
}

function withAlpha(color, alpha) {
    if (!color) {
        return color;
    }

    // Colours are hex (#rrggbb) in the design tokens; anything else is returned as is.
    if (/^#[0-9a-f]{6}$/i.test(color)) {
        const value = Math.round(alpha * 255).toString(16).padStart(2, '0');

        return `${color}${value}`;
    }

    return color;
}

function readConfig(figure) {
    try {
        return JSON.parse(figure.querySelector('script[data-chart-config]')?.textContent ?? 'null');
    } catch {
        return null;
    }
}

function build(figure) {
    const config = readConfig(figure);
    const canvas = figure.querySelector('canvas');

    if (!config || !canvas) {
        return;
    }

    instances.get(figure)?.destroy();

    const rtl = document.documentElement.dir === 'rtl';
    const options = config.options ?? {};
    const circular = config.type === 'doughnut' || config.type === 'pie';
    const text = token('text-secondary');
    const grid = token('border-subtle');
    const surface = token('surface-default');
    const font = getComputedStyle(document.body).fontFamily;
    const format = (value) => formatNumber(value, options.decimals ?? 0) + (options.percent ? '%' : '');

    const datasets = (config.datasets ?? []).map((dataset, index) => {
        if (circular) {
            const colours = config.labels.map((label, position) => token(PALETTE[position % PALETTE.length]));

            return { label: dataset.label, data: dataset.data, backgroundColor: colours, borderColor: surface, borderWidth: 2 };
        }

        const colour = token(dataset.color ?? PALETTE[index % PALETTE.length]);

        return {
            label: dataset.label,
            data: dataset.data,
            backgroundColor: config.type === 'line' ? withAlpha(colour, 0.15) : colour,
            borderColor: colour,
            borderWidth: config.type === 'line' ? 2 : 0,
            borderRadius: config.type === 'bar' ? 4 : 0,
            fill: config.type === 'line' && Boolean(options.fill),
            tension: 0.3,
            pointRadius: config.type === 'line' ? 2 : 0,
            maxBarThickness: 36,
        };
    });

    const axis = (reverse) => ({
        reverse,
        stacked: Boolean(options.stacked),
        grid: { color: grid, drawTicks: false },
        border: { display: false },
        ticks: { color: text, font: { family: font, size: 11 }, padding: 6 },
    });

    const valueAxis = { ...axis(false), beginAtZero: true, ticks: { ...axis(false).ticks, callback: (value) => format(value), precision: options.decimals ? undefined : 0 } };
    const categoryAxis = { ...axis(rtl && !options.horizontal), grid: { display: false } };

    const chart = new Chart(canvas, {
        type: config.type,
        data: { labels: config.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 300 },
            indexAxis: options.horizontal ? 'y' : 'x',
            locale: 'en-US',
            plugins: {
                legend: {
                    display: circular || datasets.length > 1,
                    position: 'bottom',
                    rtl,
                    textDirection: rtl ? 'rtl' : 'ltr',
                    labels: { color: text, font: { family: font, size: 12 }, boxWidth: 10, boxHeight: 10, usePointStyle: true },
                },
                tooltip: {
                    rtl,
                    textDirection: rtl ? 'rtl' : 'ltr',
                    titleFont: { family: font },
                    bodyFont: { family: font },
                    callbacks: {
                        label: (context) => `${context.dataset.label ? `${context.dataset.label}: ` : ''}${format(circular ? context.parsed : (options.horizontal ? context.parsed.x : context.parsed.y))}`,
                    },
                },
            },
            scales: circular
                ? {}
                : options.horizontal
                  ? { x: { ...valueAxis, reverse: rtl }, y: { ...categoryAxis, reverse: false, position: rtl ? 'right' : 'left' } }
                  : { x: categoryAxis, y: { ...valueAxis, position: rtl ? 'right' : 'left' } },
        },
    });

    instances.set(figure, chart);
}

export function initCharts(scope = document) {
    scope.querySelectorAll('figure[data-chart]').forEach((figure) => build(figure));
}

let listening = false;

export function initChartTheme() {
    if (listening) {
        return;
    }

    listening = true;
    // Token values change with the theme: rebuild every chart with the new colours.
    document.addEventListener('theme:change', () => {
        requestAnimationFrame(() => instances.forEach((chart, figure) => build(figure)));
    });
}
