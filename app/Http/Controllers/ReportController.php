<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportPeriodRequest;
use App\Reports\ActiveEnrollmentsByPlanReport;
use App\Reports\CashFlowReport;
use App\Reports\ExpenseReport;
use App\Reports\ReportPeriod;
use App\Reports\RevenueProjection;
use App\Reports\RevenueReport;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display the reports page for one period (`?from=YYYY-MM&to=YYYY-MM`, the default period when absent).
     *
     * Each chart is its own deferred prop in its own group, so the page opens right away and the charts load in
     * parallel requests: one slow chart never holds back the others.
     */
    public function index(ReportPeriodRequest $request): Response
    {
        $period = $request->period();

        return Inertia::render('reports/Index', [
            'from' => $period->firstMonth(),
            'to' => $period->lastMonth(),
            'maxMonths' => ReportPeriod::MAX_MONTHS,
            'months' => $period->months(),
            'cashFlow' => Inertia::defer(fn (): array => (new CashFlowReport($period))->toArray(), 'cashFlow'),
            'revenues' => Inertia::defer(fn (): array => (new RevenueReport($period))->toArray(), 'revenues'),
            'revenueProjection' => Inertia::defer(fn (): array => (new RevenueProjection($period))->toArray(), 'revenueProjection'),
            'expenses' => Inertia::defer(fn (): array => (new ExpenseReport($period))->toArray(), 'expenses'),
            'activeEnrollmentsByPlan' => Inertia::defer(fn (): array => (new ActiveEnrollmentsByPlanReport)->toArray(), 'activeEnrollmentsByPlan'),
        ]);
    }
}
