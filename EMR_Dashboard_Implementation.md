# Implementation Prompt: Expected Milling Recovery (EMR) Dashboard

## 1. Objective

Implement an **Expected Milling Recovery (EMR) Dashboard** in the existing application.

**Important terminology:**

> **EMR = Expected Milling Recovery**

Do **not** interpret EMR as Experimental Milling Recovery.

The dashboard must provide an interactive summary of expected milling recovery by **Branch**, **Warehouse**, and **Pile**, with dynamic filtering, KPI cards, EMR range visualization, warehouse summaries, detailed results, validation, and optional export/print functionality.

Integrate this into the existing application. Do not create a separate standalone application.

---

## 2. Existing Data

The dashboard is based on these logical fields:

| Field | Description |
|---|---|
| Branch | NFA branch |
| No. | Report sequence number |
| Warehouse | Warehouse where the pile is stored |
| Pile Number | Pile identifier |
| Variety | Palay variety |
| Age | Age in months |
| Volume | Palay volume |
| Purity | Purity percentage |
| Quality | Quality classification |
| AMR | Actual Milling Recovery |
| PMR | Predicted Milling Recovery |
| EMR | Expected Milling Recovery range |

Sample data:

| Branch | Warehouse | Pile | Variety | Age | Volume | Purity | Quality | AMR | PMR |
|---|---|---:|---|---:|---:|---:|---|---:|---:|
| NORTH COTABATO | GID#2, MLANG BS | 1 | PD | 5 | 11,522 | 94.31% | GQA | 61.53% | 62.22% |
| NORTH COTABATO | GID#2, MLANG BS | 2 | PD | 5 | 12,259 | 95.41% | GQA | 62.66% | 62.77% |
| NORTH COTABATO | GID#2, MLANG BS | 3 | PD | 4 | 6,624 | 92.29% | GQA | 63.56% | 63.59% |
| NORTH COTABATO | GID#2, MLANG BS | 4 | PD | 6 | 4,871 | 93.43% | GQA | 64.41% | 64.78% |
| NORTH COTABATO | GID#2, MLANG BS | 5 | PD | 4 | 9,438 | 94.85% | GQA | 62.91% | 63.14% |
| NORTH COTABATO | GID#4, MLANG BS | 1 | PD | 9 | 7,517 | 93.54% | GQA | 63.25% | 63.64% |

---

# 3. EMR Calculation

For every pile:

```text
EMR LOWER LIMIT = AMR
EMR UPPER LIMIT = PMR
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

Display:

**61.53% – 62.22%**

### Critical rule

Do **not** calculate EMR as:

```text
(AMR + PMR) / 2
```

EMR is a **range**, not an average.

Prefer storing AMR and PMR as numeric values and deriving EMR dynamically.

If an EMR text field already exists, do not make the text field the source of truth unless the existing application architecture requires it.

---

# 4. AMR / PMR Validation

The expected relationship is:

```text
PMR >= AMR
```

If:

```text
PMR >= AMR
```

status:

**VALID**

If:

```text
PMR < AMR
```

status:

**QUESTIONABLE**

Do not automatically swap AMR and PMR.

Do not hide questionable records.

Do not silently correct the values.

Example:

```text
AMR = 63.50%
PMR = 62.90%

Status = QUESTIONABLE
```

The questionable record should remain visible with a clear validation indicator.

---

# 5. Page Name

Use:

**EXPECTED MILLING RECOVERY (EMR) DASHBOARD**

Subtitle:

**Summary of Expected Milling Recovery by Branch, Warehouse, and Pile**

Do not use "Experimental Milling Recovery" anywhere on this page.

---

# 6. Navigation

Integrate the page into the existing navigation.

Suggested structure:

```text
Dashboard
  └── Milling Recovery
       └── Expected Milling Recovery
```

Use the existing:

- layout
- navigation
- authentication
- authorization
- breadcrumbs
- UI components
- typography
- spacing
- table components
- chart library

Do not introduce a completely different design system.

---

# 7. Filters

Place the filters at the top of the dashboard.

Required filters:

### Branch

```text
Branch
[ All Branches ▼ ]
```

### Warehouse

```text
Warehouse
[ All Warehouses ▼ ]
```

Button:

```text
[ Reset Filters ]
```

Optional existing application controls:

```text
[ Export ]
[ Print ]
```

---

# 8. Branch Filter

The Branch filter must affect the complete dashboard.

When:

```text
Branch = All Branches
```

show all warehouses.

When:

```text
Branch = North Cotabato
```

the Warehouse dropdown must show only warehouses belonging to North Cotabato.

Example:

```text
Branch
[ North Cotabato ▼ ]

Warehouse
[ All Warehouses ▼ ]

Options:
- All Warehouses
- GID#2, MLANG BS
- GID#4, MLANG BS
```

When the Branch changes:

1. Refresh the warehouse options.
2. Clear the warehouse selection if the selected warehouse no longer belongs to the branch.
3. Refresh the KPI cards.
4. Refresh the EMR visualization.
5. Refresh the warehouse summary.
6. Refresh the detailed table.

Do not leave stale warehouse data visible.

---

# 9. Warehouse Filter

The Warehouse filter must work together with Branch.

If:

```text
Branch = North Cotabato
Warehouse = GID#2, MLANG BS
```

show only records matching both conditions.

Conceptually:

```text
WHERE branch_id = selected_branch
AND warehouse_id = selected_warehouse
```

Use database IDs for filtering rather than warehouse names.

---

# 10. KPI Cards

Create a summary area with these KPI cards:

### Total Piles

```text
TOTAL PILES
6
```

Count unique evaluated piles using the application's actual pile identifier.

### Total Volume

```text
TOTAL VOLUME
52,231 kg
```

Calculate:

```text
SUM(volume)
```

### Average Purity

```text
AVERAGE PURITY
93.97%
```

Use the application's established aggregation rule.

If no existing business rule exists, determine whether a simple or volume-weighted average is appropriate and document it.

### Average AMR

```text
AVERAGE AMR
62.70%
```

### Average PMR

```text
AVERAGE PMR
62.99%
```

### Overall EMR Range

```text
EXPECTED MILLING RECOVERY
61.53% – 64.78%
```

Do not calculate the overall EMR by averaging individual EMR ranges.

Use:

```text
EMR LOWER = MIN(AMR)
EMR UPPER = MAX(PMR)
```

for the currently filtered dataset.

---

# 11. EMR Visualization

Create a main chart titled:

**Expected Milling Recovery by Pile**

Use a horizontal range-bar visualization.

The start of every range is AMR.

The end of every range is PMR.

Conceptually:

```text
61%       62%       63%       64%       65%
|---------|---------|---------|---------|

Pile 1
████████
61.53% ─ 62.22%

Pile 2
              ██
              62.66% ─ 62.77%

Pile 3
                    ██
                    63.56% ─ 63.59%

Pile 4
                            ███
                            64.41% ─ 64.78%

Pile 5
                   ███
                   62.91% ─ 63.14%

GID#4 - Pile 1
                     ███
                     63.25% ─ 63.64%
```

The chart must update whenever Branch or Warehouse filters change.

Use a label that identifies both warehouse and pile because pile numbers may repeat between warehouses.

Preferred:

```text
GID#2, MLANG BS - Pile 1
```

---

# 12. Warehouse Summary

Add a section:

**EMR SUMMARY BY WAREHOUSE**

Columns:

| Warehouse | Piles | Volume | Avg Purity | Avg AMR | Avg PMR | EMR |
|---|---:|---:|---:|---:|---:|---|
| GID#2, MLANG BS | 5 | 44,714 kg | 94.06% | 62.61% | 62.90% | 61.53–64.78% |
| GID#4, MLANG BS | 1 | 7,517 kg | 93.54% | 63.25% | 63.64% | 63.25–63.64% |

For each warehouse:

```text
Piles = COUNT DISTINCT piles
Volume = SUM(volume)
Avg Purity = configured average method
Avg AMR = configured average method
Avg PMR = configured average method
EMR Lower = MIN(AMR)
EMR Upper = MAX(PMR)
```

Do not average EMR ranges.

---

# 13. Detailed EMR Table

Create a detailed table with:

| No. | Warehouse | Pile | Variety | Age | Volume | Purity | Quality | AMR | PMR | EMR | Status |
|---:|---|---:|---|---:|---:|---:|---|---:|---:|---|---|
| 1 | GID#2, MLANG BS | 1 | PD | 5 | 11,522 | 94.31% | GQA | 61.53% | 62.22% | 61.53–62.22% | Valid |
| 2 | GID#2, MLANG BS | 2 | PD | 5 | 12,259 | 95.41% | GQA | 62.66% | 62.77% | 62.66–62.77% | Valid |
| 3 | GID#2, MLANG BS | 3 | PD | 4 | 6,624 | 92.29% | GQA | 63.56% | 63.59% | 63.56–63.59% | Valid |
| 4 | GID#2, MLANG BS | 4 | PD | 6 | 4,871 | 93.43% | GQA | 64.41% | 64.78% | 64.41–64.78% | Valid |
| 5 | GID#2, MLANG BS | 5 | PD | 4 | 9,438 | 94.85% | GQA | 62.91% | 63.14% | 62.91–63.14% | Valid |
| 6 | GID#4, MLANG BS | 1 | PD | 9 | 7,517 | 93.54% | GQA | 63.25% | 63.64% | 63.25–63.64% | Valid |

---

# 14. Table Behavior

Support:

- Pagination
- Numeric sorting
- Percentage sorting
- Filter synchronization
- Responsive horizontal scrolling
- Search if already supported by the application

Sort using numeric values, not formatted EMR strings.

For example, do not sort:

```text
61.53–62.22
62.66–62.77
```

as text.

Use numeric AMR/PMR/EMR lower/upper values.

---

# 15. Filtered Example

If:

```text
Branch = North Cotabato
Warehouse = GID#2, MLANG BS
```

the dashboard should contain exactly five piles.

Expected summary:

```text
TOTAL PILES
5

TOTAL VOLUME
44,714 kg

AVERAGE PURITY
94.06%

EMR RANGE
61.53% – 64.78%
```

The chart should contain only the five GID#2 piles.

The warehouse summary should contain only GID#2, MLANG BS.

---

# 16. Reset Filters

Reset should return:

```text
Branch = All Branches
Warehouse = All Warehouses
```

and refresh the complete dashboard.

No full-page reload is required unless that is consistent with the application's architecture.

---

# 17. Empty State

If no records match the filters, display:

```text
No EMR records found.

Try changing the selected branch or warehouse.
```

Do not display misleading empty charts.

KPI values should display appropriate empty values such as:

```text
0
0 kg
N/A
```

---

# 18. Performance

Do not load the entire database into the browser if the dataset may become large.

Prefer server-side aggregation for:

- Total piles
- Total volume
- Average purity
- Average AMR
- Average PMR
- Minimum AMR
- Maximum PMR
- Warehouse summaries

Avoid N+1 queries.

Use appropriate database indexes and eager loading where necessary.

---

# 19. Security

Use existing authentication and authorization.

If the application restricts users by branch or warehouse, the dashboard must respect those restrictions.

Do not bypass access control.

---

# 20. Responsive Design

The dashboard must work on:

- Desktop
- Laptop
- Tablet

KPI cards should stack appropriately on smaller screens.

The detailed table may use horizontal scrolling.

Maintain the existing application's responsive design conventions.

---

# 21. Export / Print

If the existing application supports exporting or printing reports, allow the user to export/print the **currently filtered dataset**.

Export fields:

```text
Branch
Warehouse
Pile Number
Variety
Age
Volume
Purity
Quality
AMR
PMR
EMR Lower
EMR Upper
EMR Display
Validation Status
```

Do not export only the formatted EMR string.

---

# 22. Important Aggregation Rules

There are three aggregation levels.

## Pile EMR

```text
EMR Lower = AMR
EMR Upper = PMR
```

Example:

```text
61.53% – 62.22%
```

## Warehouse EMR

```text
EMR Lower = MIN(AMR)
EMR Upper = MAX(PMR)
```

Example:

```text
61.53% – 64.78%
```

## Overall Filtered EMR

```text
EMR Lower = MIN(AMR)
EMR Upper = MAX(PMR)
```

Example:

```text
61.53% – 64.78%
```

Never average EMR ranges.

---

# 23. Development Instructions

Before writing code:

1. Inspect the current project structure.
2. Identify the existing pile model/entity.
3. Identify Branch relationships.
4. Identify Warehouse relationships.
5. Identify where AMR is stored.
6. Identify where PMR is stored.
7. Identify whether EMR already exists.
8. Identify existing dashboard components.
9. Identify existing table components.
10. Identify existing chart library.
11. Identify existing export/print functionality.
12. Reuse existing components whenever possible.

Do not create duplicate tables or duplicate business logic.

Do not modify unrelated modules.

After implementation, test with the six sample records provided above.

---

# 24. Acceptance Criteria

## Filters

- [ ] Branch filter works.
- [ ] Warehouse filter works.
- [ ] Warehouse options are dependent on Branch.
- [ ] Changing Branch refreshes Warehouse options.
- [ ] Changing filters refreshes the complete dashboard.
- [ ] Reset Filters works.

## KPI

- [ ] Total Piles is correct.
- [ ] Total Volume is correct.
- [ ] Average Purity is correct.
- [ ] Average AMR is correct.
- [ ] Average PMR is correct.
- [ ] Overall EMR range is correct.

## EMR

- [ ] EMR lower limit comes from AMR.
- [ ] EMR upper limit comes from PMR.
- [ ] EMR is displayed as a range.
- [ ] EMR is not calculated as an average.
- [ ] Overall EMR uses MIN(AMR) and MAX(PMR).
- [ ] Warehouse EMR uses MIN(AMR) and MAX(PMR).

## Validation

- [ ] PMR >= AMR is valid.
- [ ] PMR < AMR is questionable.
- [ ] AMR and PMR are never automatically swapped.
- [ ] Questionable records remain visible.

## Visualization

- [ ] EMR range chart works.
- [ ] Chart updates after filtering.
- [ ] Chart identifies warehouse and pile.
- [ ] Empty state works.

## Table

- [ ] All required fields are displayed.
- [ ] Numeric formatting is correct.
- [ ] Percentage formatting is correct.
- [ ] Numeric sorting works.
- [ ] Pagination works if applicable.

## Reporting

- [ ] Filtered results can be exported/printed where supported.
- [ ] Export contains AMR and PMR.
- [ ] Export contains EMR lower and upper values.
- [ ] Export contains validation status.

---

# 25. SAMPLE DESIGN / UI MOCKUP

The following is the target layout for the dashboard. Treat this as the visual and functional reference, while adapting it to the application's existing design system.

```text
┌──────────────────────────────────────────────────────────────────────────────────────┐
│ EXPECTED MILLING RECOVERY (EMR)                                      Export  Print   │
│ Summary of Expected Milling Recovery by Branch, Warehouse, and Pile                  │
├──────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                      │
│  BRANCH                         WAREHOUSE                                             │
│  ┌────────────────────────┐     ┌────────────────────────────────────┐               │
│  │ All Branches        ▼ │     │ All Warehouses                  ▼ │               │
│  └────────────────────────┘     └────────────────────────────────────┘               │
│                                                                                      │
│                                      [ Reset Filters ]                               │
│                                                                                      │
├──────────────────────────────────────────────────────────────────────────────────────┤
│ SUMMARY                                                                              │
│                                                                                      │
│ ┌────────────────┐ ┌────────────────┐ ┌────────────────┐ ┌────────────────────────┐ │
│ │ TOTAL PILES    │ │ TOTAL VOLUME   │ │ AVG. PURITY    │ │ EXPECTED MILLING       │ │
│ │                │ │                │ │                │ │ RECOVERY               │ │
│ │      6         │ │   52,231 kg    │ │    93.97%      │ │  61.53% – 64.78%      │ │
│ └────────────────┘ └────────────────┘ └────────────────┘ └────────────────────────┘ │
│                                                                                      │
│ ┌────────────────┐ ┌────────────────┐                                               │
│ │ AVG. AMR       │ │ AVG. PMR       │                                               │
│ │    62.70%      │ │    62.99%      │                                               │
│ └────────────────┘ └────────────────┘                                               │
│                                                                                      │
├──────────────────────────────────────────────────────────────────────────────────────┤
│ EXPECTED MILLING RECOVERY BY PILE                                                    │
│                                                                                      │
│  61%          62%          63%          64%          65%                             │
│   │------------│------------│------------│------------│                              │
│                                                                                      │
│  GID#2 MLANG - Pile 1                                                               │
│       ├──────────────┤                                                              │
│       61.53%       62.22%                                                           │
│                                                                                      │
│  GID#2 MLANG - Pile 2                                                               │
│                    ├──┤                                                             │
│                    62.66% 62.77%                                                    │
│                                                                                      │
│  GID#2 MLANG - Pile 3                                                               │
│                           ├─┤                                                       │
│                           63.56% 63.59%                                             │
│                                                                                      │
│  GID#2 MLANG - Pile 4                                                               │
│                                      ├─────┤                                         │
│                                      64.41% 64.78%                                  │
│                                                                                      │
│  GID#2 MLANG - Pile 5                                                               │
│                         ├────┤                                                       │
│                         62.91% 63.14%                                               │
│                                                                                      │
│  GID#4 MLANG - Pile 1                                                               │
│                             ├────┤                                                   │
│                             63.25% 63.64%                                           │
│                                                                                      │
├──────────────────────────────────────────────────────────────────────────────────────┤
│ EMR SUMMARY BY WAREHOUSE                                                             │
│                                                                                      │
│ ┌───────────────────┬───────┬──────────┬───────────┬─────────┬─────────┬──────────┐ │
│ │ Warehouse         │ Piles │ Volume   │ Avg Purity│ Avg AMR │ Avg PMR │ EMR      │ │
│ ├───────────────────┼───────┼──────────┼───────────┼─────────┼─────────┼──────────┤ │
│ │ GID#2, MLANG BS   │   5   │ 44,714kg │   94.06%  │ 62.61%  │ 62.90%  │61.53-64.78│ │
│ │ GID#4, MLANG BS   │   1   │  7,517kg │   93.54%  │ 63.25%  │ 63.64%  │63.25-63.64│ │
│ └───────────────────┴───────┴──────────┴───────────┴─────────┴─────────┴──────────┘ │
│                                                                                      │
├──────────────────────────────────────────────────────────────────────────────────────┤
│ DETAILED EMR RESULTS                                                                 │
│                                                                                      │
│ ┌────┬───────────────┬────┬─────┬─────┬────────┬────────┬──────┬──────┬───────────┐ │
│ │ No │ Warehouse     │Pile│Var. │ Age │ Volume │ Purity │ AMR  │ PMR  │ EMR       │ │
│ ├────┼───────────────┼────┼─────┼─────┼────────┼────────┼──────┼──────┼───────────┤ │
│ │ 1  │ GID#2 MLANG   │ 1  │ PD  │  5  │ 11,522 │ 94.31% │61.53 │62.22 │61.53-62.22│ │
│ │ 2  │ GID#2 MLANG   │ 2  │ PD  │  5  │ 12,259 │ 95.41% │62.66 │62.77 │62.66-62.77│ │
│ │ 3  │ GID#2 MLANG   │ 3  │ PD  │  4  │  6,624 │ 92.29% │63.56 │63.59 │63.56-63.59│ │
│ │ 4  │ GID#2 MLANG   │ 4  │ PD  │  6  │  4,871 │ 93.43% │64.41 │64.78 │64.41-64.78│ │
│ │ 5  │ GID#2 MLANG   │ 5  │ PD  │  4  │  9,438 │ 94.85% │62.91 │63.14 │62.91-63.14│ │
│ │ 6  │ GID#4 MLANG   │ 1  │ PD  │  9  │  7,517 │ 93.54% │63.25 │63.64 │63.25-63.64│ │
│ └────┴───────────────┴────┴─────┴─────┴────────┴────────┴──────┴──────┴───────────┘ │
│                                                                                      │
│ Validation: 6 Valid    0 Questionable                                                 │
└──────────────────────────────────────────────────────────────────────────────────────┘
```

## 26. Final Implementation Instruction

Implement the dashboard based on the requirements above.

The **sample design at the end of this prompt is a visual reference**, not a requirement to reproduce the ASCII layout literally.

The final application should look modern, clean, professional, and consistent with the existing system.

Prioritize:

1. Correct EMR calculation.
2. Correct Branch/Warehouse filtering.
3. Correct aggregation.
4. Clear distinction between AMR, PMR, and EMR.
5. Easy identification of questionable records.
6. Clear visualization of EMR ranges.
7. Accurate detailed data.
8. Responsive design.
9. Reuse of existing application components.
10. No unnecessary changes to unrelated modules.
