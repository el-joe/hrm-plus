<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\Employees;
use App\Http\Controllers\Admin\Structure;
use App\Http\Controllers\CompanyLogoController;
use App\Http\Controllers\Employee;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| Two separate areas:
|   /admin   admin dashboard   — middleware "admin"  (admin guard, Admin accounts only)
|   /portal  employee portal   — middleware "portal" (employee guard, or an admin
|                                 linked to an employee profile)
|
| "admin" only proves the visitor is an admin. EVERY admin route must also
| carry a permission: ->can('module.action') for a plain permission, or
| Gate::authorize()/a Form Request when a policy decides per record.
|
*/

Route::redirect('/', '/portal');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

// Public: shown on sign-in pages and in the mobile app.
Route::get('company/logo', CompanyLogoController::class)->name('company.logo');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest:admin')->group(function (): void {
        Route::get('login', [Admin\Auth\LoginController::class, 'create'])->name('login');
        Route::post('login', [Admin\Auth\LoginController::class, 'store'])->name('login.store');
        Route::get('forgot-password', [Admin\Auth\PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [Admin\Auth\PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:password-reset')->name('password.email');
        Route::get('reset-password/{token}', [Admin\Auth\NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [Admin\Auth\NewPasswordController::class, 'store'])
            ->middleware('throttle:password-reset')->name('password.update');
    });

    Route::middleware('admin')->group(function (): void {
        Route::post('logout', [Admin\Auth\LoginController::class, 'destroy'])->name('logout');

        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        // Company structure. Record-level rules (data scope, in-use protection)
        // live in App\Policies\StructurePolicy and App\Actions\Structure.
        Route::get('company', [Structure\CompanyController::class, 'show'])->can('company.view')->name('company.show');
        Route::get('company/edit', [Structure\CompanyController::class, 'edit'])->can('company.update')->name('company.edit');
        Route::put('company', [Structure\CompanyController::class, 'update'])->can('company.update')->name('company.update');

        Route::get('org-chart', Structure\OrgChartController::class)->can('departments.view')->name('org-chart');

        $structure = [
            'branches' => Structure\BranchController::class,
            'departments' => Structure\DepartmentController::class,
            'teams' => Structure\TeamController::class,
            'positions' => Structure\PositionController::class,
            'job-titles' => Structure\JobTitleController::class,
            'job-grades' => Structure\JobGradeController::class,
            'employment-types' => Structure\EmploymentTypeController::class,
            'cost-centers' => Structure\CostCenterController::class,
            'employee-groups' => Structure\EmployeeGroupController::class,
        ];

        foreach ($structure as $resource => $controller) {
            $parameter = str_replace('-', '_', Str::singular($resource));
            $withShow = in_array($resource, ['branches', 'departments'], true);

            Route::resource($resource, $controller)->except($withShow ? [] : ['show']);
            Route::patch("{$resource}/{{$parameter}}/status", [$controller, 'status'])->name("{$resource}.status");
        }

        Route::resource('branches.locations', Structure\BranchLocationController::class)
            ->except(['index', 'show'])->scoped();

        // Employees (Phase 3b). Record-level rules (permission + data scope,
        // sensitive / financial / document permissions) live in EmployeePolicy
        // and the Form Requests; nested records are scoped to their employee.
        Route::prefix('employees')->name('employees.')->group(function (): void {
            Route::get('export', Employees\EmployeeExportController::class)->name('export');
            Route::get('import', [Employees\EmployeeImportController::class, 'create'])->name('import');
            Route::get('import/template', [Employees\EmployeeImportController::class, 'template'])->name('import.template');
            Route::post('import', [Employees\EmployeeImportController::class, 'analyse'])->name('import.analyse');
            Route::post('import/{token}', [Employees\EmployeeImportController::class, 'store'])->name('import.store');
            Route::post('bulk', Employees\EmployeeBulkController::class)->name('bulk');
        });

        Route::resource('employees', Employees\EmployeeController::class)->except('destroy');

        Route::prefix('employees/{employee}')->name('employees.')->scopeBindings()->group(function (): void {
            Route::get('tab/{tab}', [Employees\EmployeeController::class, 'show'])
                ->whereIn('tab', Employees\EmployeeController::TABS)->name('tab');
            Route::get('photo', Employees\EmployeePhotoController::class)->name('photo');
            Route::patch('status', Employees\EmployeeStatusController::class)->name('status');

            Route::get('assignments/create', [Employees\EmployeeAssignmentController::class, 'create'])->name('assignments.create');
            Route::post('assignments', [Employees\EmployeeAssignmentController::class, 'store'])->name('assignments.store');
            Route::delete('assignments/{assignment}', [Employees\EmployeeAssignmentController::class, 'destroy'])->name('assignments.destroy');

            Route::get('terminate', [Employees\EmployeeTerminationController::class, 'create'])->name('terminate');
            Route::post('terminate', [Employees\EmployeeTerminationController::class, 'store'])->name('terminate.store');
            Route::get('rehire', [Employees\EmployeeTerminationController::class, 'rehireForm'])->name('rehire');
            Route::post('rehire', [Employees\EmployeeTerminationController::class, 'rehire'])->name('rehire.store');

            Route::post('salaries', [Employees\EmployeeFinanceController::class, 'storeSalary'])->name('salaries.store');
            Route::delete('salaries/{salary}', [Employees\EmployeeFinanceController::class, 'cancelSalary'])->name('salaries.destroy');
            Route::post('bank-accounts', [Employees\EmployeeFinanceController::class, 'storeBankAccount'])->name('bank-accounts.store');
            Route::put('bank-accounts/{bank_account}', [Employees\EmployeeFinanceController::class, 'updateBankAccount'])->name('bank-accounts.update');
            Route::delete('bank-accounts/{bank_account}', [Employees\EmployeeFinanceController::class, 'destroyBankAccount'])->name('bank-accounts.destroy');

            Route::get('contracts/create', [Employees\EmployeeContractController::class, 'create'])->name('contracts.create');
            Route::post('contracts', [Employees\EmployeeContractController::class, 'store'])->name('contracts.store');
            Route::get('contracts/{contract}/edit', [Employees\EmployeeContractController::class, 'edit'])->name('contracts.edit');
            Route::put('contracts/{contract}', [Employees\EmployeeContractController::class, 'update'])->name('contracts.update');
            Route::get('contracts/{contract}/renew', [Employees\EmployeeContractController::class, 'renewForm'])->name('contracts.renew-form');
            Route::post('contracts/{contract}/renew', [Employees\EmployeeContractController::class, 'renew'])->name('contracts.renew');
            Route::get('contracts/{contract}/download', [Employees\EmployeeContractController::class, 'download'])->name('contracts.download');

            Route::post('documents', [Employees\EmployeeDocumentController::class, 'store'])->name('documents.store');
            Route::put('documents/{document}', [Employees\EmployeeDocumentController::class, 'update'])->name('documents.update');
            Route::delete('documents/{document}', [Employees\EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');
            Route::get('documents/{document}/download', [Employees\EmployeeDocumentController::class, 'download'])->name('documents.download');

            Route::post('account', [Employees\EmployeeAccountController::class, 'store'])->name('account.store');
            Route::post('account/invite', [Employees\EmployeeAccountController::class, 'invite'])->name('account.invite');
            Route::patch('account/status', [Employees\EmployeeAccountController::class, 'status'])->name('account.status');
        });

        Route::resource('employee-custom-fields', Employees\EmployeeCustomFieldController::class)
            ->except('show')->parameters(['employee-custom-fields' => 'custom_field'])->middleware('can:employees.manage');

        // Access control. Record-level rules live in AdminUserPolicy / RolePolicy
        // and are enforced inside the controllers and Form Requests.
        Route::resource('admin-users', Admin\AdminUserController::class);
        Route::get('admin-users/{admin_user}/login-history', Admin\AdminUserLoginHistoryController::class)
            ->name('admin-users.login-history');
        Route::resource('roles', Admin\RoleController::class)->except('show');

        Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])
            ->can('audit_logs.view')->name('audit-logs.index');
        Route::get('audit-logs/{audit_log}', [Admin\AuditLogController::class, 'show'])
            ->can('audit_logs.view')->name('audit-logs.show');

        Route::get('settings', [Admin\SettingController::class, 'index'])
            ->can('settings.view')->name('settings.index');
        Route::get('settings/{group}', [Admin\SettingController::class, 'edit'])
            ->whereIn('group', array_keys(config('settings')))
            ->can('settings.view')->name('settings.edit');
        Route::put('settings/{group}', [Admin\SettingController::class, 'update'])
            ->whereIn('group', array_keys(config('settings')))
            ->can('settings.manage')->name('settings.update');

        // Component showcase for developers; Super Admin only (Gate "viewUiKit").
        Route::get('ui-kit', Admin\UiKitController::class)->can('viewUiKit')->name('ui-kit');
    });
});

Route::prefix('portal')->name('portal.')->group(function (): void {
    Route::middleware('guest:employee')->group(function (): void {
        Route::get('login', [Employee\Auth\LoginController::class, 'create'])->name('login');
        Route::post('login', [Employee\Auth\LoginController::class, 'store'])->name('login.store');
        Route::get('forgot-password', [Employee\Auth\PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [Employee\Auth\PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:password-reset')->name('password.email');
        Route::get('reset-password/{token}', [Employee\Auth\NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [Employee\Auth\NewPasswordController::class, 'store'])
            ->middleware('throttle:password-reset')->name('password.update');
    });

    Route::middleware('portal')->group(function (): void {
        Route::post('logout', [Employee\Auth\LoginController::class, 'destroy'])->name('logout');

        Route::get('/', Employee\HomeController::class)->name('home');

        // My profile: always the signed-in user's own employee record.
        Route::get('profile/{tab?}', Employee\ProfileController::class)
            ->whereIn('tab', Employee\ProfileController::TABS)->name('profile');
        Route::get('documents/{document}/download', Employee\DocumentDownloadController::class)
            ->whereNumber('document')->name('documents.download');
    });
});
