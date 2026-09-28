# Plan: Central-Office GMR Approval + Pile Lock, and Rice Milling Progress Monitoring

## Goal
Add two capabilities **without altering existing behavior**:

1. **GMR Approval & Permanent Pile Lock** — After a GMR is computed and the report is printed, the report is submitted to the Central Office. When the Central Office approves it, the system records an *approved GMR* per pile and locks that pile forever (no further AMR/PMR/GMR changes for that approved cycle).
2. **Rice Milling Progress Monitoring** — A pile that has an approved GMR becomes eligible for actual milling. The system assigns a rice milling to a pile, and staff log accomplishment/progress per pile over time. The only pile data carried into milling is the pile's details (especially `volume_kg`).

## Non‑invasive design principle
- All new columns on `piles` are **nullable** and default to `null`. Existing piles/records/tests therefore read as "not locked", so every existing controller, service, and test behaves identically.
- New features live in **new** models, migrations, controllers, routes, and views. Existing files only receive **guard clauses** that short‑circuit when the new lock is active (which it never is for current data) and a new `isGmrLocked()` helper — no existing logic branches are rewritten.

---

## Part A — Data model

### Migration 1: `piles` additions (nullable, additive)
- `gmr_status` varchar(20) nullable — `submitted` | `approved` | `rejected` (null = not yet submitted).
- `gmr_approval_pile_id` foreignId nullable → `gmr_approval_piles.id` (the approved‑GMR record that locked this pile).
- `gmr_locked_at` timestamp nullable — set when permanently locked.

### Migration 2: `gmr_approvals` (the submission batch sent to Central Office)
- `id`
- `branch_id` foreignId
- `reference_number` varchar nullable (Central Office reference / transmittal no.)
- `status` varchar(20) default `submitted` — `submitted` | `approved` | `rejected`
- `submitted_by` foreignId nullable, `submitted_at` timestamp nullable
- `approved_by` foreignId nullable, `approved_at` timestamp nullable
- `remarks` text nullable, `rejection_reason` text nullable
- `report_snapshot` json nullable (title/subtitle/branch text + signatories at print time)
- timestamps

### Migration 3: `gmr_approval_piles` (pivot: piles in a submission, with frozen snapshot)
- `id`
- `gmr_approval_id` foreignId cascade
- `pile_id` foreignId cascade
- `amr` decimal(8,2), `pmr` decimal(8,2), `emr_display` varchar, `gmr` decimal(8,2) — snapshot at approval
- `volume_kg` decimal(12,3), `volume_bags` decimal(12,3) — frozen pile volume
- `quality` varchar, `variety` varchar nullable
- unique(`gmr_approval_id`, `pile_id`); index(`pile_id`)

### Migration 4: `millings` (a rice‑milling assignment — **one milling per pile**; a pile may have many millings over time)
- `id`
- `branch_id` foreignId
- `pile_id` foreignId cascade — the single pile this milling covers
- `miller` varchar(191) nullable (rice mill / contractor name — "one miller per pile")
- `reference_number` varchar nullable
- `status` varchar(20) default `assigned` — `assigned` | `ongoing` | `completed` | `cancelled`
- `target_volume_kg` decimal(12,3) — frozen from pile `volume_kg` at assignment (the only pile data carried in)
- `target_volume_bags` decimal(12,3)
- `assigned_by` foreignId, `assigned_at` timestamp, `started_at` nullable, `completed_at` nullable
- `remarks` text nullable
- timestamps

### Migration 5: `milling_progress` (time‑series accomplishment entries for a milling)
- `id`
- `milling_id` foreignId cascade
- `pile_id` foreignId (denormalized for easy querying; equals `milling.pile_id`)
- `progress_date` date
- `palay_input_kg` decimal(12,3) nullable
- `milled_rice_kg` decimal(12,3) nullable
- `recovery_percentage` decimal(8,2) nullable (auto‑computed if both present)
- `remarks` text nullable
- `recorded_by` foreignId
- timestamps

---

## Part B — Models (all new unless noted)
- `GmrApproval` (hasMany `GmrApprovalPile`, belongsTo branch/user×3)
- `GmrApprovalPile` (belongsTo `GmrApproval`, `Pile`)
- `Milling` (belongsTo `Pile` + branch, hasMany `MillingProgress`)
- `MillingProgress` (belongsTo `Milling`, `Pile`, user)
- `Pile` (existing) — add relations `gmrApprovalPile()`, `millings()` (hasMany), and helpers:
  - `isGmrLocked(): bool` → `gmr_status === 'approved'`
  - `isGmrApproved(): bool`
  - `canBeMilled(): bool` → `isGmrApproved()` (only approved‑GMR piles may be assigned to a milling)

---

## Part C — Authorization (additive, in `AppServiceProvider::boot`)
```
Gate::define('manage-gmr-approvals', fn(User $u) => $u->hasRole('RMEC','ADMINISTRATOR')); // submit + approve + reject
Gate::define('manage-millings',      fn(User $u) => $u->hasRole('RMEC','ADMINISTRATOR')); // assign milling + change status
Gate::define('record-milling-progress', fn(User $u) => $u->hasRole('STAFF','RMEC','ADMINISTRATOR'));
```
(Existing gates unchanged. Per your decision, RMEC **and** ADMINISTRATOR may approve a Central‑Office submission — the same `manage-gmr-approvals` gate covers submit, approve, and reject.)

---

## Part D — Controllers & routes (all new)

### `GmrApprovalController` (new) — routes under `/gmr-approvals`
- `index` – list submissions (filter by branch/status), show per‑submission piles + status badges.
- `store` – **Submit to Central Office**: accepts `selected_piles[]` + `branch_id` (same selection mechanism as the print form), validates each selected pile `can_compute` and not already locked, creates a `gmr_approval` (status `submitted`) with `gmr_approval_piles` snapshots, marks those piles `gmr_status = 'submitted'`. Reuses `GmrReportService`/`EmrGmrGateService` to compute the AMR/PMR/EMR/GMR snapshot.
- `show` – submission detail + printable transmittal.
- `approve` – (gate `manage-gmr-approvals`) sets status `approved`, sets `approved_by/at`, and for each `gmr_approval_pile` sets the pile `gmr_status='approved'`, `gmr_approval_pile_id=...`, `gmr_locked_at=now()`. Wrapped in a DB transaction with `AuditLog::record('GMR_APPROVED', ...)`.
- `reject` – (gate `manage-gmr-approvals`) sets status `rejected`, resets piles back to `gmr_status=null` (so they can be re‑submitted), records `rejection_reason`, audits.

Routes:
```
GET    /gmr-approvals                 -> index   (gmr-approvals.index)
POST   /gmr-approvals                 -> store   (gmr-approvals.store)        middleware can:manage-gmr-approvals
GET    /gmr-approvals/{approval}      -> show    (gmr-approvals.show)
POST   /gmr-approvals/{approval}/approve -> approve (gmr-approvals.approve)  middleware can:manage-gmr-approvals
POST   /gmr-approvals/{approval}/reject  -> reject  (gmr-approvals.reject)   middleware can:manage-gmr-approvals
```

### `MillingController` (new) — routes under `/millings`
- `index` – list millings (filter branch/status), with per‑milling pile + progress summary (cumulative milled vs target).
- `create` / `store` – assign a milling to a **single approved‑GMR pile**: choose miller (rice mill) + pile (validate `canBeMilled()` and not already on an active/ongoing milling), freeze `target_volume_kg`/`target_volume_bags` from the pile's `volume_kg`. Audits `MILLING_ASSIGNED`.
- `show` – milling detail: the pile (with frozen volume + variety), progress entries, cumulative accomplishment vs target.
- `update` – change milling status (assigned→ongoing→completed/cancelled).
- `storeProgress` – staff log a `milling_progress` entry for the milling (date, palay input, milled rice, remarks); auto‑compute recovery %; when cumulative milled ≥ target, auto‑set milling `status='completed'`. Audits `MILLING_PROGRESS_RECORDED`.
- `destroyProgress` (optional) – delete a progress entry (RMEC/Admin only).

Routes:
```
GET    /millings                            -> index
GET    /millings/create                     -> create        can:manage-millings
POST   /millings                            -> store          can:manage-millings
GET    /millings/{milling}                  -> show
PATCH  /millings/{milling}                  -> update         can:manage-millings
POST   /millings/{milling}/progress         -> storeProgress  can:record-milling-progress
DELETE /millings/progress/{progress}        -> destroyProgress can:manage-millings
```

---

## Part E — Permanent lock enforcement (guard clauses, additive)

Add `Pile::isGmrLocked()` and a tiny helper `abortIfGmrLocked(Pile $pile, string $action)` that throws `ValidationException`/403 when locked. Insert the guard at the **top** of these existing methods, *only* short‑circuiting when the pile is locked (null for all current data → no behavior change):

- `TestWorkflowController::action`, `pileAction`, `applyAction`, `resetAction`, `requestRetest` — block recommend/retest/confirm/reset and new conduct creation for a locked pile.
- `DataEntryController::store`, `update`, `destroy`, `updatePileDetails`, `updatePileStatus`, `createPile` — block edits to AMR/PMR records or pile details for a locked pile.
- `EmrGmrGateService::evaluateAmr/Pmr` — no change needed (read‑only), but the print/summary continue to show the frozen values.

Because the lock is data‑driven (`gmr_status === 'approved'`, initially null everywhere), **no existing test changes**; the guard simply never triggers for current fixtures.

---

## Part F — Views (new, additive)
- `resources/views/gmr-approvals/index.blade.php`, `show.blade.php` — submissions list + detail + Approve/Reject buttons (gated).
- `resources/views/millings/index.blade.php`, `create.blade.php`, `show.blade.php` — milling assignment (single approved pile + miller) + progress logging form on the detail page.
- **Existing `gmr-summary.blade.php`**: add a "Submit to Central Office" button next to the existing Print button in the existing `#gmr-print-form` (same `selected_piles[]` checkboxes), posting to `gmr-approvals.store`. Also add a small "GMR Status" badge column (—/Submitted/Approved) per row. This is the only edit to an existing view and is purely additive (new column + one button).
- **Existing `layouts/app.blade.php`**: add two nav entries under the Reports dropdown — "GMR Approvals" and "Rice Milling" — both gated by role.

---

## Part G — Audit
Reuse `AuditLog::record(...)` for: `GMR_SUBMITTED`, `GMR_APPROVED`, `GMR_REJECTED`, `MILLING_ASSIGNED`, `MILLING_STATUS_CHANGED`, `MILLING_PROGRESS_RECORDED`. No change to `AuditLog` model needed (module/action/metadata already flexible; add pile_id where relevant — already supported).

---

## Part H — Tests (new Feature tests, do not modify existing ones)
- `GmrApprovalTest` – submit → approve locks pile (assert `isGmrLocked`, assert RMEC action on that pile now 403/ValidationException); reject unlocks; cannot submit non‑computed pile; cannot submit already‑approved pile.
- `MillingTest` – can assign only approved‑GMR piles; `target_volume_kg` frozen from `volume_kg`; one pile per milling; staff can log progress; cumulative completion flips milling `status` to `completed`; assigning to a non‑approved pile is rejected.

---

## Implementation order
1. Migrations (1–5) + run `php artisan migrate`.
2. Models + `Pile` helpers/relations.
3. Gates in `AppServiceProvider`.
4. Guard clauses in existing controllers (lock enforcement).
5. `GmrApprovalController` + routes + views + "Submit to Central Office" button + nav.
6. `MillingController` + routes + views + nav.
7. Feature tests; run full suite to confirm existing tests still pass.

## Why this won't disturb the current system
- Every new `piles` column is nullable; existing rows stay null → `isGmrLocked()` is always false → all existing guards no‑op.
- No existing route, controller action, or blade variable is renamed or removed; edits to existing views only *add* elements.
- New modules are entirely separate tables/controllers/routes, so the AMR→PMR→EMR/GMR→print pipeline and the RMEC workflow are untouched.
