<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
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
     * Enroll the member in a plan, right after their last scheduled enrollment.
     *
     * The new enrollment is only active when the member has no active plan; otherwise it is saved
     * as inactive and waits for its start date. The member row is locked so two simultaneous requests
     * (a double click, two open tabs) run one after the other and never create two active enrollments.
     */
    public function enroll(MembershipPlan $plan): Enrollment
    {
        return DB::transaction(function () use ($plan): Enrollment {
            static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            $startsOn = $this->nextEnrollmentStartsOn($this->lastScheduledEnrollment());
            $hasActiveEnrollment = $this->enrollments()->where('status', EnrollmentStatus::Active)->exists();

            return $this->enrollments()->create([
                'membership_plan_id' => $plan->id,
                'starts_on' => $startsOn,
                'ends_on' => $startsOn->addMonthsNoOverflow($plan->billing_period->months()),
                'status' => $hasActiveEnrollment || $startsOn->isFuture()
                    ? EnrollmentStatus::Inactive
                    : EnrollmentStatus::Active,
            ]);
        });
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
