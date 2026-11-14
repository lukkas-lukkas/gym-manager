<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The gym's single bank account (a singleton resource: `/bank-account`, without an id).
 */
class BankAccountController extends Controller
{
    /**
     * Show the account and its balances, or an invitation to register it while there is none.
     */
    public function show(): Response
    {
        $account = BankAccount::current();

        return Inertia::render('bank-account/Show', [
            'account' => $account === null ? null : $this->toPageData($account),
        ]);
    }

    /**
     * Show the form for registering the account, or go to it when it already exists.
     */
    public function create(): Response|RedirectResponse
    {
        if (BankAccount::current() !== null) {
            return redirect()->route('bank-account.show');
        }

        return Inertia::render('bank-account/Create', [
            'maxOpeningBalanceInCents' => StoreBankAccountRequest::MAX_OPENING_BALANCE_IN_CENTS,
        ]);
    }

    /**
     * Register the account, refusing a second one.
     */
    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $account = BankAccount::open(
            name: $request->string('name')->toString(),
            openingBalanceInCents: $request->integer('opening_balance_in_cents'),
        );

        if ($account === null) {
            return redirect()->route('bank-account.show')
                ->withErrors(['name' => 'A academia já tem uma conta bancária cadastrada.']);
        }

        Inertia::flash('success', 'Conta bancária cadastrada com sucesso.');

        return redirect()->route('bank-account.show');
    }

    /**
     * Show the form for editing the account, or the registration form while there is none.
     */
    public function edit(): Response|RedirectResponse
    {
        $account = BankAccount::current();

        if ($account === null) {
            return redirect()->route('bank-account.create');
        }

        return Inertia::render('bank-account/Edit', [
            'account' => $this->toPageData($account),
            'maxOpeningBalanceInCents' => UpdateBankAccountRequest::MAX_OPENING_BALANCE_IN_CENTS,
        ]);
    }

    /**
     * Update the name and the opening balance; the current balance moves by the same difference as the opening one.
     */
    public function update(UpdateBankAccountRequest $request): RedirectResponse
    {
        $account = BankAccount::current();

        if ($account === null) {
            return redirect()->route('bank-account.create');
        }

        $account->updateDetails(
            name: $request->string('name')->toString(),
            openingBalanceInCents: $request->integer('opening_balance_in_cents'),
        );

        Inertia::flash('success', 'Conta bancária atualizada com sucesso.');

        return redirect()->route('bank-account.show');
    }

    /**
     * The account fields sent to the pages.
     *
     * @return array{name: string, opening_balance_in_cents: int, current_balance_in_cents: int, opened_at: string}
     */
    private function toPageData(BankAccount $account): array
    {
        return [
            'name' => $account->name,
            'opening_balance_in_cents' => $account->opening_balance_in_cents,
            'current_balance_in_cents' => $account->current_balance_in_cents,
            'opened_at' => $account->opened_at->toIso8601String(),
        ];
    }
}
