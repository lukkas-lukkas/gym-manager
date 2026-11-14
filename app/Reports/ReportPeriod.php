<?php

declare(strict_types=1);

namespace App\Reports;

use Carbon\CarbonImmutable;

/**
 * The range of whole months a report covers, from the first day of `startsOn` to the last day of `endsOn`.
 *
 * Every report receives the same period, so all charts on the page line up month by month.
 */
final readonly class ReportPeriod
{
    /**
     * Largest number of months one report can cover, so a single request never scans years of data.
     */
    public const MAX_MONTHS = 24;

    /**
     * Months before the current one shown by default (realized data).
     */
    private const DEFAULT_PAST_MONTHS = 5;

    /**
     * Months after the current one shown by default (forecast data).
     */
    private const DEFAULT_FUTURE_MONTHS = 6;

    private function __construct(
        public CarbonImmutable $startsOn,
        public CarbonImmutable $endsOn,
    ) {}

    /**
     * The last few months, the current one and the next few: past results and the forecast side by side.
     */
    public static function default(): self
    {
        $currentMonth = CarbonImmutable::today()->startOfMonth();

        return new self(
            $currentMonth->subMonthsNoOverflow(self::DEFAULT_PAST_MONTHS),
            $currentMonth->addMonthsNoOverflow(self::DEFAULT_FUTURE_MONTHS)->endOfMonth(),
        );
    }

    /**
     * Build the period from two `YYYY-MM` months, both included.
     *
     * The `!` in the format resets the missing day to 1. Without it PHP fills in today's day, so on the 31st
     * `2026-11` would become 31/11, which rolls over to December.
     */
    public static function fromMonths(string $firstMonth, string $lastMonth): self
    {
        return new self(
            CarbonImmutable::createFromFormat('!Y-m', $firstMonth),
            CarbonImmutable::createFromFormat('!Y-m', $lastMonth)->endOfMonth(),
        );
    }

    /**
     * How many months the period covers, counting both ends.
     */
    public function monthCount(): int
    {
        return (int) $this->startsOn->diffInMonths($this->endsOn->startOfMonth()) + 1;
    }

    /**
     * Every month in the period as `YYYY-MM`, in order. Reports return one value per month in this same order,
     * so the charts can line them up by position.
     *
     * @return list<string>
     */
    public function months(): array
    {
        $months = [];

        for ($month = $this->startsOn; $month->lessThanOrEqualTo($this->endsOn); $month = $month->addMonthNoOverflow()) {
            $months[] = $month->format('Y-m');
        }

        return $months;
    }

    /**
     * Turns totals keyed by `YYYY-MM` into one value per month of the period, in order. Months the database
     * returned nothing for become `0`, and months outside the period are dropped.
     *
     * @param  array<string, int>  $totalsByMonth
     * @return list<int>
     */
    public function alignToMonths(array $totalsByMonth): array
    {
        return array_map(fn (string $month): int => $totalsByMonth[$month] ?? 0, $this->months());
    }

    /**
     * The first month as `YYYY-MM`.
     */
    public function firstMonth(): string
    {
        return $this->startsOn->format('Y-m');
    }

    /**
     * The last month as `YYYY-MM`.
     */
    public function lastMonth(): string
    {
        return $this->endsOn->format('Y-m');
    }
}
