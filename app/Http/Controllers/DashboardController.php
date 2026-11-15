<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Reports\DashboardSummary;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard: today's snapshot of the gym.
     *
     * The summary is a handful of small indexed queries, so it is sent with the page instead of deferred: one
     * request is faster than the page plus a second round trip.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'summary' => (new DashboardSummary)->toArray(),
            'upcomingExpenseDays' => DashboardSummary::UPCOMING_EXPENSE_DAYS,
        ]);
    }
}
