<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\RevenueStatus;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\Revenue;
use Carbon\CarbonImmutable;

/**
 * Money in and out per month, and the bank account balance at the end of each month: real up to last month,
 * forecast from the current month on.
 *
 * Inflow and outflow:
 * - Past months: what was actually paid (`paid_at`).
 * - Current month: what was paid so far, plus what is still due this month.
 * - Future months: what is due (`due_on`) and still pending.
 * - From the current month on, the inflow also has the renewals of the current plans (see {@see RevenueProjection}):
 *   revenues that don't exist yet, because a revenue is only created when its enrollment is. Without them the months
 *   ahead would only have the expenses, and the forecast would look much worse than it will be.
 *
 * Balance at the end of each month (only once the bank account exists, never before its opening balance):
 * - Past months are worked out backwards from the current balance: the end of October is today's balance minus
 *   what came in and plus what went out since then.
 * - The current and future months start from today's balance and add the same inflow and outflow as the bars:
 *   pending revenues due from today on plus renewals, minus pending expenses. Overdue revenues are left out (the
 *   member may never pay); overdue expenses are taken out in the current month (they still have to be paid).
 *
 * Five grouped queries plus the projection's one, each returning one sum per month, never the payment rows.
 */
final readonly class CashFlowReport
{
    use SumsAmountsByMonth;

    public function __construct(private ReportPeriod $period) {}

    /**
     * One value per month of the period, in the order of {@see ReportPeriod::months()}. Amounts in cents; a `null`
     * balance means the month has no point on that line.
     *
     * - `realizedBalance`: the real balance at the end of each past month.
     * - `forecastBalance`: the forecast balance at the end of the current and future months, renewals included. It also
     *   repeats the last real point (end of last month), so the two lines join.
     *
     * @return array{
     *     currentMonth: string,
     *     currentBalance: int|null,
     *     inflow: list<int>,
     *     outflow: list<int>,
     *     realizedBalance: list<int|null>,
     *     forecastBalance: list<int|null>,
     * }
     */
    public function toArray(): array
    {
        $now = CarbonImmutable::now();
        $currentMonth = $now->format('Y-m');
        $account = BankAccount::current();

        $paidRevenues = $this->paidByMonth(Revenue::class, RevenueStatus::Paid, 'price_in_cents', $now);
        $paidExpenses = $this->paidByMonth(Expense::class, ExpenseStatus::Paid, 'amount_in_cents', $now);
        $dueRevenues = $this->addRenewals($this->dueRevenuesByMonth($now), $currentMonth);
        $dueExpenses = $this->dueExpensesByMonth($now, $currentMonth);

        $inflow = [];
        $outflow = [];

        foreach ($this->period->months() as $month) {
            $inflow[] = $this->flowFor($month, $currentMonth, $paidRevenues, $dueRevenues);
            $outflow[] = $this->flowFor($month, $currentMonth, $paidExpenses, $dueExpenses);
        }

        $balances = $account === null
            ? ['realized' => [], 'forecast' => []]
            : $this->balancesByMonth($account, $currentMonth, $paidRevenues, $paidExpenses, $dueRevenues, $dueExpenses);

        $lastRealizedMonth = CarbonImmutable::createFromFormat('!Y-m', $currentMonth)->subMonthNoOverflow()->format('Y-m');

        return [
            'currentMonth' => $currentMonth,
            'currentBalance' => $account?->current_balance_in_cents,
            'inflow' => $inflow,
            'outflow' => $outflow,
            'realizedBalance' => array_map(
                fn (string $month): ?int => $month < $currentMonth ? ($balances['realized'][$month] ?? null) : null,
                $this->period->months(),
            ),
            'forecastBalance' => array_map(
                fn (string $month): ?int => match (true) {
                    $month >= $currentMonth => $balances['forecast'][$month] ?? null,
                    $month === $lastRealizedMonth => $balances['realized'][$month] ?? null,
                    default => null,
                },
                $this->period->months(),
            ),
        ];
    }

    /**
     * The revenues still to receive per month plus the projected renewals of the current plans.
     *
     * The renewals are projected from the current month (not from the start of the period): the forecast balance walks
     * forward from today, so a period that starts in the future still carries the renewals of the months before it.
     *
     * @param  array<string, int>  $dueRevenuesByMonth
     * @return array<string, int>
     */
    private function addRenewals(array $dueRevenuesByMonth, string $currentMonth): array
    {
        if ($currentMonth > $this->period->lastMonth()) {
            return $dueRevenuesByMonth;
        }

        $renewalsByMonth = (new RevenueProjection(ReportPeriod::fromMonths($currentMonth, $this->period->lastMonth())))->byMonth();

        foreach ($renewalsByMonth as $month => $amountInCents) {
            $dueRevenuesByMonth[$month] = ($dueRevenuesByMonth[$month] ?? 0) + $amountInCents;
        }

        return $dueRevenuesByMonth;
    }

    /**
     * What moved in one month: paid amounts in the past, due amounts in the future, both in the current month.
     *
     * @param  array<string, int>  $paidByMonth
     * @param  array<string, int>  $dueByMonth
     */
    private function flowFor(string $month, string $currentMonth, array $paidByMonth, array $dueByMonth): int
    {
        $paid = $month <= $currentMonth ? ($paidByMonth[$month] ?? 0) : 0;
        $due = $month >= $currentMonth ? ($dueByMonth[$month] ?? 0) : 0;

        return $paid + $due;
    }

    /**
     * The balance at the end of each month, walking backwards (real) and forwards (forecast) from today's balance.
     *
     * Walking backwards only reaches back to the month the opening balance was informed: before it, the system
     * doesn't know the balance. Every payment made after the end of that month is after the opening balance, so it
     * is one the current balance already counts.
     *
     * @param  array<string, int>  $paidRevenues
     * @param  array<string, int>  $paidExpenses
     * @param  array<string, int>  $dueRevenues
     * @param  array<string, int>  $dueExpenses
     * @return array{realized: array<string, int>, forecast: array<string, int>}
     */
    private function balancesByMonth(BankAccount $account, string $currentMonth, array $paidRevenues, array $paidExpenses, array $dueRevenues, array $dueExpenses): array
    {
        $openedMonth = $account->opened_at->format('Y-m');
        $realized = [];
        $balance = $account->current_balance_in_cents;
        $month = $currentMonth;

        while (true) {
            $balance -= ($paidRevenues[$month] ?? 0) - ($paidExpenses[$month] ?? 0);
            $month = CarbonImmutable::createFromFormat('!Y-m', $month)->subMonthNoOverflow()->format('Y-m');

            if ($month < $openedMonth || $month < $this->period->firstMonth()) {
                break;
            }

            $realized[$month] = $balance;
        }

        $forecast = [];
        $balance = $account->current_balance_in_cents;
        $month = $currentMonth;

        while ($month <= $this->period->lastMonth()) {
            $balance += ($dueRevenues[$month] ?? 0) - ($dueExpenses[$month] ?? 0);
            $forecast[$month] = $balance;
            $month = CarbonImmutable::createFromFormat('!Y-m', $month)->addMonthNoOverflow()->format('Y-m');
        }

        return ['realized' => $realized, 'forecast' => $forecast];
    }

    /**
     * Amounts paid per month, from the start of the period until now. Months after the end of the period are
     * included too: walking the balance back from today needs them.
     *
     * @param  class-string<Revenue|Expense>  $model
     * @return array<string, int>
     */
    private function paidByMonth(string $model, RevenueStatus|ExpenseStatus $paidStatus, string $amountColumn, CarbonImmutable $now): array
    {
        if ($this->period->startsOn->isAfter($now)) {
            return [];
        }

        return $this->sumByMonth(
            $model::query()
                ->where('status', $paidStatus)
                ->whereBetween('paid_at', [$this->period->startsOn, $now]),
            'paid_at',
            $amountColumn,
        );
    }

    /**
     * Pending revenues still to receive, per month of their due date, from today until the end of the period.
     * Overdue ones are left out: the member may never pay.
     *
     * @return array<string, int>
     */
    private function dueRevenuesByMonth(CarbonImmutable $now): array
    {
        if ($this->period->endsOn->isBefore($now->startOfDay())) {
            return [];
        }

        return $this->sumByMonth(
            Revenue::query()
                ->where('status', RevenueStatus::Pending)
                ->whereBetween('due_on', [$now->toDateString(), $this->period->endsOn->toDateString()]),
            'due_on',
            'price_in_cents',
        );
    }

    /**
     * Pending expenses still to pay, per month of their due date, until the end of the period. Overdue ones still have
     * to be paid, so they are counted in the current month.
     *
     * @return array<string, int>
     */
    private function dueExpensesByMonth(CarbonImmutable $now, string $currentMonth): array
    {
        if ($this->period->endsOn->isBefore($now->startOfDay())) {
            return [];
        }

        $dueByMonth = $this->sumByMonth(
            Expense::query()
                ->where('status', ExpenseStatus::Pending)
                ->whereBetween('due_on', [$now->toDateString(), $this->period->endsOn->toDateString()]),
            'due_on',
            'amount_in_cents',
        );

        $overdueInCents = (int) Expense::query()
            ->where('status', ExpenseStatus::Pending)
            ->where('due_on', '<', $now->toDateString())
            ->sum('amount_in_cents');

        $dueByMonth[$currentMonth] = ($dueByMonth[$currentMonth] ?? 0) + $overdueInCents;

        return $dueByMonth;
    }
}
