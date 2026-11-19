<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_null_while_no_account_is_registered(): void
    {
        $this->assertNull(BankAccount::current());
    }

    public function test_current_returns_the_first_registered_account(): void
    {
        $firstAccount = BankAccount::factory()->create();
        BankAccount::factory()->create();

        $this->assertTrue($firstAccount->is(BankAccount::current()));
    }

    public function test_balances_can_be_negative(): void
    {
        $account = BankAccount::factory()->create([
            'opening_balance_in_cents' => -50_000,
            'current_balance_in_cents' => -125_000,
        ]);

        $account->refresh();

        $this->assertSame(-50_000, $account->opening_balance_in_cents);
        $this->assertSame(-125_000, $account->current_balance_in_cents);
    }

    public function test_current_balance_cannot_be_mass_assigned(): void
    {
        $account = new BankAccount;

        $account->fill(['name' => 'Conta', 'opening_balance_in_cents' => 1_000, 'current_balance_in_cents' => 999_999]);

        $this->assertSame('Conta', $account->name);
        $this->assertSame(1_000, $account->opening_balance_in_cents);
        $this->assertNull($account->current_balance_in_cents);
    }

    public function test_guest_cannot_access_the_bank_account(): void
    {
        $this->get(route('bank-account.show'))->assertRedirect(route('login'));
        $this->get(route('bank-account.create'))->assertRedirect(route('login'));
        $this->post(route('bank-account.store'))->assertRedirect(route('login'));
        $this->get(route('bank-account.edit'))->assertRedirect(route('login'));
        $this->put(route('bank-account.update'))->assertRedirect(route('login'));
    }

    public function test_show_has_no_account_while_none_is_registered(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('bank-account.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bank-account/Show')
                ->where('account', null)
            );
    }

    public function test_show_has_the_account_and_its_balances(): void
    {
        BankAccount::factory()->create([
            'name' => 'Banco do Brasil',
            'opening_balance_in_cents' => 500_000,
            'current_balance_in_cents' => 620_000,
            'opened_at' => '2026-11-14 13:30:00',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('bank-account.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('account', [
                    'name' => 'Banco do Brasil',
                    'opening_balance_in_cents' => 500_000,
                    'current_balance_in_cents' => 620_000,
                    'opened_at' => '2026-11-14T13:30:00-03:00',
                ])
            );
    }

    public function test_create_shows_the_form_while_no_account_is_registered(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('bank-account.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bank-account/Create')
                ->where('maxOpeningBalanceInCents', 9_999_999_999)
            );
    }

    public function test_create_redirects_to_the_account_when_it_already_exists(): void
    {
        BankAccount::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('bank-account.create'))
            ->assertRedirect(route('bank-account.show'));
    }

    public function test_store_opens_the_account_now_with_the_current_balance_equal_to_the_opening_one(): void
    {
        $this->freezeSecond();

        $this->actingAs(User::factory()->create())
            ->post(route('bank-account.store'), [
                'name' => 'Banco do Brasil',
                'opening_balance_in_cents' => '500000',
                'current_balance_in_cents' => '999999',
            ])
            ->assertRedirect(route('bank-account.show'))
            ->assertInertiaFlash('success', 'Conta bancária cadastrada com sucesso.');

        $this->assertDatabaseCount('bank_accounts', 1);
        $this->assertDatabaseHas('bank_accounts', [
            'name' => 'Banco do Brasil',
            'opening_balance_in_cents' => 500_000,
            'current_balance_in_cents' => 500_000,
            'opened_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_store_refuses_a_second_account(): void
    {
        $existingAccount = BankAccount::factory()->create(['name' => 'Conta principal']);

        $this->actingAs(User::factory()->create())
            ->post(route('bank-account.store'), ['name' => 'Outra conta', 'opening_balance_in_cents' => '100'])
            ->assertRedirect(route('bank-account.show'))
            ->assertSessionHasErrors(['name' => 'A academia já tem uma conta bancária cadastrada.']);

        $this->assertDatabaseCount('bank_accounts', 1);
        $this->assertModelExists($existingAccount);
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidAccounts(): array
    {
        return [
            'missing name' => [['opening_balance_in_cents' => '100'], 'name', 'O campo nome é obrigatório.'],
            'missing opening balance' => [['name' => 'Conta'], 'opening_balance_in_cents', 'O campo saldo inicial é obrigatório.'],
            'negative opening balance' => [['name' => 'Conta', 'opening_balance_in_cents' => '-1'], 'opening_balance_in_cents', 'O saldo inicial não pode ser negativo.'],
            'opening balance too large' => [['name' => 'Conta', 'opening_balance_in_cents' => '10000000000'], 'opening_balance_in_cents', 'O saldo inicial não pode passar de R$ 99.999.999,99.'],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('invalidAccounts')]
    public function test_store_rejects_invalid_data(array $payload, string $invalidField, string $message): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('bank-account.store'), $payload)
            ->assertSessionHasErrors([$invalidField => $message]);

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_edit_redirects_to_create_while_no_account_is_registered(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('bank-account.edit'))
            ->assertRedirect(route('bank-account.create'));
    }

    public function test_edit_shows_the_form_with_the_account(): void
    {
        BankAccount::factory()->create(['name' => 'Banco do Brasil']);

        $this->actingAs(User::factory()->create())
            ->get(route('bank-account.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bank-account/Edit')
                ->where('account.name', 'Banco do Brasil')
            );
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function newOpeningBalances(): array
    {
        return [
            'raised by R$ 200' => [520_000, 640_000],
            'lowered by R$ 1.000' => [400_000, 520_000],
            'unchanged' => [500_000, 620_000],
        ];
    }

    #[DataProvider('newOpeningBalances')]
    public function test_update_moves_the_current_balance_by_the_same_difference_as_the_opening_one(int $newOpeningBalance, int $expectedCurrentBalance): void
    {
        $this->freezeSecond();
        $account = BankAccount::factory()->create([
            'name' => 'Conta antiga',
            'opening_balance_in_cents' => 500_000,
            'current_balance_in_cents' => 620_000,
            'opened_at' => '2026-11-01 08:00:00',
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('bank-account.update'), [
                'name' => 'Banco do Brasil',
                'opening_balance_in_cents' => (string) $newOpeningBalance,
                'current_balance_in_cents' => '1',
            ])
            ->assertRedirect(route('bank-account.show'))
            ->assertInertiaFlash('success', 'Conta bancária atualizada com sucesso.');

        $account->refresh();
        $this->assertSame('Banco do Brasil', $account->name);
        $this->assertSame($newOpeningBalance, $account->opening_balance_in_cents);
        $this->assertSame($expectedCurrentBalance, $account->current_balance_in_cents);
        $this->assertSame('2026-11-01 08:00:00', $account->opened_at->toDateTimeString());
    }

    public function test_update_can_leave_the_current_balance_negative(): void
    {
        $account = BankAccount::factory()->create(['opening_balance_in_cents' => 500_000, 'current_balance_in_cents' => 100_000]);

        $this->actingAs(User::factory()->create())
            ->put(route('bank-account.update'), ['name' => 'Conta', 'opening_balance_in_cents' => '0']);

        $this->assertSame(-400_000, $account->refresh()->current_balance_in_cents);
    }

    public function test_update_rejects_invalid_data_without_changing_the_account(): void
    {
        $account = BankAccount::factory()->create(['opening_balance_in_cents' => 500_000, 'current_balance_in_cents' => 620_000]);

        $this->actingAs(User::factory()->create())
            ->put(route('bank-account.update'), ['name' => '', 'opening_balance_in_cents' => '-1'])
            ->assertSessionHasErrors(['name', 'opening_balance_in_cents']);

        $account->refresh();
        $this->assertSame(500_000, $account->opening_balance_in_cents);
        $this->assertSame(620_000, $account->current_balance_in_cents);
    }

    public function test_update_redirects_to_create_while_no_account_is_registered(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('bank-account.update'), ['name' => 'Conta', 'opening_balance_in_cents' => '100'])
            ->assertRedirect(route('bank-account.create'));

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_open_creates_nothing_when_an_account_already_exists(): void
    {
        BankAccount::factory()->create();

        $this->assertNull(BankAccount::open('Outra conta', 100));
        $this->assertDatabaseCount('bank_accounts', 1);
    }
}
