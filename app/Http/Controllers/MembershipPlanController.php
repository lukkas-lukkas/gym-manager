<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BillingPeriod;
use App\Http\Requests\StoreMembershipPlanRequest;
use App\Http\Requests\UpdateMembershipPlanRequest;
use App\Models\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MembershipPlanController extends Controller
{
    /**
     * Display a paginated list of active membership plans.
     */
    public function index(): Response
    {
        return Inertia::render('membership-plans/Index', [
            'plans' => MembershipPlan::query()
                ->select(['id', 'name', 'price_in_cents', 'billing_period'])
                ->active()
                ->orderBy('name')
                ->paginate(15)
                ->through(fn (MembershipPlan $plan): array => $this->toPageData($plan)),
        ]);
    }

    /**
     * Show the form for creating a new membership plan.
     */
    public function create(): Response
    {
        return Inertia::render('membership-plans/Create', [
            'billingPeriods' => BillingPeriod::options(),
        ]);
    }

    /**
     * Store a newly created membership plan.
     */
    public function store(StoreMembershipPlanRequest $request): RedirectResponse
    {
        MembershipPlan::create($request->validated());

        Inertia::flash('success', 'Plano cadastrado com sucesso.');

        return redirect()->route('membership-plans.index');
    }

    /**
     * Show the form for editing a membership plan.
     */
    public function edit(MembershipPlan $membershipPlan): Response
    {
        return Inertia::render('membership-plans/Edit', [
            'plan' => $this->toPageData($membershipPlan),
        ]);
    }

    /**
     * Rename the given membership plan. See {@see UpdateMembershipPlanRequest} for why only the name changes.
     */
    public function update(UpdateMembershipPlanRequest $request, MembershipPlan $membershipPlan): RedirectResponse
    {
        $membershipPlan->update($request->validated());

        Inertia::flash('success', 'Plano atualizado com sucesso.');

        return redirect()->route('membership-plans.index');
    }

    /**
     * The plan fields sent to the pages, with the billing period already translated.
     *
     * @return array{id: int, name: string, price_in_cents: int, billing_period: string, billing_period_label: string}
     */
    private function toPageData(MembershipPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'price_in_cents' => $plan->price_in_cents,
            'billing_period' => $plan->billing_period->value,
            'billing_period_label' => $plan->billing_period->label(),
        ];
    }
}
