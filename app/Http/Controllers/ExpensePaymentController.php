<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ExpensePaymentController extends Controller
{
    /**
     * Register the payment of the given expense and go back to the page it was paid from (keeping its month and page),
     * falling back to the expenses list on the month the expense is due.
     */
    public function store(Expense $expense): RedirectResponse
    {
        Inertia::flash('success', $expense->markAsPaid() ? 'Pagamento registrado.' : 'Este pagamento já estava registrado.');

        return redirect()->back(fallback: route('expenses.index', ['month' => $expense->due_on->format('Y-m')]));
    }
}
