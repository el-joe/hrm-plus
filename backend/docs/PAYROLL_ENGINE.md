# Payroll engine contract (Phase 7a → 7b)

Phase 7a built the **component layer**: salary components, structures, allowances, deductions,
statutory rule tables and loans. Phase 7b (periods, runs, calculation, payslips, locking,
corrections) reads it **only** through the services below. Everything is DECIMAL in the database
and a numeric string in PHP (bcmath, never float). No rule is hard-coded and nothing is evaluated.

## 1. One call per employee and period

```php
$inputs = app(App\Services\Payroll\PayrollInputs::class)->for($employee, '2026-11-01', '2026-11-30');
```

Returns `App\Services\Payroll\Data\EmployeePayrollInputs` (immutable, data scopes ignored, nothing written):

| Property | Type | Meaning |
|---|---|---|
| `employeeId`, `periodStart`, `periodEnd` | int, `Y-m-d` | |
| `currency`, `countryCode` | string | settings `localisation.currency_code`, `payroll.country_code` (may be `''`) |
| `basicSalary`, `payBasis` | `?string`, `?PayBasis` | `employee_salaries` row in force at the period end (null = no salary) |
| `salarySegments` | `list<SalarySegment>` | every salary row covering part of the period, clipped (`from`, `to`, `basicSalary`, `payBasis`, `salaryId`). Future-dated rows count from their date even if not applied yet; cancelled rows never |
| `structure` | `?StructureSnapshot` | structure, version and assignment in force **at the period end**: `structureId`, `code`, `versionId`, `version`, `versionFrom`, `assignmentId`, `scope`, `lines` (`list<ComponentRule>`, display order, line overrides applied) |
| `entries` | `list<PayEntryInput>` | approved allowances / deductions that occur in the period, **after scope precedence** |
| `loanInstalments` | `list<LoanInstalmentInput>` | scheduled instalments of active loans due on or before the period end (`overdue = true` when before the start) |
| `statutory` | `array<string, StatutoryRule>` | statutory version in force at the period end, keyed `income_tax` / `social_insurance` (missing = none applies) |
| `catalogue` | `array<code, ComponentRule>` | active basic / attendance / leave / statutory components (informational) |
| `branchId`, `departmentId`, `jobGradeId` | `?int` | placement at the period end (the one precedence used) |
| `hireDate`, `terminationDate` | `?string` | for proration |

Helpers: `allowances()`, `deductions()`, `statutoryRule(StatutoryType)`, `loanInstalmentTotal()`.
`toArray()` is JSON-safe — **store it with every calculation** (spec module 9 H: input values,
structure version, rule versions).

### ComponentRule

`componentId, code, nameAr, nameEn, kind (earning | deduction | employer_contribution), category,
calculationType, amount, rate, baseCode, usePolicyMultiplier, statutoryType, taxable, insurable,
prorate, showOnPayslip, rounding, precision, displayOrder` — `name($locale)` gives the label.

| `calculationType` | `amount` | `rate` | quantity read from the context |
|---|---|---|---|
| `basic` | — | — | `context->basic` |
| `fixed` / entry fixed | per period (× proration factor when `prorate`) | — | — |
| `percentage` | — | percent of `baseCode` (null = basic) | — |
| `late_minutes` / `early_leave_minutes` | fixed per minute, or null | multiplier of the minute rate (null = 1) | `late_minutes` / `early_leave_minutes` |
| `absent_days` / `unpaid_leave_days` | fixed per day, or null | multiplier of the day rate (null = 1) | `absent_days` / `unpaid_leave_days` |
| `overtime_hours` | fixed per hour, or null | multiplier when `usePolicyMultiplier` is false (null = setting `payroll.overtime_rate_multiplier`) | `overtime` rows `[hours, multiplier]` |
| `statutory` | — | — | use `StatutoryCalculator` |

Kinds: **earnings add to gross, deductions subtract from net, employer contributions are an
employer cost only** (never on net). All amounts returned by the calculator are positive.

### PayEntryInput

`entryId, reference, rule (ComponentRule — always fixed or percentage), target, recurrence,
activeFrom, activeTo, instalmentNumber, instalmentCount`.

- recurring: every period overlapping `[start_date, end_date]`; `activeFrom/To` is the overlap;
- one-time: only the period containing `start_date`; never prorated;
- instalment (deductions): the period containing instalment *n*'s due date (`start_date + n−1`
  months); `rule->amount` is that instalment (the last absorbs rounding).

**Precedence per component** (most specific first): employee > employee group > department
(the employee's, then each parent) > branch > company. Only the most specific level that has an
entry of a component applies; several entries of that level all apply.

### LoanInstalmentInput

`instalmentId, loanId, loanReference, loanType, sequence, dueDate, amount (remaining), overdue,
finalSettlement`.

### StatutoryRule

`setId, setCode, versionId, version, countryCode, type, isDemo, effectiveFrom, effectiveTo,
employeeRate, employerRate, salaryFloor, salaryCeiling, annualExemption, brackets
[{lower, upper|null, rate}]`. **`isDemo = true` means illustrative values — show
"DEMO — not legal advice" on anything calculated with it.**

## 2. Calculating amounts

```php
use App\Services\Payroll\{ComponentCalculator, SalaryRates, Proration, StatutoryCalculator};
use App\Services\Payroll\Data\CalculationContext;

$rates = app(SalaryRates::class)->for($segment->basicSalary, $segment->payBasis); // day, hour, minute
$proration = app(Proration::class);
$factor = $proration->factor($periodStart, $periodEnd, $segment->from, $segment->to);       // display: 0.677419
[$days, $periodDays] = $proration->fraction($periodStart, $periodEnd, $segment->from, $segment->to); // exact: [21, 31]
$context = new CalculationContext(
    basic: PayrollMath::fraction($segment->basicSalary, (string) $days, (string) $periodDays), // monthly: salary × days ÷ period days
    dayRate: $rates['day'], hourRate: $rates['hour'], minuteRate: $rates['minute'],
    prorationFactor: $factor,
    quantities: ['late_minutes' => '45', 'early_leave_minutes' => '0', 'absent_days' => '1', 'unpaid_leave_days' => '0.5'],
    overtime: [['hours' => '6.5', 'multiplier' => '1.50'], ['hours' => '4', 'multiplier' => '2.00']],
    prorationNumerator: (string) $days, prorationDenominator: (string) $periodDays,
);

$calculator = app(ComponentCalculator::class);
$structure = $calculator->evaluate($inputs->structure->lines, $context);            // code => amount
$entries   = $calculator->evaluateEntries($inputs->entries, $context, $inputs->periodStart, $inputs->periodEnd, $structure);
```

- `evaluate()` resolves percentage chains in dependency order and throws
  `App\Exceptions\PayrollConfigurationException` on a loop or a base that is not part of the call.
  Statutory rules return no amount.
- Rounding happens once per component line (`precision`, `rounding`: half_up / down / up), never on
  intermediate values (`PayrollMath::SCALE` = 6).
- **Proration (Phase 11 decision)**: a monthly salary (and every prorated fixed component or recurring
  entry) is prorated **exactly**: `amount × paid days ÷ period days`, computed with bcmath with the
  division last (`PayrollMath::fraction()`), and rounded **once** on the payroll line. The 6-decimal
  `prorationFactor` (e.g. `0.677419` for 21 / 31) is only shown on payslips and stored in snapshots — it is
  never multiplied into an amount. Example, October (31 days):
  - joiner on the 11th, salary 6,000: 6000 × 21 ÷ 31 = 4064.516… → **4,064.52** (a truncated factor
    would give 6000 × 0.677419 = 4064.51);
  - salary change 6,000 → 9,000 on the 16th: 6000 × 15 ÷ 31 + 9000 × 16 ÷ 31 = 2903.2258… + 4645.1612…
    = 7548.387… → **7,548.39** (segments are summed unrounded, the BASIC line is rounded once);
  - a prorated recurring entry inside a partial month multiplies both fractions
    (`amount × paid days × entry days ÷ period days²`).
  Percentage components are based on the rounded line they refer to (e.g. housing = 25 % of the BASIC
  line as shown on the payslip).
- Rates (`SalaryRates`): monthly / fixed → day = salary ÷ `payroll.working_days_per_month`, hour =
  day ÷ `payroll.working_hours_per_day`; daily → day = salary; hourly → hour = salary.
- **Pay basis semantics for 7b**: `monthly` prorated on partial periods and reduced by attendance /
  unpaid leave; `daily` = rate × paid days; `hourly` = rate × worked hours; `fixed` = the full amount
  every period, no proration and no attendance deductions (rates still exist for overtime).
- Mid-period salary changes: one context per `SalarySegment`, sum the results.

Quantities come from Phase 5 / 6 (7a does **not** compute them):

| Quantity | Source |
|---|---|
| `late_minutes`, `early_leave_minutes` | `attendance_days.late_minutes`, `early_leave_minutes` (+ `attendance_adjustments` deltas for locked periods) |
| `absent_days` | `attendance_days` with an absent status (`on_leave` days are not absences) |
| `overtime` rows | `attendance_days.overtime_approved_minutes` (÷ 60) per kind with the policy version's `overtime_rate_regular` / `_rest_day` / `_holiday` |
| `unpaid_leave_days` | `App\Services\Leave\LeavePayrollFeed::unpaidDays($employee, $from, $to)` |
| encashments | `LeavePayrollFeed::encashments()` — pay as an earning line (days × day rate) |

Statutory:

```php
$si  = app(StatutoryCalculator::class)->socialInsurance($rule, $insurableTotal); // ['base', 'employee', 'employer']
$tax = app(StatutoryCalculator::class)->incomeTax($rule, $taxableTotal, 12);      // per-period tax
```

Insurable / taxable totals are the sums of the lines whose rule says `insurable` / `taxable`
(minus the deductions configured on the statutory version as base reductions — implemented in 7b, see §6).
Social insurance employee share is a deduction; employer share an employer contribution.

## 3. Loans: write-back on finalisation

```php
$hook = app(App\Services\Payroll\LoanInstalments::class);
$hook->markDeducted($instalment, $amountRecovered, $payrollItem, $user);   // when the run is FINALISED
$hook->reverseDeduction($instalment, $correction, $user);                  // correction / reversal workflow
```

- Call `markDeducted()` only when a run is finalised (drafts and recalculations must not touch
  loans — `dueFor()` stays idempotent). Partial recovery (net pay too low) keeps the rest scheduled.
- Both write the append-only ledger (`loan_transactions`); a zero balance completes the loan; a
  reversal reopens it. `settled_by` (morph) on the instalment points at your payroll record — add
  your model to the morph map.

## 4. What 7b must not do

- Do not read `employees.basic_salary` (a mirror for display) — use `salarySegments`.
- Do not query `pay_entries`, `salary_structure_*`, `statutory_*` or `loan_*` directly for
  calculation; go through the services so precedence and versioning stay in one place.
- Never edit a structure version, statutory version or approved entry: they are history.
- Do not seed real statutory rates (blocker B7). The dev seed sets `payroll.country_code = EG`
  and two **demo** tables (`is_demo = true`, made-up round numbers).

## 5. Approval workflows and permissions available to 7b

Workflows (seeded by `PayrollDefaultsSeeder`): `payroll_allowance`, `payroll_deduction`,
`loan_request`, `loan_change`. Permissions: `payroll.*` is reserved for runs; components use
`salary_components.*`, `salary_structures.*`, `allowances.*`, `deductions.*`, `statutory_rules.*`,
`loans.*`. Financial values shown on the employee profile need `employee_financials.view` and are
logged through `SensitiveDataAccessLog::FINANCIAL`.

---

# Phase 7b — runs, engine, payslips (implemented)

## 6. The engine: `App\Services\Payroll\PayrollCalculator`

```php
$result = app(PayrollCalculator::class)->calculate($employee, $period, PayrollRunType::Regular, $pendingAdjustments, $leavingDate);
// PayrollResult: lines (PayrollLine[]), grossPay, totalDeductions, employerContributions, netPay,
// taxableBase, insurableBase, basicSalary, payBasis, prorationFactor, inputs (snapshot), quantities,
// ruleVersions, warnings [{code, params, blocking}], loanRecoveries, adjustmentIds, hash (sha256)
```

Pure (reads only). Order for regular runs and final settlements:

1. **Employment window** = period ∩ [hire date, termination date] (a final settlement prepared before the
   case is completed uses the case's last working day). Salary segments are clipped to it.
2. **Per salary segment** (mid-period changes): rates (`SalaryRates`), its own attendance quantities
   (`AttendanceQuantities`) and unpaid days (`LeavePayrollFeed::unpaidDays`); the segment-sensitive rules
   (basic + per-minute / per-day / overtime) are evaluated per segment, summed unrounded, then each line is rounded once.
   - `monthly`: salary × calendar days ÷ period days (exact, rounded once on the line); attendance and unpaid-leave deductions apply;
   - `daily`: day rate × paid days (present / late + holidays + paid leave); absences and unpaid leave are simply not paid;
   - `hourly`: hour rate × worked hours;
   - `fixed`: full amount of the salary in force at the period end, no proration, no attendance deductions (overtime paid).
3. **Fixed / percentage structure lines** once, on the combined context (sum of basics; paid days of all segments ÷ period days).
4. **Entries** (`PayrollInputs::entries`) one line each — they **add** to structure lines of the same component.
5. **Leave encashment**: `LeavePayrollFeed::encashments()` × day rate, on the component of setting
   `payroll.leave_encashment_component` (default `LEAVE_ENCASH`; missing component = blocking warning).
6. **Pending adjustments** (corrections of earlier finalised periods) as `correction` lines (signed: a refund of a
   deduction is a negative deduction).
7. **Statutory** lines of the structure, with the version in force at the period end:
   - insurable base = Σ insurable earnings − Σ deduction lines whose category / code is in the version's
     `base_reductions`, then floor / ceiling → employee share (deduction) and employer share (employer contribution);
   - taxable base = Σ taxable earnings − Σ reducing deductions − employee insurance when the tax version has
     `deduct_employee_insurance`; annualised with `PayrollPeriod::periodsPerYear()` (12 for monthly).
   - **The taxable / insurable base is configured on the statutory version** (Statutory rules → new version →
     "Calculation base"): ticked component categories and an optional component code. Nothing is hard-coded.
8. **Loans**: instalments due (`PayrollInputs::loanInstalments`); a final settlement recovers every scheduled
   instalment (`LoanInstalments::dueFor($employee, $start, '2999-12-31')`). When net pay is negative the loan
   lines are reduced (latest first) — partial recovery, the rest stays scheduled (`loan_partial` warning).
9. Net = gross − deductions. Still negative → `negative_net` **blocking** warning (the run cannot be submitted).

Correction runs only carry the employees' pending adjustments (no salary, no statutory).

Every line stores `source` (basic, structure, entry, attendance, leave, encashment, loan, statutory, correction,
reversal), `sourceable` (pay entry, loan instalment, statutory version, leave transaction, adjustment), quantity,
rate and an **explanation** (`[{key, params}]`, keys in `lang/*/payroll_runs.php` `explain.*`; parameters that
need a language are stored as `{ar, en}` and resolved by `PayrollItemLine::explanationLines($locale)`), e.g.
`late_minutes` → "12 late minutes × 2.50 = 30.00".

Warnings (`payroll_runs.warnings.*`): `configuration_error`*, `not_employed`*, `negative_net`*,
`encashment_component_missing`* (* blocking), `partial_period`, `missing_salary`, `no_structure`,
`statutory_missing`, `demo_statutory`, `loan_partial`, `overtime_pending`, `mobile_pending_review` (flagged mobile
check-ins awaiting review are not attendance), `incomplete_days`, `locked_adjustments`, `no_attendance`,
`fixed_basis`, `moved_in_period`.

### Attendance quantities — `AttendanceQuantities::between($employeeId, $from, $to)`

Day figures = `attendance_days` row + its non-superseded `attendance_adjustments` (status = last
`status_after`; `removed` drops the day). Late / early / worked minutes summed; absent days = share of the date's
rows that are absent; overtime = `overtime_approved_minutes` of days with `overtime_status = approved`, grouped
by kind with the day's policy-version multiplier (`holiday_id` → `overtime_rate_holiday`, `is_rest_day` →
`_rest_day`, else `_regular`). Also: pending overtime days, incomplete days, mobile attempts pending review.

## 7. Runs — `App\Actions\Payroll\ManagePayrollRun`

States (`PayrollRunStatus`): `draft → calculating → calculated (review) → pending_approval → approved →
finalised → reversed`, or `cancelled` from draft / calculated. Every method locks the run row (`FOR UPDATE`)
and audits `payroll.run_*`.

| Method | What it does |
|---|---|
| `create($period, $type, $scope, $user, $name, $notes, $case)` | locks the period, builds the population (`PayrollPopulation`), inserts one `payroll_items` row per employee reserving the pay slot |
| `updateScope` / `refresh` / `removeEmployee` | rebuild / re-evaluate / exclude (draft or calculated only) |
| `calculate($run, $user)` | claims the run (`calculating`), scans corrections of its employees, calculates every item (lines replaced, adjustments re-reserved), totals; above `payroll.sync_calculation_limit` employees → `CalculatePayrollRun` job (unique per run) with `progress_done / progress_total` (`GET …/progress`) |
| `submit` | calculated, no failed / blocking item → approval engine workflow `payroll_run` |
| `decide` / `applyApprovalOutcome` | approval steps; final outcome via `SyncPayrollApprovals` (approved, or back to calculated) |
| `finalise` | approved only: `LoanInstalments::markDeducted($instalment, $amount, $item, $user, pay date)` for every loan line, carried adjustments → applied, payslips issued, `totals_hash`, offboarding settlement item done, notifications |
| `cancel` | releases pay slots and carried adjustments |
| `reverse` | finalised and nothing paid: a `reversal` run with mirrored negative items / lines, `reverseDeduction()` for every recovered instalment, payslips void, applied adjustments pending again, pay slots released, original run `reversed` |

**Double pay**: `payroll_items.pay_slot = 'regular'` for regular runs and final settlements; unique
`(payroll_period_id, employee_id, pay_slot)`; cancelled / reversed runs set it to null. The population preview
already excludes employees whose slot is held (`already_paid`).

**Scoping** (`PayrollPopulation`): `{all, branch_ids, department_ids (+ sub-departments), team_ids, position_ids,
employee_group_ids, employee_ids (add), exclude_employee_ids}` — AND across dimensions, OR within one; placement
at the period end; employees who moved out of a selected branch during the period are listed as `moved_out`;
candidates are employed at least one day of the period; the admin's data scope applies.

## 8. Corrections — `App\Services\Payroll\PayrollCorrections`

`scan(?User, ?array $employeeIds, int $months = 6)` compares, per finalised item and salary segment, the stored
quantities with today's (attendance + leave feed) and prices each difference with the item's frozen rule and
rates → `payroll_adjustments` (pending, fingerprinted, idempotent). Encashments booked into a paid period too.
Runs daily (`payroll:scan-corrections`, 02:45), from "Scan now", and before every salaried calculation.
`manual()` and `dismiss()` with reasons. A later regular run carries the employee's pending adjustments of
earlier periods; a **correction run** (`PayrollRunType::Correction`) pays them off-cycle.

## 9. Payslips, payments, exports

- `payslips` (one per finalised item; `PS-YYYY-NNNNN`; locale = employee account locale) with `payment_status`
  (unpaid / partially_paid / paid), `payslip_payments` (append-only) — `ManagePayslipPayments::record()` / `markPaid()`.
- `PayslipDocument::data($payslip, $locale)` (HTML + API lines) / `pdf()` (mPDF 8.3, Cairo TTF from
  `resources/fonts/cairo` with OTL shaping, RTL for Arabic; harakat stripped in the PDF because mPDF does not
  support Cairo's mark-filtering sets; fallback to mPDF's bundled XB Riyaz). Cached on the private disk under
  `payslips/{employee}/`; `TEMPLATE_VERSION` busts the cache.
- `PayrollExports::register()` (one column per component) and `bankFile()` (generic CSV / XLSX: bank, holder,
  IBAN, account, SWIFT, amount, currency, payslip number; "missing bank account" flagged).
- Access: employees only their own issued payslips (`PortalPayslips`), admins `payslips.view` within their data
  scope; every view / PDF / export → `sensitive_access.payslip` / `sensitive_access.payroll`.

## 10. Reading payroll data (Phase 8 dashboard and reports)

- Finalised, live pay of a period: `payroll_items` with `pay_slot = 'regular'` joined to `payroll_runs.status =
  'finalised'` (exclude `reversed`); corrections: `payroll_runs.type = 'correction'`; reversals carry negative
  amounts, so summing all finalised + reversal runs nets out.
- Component totals: `payroll_item_lines` (`code`, `kind`, `category`, `source`, signed `amount`, `employee_id`,
  `payroll_run_id`) — index `(payroll_run_id, code)`.
- Placement for grouping: `payroll_items.branch_id / department_id / team_id / position_id / cost_center_id`
  (period end).
- Employer cost: `payroll_items.employer_contributions`; statutory bases: `taxable_base`, `insurable_base`.
- Payment status: `payslips.payment_status`, `amount_paid`, `paid_on`.
- Always apply the admin data scope (`PayrollItem`, `Payslip` use `HasDataScope`).
