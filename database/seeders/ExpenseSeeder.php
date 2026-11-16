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
     * Basic bills a small gym pays every month: description, day of the month it is due, and amount in cents.
     *
     * @var list<array{description: string, due_day: int, amount_in_cents: int}>
     */
    private const MONTHLY_BILLS = [
        ['description' => 'Aluguel', 'due_day' => 5, 'amount_in_cents' => 80_000],
        ['description' => 'Energia elétrica', 'due_day' => 10, 'amount_in_cents' => 28_000],
        ['description' => 'Água', 'due_day' => 12, 'amount_in_cents' => 7_500],
        ['description' => 'Internet', 'due_day' => 15, 'amount_in_cents' => 10_000],
    ];

    /**
     * Seed this month's basic bills, all pending, so they can be paid from the app.
     *
     * Skipped when there are expenses already, so running it again adds nothing.
     */
    public function run(): void
    {
        if (Expense::query()->exists()) {
            return;
        }

        $currentMonth = CarbonImmutable::today()->startOfMonth();

        foreach (self::MONTHLY_BILLS as $bill) {
            Expense::create([
                'description' => $bill['description'],
                'amount_in_cents' => $bill['amount_in_cents'],
                'due_on' => $currentMonth->setDay($bill['due_day']),
                'status' => ExpenseStatus::Pending,
            ]);
        }
    }
}
