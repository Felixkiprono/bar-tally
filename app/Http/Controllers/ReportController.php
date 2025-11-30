<?php

namespace App\Http\Controllers;

use App\Services\Report\ReportService;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function show(Request $request)
    {
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : Carbon::now()->endOfMonth();
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);

        $profitAndLoss = $this->reportService->getProfitAndLoss($tenant, $startDate, $endDate);
        $balanceSheet = $this->reportService->getBalanceSheet($tenant);
        $cashFlow = $this->reportService->getCashFlow($tenant, $startDate, $endDate);

        return view('reports.financial', compact('profitAndLoss', 'balanceSheet', 'cashFlow'));
    }

    public function downloadPdf(Request $request)
    {
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : Carbon::now()->endOfMonth();
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);

        $profitAndLoss = $this->reportService->getProfitAndLoss($tenant, $startDate, $endDate);
        $balanceSheet = $this->reportService->getBalanceSheet($tenant);
        $cashFlow = $this->reportService->getCashFlow($tenant, $startDate, $endDate);

        $pdf = PDF::loadView('reports.financial-pdf', compact('profitAndLoss', 'balanceSheet', 'cashFlow', 'startDate', 'endDate'));

        return $pdf->download('financial-report.pdf');
    }
}