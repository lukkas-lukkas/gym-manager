<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevenueStatus;
use Carbon\CarbonImmutable;
use Database\Factories\RevenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'price_in_cents', 'due_on', 'status', 'paid_at'])]
class Revenue extends Model
{
    /** @use HasFactory<RevenueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_in_cents' => 'integer',
            'due_on' => 'date:Y-m-d',
            'status' => RevenueStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Only revenues due within the month of the given date.
     *
     * Compares the plain `due_on` column with a range, so the `due_on` index can be used.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dueIn(Builder $query, CarbonImmutable $month): void
    {
        $query->whereBetween('due_on', [
            $month->startOfMonth()->toDateString(),
            $month->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * A pending revenue whose due date has passed. It is worked out when read, so it is never out of date.
     */
    public function isOverdue(): bool
    {
        return $this->status === RevenueStatus::Pending && $this->due_on->isBefore(today());
    }

    /**
     * Mark the revenue as paid now, returning whether it was still pending.
     *
     * The update only matches a pending row, so a double click or two open tabs pay it once and keep the first `paid_at`.
     */
    public function markAsPaid(): bool
    {
        $paidAt = now();

        $wasPending = static::query()
            ->whereKey($this->getKey())
            ->where('status', RevenueStatus::Pending)
            ->update(['status' => RevenueStatus::Paid, 'paid_at' => $paidAt]) === 1;

        if ($wasPending) {
            $this->forceFill(['status' => RevenueStatus::Paid, 'paid_at' => $paidAt])->syncOriginal();
        }

        return $wasPending;
    }
}
