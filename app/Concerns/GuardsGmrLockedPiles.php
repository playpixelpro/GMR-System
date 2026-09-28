<?php

namespace App\Concerns;

use App\Models\AmrRecord;
use App\Models\Pile;
use App\Models\PmrRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Guard helper that blocks AMR/PMR/GMR mutations for a pile whose GMR has
 * been approved (and therefore permanently locked) by the Central Office.
 *
 * Because every new piles column is nullable, this guard is a no-op for all
 * pre-existing data — it only ever fires when a pile's gmr_status is
 * explicitly set to "approved".
 */
trait GuardsGmrLockedPiles
{
    /**
     * Abort with a 403 / validation error when the pile's GMR is locked.
     */
    protected function ensurePileNotGmrLocked(
        Pile $pile,
        Request $request,
        string $action = 'modify',
    ): void {
        if (! $pile->isGmrLocked()) {
            return;
        }

        $message = "This pile's GMR has been approved and locked by the Central Office and can no longer be {$action}.";

        if ($request->expectsJson() || $request->ajax()) {
            throw ValidationException::withMessages(['pile' => $message]);
        }

        abort(403, $message);
    }

    /**
     * Resolve the pile for a given test-milling record (AMR/PMR) and guard it.
     */
    protected function ensureRecordPileNotGmrLocked(
        AmrRecord|PmrRecord $record,
        Request $request,
        string $action = 'modify',
    ): void {
        $pile = $record->pile()->first();

        if ($pile instanceof Pile) {
            $this->ensurePileNotGmrLocked($pile, $request, $action);
        }
    }

    /**
     * Return a 403 / validation response for a locked pile when the caller
     * needs to short-circuit a redirect or JSON response manually.
     */
    protected function gmrLockedResponse(
        Request $request,
        string $message,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->withErrors(['pile' => $message]);
    }
}
