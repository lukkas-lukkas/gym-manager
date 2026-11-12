<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Models\Member;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Throwable;

#[Signature('enrollments:roll-over')]
#[Description('Close active enrollments that ended before today and move each member to their next plan')]
class RollOverExpiredEnrollments extends Command
{
    /**
     * Execute the console command.
     *
     * Only members with an expired active enrollment are loaded, in chunks by ID, so the run stays
     * light no matter how many members the gym has. A member that fails is reported and skipped, so
     * one bad record never blocks the others; the next scheduled run retries it.
     */
    public function handle(): int
    {
        $rolledOverCount = 0;
        $failedCount = 0;

        Member::query()
            ->whereHas('enrollments', function (Builder $query): void {
                $query->where('status', EnrollmentStatus::Active)
                    ->where('ends_on', '<', today()->toDateString());
            })
            ->lazyById()
            ->each(function (Member $member) use (&$rolledOverCount, &$failedCount): void {
                Context::scope(function () use ($member, &$rolledOverCount, &$failedCount): void {
                    try {
                        $member->rollOverExpiredEnrollment();
                        $rolledOverCount++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failedCount++;
                    }
                }, ['member_id' => $member->id]);
            });

        $this->info("Rolled over {$rolledOverCount} member(s).");

        if ($failedCount > 0) {
            $this->error("Failed to roll over {$failedCount} member(s). See the logs for details.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
