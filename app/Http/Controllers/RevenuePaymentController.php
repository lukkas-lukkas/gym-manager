<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Revenue;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RevenuePaymentController extends Controller
{
    /**
     * Register the payment of the given revenue and go back to the page it was paid from
     * (revenues list or member's plan history), falling back to the member's plan history.
     */
    public function store(Revenue $revenue): RedirectResponse
    {
        Inertia::flash('success', $revenue->markAsPaid() ? 'Pagamento registrado.' : 'Este pagamento já estava registrado.');

        return redirect()->back(fallback: route('members.enrollments.index', $revenue->member_id));
    }
}
