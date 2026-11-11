<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Display the expenses list. Empty for now: it only gives the create flow a place to land.
     */
    public function index(): Response
    {
        return Inertia::render('expenses/Index');
    }

    /**
     * Show the form for creating a new expense.
     */
    public function create(): Response
    {
        return Inertia::render('expenses/Create', [
            'maxMonths' => StoreExpenseRequest::MAX_MONTHS,
        ]);
    }

    /**
     * Store the expense, repeated once a month for the chosen number of months.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $months = $request->integer('months');

        Expense::createMonthly(
            description: $request->string('description')->toString(),
            amountInCents: $request->integer('amount_in_cents'),
            firstDueOn: CarbonImmutable::createFromFormat('Y-m-d', $request->string('due_on')->toString())->startOfDay(),
            months: $months,
        );

        Inertia::flash('success', $months === 1 ? 'Despesa cadastrada com sucesso.' : "{$months} despesas cadastradas com sucesso.");

        return redirect()->route('expenses.index');
    }
}
