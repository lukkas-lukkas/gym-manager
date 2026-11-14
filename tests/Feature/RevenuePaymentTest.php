<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RevenueStatus;
use App\Models\BankAccount;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenuePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_pay_a_revenue(): void
    {
        $revenue = Revenue::factory()->create();

        $this->post(route('revenues.pay', $revenue))->assertRedirect(route('login'));

        $this->assertSame(RevenueStatus::Pending, $revenue->fresh()->status);
    }

    public function test_pending_revenue_is_marked_as_paid_now(): void
    {
        $this->travelTo('2026-11-12 15:30:00');

        $revenue = Revenue::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('revenues.pay', $revenue))
            ->assertRedirect(route('members.enrollments.index', $revenue->member_id))
            ->assertInertiaFlash('success', 'Pagamento registrado.');

        $revenue->refresh();
        $this->assertSame(RevenueStatus::Paid, $revenue->status);
        $this->assertSame('2026-11-12 15:30:00', $revenue->paid_at->toDateTimeString());
    }

    public function test_paying_from_the_revenues_list_goes_back_to_the_same_month_and_page(): void
    {
        $revenue = Revenue::factory()->create();
        $revenuesUrl = route('revenues.index', ['month' => '2026-11', 'page' => 2]);

        $this->actingAs(User::factory()->create())
            ->from($revenuesUrl)
            ->post(route('revenues.pay', $revenue))
            ->assertRedirect($revenuesUrl);
    }

    public function test_paying_twice_keeps_the_first_payment_date(): void
    {
        $this->travelTo('2026-11-12 15:30:00');

        $revenue = Revenue::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('revenues.pay', $revenue));

        $this->travelTo('2026-11-13 09:00:00');

        $this->actingAs($user)
            ->post(route('revenues.pay', $revenue))
            ->assertRedirect(route('members.enrollments.index', $revenue->member_id))
            ->assertInertiaFlash('success', 'Este pagamento já estava registrado.');

        $this->assertSame('2026-11-12 15:30:00', $revenue->fresh()->paid_at->toDateTimeString());
    }

    public function test_paying_only_changes_the_given_revenue(): void
    {
        $revenue = Revenue::factory()->create();
        $otherRevenue = Revenue::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('revenues.pay', $revenue));

        $this->assertSame(RevenueStatus::Pending, $otherRevenue->fresh()->status);
    }

    public function test_paying_a_missing_revenue_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('revenues.pay', 999))
            ->assertNotFound();
    }

    public function test_paying_adds_the_revenue_price_to_the_bank_account_balance(): void
    {
        $account = BankAccount::factory()->create(['opening_balance_in_cents' => 100_000, 'current_balance_in_cents' => 150_000]);
        $revenue = Revenue::factory()->create(['price_in_cents' => 12_000]);

        $this->actingAs(User::factory()->create())->post(route('revenues.pay', $revenue));

        $account->refresh();
        $this->assertSame(162_000, $account->current_balance_in_cents);
        $this->assertSame(100_000, $account->opening_balance_in_cents);
    }

    public function test_paying_twice_adds_the_price_to_the_balance_only_once(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 150_000]);
        $revenue = Revenue::factory()->create(['price_in_cents' => 12_000]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('revenues.pay', $revenue));
        $this->actingAs($user)->post(route('revenues.pay', $revenue));

        $this->assertSame(162_000, $account->refresh()->current_balance_in_cents);
    }

    public function test_paying_without_a_bank_account_still_registers_the_payment(): void
    {
        $revenue = Revenue::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('revenues.pay', $revenue))
            ->assertInertiaFlash('success', 'Pagamento registrado.');

        $this->assertSame(RevenueStatus::Paid, $revenue->fresh()->status);
        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_revenue_stays_pending_when_the_balance_cannot_be_updated(): void
    {
        // The largest value a signed BIGINT holds: adding even one cent makes the database reject the update.
        $account = BankAccount::factory()->create(['current_balance_in_cents' => PHP_INT_MAX]);
        $revenue = Revenue::factory()->create(['price_in_cents' => 1]);

        $this->actingAs(User::factory()->create())
            ->post(route('revenues.pay', $revenue))
            ->assertServerError();

        $revenue->refresh();
        $this->assertSame(RevenueStatus::Pending, $revenue->status);
        $this->assertNull($revenue->paid_at);
        $this->assertSame(PHP_INT_MAX, $account->refresh()->current_balance_in_cents);
    }
}
