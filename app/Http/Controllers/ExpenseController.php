<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ExpenseStatus;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
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
                ->through(fn (Expense $expense): array => $this->toPageData($expense)),
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
     * Store the expense, repeated once a month for the chosen number of months, and show the month of the first one.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $months = $request->integer('months');
        $firstDueOn = CarbonImmutable::createFromFormat('Y-m-d', $request->string('due_on')->toString())->startOfDay();

        Expense::createMonthly(
            description: $request->string('description')->toString(),
            amountInCents: $request->integer('amount_in_cents'),
            firstDueOn: $firstDueOn,
            months: $months,
        );

        Inertia::flash('success', $months === 1 ? 'Despesa cadastrada com sucesso.' : "{$months} despesas cadastradas com sucesso.");

        return redirect()->route('expenses.index', ['month' => $firstDueOn->format('Y-m')]);
    }

    /**
     * Show the form for editing an expense.
     */
    public function edit(Expense $expense): Response
    {
        return Inertia::render('expenses/Edit', [
            'expense' => $this->toPageData($expense),
            'statuses' => ExpenseStatus::options(),
        ]);
    }

    /**
     * Update the given expense and go back to the list on the month it is now due.
     *
     * `paid_at` follows the status: becoming paid stamps the current time, an expense that was already paid
     * keeps its original time, and going back to pending clears it.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $isPaid = $request->enum('status', ExpenseStatus::class) === ExpenseStatus::Paid;

        $expense->update([
            ...$request->validated(),
            'paid_at' => $isPaid ? ($expense->paid_at ?? now()) : null,
        ]);

        Inertia::flash('success', 'Despesa atualizada com sucesso.');

        return redirect()->route('expenses.index', ['month' => $expense->due_on->format('Y-m')]);
    }

    /**
     * The expense fields sent to the pages, with the status already translated.
     *
     * @return array{id: int, description: string, amount_in_cents: int, due_on: string, status: string, status_label: string}
     */
    private function toPageData(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'amount_in_cents' => $expense->amount_in_cents,
            'due_on' => $expense->due_on->toDateString(),
            'status' => $expense->status->value,
            'status_label' => $expense->status->label(),
        ];
    }
}
