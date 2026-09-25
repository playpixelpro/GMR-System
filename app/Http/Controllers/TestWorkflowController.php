<?php

namespace App\Http\Controllers;

use App\Models\AmrRecord;
use App\Models\AuditLog;
use App\Models\PmrRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TestWorkflowController extends Controller
{
    public function action(
        Request $request,
        string $formType,
        int $record,
    ): JsonResponse {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user?->hasRole('RMEC', 'ADMINISTRATOR'), 403);
        abort_unless(in_array($formType, ['amr', 'pmr'], true), 404);

        $validated = $request->validate([
            'action' => [
                'required',
                Rule::in(['confirm', 'recommend', 'retest']),
            ],
        ]);
        $model = $formType === 'amr' ? AmrRecord::class : PmrRecord::class;
        $test = $model::query()->with('pile')->findOrFail($record);

        if ($test->is_locked) {
            throw ValidationException::withMessages([
                'action' => 'This test conduct is locked and cannot be changed.',
            ]);
        }

        if ($validated['action'] === 'recommend' && $formType === 'pmr') {
            $this->validatePmrRecommendation($test);
        }

        $tests = $model::query()
            ->where('pile_id', $test->pile_id)
            ->where('conduct_number', $test->conduct_number)
            ->get();
        $previousStatus = $test->status;
        $newStatus = match ($validated['action']) {
            'confirm' => 'PENDING',
            'recommend' => 'RECOMMENDED',
            'retest' => 'RETEST',
        };

        DB::transaction(function () use (
            $tests,
            $newStatus,
            $validated,
            $test,
            $previousStatus,
        ): void {
            foreach ($tests as $conductTest) {
                $conductTest->update([
                    'status' => $newStatus,
                    'included_in_computation' => $newStatus === 'RECOMMENDED',
                    'is_locked' => $newStatus !== 'PENDING',
                    'confirmed_by' => $validated['action'] === 'confirm'
                            ? Auth::id()
                            : $conductTest->confirmed_by,
                    'actioned_by' => $validated['action'] === 'confirm' ? null : Auth::id(),
                    'confirmed_at' => $validated['action'] === 'confirm'
                            ? now()
                            : $conductTest->confirmed_at,
                    'actioned_at' => $validated['action'] === 'confirm' ? null : now(),
                ]);
            }

            $test->pile?->update([
                $test instanceof AmrRecord
                    ? 'amr_status'
                    : 'pmr_status' => strtolower($newStatus),
            ]);

            AuditLog::record(strtoupper($validated['action']), $test, [
                'form_type' => $test instanceof AmrRecord ? 'amr' : 'pmr',
                'conduct_number' => $test->conduct_number,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'included_in_computation' => $newStatus === 'RECOMMENDED',
            ]);
        });

        return response()->json([
            'message' => 'Test conduct marked as '.$newStatus.'.',
            'status' => $newStatus,
            'included_in_computation' => $newStatus === 'RECOMMENDED',
            'is_locked' => $newStatus !== 'PENDING',
        ]);
    }

    private function validatePmrRecommendation(PmrRecord $test): void
    {
        $amrRate = $test->pile?->amrCalculation?->amr_rate;
        $pmrRate = $test->pile?->pmrCalculation?->pmr_rate;

        if (
            $amrRate !== null &&
            $pmrRate !== null &&
            (float) $pmrRate < (float) $amrRate
        ) {
            throw ValidationException::withMessages([
                'action' => 'PMR is below AMR and must be flagged for review before recommendation.',
            ]);
        }
    }
}
