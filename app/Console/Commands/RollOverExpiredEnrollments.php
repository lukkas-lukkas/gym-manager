<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Models\Member;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('enrollments:roll-over')]
#[Description('Close active enrollments that ended before today and move each member to their next plan')]
class RollOverExpiredEnrollments extends Command
{
    /**
     * Execute the console command.
     *
     * Only members with an expired active enrollment are loaded, in chunks by ID, so the run stays
     * light no matter how many members the gym has.
     */
    public function handle(): int
    {
        $rolledOverCount = 0;

        Member::query()
            ->whereHas('enrollments', function (Builder $query): void {
                $query->where('status', EnrollmentStatus::Active)
                    ->where('ends_on', '<', today()->toDateString());
            })
            ->lazyById()
            ->each(function (Member $member) use (&$rolledOverCount): void {
                $member->rollOverExpiredEnrollment();
                $rolledOverCount++;
            });

        $this->info("Rolled over {$rolledOverCount} member(s).");

        return self::SUCCESS;
    }
}
