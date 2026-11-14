/**
 * Realized × forecast amounts of one report, in cents, with one value per month of the page's `months`, in the same order.
 */
export type MonthlyTotals = {
    realized: number[];
    forecast: number[];
};

/**
 * Cash flow of the period: one value per month of the page's `months`. Amounts in cents.
 */
export type CashFlow = {
    /** Current month as `YYYY-MM`: from it on, values are forecast. */
    currentMonth: string;
    /** Today's bank account balance, or `null` while no account is registered. */
    currentBalance: number | null;
    inflow: number[];
    outflow: number[];
    /** Real balance at the end of each past month; `null` where unknown. */
    realizedBalance: (number | null)[];
    /**
     * Forecast balance at the end of each month from the current one, counting the renewals of the current plans
     * (repeats the last real point to join the lines).
     */
    forecastBalance: (number | null)[];
};
