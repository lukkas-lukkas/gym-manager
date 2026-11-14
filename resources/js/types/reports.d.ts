/**
 * Realized × forecast amounts of one report, in cents, with one value per month of the page's `months`, in the same order.
 */
export type MonthlyTotals = {
    realized: number[];
    forecast: number[];
};
