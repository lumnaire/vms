<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Report;
use App\Services\FishEntrancePdf;
use App\Services\FishEntranceReport;
use App\Services\SupplyReport;
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
        $entrance = FishEntranceReport::fromRequest($request);
        $report = $entrance->range;
        $content = app(FishEntrancePdf::class)->render($entrance);

        // A preview is read-only. Archive only a successfully generated download.
        if ($request->boolean('preview')) {
            return response($content)->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$report->filename().'"')
                ->header('Cache-Control', 'private, no-store');
        }

        // Archive a snapshot of what was handed out. report_type is an enum that
        // predates the period split, so the period lives in the data.
        Report::create([
            'generated_by' => Auth::id(),
            'report_type' => 'supply_summary',
            'report_date' => $report->start->toDateString(),
            'report_data' => [
                'period' => $report->period,
                'label' => $report->label(),
                'vendor_id' => $entrance->vendorId,
                'template' => $report->period,
                'totals' => $entrance->totals(),
                'total_price_basis' => 'stock_kg * price_per_kg',
            ],
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'generate_report',
            'description' => 'Generated '.strtolower($report->title()).' for '.$report->label().'.',
        ]);

        return response($content)->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$report->filename().'"')
            ->header('Cache-Control', 'private, no-store');
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
