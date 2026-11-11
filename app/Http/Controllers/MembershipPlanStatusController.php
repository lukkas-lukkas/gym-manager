<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MembershipPlanStatusController extends Controller
{
    /**
     * Deactivate the given membership plan, so it can no longer be chosen for new enrollments.
     */
    public function deactivate(MembershipPlan $membershipPlan): RedirectResponse
    {
        $membershipPlan->deactivate();

        Inertia::flash('success', 'Plano desativado.');

        return redirect()->route('membership-plans.index');
    }
}
