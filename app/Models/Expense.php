<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['description', 'amount_in_cents', 'due_on', 'status'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_in_cents' => 'integer',
            'due_on' => 'date:Y-m-d',
            'status' => ExpenseStatus::class,
        ];
    }

    /**
     * Only expenses due within the month of the given date.
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
     * Create the same expense once a month, starting on `$firstDueOn`. All of them start as pending.
     *
     * Each due date is counted from the first one (not from the previous month), so an expense due on
     * Jan 31 falls on Feb 28 and goes back to Mar 31. Everything is saved in a single insert inside a
     * transaction: either all months are created or none.
     */
    public static function createMonthly(string $description, int $amountInCents, CarbonImmutable $firstDueOn, int $months): void
    {
        $now = now();

        $rows = array_map(fn (int $month): array => [
            'description' => $description,
            'amount_in_cents' => $amountInCents,
            'due_on' => $firstDueOn->addMonthsNoOverflow($month)->toDateString(),
            'status' => ExpenseStatus::Pending->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], range(0, $months - 1));

        DB::transaction(fn () => static::query()->insert($rows));
    }
}
