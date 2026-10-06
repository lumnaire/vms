<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Report;
use App\Services\SupplyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Staff supply reports: confirmed fish supply and pulled-out releases per day, month or year, previewed
 * on screen and downloaded as a PDF.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $report = $this->resolve($request);

        return view('staff.reports', ['report' => $report] + $report->build());
    }

    public function pdf(Request $request)
    {
        $report = $this->resolve($request);
        $data = $report->build();

        // Archive a snapshot of what was handed out. report_type is an enum that
        // predates the period split, so the period lives in the data.
        Report::create([
            'generated_by' => Auth::id(),
            'report_type' => 'supply_summary',
            'report_date' => $report->start->toDateString(),
            'report_data' => [
                'period' => $report->period,
                'label' => $report->label(),
                'totals' => $data['totals'],
                'by_fish' => $data['byFish']->all(),
                'pull_outs' => $data['pullOuts']->all(),
            ],
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'generate_report',
            'description' => 'Generated '.strtolower($report->title()).' for '.$report->label().'.',
        ]);

        return Pdf::loadView('staff.reports-pdf', [
            'report' => $report,
            'generatedBy' => Auth::user(),
            'generatedAt' => now(),
        ] + $data)
            ->setPaper('a4', 'portrait')
            ->download($report->filename());
    }

    private function resolve(Request $request): SupplyReport
    {
        $period = $request->input('period', 'daily');

        $value = match ($period) {
            'monthly' => $request->input('month'),
            'yearly' => $request->input('year'),
            default => $request->input('date'),
        };

        return SupplyReport::for($period, $value);
    }
}
