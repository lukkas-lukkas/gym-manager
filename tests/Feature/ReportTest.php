<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\RevenueStatus;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_five_past_months_to_six_future_months_by_default(): void
    {
        $this->travelTo('2026-11-15 10:00:00');

        $this->actingAs(User::factory()->create())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/Index')
                ->where('from', '2026-06')
                ->where('to', '2027-05')
                ->where('maxMonths', 24)
                ->has('months', 12)
                ->where('months.0', '2026-06')
                ->where('months.11', '2027-05')
                ->missing('revenues')
                ->missing('revenueProjection')
                ->missing('expenses')
            );
    }

    public function test_revenues_sum_paid_amounts_by_payment_month_and_all_amounts_by_due_month(): void
    {
        $this->travelTo('2026-11-15 10:00:00');

        // Due in September, paid late in October: forecast in September, realized in October.
        Revenue::factory()->create(['price_in_cents' => 10_000, 'due_on' => '2026-09-05', 'status' => RevenueStatus::Paid, 'paid_at' => '2026-10-02 10:00:00']);
        Revenue::factory()->create(['price_in_cents' => 20_000, 'due_on' => '2026-10-10', 'status' => RevenueStatus::Paid, 'paid_at' => '2026-10-10 09:00:00']);
        Revenue::factory()->create(['price_in_cents' => 5_000, 'due_on' => '2026-11-20']);
        // Due on the last day of the period but paid the day after it ends: forecast only.
        Revenue::factory()->create(['price_in_cents' => 7_000, 'due_on' => '2026-11-30', 'status' => RevenueStatus::Paid, 'paid_at' => '2026-12-01 00:00:00']);
        // Due after the period but paid on its last second: realized only.
        Revenue::factory()->create(['price_in_cents' => 3_000, 'due_on' => '2026-12-01', 'status' => RevenueStatus::Paid, 'paid_at' => '2026-11-30 23:59:59']);
        Revenue::factory()->create(['price_in_cents' => 99, 'due_on' => '2026-08-31']);

        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['from' => '2026-09', 'to' => '2026-11']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('months', ['2026-09', '2026-10', '2026-11'])
                ->missing('revenues')
                ->loadDeferredProps('revenues', fn (Assert $reload) => $reload
                    ->where('revenues', [
                        'realized' => [0, 30_000, 3_000],
                        'forecast' => [10_000, 20_000, 12_000],
                    ])
                )
            );
    }

    public function test_revenues_are_zero_in_every_month_without_revenues(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['from' => '2026-01', 'to' => '2026-02']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps('revenues', fn (Assert $reload) => $reload
                    ->where('revenues', ['realized' => [0, 0], 'forecast' => [0, 0]])
                )
            );
    }

    public function test_expenses_sum_paid_amounts_by_payment_month_and_all_amounts_by_due_month(): void
    {
        $this->travelTo('2026-11-15 10:00:00');

        // Due in September, paid late in October: forecast in September, realized in October.
        Expense::factory()->create(['amount_in_cents' => 10_000, 'due_on' => '2026-09-05', 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-10-02 10:00:00']);
        Expense::factory()->create(['amount_in_cents' => 20_000, 'due_on' => '2026-10-10', 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-10-10 09:00:00']);
        Expense::factory()->create(['amount_in_cents' => 5_000, 'due_on' => '2026-11-20']);
        // Due on the last day of the period but paid the day after it ends: forecast only.
        Expense::factory()->create(['amount_in_cents' => 7_000, 'due_on' => '2026-11-30', 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-12-01 00:00:00']);
        // Due after the period but paid on its last second: realized only.
        Expense::factory()->create(['amount_in_cents' => 3_000, 'due_on' => '2026-12-01', 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-11-30 23:59:59']);
        Expense::factory()->create(['amount_in_cents' => 99, 'due_on' => '2026-08-31']);

        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['from' => '2026-09', 'to' => '2026-11']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('expenses')
                ->loadDeferredProps('expenses', fn (Assert $reload) => $reload
                    ->where('expenses', [
                        'realized' => [0, 30_000, 3_000],
                        'forecast' => [10_000, 20_000, 12_000],
                    ])
                )
            );
    }

    public function test_index_shows_the_period_from_the_url(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['from' => '2026-01', 'to' => '2026-03']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('from', '2026-01')
                ->where('to', '2026-03')
            );
    }

    public function test_index_keeps_the_month_from_the_url_on_the_last_day_of_a_month(): void
    {
        $this->travelTo('2026-10-31 10:00:00');

        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['from' => '2026-11', 'to' => '2026-11']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('from', '2026-11')
                ->where('to', '2026-11')
            );
    }

    public function test_index_accepts_a_period_of_exactly_the_maximum_size(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['from' => '2026-01', 'to' => '2027-12']))
            ->assertOk()
            ->assertSessionHasNoErrors();
    }

    public function test_index_rejects_a_period_longer_than_the_maximum(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['from' => '2026-01', 'to' => '2028-01']))
            ->assertSessionHasErrors(['to' => 'O período pode ter no máximo 24 meses.']);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidPeriods(): array
    {
        return [
            'month 13' => [['from' => '2026-13', 'to' => '2027-01'], 'from'],
            'full date' => [['from' => '2026-01', 'to' => '2026-03-01'], 'to'],
            'only from' => [['from' => '2026-01'], 'to'],
            'only to' => [['to' => '2026-01'], 'from'],
            'to before from' => [['from' => '2026-05', 'to' => '2026-04'], 'to'],
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    #[DataProvider('invalidPeriods')]
    public function test_index_rejects_an_invalid_period(array $query, string $invalidField): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', $query))
            ->assertSessionHasErrors($invalidField);
    }
}
