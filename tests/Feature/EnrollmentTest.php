<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_lists_all_their_enrollments_including_ended_ones(): void
    {
        $member = Member::factory()->create();
        $endedEnrollment = Enrollment::factory()->for($member)->create(['status' => EnrollmentStatus::Inactive]);
        $activeEnrollment = Enrollment::factory()->for($member)->create();
        Enrollment::factory()->create();

        $this->assertEqualsCanonicalizing(
            [$endedEnrollment->id, $activeEnrollment->id],
            $member->enrollments->pluck('id')->all(),
        );
    }

    public function test_enrollment_belongs_to_a_member(): void
    {
        $member = Member::factory()->create();
        $enrollment = Enrollment::factory()->for($member)->create();

        $this->assertTrue($enrollment->member->is($member));
    }
}
