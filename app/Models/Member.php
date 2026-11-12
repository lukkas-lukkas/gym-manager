<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\RevenueStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'birth_date'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Every plan the member has been enrolled in, active and ended.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<Revenue, $this>
     */
    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    /**
     * The active or upcoming enrollment that ends last: the one a new enrollment must follow.
     *
     * Upcoming enrollments are saved as inactive with a start date in the future.
     */
    public function lastScheduledEnrollment(): ?Enrollment
    {
        return $this->enrollments()
            ->where(function (Builder $query): void {
                $query->where('status', EnrollmentStatus::Active)
                    ->orWhere('starts_on', '>', today()->toDateString());
            })
            ->orderByDesc('ends_on')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * When a new enrollment starts: the day after the last scheduled enrollment ends, or today if there is none.
     */
    public function nextEnrollmentStartsOn(?Enrollment $lastScheduledEnrollment): CarbonImmutable
    {
        return $lastScheduledEnrollment === null
            ? CarbonImmutable::today()
            : $lastScheduledEnrollment->ends_on->toImmutable()->addDay();
    }

    /**
     * Enroll the member in a plan, right after their last scheduled enrollment, and create its pending revenue.
     *
     * The new enrollment is only active when the member has no active plan; otherwise it is saved
     * as inactive and waits for its start date. The revenue keeps the plan price of today and is due when
     * the enrollment starts. Both are saved in one transaction: either both exist or neither does.
     * The member row is locked so two simultaneous requests (a double click, two open tabs) run one
     * after the other and never create two active enrollments.
     */
    public function enroll(MembershipPlan $plan): Enrollment
    {
        return DB::transaction(function () use ($plan): Enrollment {
            static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            $startsOn = $this->nextEnrollmentStartsOn($this->lastScheduledEnrollment());
            $hasActiveEnrollment = $this->enrollments()->where('status', EnrollmentStatus::Active)->exists();

            $enrollment = $this->enrollments()->create([
                'membership_plan_id' => $plan->id,
                'starts_on' => $startsOn,
                'ends_on' => $startsOn->addMonthsNoOverflow($plan->billing_period->months()),
                'status' => $hasActiveEnrollment || $startsOn->isFuture()
                    ? EnrollmentStatus::Inactive
                    : EnrollmentStatus::Active,
            ]);

            $enrollment->revenue()->create([
                'member_id' => $this->id,
                'price_in_cents' => $plan->price_in_cents,
                'due_on' => $startsOn,
                'status' => RevenueStatus::Pending,
            ]);

            return $enrollment;
        });
    }

    /**
     * Close the active enrollment once its end date has passed and move the member to their next plan.
     *
     * The next plan is the upcoming enrollment that follows the expired one. When there is none, an active
     * member is re-enrolled in the same plan starting today. A deactivated member only has the enrollment
     * closed. It loops so a member whose upcoming enrollment also expired (the scheduler was down for days)
     * catches up in one run. The member row is locked so this never races with a manual enrollment.
     */
    public function rollOverExpiredEnrollment(): void
    {
        DB::transaction(function (): void {
            $lockedMember = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            while ($expiredEnrollment = $this->expiredActiveEnrollment()) {
                $expiredEnrollment->update(['status' => EnrollmentStatus::Inactive]);

                if (! $lockedMember->isActive()) {
                    return;
                }

                $nextEnrollment = $this->enrollments()
                    ->where('status', EnrollmentStatus::Inactive)
                    ->where('starts_on', '>', $expiredEnrollment->ends_on->toDateString())
                    ->where('starts_on', '<=', today()->toDateString())
                    ->orderBy('starts_on')
                    ->orderBy('id')
                    ->first();

                if ($nextEnrollment !== null) {
                    $nextEnrollment->update(['status' => EnrollmentStatus::Active]);

                    continue;
                }

                if ($this->lastScheduledEnrollment() === null) {
                    $this->enroll($expiredEnrollment->membershipPlan);
                }

                return;
            }
        });
    }

    /**
     * The active enrollment whose last day was before today, if any.
     */
    private function expiredActiveEnrollment(): ?Enrollment
    {
        return $this->enrollments()
            ->where('status', EnrollmentStatus::Active)
            ->where('ends_on', '<', today()->toDateString())
            ->first();
    }

    /**
     * Whether the member is currently active (not deactivated).
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * Deactivate the member, keeping the original date if they were already inactive.
     */
    public function deactivate(): void
    {
        $this->deactivated_at ??= now();
        $this->save();
    }

    /**
     * Activate a previously deactivated member.
     */
    public function activate(): void
    {
        $this->deactivated_at = null;
        $this->save();
    }

    /**
     * Only members that are currently active.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * Only members that have been deactivated.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inactive(Builder $query): void
    {
        $query->whereNotNull('deactivated_at');
    }

    /**
     * Filter members by ID (with or without a leading "#"), name or e-mail.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $possibleId = ltrim($term, '#');

            if (ctype_digit($possibleId)) {
                $query->orWhere('id', (int) $possibleId);
            }

            $pattern = '%'.addcslashes($term, '\\%_').'%';

            $query->orWhere('name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern);
        });
    }
}
