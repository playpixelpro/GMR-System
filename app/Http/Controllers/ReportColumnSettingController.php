<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ReportColumnSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportColumnSettingController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless(
            auth()->user()?->hasRole('ADMINISTRATOR', 'RMEC'),
            403,
            'Unauthorized. Column visibility settings are accessible only to authorized administrators.'
        );
    }

    public function edit(): View
    {
        $this->authorizeAdmin();

        $amrAvailable = ReportColumnSetting::availableColumns('amr');
        $pmrAvailable = ReportColumnSetting::availableColumns('pmr');

        $amrVisible = ReportColumnSetting::forReport('amr');
        $pmrVisible = ReportColumnSetting::forReport('pmr');

        return view('settings.reports.columns', [
            'amrAvailable' => $amrAvailable,
            'pmrAvailable' => $pmrAvailable,
            'amrVisible' => $amrVisible,
            'pmrVisible' => $pmrVisible,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'amr_columns' => ['nullable', 'array'],
            'amr_columns.*' => ['string'],
            'pmr_columns' => ['nullable', 'array'],
            'pmr_columns.*' => ['string'],
        ]);

        $amrColumns = $validated['amr_columns'] ?? [];
        $pmrColumns = $validated['pmr_columns'] ?? [];

        $validAmrKeys = array_keys(ReportColumnSetting::availableColumns('amr'));
        $validPmrKeys = array_keys(ReportColumnSetting::availableColumns('pmr'));

        $amrFiltered = array_values(array_intersect($amrColumns, $validAmrKeys));
        $pmrFiltered = array_values(array_intersect($pmrColumns, $validPmrKeys));

        $amrSetting = ReportColumnSetting::setForReport('amr', $amrFiltered);
        $pmrSetting = ReportColumnSetting::setForReport('pmr', $pmrFiltered);

        AuditLog::record('REPORT_COLUMNS_UPDATED', $amrSetting, [
            'amr_columns' => $amrFiltered,
            'pmr_columns' => $pmrFiltered,
        ], 'settings', 'Report column visibility settings updated');

        return redirect()->route('settings.reports.columns')
            ->with('status', 'Report column visibility settings successfully saved.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        ReportColumnSetting::setForReport('amr', ReportColumnSetting::defaultColumns('amr'));
        ReportColumnSetting::setForReport('pmr', ReportColumnSetting::defaultColumns('pmr'));

        return redirect()->route('settings.reports.columns')
            ->with('status', 'Report columns have been reset to default visibility.');
    }
}
