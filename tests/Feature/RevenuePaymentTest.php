<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RevenueStatus;
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
}
