<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Reports\ActiveEnrollmentsByPlanReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActiveEnrollmentsByPlanReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_only_active_enrollments_with_the_most_popular_plan_first(): void
    {
        $monthly = MembershipPlan::factory()->create(['name' => 'Mensal']);
        $yearly = MembershipPlan::factory()->create(['name' => 'Anual']);
        Enrollment::factory(3)->for($monthly)->create();
        Enrollment::factory(2)->for($monthly)->create(['status' => EnrollmentStatus::Inactive]);
        Enrollment::factory()->for($yearly)->create();

        $this->assertSame([
            ['name' => 'Mensal', 'is_deactivated' => false, 'active_enrollments' => 3],
            ['name' => 'Anual', 'is_deactivated' => false, 'active_enrollments' => 1],
        ], (new ActiveEnrollmentsByPlanReport)->toArray());
    }

    public function test_plans_with_the_same_count_are_sorted_by_name(): void
    {
        foreach (['Trimestral', 'Anual', 'Mensal'] as $name) {
            Enrollment::factory()->for(MembershipPlan::factory()->create(['name' => $name]))->create();
        }

        $names = array_column((new ActiveEnrollmentsByPlanReport)->toArray(), 'name');

        $this->assertSame(['Anual', 'Mensal', 'Trimestral'], $names);
    }

    public function test_plan_on_sale_without_members_is_listed_with_zero(): void
    {
        MembershipPlan::factory()->create(['name' => 'Semestral']);

        $this->assertSame(
            [['name' => 'Semestral', 'is_deactivated' => false, 'active_enrollments' => 0]],
            (new ActiveEnrollmentsByPlanReport)->toArray(),
        );
    }

    public function test_deactivated_plan_is_listed_only_while_someone_is_still_on_it(): void
    {
        $oldPlanWithMembers = MembershipPlan::factory()->inactive()->create(['name' => 'Promoção 2025']);
        Enrollment::factory()->for($oldPlanWithMembers)->create();
        $oldPlanWithoutMembers = MembershipPlan::factory()->inactive()->create(['name' => 'Promoção 2024']);
        Enrollment::factory()->for($oldPlanWithoutMembers)->create(['status' => EnrollmentStatus::Inactive]);

        $this->assertSame(
            [['name' => 'Promoção 2025', 'is_deactivated' => true, 'active_enrollments' => 1]],
            (new ActiveEnrollmentsByPlanReport)->toArray(),
        );
    }

    public function test_reports_page_loads_it_as_a_deferred_prop(): void
    {
        Enrollment::factory()->for(MembershipPlan::factory()->create(['name' => 'Mensal']))->create();

        $this->actingAs(User::factory()->create())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('activeEnrollmentsByPlan')
                ->loadDeferredProps('activeEnrollmentsByPlan', fn (Assert $reload) => $reload
                    ->where('activeEnrollmentsByPlan', [['name' => 'Mensal', 'is_deactivated' => false, 'active_enrollments' => 1]])
                )
            );
    }
}
