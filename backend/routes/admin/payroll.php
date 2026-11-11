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
