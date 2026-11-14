<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingPeriod;
use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'price_in_cents', 'billing_period'])]
class MembershipPlan extends Model
{
    /** @use HasFactory<MembershipPlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_in_cents' => 'integer',
            'billing_period' => BillingPeriod::class,
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Every enrollment in this plan, active and ended.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Whether the plan is currently active (available to be sold).
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * Stop selling the plan, keeping the original date if it was already inactive.
     *
     * Existing enrollments are not touched: members already on this plan keep it.
     */
    public function deactivate(): void
    {
        $this->deactivated_at ??= now();
        $this->save();
    }

    /**
     * Only plans that are currently active.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * Only plans that have been deactivated.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inactive(Builder $query): void
    {
        $query->whereNotNull('deactivated_at');
    }
}
