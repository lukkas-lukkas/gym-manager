<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Revenue;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RevenueController extends Controller
{
    /**
     * Display the revenues due in one month (`?month=YYYY-MM`, current month by default), oldest due date first,
     * with the member and plan of each one.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : CarbonImmutable::today()->startOfMonth();

        return Inertia::render('revenues/Index', [
            'month' => $month->format('Y-m'),
            'previousMonth' => $month->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->addMonthNoOverflow()->format('Y-m'),
            'revenues' => Revenue::query()
                ->select(['id', 'enrollment_id', 'member_id', 'price_in_cents', 'due_on', 'status'])
                ->with([
                    'member:id,name',
                    'enrollment:id,membership_plan_id',
                    'enrollment.membershipPlan:id,name',
                ])
                ->dueIn($month)
                ->orderBy('due_on')
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Revenue $revenue): array => [
                    'id' => $revenue->id,
                    'member_id' => $revenue->member_id,
                    'member_name' => $revenue->member->name,
                    'plan_name' => $revenue->enrollment->membershipPlan->name,
                    'price_in_cents' => $revenue->price_in_cents,
                    'due_on' => $revenue->due_on->toDateString(),
                    'status' => $revenue->status->value,
                    'status_label' => $revenue->status->label(),
                    'is_overdue' => $revenue->isOverdue(),
                ]),
        ]);
    }
}
