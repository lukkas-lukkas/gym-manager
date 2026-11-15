<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\RevenueStatus;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\Member;
use App\Models\Revenue;
use Carbon\CarbonImmutable;

/**
 * What the gym needs to see first thing in the day: the balance, how many members are active, how the month is
 * going (money in and out), the members who are late and the bills that are about to be due.
 *
 * Every number is a single aggregate query (`count`/`sum`) on an index, and the two lists are capped at
 * {@see self::LIST_LIMIT} rows, so the cost stays the same no matter how many members or payments the gym has.
 */
final readonly class DashboardSummary
{
    /** How many rows each list shows. The full list is one click away (revenues and expenses pages). */
    public const int LIST_LIMIT = 5;

    /** How many days ahead the "bills to pay" list looks. */
    public const int UPCOMING_EXPENSE_DAYS = 7;

    private CarbonImmutable $today;

    public function __construct()
    {
        $this->today = CarbonImmutable::today();
    }

    /**
     * Amounts in cents. `balanceInCents` is `null` while no bank account is registered.
     *
     * - `month.received` / `month.paid`: what was paid this month so far (`paid_at`).
     * - `month.toReceive` / `month.toPay`: what is still pending and due from today to the end of the month. Overdue
     *   amounts are left out here: they have their own block.
     *
     * @return array{
     *     balanceInCents: int|null,
     *     activeMembers: int,
     *     month: array{receivedInCents: int, toReceiveInCents: int, paidInCents: int, toPayInCents: int},
     *     overdueRevenues: array{
     *         count: int,
     *         totalInCents: int,
     *         oldest: list<array{id: int, member_id: int, member_name: string, due_on: string, price_in_cents: int}>,
     *     },
     *     upcomingExpenses: list<array{id: int, description: string, due_on: string, amount_in_cents: int, is_overdue: bool}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'balanceInCents' => BankAccount::current()?->current_balance_in_cents,
            'activeMembers' => Member::query()->active()->count(),
            'month' => $this->month(),
            'overdueRevenues' => $this->overdueRevenues(),
            'upcomingExpenses' => $this->upcomingExpenses(),
        ];
    }

    /**
     * Money in and out of the current month: four sums, on the `(status, paid_at)` and `(status, due_on)` / `due_on` indexes.
     *
     * @return array{receivedInCents: int, toReceiveInCents: int, paidInCents: int, toPayInCents: int}
     */
    private function month(): array
    {
        $monthPaidRange = [$this->today->startOfMonth(), $this->today->endOfMonth()];
        $restOfMonthRange = [$this->today->toDateString(), $this->today->endOfMonth()->toDateString()];

        return [
            'receivedInCents' => (int) Revenue::query()
                ->where('status', RevenueStatus::Paid)
                ->whereBetween('paid_at', $monthPaidRange)
                ->sum('price_in_cents'),
            'toReceiveInCents' => (int) Revenue::query()
                ->where('status', RevenueStatus::Pending)
                ->whereBetween('due_on', $restOfMonthRange)
                ->sum('price_in_cents'),
            'paidInCents' => (int) Expense::query()
                ->where('status', ExpenseStatus::Paid)
                ->whereBetween('paid_at', $monthPaidRange)
                ->sum('amount_in_cents'),
            'toPayInCents' => (int) Expense::query()
                ->where('status', ExpenseStatus::Pending)
                ->whereBetween('due_on', $restOfMonthRange)
                ->sum('amount_in_cents'),
        ];
    }

    /**
     * Pending revenues past their due date: how many, how much, and the oldest ones (the members to call first).
     *
     * The count and the total come from one query; the list reads only {@see self::LIST_LIMIT} rows. Both walk the
     * `(status, due_on)` index.
     *
     * @return array{
     *     count: int,
     *     totalInCents: int,
     *     oldest: list<array{id: int, member_id: int, member_name: string, due_on: string, price_in_cents: int}>,
     * }
     */
    private function overdueRevenues(): array
    {
        $overdue = fn () => Revenue::query()
            ->where('status', RevenueStatus::Pending)
            ->where('due_on', '<', $this->today->toDateString());

        $totals = $overdue()
            ->toBase()
            ->selectRaw('count(*) as overdue_count, coalesce(sum(price_in_cents), 0) as overdue_total')
            ->first();

        $oldest = $overdue()
            ->select(['id', 'member_id', 'due_on', 'price_in_cents'])
            ->with('member:id,name')
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Revenue $revenue): array => [
                'id' => $revenue->id,
                'member_id' => $revenue->member_id,
                'member_name' => $revenue->member->name,
                'due_on' => $revenue->due_on->toDateString(),
                'price_in_cents' => $revenue->price_in_cents,
            ])
            ->all();

        return [
            'count' => (int) $totals->overdue_count,
            'totalInCents' => (int) $totals->overdue_total,
            'oldest' => $oldest,
        ];
    }

    /**
     * Pending expenses due up to {@see self::UPCOMING_EXPENSE_DAYS} days from today, overdue ones included (they
     * still have to be paid), oldest due date first.
     *
     * @return list<array{id: int, description: string, due_on: string, amount_in_cents: int, is_overdue: bool}>
     */
    private function upcomingExpenses(): array
    {
        return Expense::query()
            ->select(['id', 'description', 'due_on', 'amount_in_cents'])
            ->where('status', ExpenseStatus::Pending)
            ->where('due_on', '<=', $this->today->addDays(self::UPCOMING_EXPENSE_DAYS)->toDateString())
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'description' => $expense->description,
                'due_on' => $expense->due_on->toDateString(),
                'amount_in_cents' => $expense->amount_in_cents,
                'is_overdue' => $expense->due_on->isBefore($this->today),
            ])
            ->all();
    }
}
