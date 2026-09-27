<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Pile;
use App\Services\GmrReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GmrReportPrintController extends Controller
{
    public function __construct(
        protected GmrReportService $reportService,
    ) {}

    /**
     * Generate the print-ready GMR report for selected records.
     */
    public function print(Request $request): View|Response|RedirectResponse
    {
        $rawPiles = $request->input('selected_piles');

        if (is_string($rawPiles)) {
            $pileIds = array_filter(array_map('trim', explode(',', $rawPiles)));
        } elseif (is_array($rawPiles)) {
            $pileIds = array_filter($rawPiles);
        } else {
            $pileIds = [];
        }

        if (empty($pileIds)) {
            return redirect()
                ->route('gmr.summary')
                ->with('error', 'Please select at least one GMR record to print the report.');
        }

        // Require a branch selection to scope the report
        $branchId = $request->input('report_branch_id');
        if (empty($branchId)) {
            return redirect()
                ->route('gmr.summary')
                ->with('error', 'Please select a branch before printing the report.');
        }

        $branch = Branch::find($branchId);
        if (! $branch) {
            return redirect()
                ->route('gmr.summary')
                ->with('error', 'The selected branch does not exist.');
        }

        // Filter pile IDs to only include piles belonging to the selected branch
        $allowedPileIds = Pile::whereIn('id', $pileIds)
            ->where(function ($query) use ($branchId): void {
                $query
                    ->where('branch_id', $branchId)
                    ->orWhereHas(
                        'warehouse',
                        fn ($warehouseQuery) => $warehouseQuery->where('branch_id', $branchId),
                    );
            })
            ->pluck('id')
            ->all();

        if (empty($allowedPileIds)) {
            return redirect()
                ->route('gmr.summary')
                ->with('error', 'None of the selected records belong to the chosen branch.');
        }

        $rows = $this->reportService->getRowsForPiles($allowedPileIds);

        if ($rows->isEmpty()) {
            return redirect()
                ->route('gmr.summary')
                ->with('error', 'No valid GMR data found for the selected records.');
        }

        $config = $this->reportService->getConfiguration();
        $signatories = $this->reportService->getActiveSignatories();

        // Use branch name from the selected branch
        $branchName = $config->branch_text ?: $branch->name;

        if ($request->boolean('pdf')) {
            return $this->reportService->generatePdf($rows, $config, $signatories, $branchName);
        }

        $printUrlWithPdf = route('gmr.report.print', [
            'selected_piles' => implode(',', $allowedPileIds),
            'report_branch_id' => $branchId,
            'pdf' => 1,
        ]);

        return view('reports.gmr-print', [
            'rows' => $rows,
            'config' => $config,
            'signatories' => $signatories,
            'branchName' => $branchName,
            'printUrlWithPdf' => $printUrlWithPdf,
            'selectedPileIds' => $allowedPileIds,
            'isPreview' => false,
        ]);
    }
}
