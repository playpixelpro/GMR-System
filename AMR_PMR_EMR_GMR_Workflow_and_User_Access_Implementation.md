# AMR / PMR Confirmation, Retest, EMR/GMR Computation, and User Access Control

## Complete Implementation Specification

Implement the following changes in the existing application.

**IMPORTANT:** Do not implement everything at once without first understanding the data relationships. Follow the implementation order in this document.

The system already has Branch, Warehouse, Pile, Test Milling, Laboratory Test Milling, AMR, PMR, EMR, and GMR. Reuse existing modules and relationships. Do not create duplicate functionality.

---

## PHASE 1 — UNDERSTAND THE EXISTING DATA STRUCTURE

Before modifying code, identify:

1. Pile master data/entity/model.
2. Branch relationship.
3. Warehouse relationship.
4. Test Milling records.
5. Laboratory Test Milling records.
6. AMR records.
7. PMR records.
8. Trial records.
9. Existing AMR calculation.
10. Existing PMR calculation.
11. Existing EMR calculation.
12. Existing GMR calculation.
13. Existing authentication system.
14. Existing audit/logging system.
15. Existing permissions/roles.

The logical relationship is:

```text
Branch
  ↓
Warehouse
  ↓
Pile
  ↓
Test Milling / Laboratory Test Milling
  ↓
Trials
  ↓
AMR / PMR
  ↓
EMR
  ↓
GMR
```

---

## PHASE 2 — SEPARATE PILE MASTER DATA FROM TEST DATA

This distinction is critical.

### Pile Master Data

Pile master data remains editable even after AMR or PMR tests are confirmed as Retest.

Examples include:

- Branch
- Warehouse
- Pile Number
- Variety
- Purity
- Moisture Content
- Quality
- Age
- Volume
- Other existing pile-level information

A Retest action must **never lock the pile master record**.

The user may continue editing pile master data according to normal permissions.

### Test Milling / Laboratory Test Milling Data

Only the specific Test Milling or Laboratory Test Milling record is locked after the user confirms:

- Recommend, or
- Retest

Do not implement a single pile-level lock that prevents all editing.

The locking applies specifically to the test record.

---

## PHASE 3 — TEST ACTION WORKFLOW

For every Test Milling and Laboratory Test Milling record, the initial action is:

```text
[ Confirm ]
```

When Confirm is clicked, show exactly:

```text
[ Recommend ]
[ Retest ]
```

Remove **Approve** completely.

Workflow:

```text
PENDING
  ↓
CONFIRM
  ↓
┌───────────────┐
│               │
Recommend      Retest
│               │
↓               ↓
LOCKED         LOCKED
INCLUDED       EXCLUDED
```

---

## PHASE 4 — RECOMMEND ACTION

When the authorized user selects **Recommend**:

1. Mark the specific AMR/PMR test as `RECOMMENDED`.
2. Set `included_in_computation = TRUE`.
3. Lock the Test Milling/Laboratory Test Milling record.
4. Prevent normal editing of that test.
5. Preserve all original test data.
6. Record the acting user.
7. Record date/time.
8. Record the action in the audit log.

Display:

```text
✓ RECOMMENDED
🔒 LOCKED
```

The Recommended result is eligible for final AMR/PMR computation.

---

## PHASE 5 — RETEST ACTION

When the authorized user selects **Retest**, require confirmation.

After confirmation:

1. Mark the specific test as `RETEST`.
2. Set `included_in_computation = FALSE`.
3. Lock the original test record.
4. Prevent normal editing of the original test.
5. Preserve the original laboratory/test result.
6. Record the acting user.
7. Record date/time.
8. Record the action in the audit log.

Display:

```text
↻ RETEST
🔒 LOCKED
```

A Retest record must NOT contribute to:

- Final AMR
- Final PMR
- EMR
- GMR

Never delete the Retest record.

---

## PHASE 6 — RETEST INPUT BEHAVIOR

When a Test Milling or Laboratory Test Milling record is marked Retest, that old record must NOT appear as the current editable test in the normal data-entry form.

The user must create a **NEW test conduct** for the retest.

Example:

```text
PILE 1

AMR Conduct #1
→ RETEST
→ LOCKED
→ EXCLUDED
```

When AMR data entry is opened again:

```text
AMR Conduct #2
Trial 1
Trial 2
Trial 3
```

must be created as a new record.

The old Conduct #1 remains available only as history/audit data.

---

## PHASE 7 — RETEST INDICATOR

When encoding a new AMR or PMR test for a pile that has any previous Retest conduct, show:

```text
⚠ RETEST NOTICE

A previous AMR/PMR test for this pile was recommended
for retest. Please conduct and encode the new test result.
```

This indicator is informational and must not prevent encoding.

It must continue to appear regardless of how many times retesting occurred.

Example:

```text
Conduct #1 → RETEST
Conduct #2 → RETEST
Conduct #3 → RETEST
Conduct #4 → RECOMMEND
```

Do not use a single boolean that loses retest history. Determine the indicator from the existence of previous Retest records.

---

## PHASE 8 — MULTIPLE RETESTS

A pile may be retested any number of times.

Example:

```text
Conduct #1 → Retest
Conduct #2 → Retest
Conduct #3 → Retest
Conduct #4 → Recommend
```

All previous Retest records:

- remain in history
- remain locked
- remain excluded

Only the Recommended conduct becomes eligible.

Never overwrite or reuse a previous conduct record.

Each conduct must have its own unique Test Milling/Laboratory Test Milling record.

---

## PHASE 9 — TEST CONDUCT HISTORY

The system must distinguish separate test conducts for the same pile.

Example:

```text
Pile 1

AMR Conduct #1 → RETEST
AMR Conduct #2 → RETEST
AMR Conduct #3 → RECOMMENDED
```

The same applies to PMR.

Use the existing structure if it already supports this. Otherwise, create a proper conduct identifier/relationship. Do not rely on physical row position or timestamps alone.

---

## PHASE 10 — AMR / PMR COMPUTATION

Only eligible Recommended tests contribute to final AMR/PMR.

```text
RECOMMENDED → INCLUDED
RETEST      → EXCLUDED
PENDING     → NOT INCLUDED
```

Therefore:

```text
Final AMR = calculation using only eligible Recommended AMR trials
Final PMR = calculation using only eligible Recommended PMR trials
```

Do not include Retest or pending trials.

---

## PHASE 11 — PILE RECOMMENDATION STATE

EMR and GMR must NOT automatically appear merely because AMR and PMR values exist.

The pile must first satisfy the required recommendation/finalization process.

Conceptually:

```text
AMR STATUS
- Pending
- Recommended
- Retest

PMR STATUS
- Pending
- Recommended
- Retest
```

The pile becomes eligible for EMR/GMR only when the required AMR and PMR results are finalized/recommended according to the applicable business rules.

Do not calculate EMR/GMR before that condition is satisfied.

---

## PHASE 12 — EMR VISIBILITY

EMR must only be generated/displayed when the pile is eligible and properly Recommended.

If the pile is Pending or Retest:

```text
EMR = NOT AVAILABLE
```

and it must NOT appear in the EMR report.

Do not display EMR simply because trial data exists.

---

## PHASE 13 — GMR VISIBILITY

The same rule applies to GMR.

Before the pile is properly Recommended:

- Do not show the pile in the GMR report.
- Do not calculate GMR.
- Do not display a temporary GMR.

Once all required final AMR/PMR values are available and the pile is properly Recommended, calculate GMR.

---

## PHASE 14 — EMR COMPUTATION

Once eligible:

```text
EMR LOWER LIMIT = final AMR
EMR UPPER LIMIT = final PMR
```

Therefore:

```text
EMR = AMR – PMR
```

Example:

```text
AMR = 61.53%
PMR = 62.22%

EMR = 61.53% – 62.22%
```

EMR is a range.

Do NOT calculate EMR as `(AMR + PMR) / 2`. That calculation belongs to GMR.

---

## PHASE 15 — GMR COMPUTATION

Use the updated guideline:

```text
GMR = (AMR + PMR) / 2
```

Example:

```text
AMR = 61.53%
PMR = 62.22%

GMR = (61.53 + 62.22) / 2
    = 61.875%
    = 61.88%
```

Do not round AMR or PMR before calculation if more precise stored values exist. Round only the final displayed GMR according to the application's required precision.

---

## PHASE 16 — AMR/PMR VALIDATION

Before a result can become Recommended, apply the existing AMR/PMR validation rules.

At minimum:

```text
PMR >= AMR
```

If:

```text
PMR < AMR
```

flag the result for review and do not silently accept it as a normal Recommended result.

Preserve all existing validation rules.

---

## PHASE 17 — AMR/PMR REPORT UI

Before action:

```text
STATUS:
Pending

ACTION:
[ Confirm ]
```

After clicking Confirm:

```text
┌────────────────────┐
│ Recommend          │
│ Retest             │
└────────────────────┘
```

After Recommend:

```text
✓ RECOMMENDED
🔒 LOCKED
```

After Retest:

```text
↻ RETEST
🔒 LOCKED
```

Remove **Approve** completely.

---

## PHASE 18 — PILE MASTER DATA UI

Even when AMR/PMR tests are locked, the Pile Details/Edit function remains available according to normal permissions.

Example:

```text
Pile 1
AMR Test = LOCKED
PMR Test = LOCKED

Pile Details
[ Edit Pile ]
```

must still be available if the user has permission.

Do not interpret Test Locked as Pile Locked.

---

# USER ACCESS CONTROL

## PHASE 19 — USER ROLES

Implement exactly three primary roles:

1. STAFF
2. RMEC
3. ADMINISTRATOR

There is no public registration.

Only Administrator can create accounts.

---

## PHASE 20 — STAFF

Staff is primarily responsible for data encoding.

Staff can:

- Encode pile data.
- Encode Test Milling data.
- Encode Laboratory Test Milling data.
- View applicable reports.
- Edit their encoded data within the allowed editing period.

### 24-hour editing window

A Staff user can edit their saved data for 24 hours after it is saved.

Example:

```text
Saved:       September 25, 2026 10:00 AM
Editable to: September 26, 2026 10:00 AM
```

Every edit must be audited.

After 24 hours:

- Staff can no longer edit the record.
- Display an appropriate message.
- Administrator authorization is required for changes.

The restriction must be enforced server-side using the database/server timestamp. Do not rely only on a frontend timer.

---

## PHASE 21 — RMEC

RMEC has all Staff privileges.

Additionally, RMEC can:

- Review Test Milling results.
- Review Laboratory Test Milling results.
- Click Confirm.
- Select Recommend.
- Select Retest.
- Finalize applicable AMR/PMR test conducts.
- Trigger EMR/GMR eligibility when all required conditions are satisfied.

RMEC cannot perform Administrator-only functions.

---

## PHASE 22 — ADMINISTRATOR

Administrator has full privileges.

Administrator can:

- Create users.
- Edit users.
- Disable users.
- Reset user access.
- Manage roles.
- Manage application settings.
- Edit records when authorized.
- Override the Staff 24-hour editing restriction.
- View audit logs.
- Manage all data.
- Perform administrative corrections.
- Manage account/password issues.

All Administrator overrides must be audited.

---

## PHASE 23 — NO PUBLIC REGISTRATION

Do not provide:

- Register
- Create Account
- Sign Up

on the login page.

Only Administrator can create accounts.

---

## PHASE 24 — ADMIN USER CREATION

When Administrator creates a user, collect:

- Name
- Email
- Username, if applicable
- Role
- Other required account information

Generate a temporary password and email it to the registered email address.

The email must state:

- It is a temporary password.
- The user must change it on first login.
- It expires after 24 hours.

---

## PHASE 25 — FIRST LOGIN

If a user logs in using the temporary password:

Force:

```text
CHANGE PASSWORD
```

before allowing access to the application.

The user cannot skip this step.

After successful password change:

- Mark temporary password as used.
- Mark account activated.
- Allow normal login.

---

## PHASE 26 — TEMPORARY PASSWORD EXPIRATION

Temporary passwords expire after 24 hours if unused.

If expired:

Do not allow normal login.

Display:

> Your temporary password has expired. Please contact the Administrator.

Administrator can generate a new temporary password.

Never send the old temporary password again.

Use the framework's secure password hashing. Never store plaintext passwords.

---

## PHASE 27 — FORGOT PASSWORD

The login page must provide:

```text
[ Forgot Password? ]
```

The recovery process must:

1. Ask for registered email.
2. Generate secure reset token.
3. Email the reset link.
4. Give the token a limited expiration.
5. Allow creation of a new password.
6. Invalidate the token after use.

Use the framework's existing secure password-reset functionality where available.

---

# AUDITING AND COMPUTATION STATE

## PHASE 28 — AUDIT LOGGING

Log at minimum:

- User login
- Failed login where appropriate
- Password reset
- User creation
- Role changes
- Data creation
- Data edits
- Confirm
- Recommend
- Retest
- Administrator override
- Changes to locked records
- Changes after Staff 24-hour editing period

For test actions record:

- Test ID
- Pile ID
- AMR/PMR type
- Conduct number
- Previous status
- New status
- Action
- User
- Date/time
- Included/excluded computation state

Staff must not be able to edit audit logs.

---

## PHASE 29 — COMPUTATION STATE

Do not rely only on displayed status text.

Every Test Milling/Laboratory Test Milling record must have a clear computation state.

Conceptually:

```text
status:
PENDING
RECOMMENDED
RETEST
```

and:

```text
included_in_computation:
TRUE
FALSE
```

Rules:

```text
PENDING
→ included_in_computation = FALSE

RECOMMENDED
→ included_in_computation = TRUE

RETEST
→ included_in_computation = FALSE
```

Final AMR/PMR calculations use only:

```text
included_in_computation = TRUE
```

---

## PHASE 30 — LOCK STATE

Every test record should have a clear lock state.

Before action:

```text
is_locked = FALSE
```

After Recommend:

```text
is_locked = TRUE
status = RECOMMENDED
included_in_computation = TRUE
```

After Retest:

```text
is_locked = TRUE
status = RETEST
included_in_computation = FALSE
```

Locking must be enforced server-side.

---

## PHASE 31 — DO NOT DELETE RETEST DATA

Never delete a Test Milling or Laboratory Test Milling record because it was marked Retest.

Retest records are permanent history and remain available for:

- Audit
- History
- Review
- Reporting
- Verification

They are simply excluded from computation.

---

## PHASE 32 — PILE DATA VS TEST DATA

Do not confuse Pile Data with Test Data.

Example:

```text
Pile 1:
Volume = 11,522 bags
Purity = 94.31%
Age = 5 months
```

This remains editable according to normal permissions.

Test:

```text
AMR Conduct #1
Trial 1
Trial 2
Trial 3
Status = RETEST
```

This is locked.

A new conduct is created:

```text
AMR Conduct #2
Trial 1
Trial 2
Trial 3
```

The old conduct remains locked and excluded.

---

# EMR/GMR REPORT VISIBILITY

## PHASE 33 — FINAL REPORT VISIBILITY

The EMR and GMR reports must NOT show every pile automatically.

If any required condition is still pending, including:

- AMR pending
- PMR pending
- AMR/PMR conduct marked Retest
- Required pile recommendation/finalization not completed

then the pile must NOT appear in the final EMR/GMR reports.

Once the pile is properly Recommended and the required final AMR/PMR values are available:

- Show the pile in EMR report.
- Show the pile in GMR report.

---

## PHASE 34 — RETEST INDICATOR

When a pile has a previous Retest conduct, show in the new test-entry form:

```text
⚠ RETEST REQUIRED

This pile has a previous AMR/PMR test that was recommended
for retest. This is a new test conduct.
```

If the pile has been retested multiple times, the indicator remains.

Do not show the previous Retest result as the current editable test.

---

# FINAL WORKFLOW

## PHASE 35 — COMPLETE WORKFLOW

```text
PILE MASTER DATA
       │
       ├── remains editable
       │
       ▼
TEST MILLING / LABORATORY TEST MILLING
       │
       ▼
TRIAL RESULTS
       │
       ▼
PENDING
       │
       ▼
CONFIRM
       │
       ├───────────────┐
       ▼               ▼
   RECOMMEND         RETEST
       │               │
       ▼               ▼
    LOCKED           LOCKED
       │               │
   INCLUDED          EXCLUDED
       │               │
       │          New Test Conduct
       │               │
       └───────┬───────┘
               ▼
        FINAL AMR / PMR
               │
               ▼
             EMR
               │
               ▼
             GMR
```

---

# IMPLEMENTATION ORDER

## Step 1 — Database and Domain Model

First establish:

- Test conduct identity
- Test status
- `included_in_computation`
- `is_locked`
- Action metadata
- Action timestamp
- User who performed action
- Audit history
- User roles

Do not modify the UI first.

## Step 2 — User Roles and Authorization

Implement:

- Staff
- RMEC
- Administrator

Enforce permissions server-side.

## Step 3 — Authentication

Implement:

- Admin-created users
- Temporary password
- 24-hour temporary password expiration
- First-login password change
- Forgot password
- Secure password reset

## Step 4 — Test Action Workflow

Implement:

```text
Confirm
  ↓
Recommend / Retest
```

Remove Approve.

Implement locking and computation eligibility.

## Step 5 — Retest Workflow

Implement:

- Locked previous test
- Excluded computation
- New test conduct
- Retest indicator
- Multiple retest history

## Step 6 — AMR/PMR Calculations

Update the calculation engine to use only:

```text
included_in_computation = TRUE
```

Do not change unrelated calculations.

## Step 7 — EMR/GMR Eligibility

Only calculate/display EMR and GMR when the pile satisfies all required recommendation/finalization conditions.

## Step 8 — Reports

Update:

- AMR report
- PMR report
- EMR report
- GMR report

to reflect the new workflow.

## Step 9 — UI

Update the Action column.

Before action:

```text
[ Confirm ]
```

After Confirm:

```text
[ Recommend ]
[ Retest ]
```

After Recommend:

```text
✓ Recommended
🔒 Locked
```

After Retest:

```text
↻ Retest
🔒 Locked
```

## Step 10 — Testing

Test all workflows before considering the implementation complete.

---

# REQUIRED TEST SCENARIOS

## Scenario 1 — Normal Recommendation

AMR Conduct #1 → Recommend

Expected:

- Locked
- Included
- Used in AMR
- Can contribute to EMR/GMR

## Scenario 2 — Retest

AMR Conduct #1 → Retest

Expected:

- Locked
- Excluded
- Not used in AMR
- Not used in EMR
- Not used in GMR
- New AMR Conduct #2 can be encoded

## Scenario 3 — Multiple Retests

```text
Conduct #1 → Retest
Conduct #2 → Retest
Conduct #3 → Retest
Conduct #4 → Recommend
```

Expected:

Only Conduct #4 is eligible. All previous conducts remain in history.

## Scenario 4 — Pile Data Editing

AMR test is locked.

Expected:

Pile master data remains editable according to user permissions.

## Scenario 5 — EMR Before Recommendation

AMR/PMR results exist but pile is not finalized/recommended.

Expected:

Pile does NOT appear in EMR report.

## Scenario 6 — GMR Before Recommendation

Expected:

Pile does NOT appear in GMR report.

## Scenario 7 — EMR/GMR After Recommendation

AMR and PMR are finalized and pile is Recommended.

Expected:

EMR and GMR become available.

## Scenario 8 — Staff Editing Within 24 Hours

Expected:

Staff can edit eligible encoded data. Edit is logged.

## Scenario 9 — Staff Editing After 24 Hours

Expected:

Staff cannot edit. Administrator authorization is required.

## Scenario 10 — RMEC

Expected:

RMEC can perform all Staff functions plus Recommend/Retest actions.

## Scenario 11 — Administrator

Expected:

Administrator can perform all authorized operations and override the 24-hour editing restriction. All overrides are audited.

## Scenario 12 — Temporary Password

Admin creates user.

Expected:

- Temporary password emailed.
- Temporary password expires after 24 hours.
- First login requires password change.

## Scenario 13 — Forgot Password

Expected:

User can securely reset password using registered email.

---

# FINAL ACCEPTANCE CRITERIA

The implementation is complete only when:

- [ ] Pile master data remains editable even when tests are locked.
- [ ] Only Test Milling/Laboratory Test Milling records are locked after Recommend/Retest.
- [ ] Approve action has been completely removed.
- [ ] Initial action is Confirm.
- [ ] Confirm opens Recommend or Retest.
- [ ] Recommend locks the test and includes it in computation.
- [ ] Retest locks the test and excludes it from computation.
- [ ] Retest records are never deleted.
- [ ] New test conducts can be encoded after Retest.
- [ ] Previous Retest indicator appears during new test entry.
- [ ] Indicator continues to appear after multiple Retests.
- [ ] Each test conduct has its own history.
- [ ] Retest results never affect final AMR/PMR.
- [ ] Retest results never affect EMR.
- [ ] Retest results never affect GMR.
- [ ] EMR is not shown until the pile is properly recommended/finalized.
- [ ] GMR is not shown until the pile is properly recommended/finalized.
- [ ] GMR uses `(AMR + PMR) / 2`.
- [ ] Staff editing is restricted to 24 hours.
- [ ] Staff edits are audited.
- [ ] Administrator can authorize editing after 24 hours.
- [ ] RMEC can Recommend or Retest.
- [ ] Administrator has full privileges.
- [ ] Public registration is disabled.
- [ ] Only Administrator can create users.
- [ ] Temporary passwords expire after 24 hours.
- [ ] First login requires password change.
- [ ] Forgot-password workflow works.
- [ ] All important actions are audited.
- [ ] Server-side authorization is enforced.
- [ ] Existing unrelated modules continue to work.

---

# FINAL INSTRUCTION TO THE CODING AGENT

Do not start by changing the UI.

First inspect the existing application and understand the current data model and AMR/PMR/EMR/GMR calculation flow.

Then implement the changes in the exact implementation order specified above.

Do not delete historical test data.

Do not overwrite previous Test Milling/Laboratory Test Milling conducts when a Retest is performed.

Do not lock the Pile master record.

Do not allow Retest records to enter AMR/PMR/EMR/GMR computations.

Do not show EMR/GMR for piles that have not satisfied the required recommendation/finalization state.

Do not add an Approve action.

The final user workflow must be:

```text
CONFIRM
→ RECOMMEND or RETEST
```

Once selected:

```text
RECOMMEND
→ LOCKED + INCLUDED

RETEST
→ LOCKED + EXCLUDED
```

while the Pile master data remains independently editable.
