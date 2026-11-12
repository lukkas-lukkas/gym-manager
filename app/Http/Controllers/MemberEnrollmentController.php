<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Member;
use App\Models\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberEnrollmentController extends Controller
{
    /**
     * Display the member's plan history (the "Planos" tab of the member page), most recent first, with the
     * payment of each plan. The price comes from the revenue, which keeps the price of the enrollment day;
     * enrollments created before revenues existed fall back to the current plan price.
     */
    public function index(Member $member): Response
    {
        return Inertia::render('members/Enrollments', [
            'member' => $member->only(['id', 'name']) + [
                'deactivated_at' => $member->deactivated_at?->toIso8601String(),
            ],
            'enrollments' => $member->enrollments()
                ->select(['id', 'member_id', 'membership_plan_id', 'starts_on', 'ends_on', 'status'])
                ->with([
                    'membershipPlan:id,name,price_in_cents,billing_period',
                    'revenue:id,enrollment_id,price_in_cents,due_on,status,paid_at',
                ])
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->paginate(15)
                ->through(fn (Enrollment $enrollment): array => [
                    'id' => $enrollment->id,
                    'plan_name' => $enrollment->membershipPlan->name,
                    'price_in_cents' => $enrollment->revenue->price_in_cents ?? $enrollment->membershipPlan->price_in_cents,
                    'billing_period_label' => $enrollment->membershipPlan->billing_period->label(),
                    'starts_on' => $enrollment->starts_on->toDateString(),
                    'ends_on' => $enrollment->ends_on->toDateString(),
                    'status' => $enrollment->status->value,
                    'revenue' => $enrollment->revenue === null ? null : [
                        'id' => $enrollment->revenue->id,
                        'due_on' => $enrollment->revenue->due_on->toDateString(),
                        'status' => $enrollment->revenue->status->value,
                        'status_label' => $enrollment->revenue->status->label(),
                        'is_overdue' => $enrollment->revenue->isOverdue(),
                        'paid_at' => $enrollment->revenue->paid_at?->toIso8601String(),
                    ],
                ]),
        ]);
    }

    /**
     * Show the page for enrolling the member in a new plan.
     *
     * The new plan starts the day after the member's last scheduled plan (active or upcoming) ends,
     * so there is no gap or overlap between them. A member with no scheduled plan starts today.
     */
    public function create(Member $member): Response
    {
        $lastScheduledEnrollment = $member->lastScheduledEnrollment()?->load('membershipPlan:id,name');

        return Inertia::render('members/CreateEnrollment', [
            'member' => $member->only(['id', 'name']),
            'previousEnrollment' => $lastScheduledEnrollment === null ? null : [
                'plan_name' => $lastScheduledEnrollment->membershipPlan->name,
                'ends_on' => $lastScheduledEnrollment->ends_on->toDateString(),
            ],
            'startsOn' => $member->nextEnrollmentStartsOn($lastScheduledEnrollment)->toDateString(),
            'plans' => MembershipPlan::query()
                ->select(['id', 'name', 'price_in_cents', 'billing_period'])
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn (MembershipPlan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price_in_cents' => $plan->price_in_cents,
                    'billing_period' => $plan->billing_period->value,
                    'billing_period_label' => $plan->billing_period->label(),
                ]),
        ]);
    }

    /**
     * Enroll the member in the chosen plan. See {@see Member::enroll()} for how the dates and status are set.
     */
    public function store(StoreEnrollmentRequest $request, Member $member): RedirectResponse
    {
        $member->enroll(MembershipPlan::findOrFail($request->integer('membership_plan_id')));

        Inertia::flash('success', 'Aluno matriculado com sucesso.');

        return redirect()->route('members.enrollments.index', $member);
    }
}
