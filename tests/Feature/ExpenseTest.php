<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_expenses(): void
    {
        $this->get(route('expenses.index'))->assertRedirect(route('login'));
        $this->get(route('expenses.create'))->assertRedirect(route('login'));
        $this->post(route('expenses.store'))->assertRedirect(route('login'));
    }

    public function test_index_shows_the_current_month_by_default(): void
    {
        $this->travelTo('2026-11-15 10:00:00');

        Expense::factory()->create(['description' => 'Luz', 'due_on' => '2026-11-30']);
        Expense::factory()->create(['description' => 'Aluguel', 'due_on' => '2026-11-01', 'amount_in_cents' => 500_000]);
        Expense::factory()->paid()->create(['description' => 'Internet', 'due_on' => '2026-11-10']);
        Expense::factory()->create(['due_on' => '2026-10-31']);
        Expense::factory()->create(['due_on' => '2026-12-01']);

        $this->actingAs(User::factory()->create())
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('expenses/Index')
                ->where('month', '2026-11')
                ->where('previousMonth', '2026-10')
                ->where('nextMonth', '2026-12')
                ->has('expenses.data', 3)
                ->where('expenses.data.0.description', 'Aluguel')
                ->where('expenses.data.0.amount_in_cents', 500_000)
                ->where('expenses.data.0.due_on', '2026-11-01')
                ->where('expenses.data.0.status', 'pending')
                ->where('expenses.data.0.status_label', 'Pendente')
                ->where('expenses.data.1.description', 'Internet')
                ->where('expenses.data.1.status_label', 'Pago')
                ->where('expenses.data.2.description', 'Luz')
            );
    }

    public function test_index_shows_the_month_from_the_url(): void
    {
        Expense::factory()->create(['description' => 'IPTU', 'due_on' => '2027-01-31']);
        Expense::factory()->create(['due_on' => '2027-02-01']);

        $this->actingAs(User::factory()->create())
            ->get(route('expenses.index', ['month' => '2027-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2027-01')
                ->where('previousMonth', '2026-12')
                ->where('nextMonth', '2027-02')
                ->has('expenses.data', 1)
                ->where('expenses.data.0.description', 'IPTU')
            );
    }

    public function test_index_is_paginated_keeping_the_month_in_the_links(): void
    {
        Expense::factory(16)->create(['due_on' => '2027-03-10']);

        $this->actingAs(User::factory()->create())
            ->get(route('expenses.index', ['month' => '2027-03']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenses.data', 15)
                ->where('expenses.total', 16)
                ->where('expenses.next_page_url', fn (string $url): bool => str_contains($url, 'month=2027-03') && str_contains($url, 'page=2'))
            );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidMonths(): array
    {
        return [
            'month 13' => ['2026-13'],
            'full date' => ['2026-11-01'],
            'text' => ['novembro'],
        ];
    }

    #[DataProvider('invalidMonths')]
    public function test_index_rejects_an_invalid_month(string $month): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('expenses.index', ['month' => $month]))
            ->assertSessionHasErrors('month');
    }

    public function test_create_page_sends_the_repetition_limit(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('expenses.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('expenses/Create')
                ->where('maxMonths', 24)
            );
    }

    public function test_single_expense_is_created_as_pending(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), [
                'description' => 'Conta de luz',
                'amount_in_cents' => '35090',
                'due_on' => '2026-11-20',
                'months' => '1',
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertInertiaFlash('success', 'Despesa cadastrada com sucesso.');

        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('expenses', [
            'description' => 'Conta de luz',
            'amount_in_cents' => 35_090,
            'due_on' => '2026-11-20',
            'status' => 'pending',
        ]);
    }

    public function test_status_sent_by_the_client_is_ignored(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), [
                'description' => 'Aluguel',
                'amount_in_cents' => '500000',
                'due_on' => '2026-11-05',
                'months' => '1',
                'status' => 'paid',
            ])
            ->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', ['description' => 'Aluguel', 'status' => 'pending']);
        $this->assertDatabaseMissing('expenses', ['status' => 'paid']);
    }

    public function test_expense_is_repeated_monthly_keeping_the_original_day(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), [
                'description' => 'Aluguel',
                'amount_in_cents' => '500000',
                'due_on' => '2027-01-31',
                'months' => '4',
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertInertiaFlash('success', '4 despesas cadastradas com sucesso.');

        $this->assertDatabaseCount('expenses', 4);

        foreach (['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'] as $dueOn) {
            $this->assertDatabaseHas('expenses', [
                'description' => 'Aluguel',
                'amount_in_cents' => 500_000,
                'due_on' => $dueOn,
                'status' => 'pending',
            ]);
        }
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), [])
            ->assertSessionHasErrors([
                'description' => 'O campo descrição é obrigatório.',
                'amount_in_cents' => 'O campo valor é obrigatório.',
                'due_on' => 'O campo vencimento é obrigatório.',
                'months' => 'O campo meses é obrigatório.',
            ]);

        $this->assertDatabaseCount('expenses', 0);
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidFields(): array
    {
        return [
            'zero amount' => [['amount_in_cents' => '0'], 'amount_in_cents', 'O valor deve ser de pelo menos R$ 0,01.'],
            'decimal amount' => [['amount_in_cents' => '99.90'], 'amount_in_cents', 'O campo valor deve ser um número inteiro.'],
            'amount above maximum' => [['amount_in_cents' => '10000000'], 'amount_in_cents', 'O valor não pode passar de R$ 99.999,99.'],
            'invalid date' => [['due_on' => '2026-02-30'], 'due_on', 'O campo vencimento deve ser uma data válida.'],
            'date in another format' => [['due_on' => '20/11/2026'], 'due_on', 'O campo vencimento deve ser uma data válida.'],
            'zero months' => [['months' => '0'], 'months', 'Repita por pelo menos 1 mês.'],
            'months above maximum' => [['months' => '25'], 'months', 'Repita por no máximo 24 meses.'],
            'decimal months' => [['months' => '1.5'], 'months', 'O campo meses deve ser um número inteiro.'],
            'description too long' => [['description' => str_repeat('a', 256)], 'description', 'O campo descrição não pode ter mais de 255 caracteres.'],
        ];
    }

    /**
     * @param  array<string, string>  $override
     */
    #[DataProvider('invalidFields')]
    public function test_fields_are_validated(array $override, string $field, string $expectedError): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.store'), [
                'description' => 'Conta de luz',
                'amount_in_cents' => '35090',
                'due_on' => '2026-11-20',
                'months' => '1',
                ...$override,
            ])
            ->assertSessionHasErrors([$field => $expectedError]);

        $this->assertDatabaseCount('expenses', 0);
    }
}
