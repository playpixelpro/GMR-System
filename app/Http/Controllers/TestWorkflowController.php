<?php

namespace App\Http\Controllers;

use App\Concerns\GuardsGmrLockedPiles;
use App\Models\AmrRecord;
use App\Models\AuditLog;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TestWorkflowController extends Controller
{
    use GuardsGmrLockedPiles;

    /**
     * Apply RMEC Action (RECOMMEND or RETEST) to a test milling conduct.
     */
    public function action(
        Request $request,
        string $formType,
        int $record,
    ): JsonResponse|RedirectResponse {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user?->hasRole('RMEC', 'ADMINISTRATOR'), 403, 'Unauthorized. Only RMEC and Administrators can perform this action.');
        abort_unless(in_array($formType, ['amr', 'pmr'], true), 404);

        $validated = $request->validate([
            'action' => [
                'required',
                Rule::in(['confirm', 'recommend', 'retest']),
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = $formType === 'amr' ? AmrRecord::class : PmrRecord::class;
        $test = $model::query()->with('pile')->findOrFail($record);

        // Block RMEC actions on a pile whose GMR has been approved/locked.
        if ($test->pile && $test->pile->isGmrLocked()) {
            $message = "This pile's GMR has been approved and locked by the Central Office; RMEC actions are no longer permitted.";

            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['action' => $message]);
            }

            return back()->withErrors(['action' => $message]);
        }

        if ($test->is_locked) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'action' => 'This test conduct is locked and cannot be changed.',
                ]);
            }

            return back()->withErrors(['action' => 'This test conduct is locked and cannot be changed.']);
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

        $remarks = $validated['remarks'] ?? null;

        DB::transaction(function () use (
            $tests,
            $newStatus,
            $validated,
            $test,
            $previousStatus,
            $remarks,
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
                    'action_remarks' => $remarks ?? $conductTest->action_remarks,
                ]);
            }

            $test->pile?->update([
                $test instanceof AmrRecord
                    ? 'amr_status'
                    : 'pmr_status' => strtolower($newStatus),
            ]);

            $module = $test instanceof AmrRecord ? 'amr' : 'pmr';

            AuditLog::record(strtoupper($validated['action']), $test, [
                'form_type' => $module,
                'pile_id' => $test->pile_id,
                'conduct_number' => $test->conduct_number,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'included_in_computation' => $newStatus === 'RECOMMENDED',
                'action_remarks' => $remarks,
                'actioned_by' => Auth::id(),
                'actioned_at' => now()->toIso8601String(),
            ], $module, "Test milling data marked as {$newStatus}");
        });

        $message = "Test conduct marked as {$newStatus}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'status' => $newStatus,
                'included_in_computation' => $newStatus === 'RECOMMENDED',
                'is_locked' => $newStatus !== 'PENDING',
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Apply RMEC action directly to a pile's current conduct.
     */
    public function pileAction(Request $request, Pile $pile): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'form_type' => ['required', 'in:amr,pmr'],
            'action' => ['required', 'in:recommend,retest,confirm'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $formType = $validated['form_type'];
        $model = $formType === 'amr' ? AmrRecord::class : PmrRecord::class;
        $latestConduct = $model::where('pile_id', $pile->id)->max('conduct_number');

        $record = $model::where('pile_id', $pile->id)
            ->where('conduct_number', $latestConduct)
            ->firstOrFail();

        return $this->action($request, $formType, $record->id);
    }

    /**
     * Apply workflow action via JSON or unified endpoint.
     */
    public function applyAction(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->filled('pile_id')) {
            $pile = Pile::findOrFail($request->input('pile_id'));

            return $this->pileAction($request, $pile);
        }

        $formType = $request->input('form_type', 'amr');
        $recordId = (int) $request->input('record_id');

        return $this->action($request, $formType, $recordId);
    }

    /**
     * Administrator Reset of an RMEC Action.
     *
     * Business Rule:
     * - Only authorized Administrator can perform reset.
     * - Does NOT delete previous RMEC action or historical test milling data.
     * - Marks previous action as RESET/SUPERSEDED.
     * - Records who performed reset, date/time, reset reason.
     * - Unlocks and allows a new RMEC action according to existing workflow.
     */
    public function resetAction(Request $request, Pile $pile): JsonResponse|RedirectResponse
    {
        $this->ensurePileNotGmrLocked($pile, $request, 'reset');

        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user?->hasRole('ADMINISTRATOR'), 403, 'Unauthorized. Only Administrators can reset an RMEC action.');

        $validated = $request->validate([
            'form_type' => ['required', 'in:amr,pmr'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $formType = $validated['form_type'];
        $model = $formType === 'amr' ? AmrRecord::class : PmrRecord::class;
        $latestConduct = $model::where('pile_id', $pile->id)->max('conduct_number');

        $tests = $model::where('pile_id', $pile->id)
            ->where('conduct_number', $latestConduct)
            ->get();

        if ($tests->isEmpty()) {
            abort(404, 'No test conduct records found to reset.');
        }

        $previousAction = $tests->first()->status;

        DB::transaction(function () use ($tests, $pile, $formType, $previousAction, $validated): void {
            foreach ($tests as $t) {
                $t->update([
                    'previous_action' => $previousAction,
                    'reset_by' => Auth::id(),
                    'reset_at' => now(),
                    'reset_reason' => $validated['reason'],
                    'status' => 'PENDING',
                    'included_in_computation' => false,
                    'is_locked' => false,
                ]);
            }

            $pile->update([
                $formType === 'amr' ? 'amr_status' : 'pmr_status' => 'pending',
            ]);

            AuditLog::record('RMEC_ACTION_RESET', $tests->first(), [
                'form_type' => $formType,
                'pile_id' => $pile->id,
                'conduct_number' => $tests->first()->conduct_number,
                'previous_action' => $previousAction,
                'reset_reason' => $validated['reason'],
                'reset_by' => Auth::id(),
                'reset_at' => now()->toIso8601String(),
            ], $formType, 'RMEC action reset');
        });

        $message = "RMEC action for {$pile->pile_number} has been reset to PENDING. Reason recorded.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'status' => 'PENDING',
                'is_locked' => false,
                'previous_action' => $previousAction,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Request Retest: redirect to create new test milling data for the pile.
     */
    public function requestRetest(Request $request, Pile $pile): RedirectResponse
    {
        $this->ensurePileNotGmrLocked($pile, $request, 'retested');

        $formType = $request->input('form_type', 'amr');

        return redirect()->route('records.create', [
            'type' => $formType,
            'pile_id' => $pile->id,
            'retest' => 1,
        ]);
    }
}
