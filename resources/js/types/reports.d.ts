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

/**
 * One plan and how many enrollments it has active today.
 */
export type ActiveEnrollmentsByPlan = {
    name: string;
    /** The plan is no longer on sale; it is listed because members are still on it. */
    is_deactivated: boolean;
    active_enrollments: number;
};

/**
 * Today's snapshot of the gym shown on the dashboard. Amounts in cents.
 */
export type DashboardSummary = {
    /** Current bank account balance, or `null` while no account is registered. */
    balanceInCents: number | null;
    activeMembers: number;
    /** Current month: paid so far, and still pending from today to the end of the month (overdue left out). */
    month: {
        receivedInCents: number;
        toReceiveInCents: number;
        paidInCents: number;
        toPayInCents: number;
    };
    overdueRevenues: {
        count: number;
        totalInCents: number;
        /** The oldest overdue revenues first, capped to a few rows. */
        oldest: { id: number; member_id: number; member_name: string; due_on: string; price_in_cents: number }[];
    };
    /** Pending expenses due in the next days, overdue ones included, oldest first, capped to a few rows. */
    upcomingExpenses: { id: number; description: string; due_on: string; amount_in_cents: number; is_overdue: boolean }[];
};
