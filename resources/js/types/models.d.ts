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
