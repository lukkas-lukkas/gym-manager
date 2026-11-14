<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\EnrollmentStatus;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Builder;

/**
 * How many enrollments each plan has active today. A snapshot of now: it doesn't depend on the period.
 *
 * Plans still on sale are always listed, even with no one on them (a plan nobody buys is worth seeing).
 * Deactivated plans are only listed while someone is still on them: members keep their plan until it ends.
 *
 * The plans are few, so they are read with their count in a single query (one count per plan, on the enrollments
 * `membership_plan_id` index); the enrollment rows are never loaded.
 */
final readonly class ActiveEnrollmentsByPlanReport
{
    /**
     * Plans with the most active enrollments first, then by name.
     *
     * @return list<array{name: string, is_deactivated: bool, active_enrollments: int}>
     */
    public function toArray(): array
    {
        return MembershipPlan::query()
            ->select(['id', 'name', 'deactivated_at'])
            ->withCount(['enrollments as active_enrollments_count' => function (Builder $query): void {
                $query->where('status', EnrollmentStatus::Active);
            }])
            ->get()
            ->filter(fn (MembershipPlan $plan): bool => $plan->isActive() || $plan->active_enrollments_count > 0)
            ->sortBy([['active_enrollments_count', 'desc'], ['name', 'asc']])
            ->map(fn (MembershipPlan $plan): array => [
                'name' => $plan->name,
                'is_deactivated' => ! $plan->isActive(),
                'active_enrollments' => (int) $plan->active_enrollments_count,
            ])
            ->values()
            ->all();
    }
}
