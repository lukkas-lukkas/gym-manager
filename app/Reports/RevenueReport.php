<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\RevenueStatus;
use App\Models\Revenue;

/**
 * Revenues per month: what came in (realized) and what was expected to come in (forecast).
 *
 * - Realized: paid revenues, in the month they were paid (`paid_at`), the day the money arrived.
 * - Forecast: every revenue due in the month (`due_on`), paid or not. Comparing it with the realized amount
 *   of the same month shows what is still missing. Renewals that don't exist yet are not here: they have their
 *   own chart (see {@see RevenueProjection}).
 *
 * Each one is a single grouped query that only returns one sum per month, never the revenue rows, so the cost
 * stays the same no matter how many revenues the gym has. Both filter on indexed columns:
 * `(status, paid_at)` and `due_on`.
 */
final readonly class RevenueReport
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
            Revenue::query()
                ->where('status', RevenueStatus::Paid)
                ->whereBetween('paid_at', [$this->period->startsOn, $this->period->endsOn]),
            'paid_at',
            'price_in_cents',
        );
    }

    /**
     * @return array<string, int> Amount in cents keyed by `YYYY-MM`, only for months that have revenues due.
     */
    private function forecastByMonth(): array
    {
        return $this->sumByMonth(
            Revenue::query()->whereBetween('due_on', [
                $this->period->startsOn->toDateString(),
                $this->period->endsOn->toDateString(),
            ]),
            'due_on',
            'price_in_cents',
        );
    }
}
