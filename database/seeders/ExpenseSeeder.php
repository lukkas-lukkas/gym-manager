<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    /**
     * Months of expenses before and after the current one, so the reports have both history and a forecast.
     */
    private const MONTHS_AROUND_TODAY = 6;

    /**
     * Share of past expenses that end up paid; the rest stay pending, as overdue bills.
     */
    private const PAID_PERCENT = 90;

    /**
     * Bills a small gym pays every month: description, day of the month it is due, and the amount range in cents
     * (bills like energy change a little every month).
     *
     * @var list<array{description: string, due_day: int, min_cents: int, max_cents: int}>
     */
    private const MONTHLY_BILLS = [
        ['description' => 'Aluguel', 'due_day' => 5, 'min_cents' => 80_000, 'max_cents' => 80_000],
        ['description' => 'Energia elétrica', 'due_day' => 10, 'min_cents' => 22_000, 'max_cents' => 35_000],
        ['description' => 'Água', 'due_day' => 12, 'min_cents' => 6_000, 'max_cents' => 9_000],
        ['description' => 'Internet', 'due_day' => 15, 'min_cents' => 10_000, 'max_cents' => 10_000],
        ['description' => 'Limpeza', 'due_day' => 20, 'min_cents' => 30_000, 'max_cents' => 30_000],
    ];

    /**
     * Seed monthly bills from a few months ago to a few months ahead, plus an occasional one-off expense.
     *
     * Past bills are mostly paid, up to 3 days after the due date; future ones are pending. Skipped when there
     * are expenses already, so running it again adds nothing.
     */
    public function run(): void
    {
        if (Expense::query()->exists()) {
            return;
        }

        $currentMonth = CarbonImmutable::today()->startOfMonth();

        foreach (range(-self::MONTHS_AROUND_TODAY, self::MONTHS_AROUND_TODAY) as $monthOffset) {
            $month = $currentMonth->addMonthsNoOverflow($monthOffset);

            foreach (self::MONTHLY_BILLS as $bill) {
                $this->createExpense($bill['description'], fake()->numberBetween($bill['min_cents'], $bill['max_cents']), $month->setDay($bill['due_day']));
            }

            if (fake()->boolean(30)) {
                $this->createExpense('Manutenção de equipamentos', fake()->numberBetween(15_000, 60_000), $month->setDay(fake()->numberBetween(1, 28)));
            }
        }
    }

    /**
     * Save one expense: paid a few days after the due date when that day has passed (most of the time), pending otherwise.
     */
    private function createExpense(string $description, int $amountInCents, CarbonImmutable $dueOn): void
    {
        $paidAt = $dueOn->addDays(fake()->numberBetween(0, 3))->setTime(fake()->numberBetween(8, 18), fake()->numberBetween(0, 59));
        $isPaid = $paidAt->isPast() && fake()->boolean(self::PAID_PERCENT);

        Expense::create([
            'description' => $description,
            'amount_in_cents' => $amountInCents,
            'due_on' => $dueOn,
            'status' => $isPaid ? ExpenseStatus::Paid : ExpenseStatus::Pending,
            'paid_at' => $isPaid ? $paidAt : null,
        ]);
    }
}
