<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared by the money reports: adds up an amount per month in the database.
 */
trait SumsAmountsByMonth
{
    /**
     * Adds up `$amountColumn` per month of `$dateColumn`, returning only one sum per month, never the rows.
     *
     * The query should already filter `$dateColumn` to the period, so the database can use its index.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array<string, int> Amount in cents keyed by `YYYY-MM`, only for months that have rows.
     */
    private function sumByMonth(Builder $query, string $dateColumn, string $amountColumn): array
    {
        return $query->toBase()
            ->selectRaw("DATE_FORMAT({$dateColumn}, '%Y-%m') as month, SUM({$amountColumn}) as total")
            ->groupBy('month')
            ->pluck('total', 'month')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }
}
