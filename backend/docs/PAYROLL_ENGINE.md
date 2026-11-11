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
$factor = app(Proration::class)->factor($periodStart, $periodEnd, $segment->from, $segment->to);
$context = new CalculationContext(
    basic: bcmul($segment->basicSalary, $factor, 6),   // monthly × factor; daily × paid days; hourly × hours
    dayRate: $rates['day'], hourRate: $rates['hour'], minuteRate: $rates['minute'],
    prorationFactor: $factor,
    quantities: ['late_minutes' => '45', 'early_leave_minutes' => '0', 'absent_days' => '1', 'unpaid_leave_days' => '0.5'],
    overtime: [['hours' => '6.5', 'multiplier' => '1.50'], ['hours' => '4', 'multiplier' => '2.00']],
);

$calculator = app(ComponentCalculator::class);
$structure = $calculator->evaluate($inputs->structure->lines, $context);            // code => amount
$entries   = $calculator->evaluateEntries($inputs->entries, $context, $inputs->periodStart, $inputs->periodEnd, $structure);
```

- `evaluate()` resolves percentage chains in dependency order and throws
  `App\Exceptions\PayrollConfigurationException` on a loop or a base that is not part of the call.
  Statutory rules return no amount.
- Rounding happens once per component (`precision`, `rounding`: half_up / down / up), never on
  intermediate values (`PayrollMath::SCALE` = 6).
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
(minus deductions that reduce the taxable base, if the client's rules say so — decision for 7b).
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
