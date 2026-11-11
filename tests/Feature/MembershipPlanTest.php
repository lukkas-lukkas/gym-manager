<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_membership_plans(): void
    {
        $this->get(route('membership-plans.index'))->assertRedirect(route('login'));
        $this->get(route('membership-plans.create'))->assertRedirect(route('login'));
        $this->post(route('membership-plans.store'))->assertRedirect(route('login'));

        $plan = MembershipPlan::factory()->create();

        $this->get(route('membership-plans.edit', $plan))->assertRedirect(route('login'));
        $this->put(route('membership-plans.update', $plan))->assertRedirect(route('login'));
    }

    public function test_active_plans_are_listed_alphabetically_and_paginated(): void
    {
        MembershipPlan::factory()->create(['name' => 'Plano Zeta']);
        MembershipPlan::factory()->create([
            'name' => 'Plano Anual',
            'price_in_cents' => 99_990,
            'billing_period' => BillingPeriod::Yearly,
        ]);
        MembershipPlan::factory(14)
            ->sequence(fn ($sequence): array => ['name' => 'Plano Básico '.$sequence->index])
            ->create();
        MembershipPlan::factory()->inactive()->create(['name' => 'Plano Antigo']);

        $this->actingAs(User::factory()->create())
            ->get(route('membership-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('membership-plans/Index')
                ->has('plans.data', 15)
                ->where('plans.total', 16)
                ->where('plans.data.0.name', 'Plano Anual')
                ->where('plans.data.0.price_in_cents', 99_990)
                ->where('plans.data.0.billing_period', 'yearly')
                ->where('plans.data.0.billing_period_label', 'Anual')
            );
    }

    public function test_create_page_lists_the_billing_periods(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('membership-plans.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('membership-plans/Create')
                ->where('billingPeriods', [
                    ['value' => 'monthly', 'label' => 'Mensal'],
                    ['value' => 'quarterly', 'label' => 'Trimestral'],
                    ['value' => 'semiannual', 'label' => 'Semestral'],
                    ['value' => 'yearly', 'label' => 'Anual'],
                ])
            );
    }

    public function test_plan_can_be_created(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('membership-plans.store'), [
                'name' => 'Plano Mensal',
                'price_in_cents' => '9990',
                'billing_period' => 'monthly',
            ])
            ->assertRedirect(route('membership-plans.index'))
            ->assertInertiaFlash('success', 'Plano cadastrado com sucesso.');

        $this->assertDatabaseHas('membership_plans', [
            'name' => 'Plano Mensal',
            'price_in_cents' => 9_990,
            'billing_period' => 'monthly',
            'deactivated_at' => null,
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('membership-plans.store'), [])
            ->assertSessionHasErrors([
                'name' => 'O campo nome é obrigatório.',
                'price_in_cents' => 'O campo preço é obrigatório.',
                'billing_period' => 'O campo período de cobrança é obrigatório.',
            ]);

        $this->assertDatabaseCount('membership_plans', 0);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidPrices(): array
    {
        return [
            'zero' => ['0', 'O preço deve ser de pelo menos R$ 0,01.'],
            'negative' => ['-10', 'O preço deve ser de pelo menos R$ 0,01.'],
            'decimal value' => ['99.90', 'O campo preço deve ser um número inteiro.'],
            'not a number' => ['abc', 'O campo preço deve ser um número inteiro.'],
            'above maximum' => ['10000000', 'O preço não pode passar de R$ 99.999,99.'],
        ];
    }

    #[DataProvider('invalidPrices')]
    public function test_price_is_validated(string $priceInCents, string $expectedError): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('membership-plans.store'), [
                'name' => 'Plano Mensal',
                'price_in_cents' => $priceInCents,
                'billing_period' => 'monthly',
            ])
            ->assertSessionHasErrors(['price_in_cents' => $expectedError]);

        $this->assertDatabaseCount('membership_plans', 0);
    }

    public function test_billing_period_must_be_a_known_value(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('membership-plans.store'), [
                'name' => 'Plano Mensal',
                'price_in_cents' => '9990',
                'billing_period' => 'weekly',
            ])
            ->assertSessionHasErrors(['billing_period' => 'O período de cobrança selecionado é inválido.']);
    }

    public function test_name_must_be_unique(): void
    {
        MembershipPlan::factory()->create(['name' => 'Plano Mensal']);

        $this->actingAs(User::factory()->create())
            ->post(route('membership-plans.store'), [
                'name' => 'Plano Mensal',
                'price_in_cents' => '9990',
                'billing_period' => 'monthly',
            ])
            ->assertSessionHasErrors(['name' => 'Este nome já está em uso.']);

        $this->assertDatabaseCount('membership_plans', 1);
    }

    public function test_edit_page_shows_the_plan_data(): void
    {
        $plan = MembershipPlan::factory()->create([
            'name' => 'Plano Trimestral',
            'price_in_cents' => 27_000,
            'billing_period' => BillingPeriod::Quarterly,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('membership-plans.edit', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('membership-plans/Edit')
                ->where('plan', [
                    'id' => $plan->id,
                    'name' => 'Plano Trimestral',
                    'price_in_cents' => 27_000,
                    'billing_period' => 'quarterly',
                    'billing_period_label' => 'Trimestral',
                ])
            );
    }

    public function test_editing_a_missing_plan_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('membership-plans.edit', 999))
            ->assertNotFound();
    }

    public function test_plan_name_can_be_updated(): void
    {
        $plan = MembershipPlan::factory()->create(['name' => 'Plano Mensal']);

        $this->actingAs(User::factory()->create())
            ->put(route('membership-plans.update', $plan), ['name' => 'Plano Mensal Promocional'])
            ->assertRedirect(route('membership-plans.index'))
            ->assertInertiaFlash('success', 'Plano atualizado com sucesso.');

        $this->assertSame('Plano Mensal Promocional', $plan->fresh()->name);
    }

    public function test_price_and_billing_period_cannot_be_changed(): void
    {
        $plan = MembershipPlan::factory()->create([
            'price_in_cents' => 9_990,
            'billing_period' => BillingPeriod::Monthly,
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('membership-plans.update', $plan), [
                'name' => 'Plano Renomeado',
                'price_in_cents' => '1',
                'billing_period' => 'yearly',
                'deactivated_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect(route('membership-plans.index'));

        $plan->refresh();

        $this->assertSame('Plano Renomeado', $plan->name);
        $this->assertSame(9_990, $plan->price_in_cents);
        $this->assertSame(BillingPeriod::Monthly, $plan->billing_period);
        $this->assertTrue($plan->isActive());
    }

    public function test_plan_can_keep_its_own_name_when_updated(): void
    {
        $plan = MembershipPlan::factory()->create(['name' => 'Plano Mensal']);

        $this->actingAs(User::factory()->create())
            ->put(route('membership-plans.update', $plan), ['name' => 'Plano Mensal'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('membership-plans.index'));
    }

    public function test_plan_cannot_take_another_plans_name(): void
    {
        MembershipPlan::factory()->create(['name' => 'Plano Anual']);
        $plan = MembershipPlan::factory()->create(['name' => 'Plano Mensal']);

        $this->actingAs(User::factory()->create())
            ->put(route('membership-plans.update', $plan), ['name' => 'Plano Anual'])
            ->assertSessionHasErrors(['name' => 'Este nome já está em uso.']);

        $this->assertSame('Plano Mensal', $plan->fresh()->name);
    }

    public function test_update_requires_a_name(): void
    {
        $plan = MembershipPlan::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('membership-plans.update', $plan), [])
            ->assertSessionHasErrors(['name' => 'O campo nome é obrigatório.']);
    }
}
