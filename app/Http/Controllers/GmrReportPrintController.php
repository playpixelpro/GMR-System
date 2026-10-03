<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Pile;
use App\Services\GmrReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GmrReportPrintController extends Controller
{
    public function __construct(protected GmrReportService $reportService) {}

    /**
     * Generate the print-ready GMR report for selected records.
     */
    public function print(
        Request $request,
    ): View|Response|RedirectResponse|StreamedResponse {
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
                ->with(
                    'error',
                    'Please select at least one GMR record to print the report.',
                );
        }

        // Require a branch selection to scope the report
        $user = $request->user();
        if ($user && $user->hasRole('STAFF') && $user->branch_id) {
            $branchId = (int) $user->branch_id;
        } else {
            $branchId = $request->input('report_branch_id');
            if (empty($branchId)) {
                $firstPile = Pile::with('warehouse')
                    ->whereIn('id', $pileIds)
                    ->first();
                $branchId =
                    $firstPile?->branch_id ??
                    $firstPile?->warehouse?->branch_id;
            }
        }

        if (empty($branchId)) {
            return redirect()
                ->route('gmr.summary')
                ->with(
                    'error',
                    'Please select a branch before printing the report.',
                );
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
                        fn ($warehouseQuery) => $warehouseQuery->where(
                            'branch_id',
                            $branchId,
                        ),
                    );
            })
            ->pluck('id')
            ->all();

        if (empty($allowedPileIds)) {
            return redirect()
                ->route('gmr.summary')
                ->with(
                    'error',
                    'None of the selected records belong to the chosen branch.',
                );
        }

        $rows = $this->reportService->getRowsForPiles($allowedPileIds);

        if ($rows->isEmpty()) {
            return redirect()
                ->route('gmr.summary')
                ->with(
                    'error',
                    'No valid GMR data found for the selected records.',
                );
        }

        $action = $request->boolean('excel')
            ? 'GMR_EXCEL_EXPORTED'
            : ($request->boolean('pdf')
                ? 'GMR_PDF_EXPORTED'
                : 'GMR_PRINTED');
        $description = $request->boolean('excel')
            ? 'GMR report exported as Excel'
            : ($request->boolean('pdf')
                ? 'GMR report exported as PDF'
                : 'GMR report generated');

        AuditLog::create([
            'module' => 'gmr',
            'action' => $action,
            'description' => $description,
            'branch_id' => $branchId,
            'branch_name' => $branch->name,
            'ip_address' => $request->ip(),
            'metadata' => [
                'branch_id' => $branchId,
                'pile_count' => count($allowedPileIds),
                'pdf' => $request->boolean('pdf'),
                'excel' => $request->boolean('excel'),
            ],
        ]);

        $config = $this->reportService->getConfiguration();
        $signatories = $this->reportService->getActiveSignatories();

        // Use branch name from the selected branch
        $branchName = $this->reportService->formatBranchName(
            $config->branch_text ?: $branch->name,
        );

        if ($request->boolean('pdf')) {
            return $this->reportService->generatePdf(
                $rows,
                $config,
                $signatories,
                $branchName,
            );
        }

        if ($request->boolean('excel')) {
            return $this->reportService->exportExcel(
                $rows,
                $config,
                $signatories,
                $branchName,
            );
        }

        $printUrlWithPdf = route('gmr.report.print', [
            'selected_piles' => implode(',', $allowedPileIds),
            'report_branch_id' => $branchId,
            'pdf' => 1,
        ]);

        $printUrlWithExcel = route('gmr.report.print', [
            'selected_piles' => implode(',', $allowedPileIds),
            'report_branch_id' => $branchId,
            'excel' => 1,
        ]);

        return view('reports.gmr-print', [
            'rows' => $rows,
            'config' => $config,
            'signatories' => $signatories,
            'branchName' => $branchName,
            'visibleColumns' => $config->getVisibleReportColumns(),
            'printUrlWithPdf' => $printUrlWithPdf,
            'printUrlWithExcel' => $printUrlWithExcel,
            'selectedPileIds' => $allowedPileIds,
            'isPreview' => false,
        ]);
    }
}
