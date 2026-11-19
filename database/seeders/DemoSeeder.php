<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Enums\EnrollmentStatus;
use App\Enums\ExpenseStatus;
use App\Enums\RevenueStatus;
use App\Models\BankAccount;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Revenue;
use Carbon\CarbonImmutable;
use Faker\Generator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A gym with a year and a half of history and six months of bills ahead, to show the dashboard and the reports with
 * real-looking numbers: a growing member base, revenues paid month after month, a few late members, recurring bills
 * and the forecast of what is still to come.
 *
 * It is what {@see DatabaseSeeder} runs. Run it on an empty database: `sail artisan migrate:fresh --seed`. Every date is relative to today,
 * and the random numbers come from a fixed seed, so running it again on another day gives the same story.
 *
 * The rows follow the same rules the app uses (see {@see Member::enroll()} and {@see Member::rollOverExpiredEnrollment()}):
 * enrollments follow one another with no gap, only the one covering today is active, every enrollment has its revenue
 * due on its first day, and the bank account balance is the opening balance plus every payment since it was opened.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /** How many months of history the gym has, before the current one. */
    private const HISTORY_MONTHS = 18;

    /** How many months of bills ahead are already registered (repeated expenses are created up front). */
    private const FUTURE_MONTHS = 6;

    private const OPENING_BALANCE_IN_CENTS = 2_000_000;

    /**
     * Plans on sale and how often each one is picked by a new member (weights add up to 100).
     *
     * @var list<array{name: string, price_in_cents: int, billing_period: BillingPeriod, weight: int}>
     */
    private const PLANS = [
        ['name' => 'Mensal', 'price_in_cents' => 12_000, 'billing_period' => BillingPeriod::Monthly, 'weight' => 45],
        ['name' => 'Trimestral', 'price_in_cents' => 33_000, 'billing_period' => BillingPeriod::Quarterly, 'weight' => 25],
        ['name' => 'Semestral', 'price_in_cents' => 60_000, 'billing_period' => BillingPeriod::Semiannual, 'weight' => 12],
        ['name' => 'Anual', 'price_in_cents' => 110_000, 'billing_period' => BillingPeriod::Yearly, 'weight' => 18],
    ];

    /** A promotion sold for a few months a year ago and then taken off sale: its members keep it until they leave. */
    private const PROMOTIONAL_PLAN = ['name' => 'Anual Promocional', 'price_in_cents' => 90_000, 'billing_period' => BillingPeriod::Yearly];

    /** Months (counted from the first month of history) when the promotional plan was on sale. */
    private const PROMOTION_MONTHS = [6, 7, 8];

    /**
     * Chance, in percent, that a member leaves when their plan ends instead of renewing it.
     *
     * @var array<string, int>
     */
    private const CHURN_PERCENT_BY_PERIOD = [
        'monthly' => 5,
        'quarterly' => 9,
        'semiannual' => 12,
        'yearly' => 15,
    ];

    /** Chance, in percent, that a member's current plan is still unpaid after its due date. */
    private const LATE_PAYMENT_PERCENT = 6;

    /** Chance, in percent, that a member whose plan ends in the next few weeks has already renewed it. */
    private const EARLY_RENEWAL_PERCENT = 60;

    private CarbonImmutable $now;

    private CarbonImmutable $firstMonth;

    private Generator $faker;

    /** @var array<string, true> */
    private array $usedEmails = [];

    /**
     * Seed the user, the plans, the members with their enrollments and revenues, the expenses and the bank account.
     *
     * @throws RuntimeException when the gym already has data, since seeding again would duplicate it.
     */
    public function run(): void
    {
        $this->ensureDatabaseIsEmpty();

        mt_srand(2026);
        $this->faker = fake('pt_BR');
        $this->faker->seed(2026);

        $this->now = CarbonImmutable::now();
        $this->firstMonth = $this->now->startOfMonth()->subMonthsNoOverflow(self::HISTORY_MONTHS);

        $this->call(UserSeeder::class);

        DB::transaction(function (): void {
            $account = BankAccount::query()->forceCreate([
                'name' => 'Conta principal',
                'opening_balance_in_cents' => self::OPENING_BALANCE_IN_CENTS,
                'current_balance_in_cents' => self::OPENING_BALANCE_IN_CENTS,
                'opened_at' => $this->firstMonth,
            ]);

            [$plans, $promotionalPlan] = $this->seedPlans();
            $this->seedMembers($plans, $promotionalPlan);
            $this->seedExpenses();

            $account->forceFill(['current_balance_in_cents' => $account->expectedBalanceInCents()])->save();
        });
    }

    /**
     * The seeder builds a whole history and only makes sense on an empty gym: running it twice would duplicate every
     * row and open a second bank account. The user is not checked, since {@see UserSeeder} can run again safely.
     */
    private function ensureDatabaseIsEmpty(): void
    {
        $hasData = BankAccount::query()->exists()
            || MembershipPlan::query()->exists()
            || Member::query()->exists()
            || Expense::query()->exists();

        if ($hasData) {
            throw new RuntimeException('The database already has gym data. Run it on an empty database: `sail artisan migrate:fresh --seed`.');
        }
    }

    /**
     * The plans on sale, plus the promotional one, already taken off sale.
     *
     * @return array{0: list<array{plan: MembershipPlan, weight: int}>, 1: MembershipPlan}
     */
    private function seedPlans(): array
    {
        $plans = array_map(fn (array $plan): array => [
            'plan' => MembershipPlan::query()->forceCreate([
                'name' => $plan['name'],
                'price_in_cents' => $plan['price_in_cents'],
                'billing_period' => $plan['billing_period'],
                'created_at' => $this->firstMonth,
            ]),
            'weight' => $plan['weight'],
        ], self::PLANS);

        $promotionalPlan = MembershipPlan::query()->forceCreate(self::PROMOTIONAL_PLAN + [
            'created_at' => $this->firstMonth->addMonthsNoOverflow(self::PROMOTION_MONTHS[0]),
            'deactivated_at' => $this->firstMonth->addMonthsNoOverflow(max(self::PROMOTION_MONTHS) + 1),
        ]);

        return [$plans, $promotionalPlan];
    }

    /**
     * New members join every month: a big first group when the gym opened, then a steady flow with a peak in the
     * first months of the year (New Year's resolutions). Each one gets their whole plan history up to today.
     *
     * @param  list<array{plan: MembershipPlan, weight: int}>  $plans
     */
    private function seedMembers(array $plans, MembershipPlan $promotionalPlan): void
    {
        for ($offset = 0; $offset <= self::HISTORY_MONTHS; $offset++) {
            $month = $this->firstMonth->addMonthsNoOverflow($offset);
            $lastJoinDay = $offset === self::HISTORY_MONTHS ? $this->now->day : $month->daysInMonth;

            for ($i = 0; $i < $this->newMembersIn($month, $offset); $i++) {
                $joinedOn = $month->setDay(mt_rand(1, $lastJoinDay));
                $isPromotion = in_array($offset, self::PROMOTION_MONTHS, true) && mt_rand(1, 100) <= 35;

                $this->seedMember($joinedOn, $isPromotion ? $promotionalPlan : $this->pickPlan($plans));
            }
        }
    }

    /**
     * How many members join in a month. The current month only gets the share of the days gone by.
     */
    private function newMembersIn(CarbonImmutable $month, int $offset): int
    {
        $count = match ($offset) {
            0 => 45,
            1 => 20,
            default => 9 + mt_rand(0, 4),
        };

        $count += match ($month->month) {
            1 => 9,
            2 => 6,
            3 => 4,
            default => 0,
        };

        if ($offset === self::HISTORY_MONTHS) {
            $count = (int) round($count * $this->now->day / $month->daysInMonth);
        }

        return $count;
    }

    /**
     * @param  list<array{plan: MembershipPlan, weight: int}>  $plans
     */
    private function pickPlan(array $plans): MembershipPlan
    {
        $roll = mt_rand(1, 100);

        foreach ($plans as $plan) {
            $roll -= $plan['weight'];

            if ($roll <= 0) {
                return $plan['plan'];
            }
        }

        return $plans[0]['plan'];
    }

    /**
     * Create the member and every enrollment they had, one right after the other, until the one covering today.
     *
     * When a plan ends in the past the member either renews it (same plan) or leaves, and is deactivated the day
     * after. The plan covering today is active; when it ends soon, the member may have renewed it already, which
     * creates an upcoming (inactive) enrollment with its revenue still to receive.
     */
    private function seedMember(CarbonImmutable $joinedOn, MembershipPlan $plan): void
    {
        $member = $this->createMember($joinedOn);
        $today = $this->now->startOfDay();
        $startsOn = $joinedOn;

        while (true) {
            $endsOn = $startsOn->addMonthsNoOverflow($plan->billing_period->months());
            $isCurrent = $endsOn->greaterThanOrEqualTo($today);

            $this->createEnrollment($member, $plan, $startsOn, $endsOn, $isCurrent ? EnrollmentStatus::Active : EnrollmentStatus::Inactive, $isCurrent);

            if ($isCurrent) {
                if ($endsOn->lessThan($today->addWeeks(3)) && mt_rand(1, 100) <= self::EARLY_RENEWAL_PERCENT) {
                    $nextStartsOn = $endsOn->addDay();
                    $nextEndsOn = $nextStartsOn->addMonthsNoOverflow($plan->billing_period->months());

                    $this->createEnrollment($member, $plan, $nextStartsOn, $nextEndsOn, EnrollmentStatus::Inactive, false);
                }

                return;
            }

            if (mt_rand(1, 100) <= self::CHURN_PERCENT_BY_PERIOD[$plan->billing_period->value]) {
                $member->forceFill(['deactivated_at' => $endsOn->addDay()->setTime(mt_rand(8, 20), mt_rand(0, 59))])->save();

                return;
            }

            $startsOn = $endsOn->addDay();
        }
    }

    private function createMember(CarbonImmutable $joinedOn): Member
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $email = Str::slug("{$firstName} {$lastName}", '.');
        $suffix = 1;

        while (isset($this->usedEmails[$email.($suffix > 1 ? $suffix : '')])) {
            $suffix++;
        }

        $email .= $suffix > 1 ? $suffix : '';
        $this->usedEmails[$email] = true;

        $member = new Member;
        $member->forceFill([
            'name' => "{$firstName} {$lastName}",
            'email' => "{$email}@example.com",
            'birth_date' => $this->now->subYears(mt_rand(16, 62))->subDays(mt_rand(0, 364))->toDateString(),
            'created_at' => $joinedOn->setTime(mt_rand(7, 21), mt_rand(0, 59)),
        ])->save();

        return $member;
    }

    /**
     * Create the enrollment and its revenue, due on its first day.
     *
     * A revenue due in the past is paid (on the day or a few days late), unless it belongs to the member's current
     * plan and falls in the small share of late payers: those are the overdue revenues of the dashboard. A revenue
     * due today may already be paid; one due in the future is still pending.
     */
    private function createEnrollment(Member $member, MembershipPlan $plan, CarbonImmutable $startsOn, CarbonImmutable $endsOn, EnrollmentStatus $status, bool $mayBeLate): void
    {
        $createdAt = $startsOn->isFuture() ? $this->now->subDays(mt_rand(0, 6)) : $startsOn->setTime(mt_rand(7, 21), mt_rand(0, 59));

        $enrollment = Enrollment::query()->forceCreate([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $paidAt = $this->revenuePaidAt($startsOn, $mayBeLate);

        Revenue::query()->forceCreate([
            'enrollment_id' => $enrollment->id,
            'member_id' => $member->id,
            'price_in_cents' => $plan->price_in_cents,
            'due_on' => $startsOn,
            'status' => $paidAt === null ? RevenueStatus::Pending : RevenueStatus::Paid,
            'paid_at' => $paidAt,
            'created_at' => $createdAt,
            'updated_at' => $paidAt ?? $createdAt,
        ]);
    }

    /**
     * When the revenue due on `$dueOn` was paid, or `null` while it is still pending.
     */
    private function revenuePaidAt(CarbonImmutable $dueOn, bool $mayBeLate): ?CarbonImmutable
    {
        $today = $this->now->startOfDay();

        if ($dueOn->greaterThan($today)) {
            return null;
        }

        if ($dueOn->equalTo($today)) {
            return mt_rand(1, 100) <= 50 ? $this->now->subMinutes(mt_rand(1, 60)) : null;
        }

        if ($mayBeLate && $dueOn->greaterThan($today->subDays(60)) && mt_rand(1, 100) <= self::LATE_PAYMENT_PERCENT) {
            return null;
        }

        $roll = mt_rand(1, 100);
        $daysLate = match (true) {
            $roll <= 70 => 0,
            $roll <= 90 => mt_rand(1, 3),
            default => mt_rand(4, 10),
        };

        return $this->notAfterNow($dueOn->addDays($daysLate)->setTime(mt_rand(7, 21), mt_rand(0, 59)));
    }

    /**
     * The bills of every month, from the opening of the account until {@see self::FUTURE_MONTHS} months ahead, plus a
     * few one-off ones. Bills due before today are paid (on the day or a little early), except this month's electricity,
     * left overdue; the rest is pending.
     */
    private function seedExpenses(): void
    {
        $rows = [];

        for ($offset = 0; $offset <= self::HISTORY_MONTHS + self::FUTURE_MONTHS; $offset++) {
            $month = $this->firstMonth->addMonthsNoOverflow($offset);

            foreach ($this->monthlyBills($month, $offset) as $bill) {
                $rows[] = $this->expenseRow($bill['description'], $bill['amount_in_cents'], $month->setDay($bill['due_day']));
            }
        }

        foreach ($this->oneOffBills() as $bill) {
            $rows[] = $this->expenseRow($bill['description'], $bill['amount_in_cents'], $bill['due_on']);
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Expense::query()->insert($chunk);
        }
    }

    /**
     * The recurring bills of one month. Rent is adjusted once a year, the payroll grows when a new instructor is
     * hired as the gym grows, and electricity goes up in the summer (air conditioning).
     *
     * @return list<array{description: string, due_day: int, amount_in_cents: int}>
     */
    private function monthlyBills(CarbonImmutable $month, int $offset): array
    {
        $isSummer = in_array($month->month, [12, 1, 2, 3], true);

        return [
            ['description' => 'Aluguel', 'due_day' => 5, 'amount_in_cents' => $offset < 12 ? 350_000 : 367_500],
            ['description' => 'Folha de pagamento (instrutores)', 'due_day' => 5, 'amount_in_cents' => $offset < 9 ? 380_000 : 520_000],
            ['description' => 'Energia elétrica', 'due_day' => 10, 'amount_in_cents' => ($isSummer ? 155_000 : 110_000) + mt_rand(0, 15_000)],
            ['description' => 'Água', 'due_day' => 12, 'amount_in_cents' => 26_000 + mt_rand(0, 6_000)],
            ['description' => 'Internet', 'due_day' => 15, 'amount_in_cents' => 14_990],
            ['description' => 'Limpeza e higiene', 'due_day' => 20, 'amount_in_cents' => 60_000],
            ['description' => 'Software de gestão', 'due_day' => 22, 'amount_in_cents' => 9_990],
            ['description' => 'Marketing digital', 'due_day' => 25, 'amount_in_cents' => 40_000],
        ];
    }

    /**
     * Bills that happen once: equipment bought after the busy start of the year, quarterly maintenance, a summer
     * campaign and a locker-room renovation already planned for the coming months.
     *
     * @return list<array{description: string, amount_in_cents: int, due_on: CarbonImmutable}>
     */
    private function oneOffBills(): array
    {
        $bills = [
            ['description' => 'Campanha de matrículas de verão', 'amount_in_cents' => 250_000, 'due_on' => $this->firstMonth->addMonthsNoOverflow(7)->setDay(8)],
            ['description' => 'Compra de equipamentos (2 esteiras e 1 leg press)', 'amount_in_cents' => 850_000, 'due_on' => $this->firstMonth->addMonthsNoOverflow(10)->setDay(18)],
            ['description' => 'Reforma do vestiário', 'amount_in_cents' => 600_000, 'due_on' => $this->now->startOfMonth()->addMonthsNoOverflow(3)->setDay(15)],
        ];

        for ($offset = 2; $offset <= self::HISTORY_MONTHS + self::FUTURE_MONTHS; $offset += 3) {
            $bills[] = [
                'description' => 'Manutenção preventiva dos equipamentos',
                'amount_in_cents' => 45_000,
                'due_on' => $this->firstMonth->addMonthsNoOverflow($offset)->setDay(18),
            ];
        }

        return $bills;
    }

    /**
     * @return array{description: string, amount_in_cents: int, due_on: string, status: string, paid_at: string|null, created_at: string, updated_at: string}
     */
    private function expenseRow(string $description, int $amountInCents, CarbonImmutable $dueOn): array
    {
        $isOverdueOnPurpose = $description === 'Energia elétrica' && $dueOn->isSameMonth($this->now);
        $isPaid = $dueOn->lessThan($this->now->startOfDay()) && ! $isOverdueOnPurpose;

        $paidAt = $isPaid
            ? $this->notAfterNow($dueOn->subDays(mt_rand(0, 2))->setTime(mt_rand(8, 18), mt_rand(0, 59)))->max($this->firstMonth)
            : null;

        $createdAt = $dueOn->isFuture() ? $this->now->startOfMonth() : $dueOn->startOfMonth();

        return [
            'description' => $description,
            'amount_in_cents' => $amountInCents,
            'due_on' => $dueOn->toDateString(),
            'status' => ($isPaid ? ExpenseStatus::Paid : ExpenseStatus::Pending)->value,
            'paid_at' => $paidAt?->toDateTimeString(),
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => ($paidAt ?? $createdAt)->toDateTimeString(),
        ];
    }

    private function notAfterNow(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->greaterThan($this->now) ? $this->now->subMinutes(mt_rand(1, 60)) : $moment;
    }
}
