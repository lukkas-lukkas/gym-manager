export type Member = {
    id: number;
    name: string;
    email: string;
    /** Date in `YYYY-MM-DD` format. */
    birth_date: string;
    /** ISO 8601 timestamp of when the member was deactivated, or `null` while active. */
    deactivated_at: string | null;
};

/**
 * Shape of Laravel's `LengthAwarePaginator` once serialized to JSON.
 */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type BillingPeriod = 'monthly' | 'quarterly' | 'semiannual' | 'yearly';

export type BillingPeriodOption = {
    value: BillingPeriod;
    label: string;
};

export type MembershipPlan = {
    id: number;
    name: string;
    price_in_cents: number;
    billing_period: BillingPeriod;
    /** Translated period name (e.g. "Mensal"), ready to display. */
    billing_period_label: string;
};

export type EnrollmentStatus = 'active' | 'inactive';

/**
 * One entry of a member's plan history, with the plan data already joined in.
 */
export type MemberEnrollment = {
    id: number;
    plan_name: string;
    price_in_cents: number;
    billing_period_label: string;
    /** Date in `YYYY-MM-DD` format. */
    starts_on: string;
    /** Date in `YYYY-MM-DD` format. */
    ends_on: string;
    /** `active` for the member's current plan; every other enrollment is `inactive`. */
    status: EnrollmentStatus;
};

export type ExpenseStatus = 'pending' | 'paid';

export type ExpenseStatusOption = {
    value: ExpenseStatus;
    label: string;
};

export type Expense = {
    id: number;
    description: string;
    amount_in_cents: number;
    /** Date in `YYYY-MM-DD` format. */
    due_on: string;
    status: ExpenseStatus;
    /** Translated status (e.g. "Pendente"), ready to display. */
    status_label: string;
};
