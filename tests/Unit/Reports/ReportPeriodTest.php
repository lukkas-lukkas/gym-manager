<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use App\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A plain unit test: `ReportPeriod` only uses Carbon, so the application is not booted. Without the framework's
 * `travelTo()`, the clock is frozen with Carbon directly and always restored in `tearDown()`.
 */
class ReportPeriodTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_default_period_covers_five_past_months_the_current_one_and_six_future_months(): void
    {
        CarbonImmutable::setTestNow('2026-11-15 10:00:00');

        $period = ReportPeriod::default();

        $this->assertSame('2026-06-01 00:00:00', $period->startsOn->format('Y-m-d H:i:s'));
        $this->assertSame('2027-05-31 23:59:59', $period->endsOn->format('Y-m-d H:i:s'));
    }

    public function test_default_period_crosses_into_the_previous_year_without_month_overflow_on_the_31st(): void
    {
        CarbonImmutable::setTestNow('2026-01-31 23:30:00');

        $period = ReportPeriod::default();

        $this->assertSame('2025-08-01', $period->startsOn->toDateString());
        $this->assertSame('2026-07-31', $period->endsOn->toDateString());
    }

    public function test_from_months_starts_on_the_first_day_and_ends_on_the_last_second_of_the_last_month(): void
    {
        $period = ReportPeriod::fromMonths('2026-03', '2026-04');

        $this->assertSame('2026-03-01 00:00:00', $period->startsOn->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-30 23:59:59', $period->endsOn->format('Y-m-d H:i:s'));
    }

    public function test_from_months_keeps_the_requested_month_when_today_is_a_day_that_month_does_not_have(): void
    {
        CarbonImmutable::setTestNow('2026-10-31 10:00:00');

        $period = ReportPeriod::fromMonths('2026-11', '2027-02');

        $this->assertSame('2026-11-01', $period->startsOn->toDateString());
        $this->assertSame('2027-02-28', $period->endsOn->toDateString());
    }

    public function test_from_months_ends_on_february_29_in_a_leap_year(): void
    {
        $period = ReportPeriod::fromMonths('2028-02', '2028-02');

        $this->assertSame('2028-02-29', $period->endsOn->toDateString());
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function monthCounts(): array
    {
        return [
            'single month' => ['2026-11', '2026-11', 1],
            'same year' => ['2026-01', '2026-12', 12],
            'across years' => ['2026-11', '2027-02', 4],
            'maximum allowed' => ['2026-01', '2027-12', 24],
        ];
    }

    #[DataProvider('monthCounts')]
    public function test_month_count_includes_both_the_first_and_the_last_month(string $firstMonth, string $lastMonth, int $expectedCount): void
    {
        $period = ReportPeriod::fromMonths($firstMonth, $lastMonth);

        $this->assertSame($expectedCount, $period->monthCount());
    }

    public function test_months_lists_every_month_in_order_across_the_turn_of_the_year(): void
    {
        $period = ReportPeriod::fromMonths('2026-11', '2027-02');

        $this->assertSame(['2026-11', '2026-12', '2027-01', '2027-02'], $period->months());
    }

    public function test_months_has_a_single_entry_for_a_one_month_period(): void
    {
        $period = ReportPeriod::fromMonths('2026-11', '2026-11');

        $this->assertSame(['2026-11'], $period->months());
    }

    public function test_align_to_months_puts_each_total_in_its_month_and_zero_in_months_without_one(): void
    {
        $period = ReportPeriod::fromMonths('2026-10', '2027-01');

        $aligned = $period->alignToMonths(['2027-01' => 300, '2026-10' => 100]);

        $this->assertSame([100, 0, 0, 300], $aligned);
    }

    public function test_align_to_months_drops_totals_outside_the_period(): void
    {
        $period = ReportPeriod::fromMonths('2026-11', '2026-12');

        $aligned = $period->alignToMonths(['2026-10' => 999, '2026-11' => 100, '2027-01' => 999]);

        $this->assertSame([100, 0], $aligned);
    }

    public function test_align_to_months_returns_zero_for_every_month_when_there_are_no_totals(): void
    {
        $period = ReportPeriod::fromMonths('2026-11', '2027-01');

        $this->assertSame([0, 0, 0], $period->alignToMonths([]));
    }

    public function test_first_and_last_month_are_formatted_as_year_and_month(): void
    {
        $period = ReportPeriod::fromMonths('2026-09', '2027-03');

        $this->assertSame('2026-09', $period->firstMonth());
        $this->assertSame('2027-03', $period->lastMonth());
    }
}
