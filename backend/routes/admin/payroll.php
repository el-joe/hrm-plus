<?php

use App\Http\Controllers\Admin\Payroll;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
|
| Payroll components (Phase 7a). Configuration (components, structures, statutory
| tables) is authorised by its policy (StructurePolicy subclasses); allowance and
| deduction entries by PayEntryPolicy (module from the entry kind + data scope,
| decisions through the approval engine); loans by EmployeeLoanPolicy / LoanChangePolicy.
*/

Route::resource('salary-components', Payroll\SalaryComponentController::class)->except('show');
Route::patch('salary-components/{salary_component}/status', [Payroll\SalaryComponentController::class, 'status'])->name('salary-components.status');

Route::resource('salary-structures', Payroll\SalaryStructureController::class);
Route::prefix('salary-structures/{salary_structure}')->name('salary-structures.')->group(function (): void {
    Route::patch('status', [Payroll\SalaryStructureController::class, 'status'])->name('status');
    Route::get('versions/create', [Payroll\SalaryStructureController::class, 'createVersion'])->name('versions.create');
    Route::post('versions', [Payroll\SalaryStructureController::class, 'storeVersion'])->name('versions.store');
    Route::post('assignments', [Payroll\SalaryStructureController::class, 'storeAssignment'])->name('assignments.store');
});
Route::post('employees/{employee}/salary-structure', [Payroll\SalaryStructureController::class, 'assignToEmployee'])->name('employees.salary-structure.store');
Route::patch('salary-structure-assignments/{assignment}/end', [Payroll\SalaryStructureController::class, 'endAssignment'])->name('salary-structures.assignments.end');
Route::delete('salary-structure-assignments/{assignment}', [Payroll\SalaryStructureController::class, 'destroyAssignment'])->name('salary-structures.assignments.destroy');

foreach (['allowances' => Payroll\AllowanceController::class, 'deductions' => Payroll\DeductionController::class] as $resource => $controller) {
    Route::prefix($resource)->name($resource.'.')->controller($controller)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('bulk', 'bulkForm')->name('bulk');
        Route::post('bulk', 'bulk')->name('bulk.store');
        Route::get('import', 'importForm')->name('import');
        Route::get('import/template', 'importTemplate')->name('import.template');
        Route::post('import', 'importAnalyse')->name('import.analyse');
        Route::post('import/{token}', 'importStore')->name('import.store');
        Route::get('export', 'export')->name('export');
        Route::get('{pay_entry}', 'show')->whereNumber('pay_entry')->name('show');
        Route::post('{pay_entry}/decide/{decision}', 'decide')->whereNumber('pay_entry')->whereIn('decision', ['approve', 'reject'])->name('decide');
        Route::post('{pay_entry}/cancel', 'cancel')->whereNumber('pay_entry')->name('cancel');
        Route::post('{pay_entry}/stop', 'stop')->whereNumber('pay_entry')->name('stop');
        Route::post('{pay_entry}/attachments', 'storeAttachments')->whereNumber('pay_entry')->name('attachments.store');
        Route::get('{pay_entry}/attachments/{attachment}', 'attachment')->whereNumber(['pay_entry', 'attachment'])->name('attachments.download');
    });
}

Route::resource('statutory-rules', Payroll\StatutoryRuleController::class);
Route::prefix('statutory-rules/{statutory_rule}')->name('statutory-rules.')->group(function (): void {
    Route::patch('status', [Payroll\StatutoryRuleController::class, 'status'])->name('status');
    Route::get('versions/create', [Payroll\StatutoryRuleController::class, 'createVersion'])->name('versions.create');
    Route::post('versions', [Payroll\StatutoryRuleController::class, 'storeVersion'])->name('versions.store');
});

Route::get('loans/export', [Payroll\LoanController::class, 'export'])->name('loans.export');
Route::resource('loans', Payroll\LoanController::class)->only(['index', 'create', 'store', 'show']);
Route::prefix('loans/{loan}')->name('loans.')->group(function (): void {
    Route::post('decide/{decision}', [Payroll\LoanController::class, 'decide'])->whereIn('decision', ['approve', 'reject'])->name('decide');
    Route::post('cancel', [Payroll\LoanController::class, 'cancel'])->name('cancel');
    Route::post('repay', [Payroll\LoanController::class, 'repay'])->name('repay');
    Route::post('changes', [Payroll\LoanController::class, 'storeChange'])->name('changes.store');
    Route::post('changes/{change}/decide/{decision}', [Payroll\LoanController::class, 'decideChange'])->whereNumber('change')->whereIn('decision', ['approve', 'reject'])->name('changes.decide');
    Route::post('settle', [Payroll\LoanController::class, 'settle'])->name('settle');
});

/*
| Payroll runs (Phase 7b): periods, runs / settlements (wizard, calculation, approval,
| finalisation, reversal), employee breakdowns, corrections, payslips and payments,
| exports, final settlements from offboarding. Authorised by PayrollPeriodPolicy,
| PayrollRunPolicy, PayrollAdjustmentPolicy and PayslipPolicy.
*/

Route::prefix('payroll-periods')->name('payroll-periods.')->controller(Payroll\PayrollPeriodController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('{payroll_period}', 'update')->name('update');
    Route::post('{payroll_period}/close', 'close')->name('close');
    Route::post('{payroll_period}/reopen', 'reopen')->name('reopen');
});

Route::prefix('payroll-runs')->name('payroll-runs.')->controller(Payroll\PayrollRunController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::get('preview', 'preview')->name('preview');
    Route::post('/', 'store')->name('store');
    Route::get('{payroll_run}', 'show')->whereNumber('payroll_run')->name('show');
    Route::get('{payroll_run}/scope', 'editScope')->name('scope.edit');
    Route::put('{payroll_run}/scope', 'updateScope')->name('scope.update');
    Route::post('{payroll_run}/refresh', 'refresh')->name('refresh');
    Route::delete('{payroll_run}/items/{item}', 'removeItem')->whereNumber('item')->name('items.destroy');
    Route::post('{payroll_run}/calculate', 'calculate')->name('calculate');
    Route::get('{payroll_run}/progress', 'progress')->name('progress');
    Route::post('{payroll_run}/submit', 'submit')->name('submit');
    Route::post('{payroll_run}/decide/{decision}', 'decide')->whereIn('decision', ['approve', 'reject'])->name('decide');
    Route::post('{payroll_run}/finalise', 'finalise')->name('finalise');
    Route::post('{payroll_run}/cancel', 'cancel')->name('cancel');
    Route::post('{payroll_run}/reverse', 'reverse')->name('reverse');
    Route::get('{payroll_run}/export/{kind}', 'export')->whereIn('kind', ['register', 'bank'])->name('export');
});
Route::get('payroll-runs/{payroll_run}/items/{item}', [Payroll\PayrollItemController::class, 'show'])->whereNumber(['payroll_run', 'item'])->name('payroll-runs.items.show');

Route::prefix('payroll-corrections')->name('payroll-corrections.')->controller(Payroll\PayrollCorrectionController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::post('scan', 'scan')->name('scan');
    Route::post('run', 'run')->name('run');
    Route::post('{adjustment}/dismiss', 'dismiss')->whereNumber('adjustment')->name('dismiss');
});

Route::prefix('payslips')->name('payslips.')->controller(Payroll\PayslipController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('export', 'export')->name('export');
    Route::post('mark-paid', 'markPaid')->name('mark-paid');
    Route::get('{payslip}', 'show')->whereNumber('payslip')->name('show');
    Route::get('{payslip}/pdf', 'pdf')->whereNumber('payslip')->name('pdf');
    Route::post('{payslip}/payments', 'pay')->whereNumber('payslip')->name('payments.store');
});

Route::get('offboarding/{offboarding}/final-settlement', [Payroll\FinalSettlementController::class, 'create'])->name('offboarding.final-settlement.create');
Route::post('offboarding/{offboarding}/final-settlement', [Payroll\FinalSettlementController::class, 'store'])->name('offboarding.final-settlement.store');
