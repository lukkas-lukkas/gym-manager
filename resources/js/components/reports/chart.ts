import { BarElement, CategoryScale, Chart, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip } from 'chart.js';

/**
 * Chart.js setup shared by every report chart.
 *
 * Only the pieces the reports use are registered, so the bundle carries just that part of Chart.js.
 * Importing this module is enough: the registration runs once, the first time any chart loads.
 */
/** `LineController` lets a bar chart also draw lines (the cash flow shows bars and the balance line together). */
Chart.register(BarElement, LineElement, PointElement, LineController, CategoryScale, LinearScale, Tooltip, Legend);

/** Same font as the app (`--font-sans` in `app.css`). Written out instead of read from the DOM, so this module also loads during SSR. */
Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = '#4b5563';

/**
 * Colors by what the series is, never by its position: revenues are always blue and expenses always orange.
 * The pair was checked for color blindness (no red × green), and every chart also has a legend.
 * The balance is the result of the two, so it gets a neutral dark tone instead of a third hue.
 */
export const seriesColors = {
    revenue: '#2a78d6',
    expense: '#eb6834',
    balance: '#111827',
} as const;

/** Grid lines stay faint so the bars are what the eye reads first. */
export const gridColor = '#e5e7eb';

/** Fill opacity of forecast bars, as a hex alpha suffix. They use the same color as the realized ones, only lighter. */
export const forecastAlpha = '59';

const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const compactCurrencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', notation: 'compact' });
const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'short', year: '2-digit' });

/**
 * Formats an amount in cents as Brazilian currency (e.g. `9990` → `R$ 99,90`).
 */
export function formatCents(cents: number): string {
    return currencyFormatter.format(cents / 100);
}

/**
 * Short currency for the axis, so the labels never crowd the chart (e.g. `1250000` → `R$ 12,5 mil`).
 */
export function formatCentsCompact(cents: number): string {
    return compactCurrencyFormatter.format(cents / 100);
}

/**
 * Formats a `YYYY-MM` month as a short label (e.g. `2026-11` → `nov. de 26`) without going through
 * the timezone, so the month can't shift.
 */
export function formatMonth(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);

    return monthFormatter.format(new Date(year, monthNumber - 1, 1));
}
