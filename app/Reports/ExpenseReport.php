<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\ExpenseStatus;
use App\Models\Expense;

/**
 * Expenses per month: what went out (realized) and what was expected to go out (forecast).
 *
 * - Realized: paid expenses, in the month they were paid (`paid_at`), the day the money left.
 * - Forecast: every expense due in the month (`due_on`), paid or not. Comparing it with the realized amount
 *   of the same month shows what is still to be paid.
 *
 * Expenses don't renew on their own (a repeated expense is created month by month up front, see
 * {@see Expense::createMonthly()}), so there is nothing to project. Each total is a single grouped query on an
 * indexed column: `(status, paid_at)` and `due_on`.
 */
final readonly class ExpenseReport
{
    use SumsAmountsByMonth;

    public function __construct(private ReportPeriod $period) {}

    /**
     * One amount in cents per month of the period, in the order of {@see ReportPeriod::months()}.
     *
     * @return array{realized: list<int>, forecast: list<int>}
     */
    public function toArray(): array
    {
        return [
            'realized' => $this->period->alignToMonths($this->realizedByMonth()),
            'forecast' => $this->period->alignToMonths($this->forecastByMonth()),
        ];
    }

    /**
     * @return array<string, int> Amount in cents keyed by `YYYY-MM`, only for months that have payments.
     */
    private function realizedByMonth(): array
    {
        return $this->sumByMonth(
            Expense::query()
                ->where('status', ExpenseStatus::Paid)
                ->whereBetween('paid_at', [$this->period->startsOn, $this->period->endsOn]),
            'paid_at',
            'amount_in_cents',
        );
    }

    /**
     * @return array<string, int> Amount in cents keyed by `YYYY-MM`, only for months that have expenses due.
     */
    private function forecastByMonth(): array
    {
        return $this->sumByMonth(
            Expense::query()->whereBetween('due_on', [
                $this->period->startsOn->toDateString(),
                $this->period->endsOn->toDateString(),
            ]),
            'due_on',
            'amount_in_cents',
        );
    }
}
