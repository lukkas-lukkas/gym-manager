<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Display the expenses due in one month (`?month=YYYY-MM`, current month by default), oldest due date first.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : CarbonImmutable::today()->startOfMonth();

        return Inertia::render('expenses/Index', [
            'month' => $month->format('Y-m'),
            'previousMonth' => $month->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->addMonthNoOverflow()->format('Y-m'),
            'expenses' => Expense::query()
                ->select(['id', 'description', 'amount_in_cents', 'due_on', 'status'])
                ->dueIn($month)
                ->orderBy('due_on')
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Expense $expense): array => [
                    'id' => $expense->id,
                    'description' => $expense->description,
                    'amount_in_cents' => $expense->amount_in_cents,
                    'due_on' => $expense->due_on->toDateString(),
                    'status' => $expense->status->value,
                    'status_label' => $expense->status->label(),
                ]),
        ]);
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
