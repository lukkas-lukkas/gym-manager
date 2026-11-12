<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Member;
use App\Models\MembershipPlan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollOverExpiredEnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-11 00:01:00');
    }

    public function test_expired_enrollment_is_closed_and_the_upcoming_one_becomes_active(): void
    {
        $member = Member::factory()->create();
        $expiredEnrollment = Enrollment::factory()->for($member)->create([
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-11-10',
        ]);
        $upcomingEnrollment = Enrollment::factory()->for($member)->create([
            'starts_on' => '2026-11-11',
            'ends_on' => '2026-12-11',
            'status' => EnrollmentStatus::Inactive,
        ]);

        $this->artisan('enrollments:roll-over')->assertSuccessful();

        $this->assertSame(EnrollmentStatus::Inactive, $expiredEnrollment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $upcomingEnrollment->fresh()->status);
        $this->assertSame(2, $member->enrollments()->count());
    }

    public function test_active_member_without_upcoming_plan_is_re_enrolled_in_the_same_plan_starting_today(): void
    {
        $member = Member::factory()->create();
        $quarterlyPlan = MembershipPlan::factory()->create(['billing_period' => BillingPeriod::Quarterly]);
        $expiredEnrollment = Enrollment::factory()->for($member)->for($quarterlyPlan)->create([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-11-01',
        ]);

        $this->artisan('enrollments:roll-over')->assertSuccessful();

        $this->assertSame(EnrollmentStatus::Inactive, $expiredEnrollment->fresh()->status);

        $renewedEnrollment = $member->enrollments()->whereKeyNot($expiredEnrollment->id)->sole();
        $this->assertSame($quarterlyPlan->id, $renewedEnrollment->membership_plan_id);
        $this->assertSame('2026-11-11', $renewedEnrollment->starts_on->toDateString());
        $this->assertSame('2027-02-11', $renewedEnrollment->ends_on->toDateString());
        $this->assertSame(EnrollmentStatus::Active, $renewedEnrollment->status);
    }

    public function test_deactivated_member_only_has_the_expired_enrollment_closed(): void
    {
        $member = Member::factory()->create(['deactivated_at' => now()->subWeek()]);
        $expiredEnrollment = Enrollment::factory()->for($member)->create([
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-11-10',
        ]);
        $upcomingEnrollment = Enrollment::factory()->for($member)->create([
            'starts_on' => '2026-11-11',
            'ends_on' => '2026-12-11',
            'status' => EnrollmentStatus::Inactive,
        ]);

        $this->artisan('enrollments:roll-over')->assertSuccessful();

        $this->assertSame(EnrollmentStatus::Inactive, $expiredEnrollment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Inactive, $upcomingEnrollment->fresh()->status);
        $this->assertSame(2, $member->enrollments()->count());
    }

    public function test_enrollment_ending_today_is_kept_active(): void
    {
        $enrollment = Enrollment::factory()->create([
            'starts_on' => '2026-10-11',
            'ends_on' => '2026-11-11',
        ]);

        $this->artisan('enrollments:roll-over')->assertSuccessful();

        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
        $this->assertSame(1, Enrollment::query()->count());
    }

    public function test_member_catches_up_when_the_upcoming_enrollment_also_expired(): void
    {
        $member = Member::factory()->create();
        $plan = MembershipPlan::factory()->create(['billing_period' => BillingPeriod::Monthly]);
        $firstEnrollment = Enrollment::factory()->for($member)->for($plan)->create([
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-10-01',
        ]);
        $secondEnrollment = Enrollment::factory()->for($member)->for($plan)->create([
            'starts_on' => '2026-10-02',
            'ends_on' => '2026-11-02',
            'status' => EnrollmentStatus::Inactive,
        ]);

        $this->artisan('enrollments:roll-over')->assertSuccessful();

        $this->assertSame(EnrollmentStatus::Inactive, $firstEnrollment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Inactive, $secondEnrollment->fresh()->status);
        $this->assertSame(1, $member->enrollments()->where('status', EnrollmentStatus::Active)->count());
        $this->assertSame(
            '2026-11-11',
            $member->enrollments()->where('status', EnrollmentStatus::Active)->sole()->starts_on->toDateString(),
        );
    }

    public function test_command_is_scheduled_daily_at_one_minute_past_midnight_in_sao_paulo(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'enrollments:roll-over'));

        $this->assertNotNull($event);
        $this->assertSame('1 0 * * *', $event->expression);
        $this->assertSame('America/Sao_Paulo', $event->timezone);
    }
}
