<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevenueStatus;
use Database\Factories\RevenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
}
