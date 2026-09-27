<?php

namespace App\Http\Controllers;

use App\Models\GmrReportSignatory;
use App\Services\GmrReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GmrReportConfigController extends Controller
{
    public function __construct(
        protected GmrReportService $reportService,
    ) {}

    /**
     * Ensure only RMEC and ADMINISTRATOR users can access this module.
     */
    private function authorizeRmec(): void
    {
        abort_unless(
            auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR'),
            403,
            'Unauthorized. GMR Report Configuration is accessible only to authorized RMEC users.',
        );
    }

    /**
     * Display the GMR report configuration page.
     */
    public function edit(): View
    {
        $this->authorizeRmec();

        $config = $this->reportService->getConfiguration();
        $signatories = GmrReportSignatory::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return view('reports.gmr-config', [
            'config' => $config,
            'signatories' => $signatories,
        ]);
    }

    /**
     * Update the GMR report configuration settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorizeRmec();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['required', 'string', 'max:255'],
            'region_text' => ['required', 'string', 'max:255'],
            'branch_text' => ['nullable', 'string', 'max:255'],
            'paper_size' => ['required', 'string', 'in:A4,Letter,Legal,Short Bond,Long Bond,Custom'],
            'custom_width' => ['nullable', 'required_if:paper_size,Custom', 'numeric', 'min:1', 'max:1000'],
            'custom_height' => ['nullable', 'required_if:paper_size,Custom', 'numeric', 'min:1', 'max:1000'],
            'custom_unit' => ['required', 'string', 'in:in,mm,cm'],
            'orientation' => ['required', 'string', 'in:portrait,landscape'],
            'margin_top' => ['required', 'numeric', 'min:0', 'max:50'],
            'margin_right' => ['required', 'numeric', 'min:0', 'max:50'],
            'margin_bottom' => ['required', 'numeric', 'min:0', 'max:50'],
            'margin_left' => ['required', 'numeric', 'min:0', 'max:50'],
            'margin_unit' => ['required', 'string', 'in:in,mm,cm'],
        ]);

        $config = $this->reportService->getConfiguration();
        $config->update($validated);

        return redirect()
            ->route('gmr.config.edit')
            ->with('status', 'GMR Report configuration successfully saved.');
    }

    /**
     * Add a new signatory.
     */
    public function storeSignatory(Request $request): RedirectResponse
    {
        $this->authorizeRmec();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'role_group' => ['nullable', 'string', 'in:member,chairperson,coa,reviewer'],
            'display_order' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['role_group'] = $validated['role_group'] ?? 'member';

        GmrReportSignatory::create($validated);

        return redirect()
            ->route('gmr.config.edit')
            ->with('status', 'Signatory added successfully.');
    }

    /**
     * Update an existing signatory.
     */
    public function updateSignatory(Request $request, GmrReportSignatory $signatory): RedirectResponse
    {
        $this->authorizeRmec();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'role_group' => ['nullable', 'string', 'in:member,chairperson,coa,reviewer'],
            'display_order' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['role_group'] = $validated['role_group'] ?? 'member';

        $signatory->update($validated);

        return redirect()
            ->route('gmr.config.edit')
            ->with('status', "Signatory '{$signatory->name}' updated successfully.");
    }

    /**
     * Delete a signatory.
     */
    public function destroySignatory(GmrReportSignatory $signatory): RedirectResponse
    {
        $this->authorizeRmec();

        $name = $signatory->name;
        $signatory->delete();

        return redirect()
            ->route('gmr.config.edit')
            ->with('status', "Signatory '{$name}' deleted successfully.");
    }

    /**
     * Toggle active state of a signatory.
     */
    public function toggleSignatory(GmrReportSignatory $signatory): RedirectResponse
    {
        $this->authorizeRmec();

        $signatory->update([
            'is_active' => ! $signatory->is_active,
        ]);

        $state = $signatory->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('gmr.config.edit')
            ->with('status', "Signatory '{$signatory->name}' {$state}.");
    }

    /**
     * Preview the GMR report using the current configuration and active signatories.
     */
    public function preview(): View
    {
        $this->authorizeRmec();

        $config = $this->reportService->getConfiguration();
        $signatories = $this->reportService->getActiveSignatories();
        $sampleRows = $this->reportService->getSampleRows();

        return view('reports.gmr-print', [
            'rows' => $sampleRows,
            'config' => $config,
            'signatories' => $signatories,
            'branchName' => $config->branch_text ?: 'North Cotabato Branch',
            'isPreview' => true,
        ]);
    }
}
