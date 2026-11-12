<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\Employees;
use App\Http\Controllers\Admin\Lifecycle;
use App\Http\Controllers\Admin\Recruitment;
use App\Http\Controllers\Admin\Structure;
use App\Http\Controllers\Careers\CareerController;
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

// Public career page (Phase 4), only while the setting recruitment.career_page_enabled is on.
Route::prefix('careers')->name('careers.')->middleware('careers')->group(function (): void {
    Route::get('/', [CareerController::class, 'index'])->name('index');
    Route::get('{reference}', [CareerController::class, 'show'])->where('reference', '[A-Z0-9-]+')->name('show');
    Route::post('{reference}/apply', [CareerController::class, 'apply'])->where('reference', '[A-Z0-9-]+')
        ->middleware('throttle:career-apply')->name('apply');
});

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

        // Recruitment (Phase 4). Record-level rules (permission + data scope by
        // vacancy branch / department, salary visibility) live in the policies
        // under App\Policies and in App\Services\Recruitment\SalaryVisibility.
        Route::resource('vacancies', Recruitment\VacancyController::class);
        Route::post('vacancies/{vacancy}/pipeline', [Recruitment\VacancyController::class, 'customisePipeline'])->name('vacancies.pipeline.customise');
        Route::delete('vacancies/{vacancy}/pipeline', [Recruitment\VacancyController::class, 'resetPipeline'])->name('vacancies.pipeline.reset');

        Route::resource('recruitment-stages', Recruitment\RecruitmentStageController::class)
            ->except('show')->parameters(['recruitment-stages' => 'stage']);
        Route::patch('recruitment-stages/{stage}/move', [Recruitment\RecruitmentStageController::class, 'move'])->name('recruitment-stages.move');
        Route::resource('evaluation-forms', Recruitment\EvaluationFormController::class)->except('show')->middleware('can:recruitment.manage');

        Route::resource('candidates', Recruitment\CandidateController::class);
        Route::prefix('candidates/{candidate}')->name('candidates.')->scopeBindings()->group(function (): void {
            Route::get('cv', [Recruitment\CandidateController::class, 'cv'])->name('cv');
            Route::post('applications', [Recruitment\CandidateController::class, 'apply'])->name('apply');
            Route::post('notes', [Recruitment\CandidateRecordController::class, 'storeNote'])->name('notes.store');
            Route::post('attachments', [Recruitment\CandidateRecordController::class, 'storeAttachment'])->name('attachments.store');
            Route::get('attachments/{attachment}', [Recruitment\CandidateRecordController::class, 'downloadAttachment'])->name('attachments.download');
            Route::delete('attachments/{attachment}', [Recruitment\CandidateRecordController::class, 'destroyAttachment'])->name('attachments.destroy');
        });

        Route::prefix('applications/{application}')->name('applications.')->group(function (): void {
            Route::get('/', [Recruitment\ApplicationController::class, 'show'])->name('show');
            Route::patch('stage', [Recruitment\ApplicationController::class, 'move'])->name('move');
            Route::get('hire', [Recruitment\ApplicationController::class, 'convertForm'])->name('convert');
            Route::post('hire', [Recruitment\ApplicationController::class, 'convert'])->name('convert.store');
            Route::get('interviews/create', [Recruitment\InterviewController::class, 'create'])->name('interviews.create');
            Route::post('interviews', [Recruitment\InterviewController::class, 'store'])->name('interviews.store');
            Route::get('offers/create', [Recruitment\OfferController::class, 'create'])->name('offers.create');
            Route::post('offers', [Recruitment\OfferController::class, 'store'])->name('offers.store');
        });

        Route::get('interviews', [Recruitment\InterviewController::class, 'index'])->name('interviews.index');
        Route::prefix('interviews/{interview}')->name('interviews.')->group(function (): void {
            Route::get('/', [Recruitment\InterviewController::class, 'show'])->name('show');
            Route::get('edit', [Recruitment\InterviewController::class, 'edit'])->name('edit');
            Route::put('/', [Recruitment\InterviewController::class, 'update'])->name('update');
            Route::patch('status', [Recruitment\InterviewController::class, 'status'])->name('status');
            Route::post('decision', [Recruitment\InterviewController::class, 'decide'])->name('decision');
            Route::post('scorecard', [Recruitment\InterviewController::class, 'scorecard'])->name('scorecard');
            Route::get('cv', [Recruitment\InterviewController::class, 'cv'])->name('cv');
        });

        Route::get('offers', [Recruitment\OfferController::class, 'index'])->name('offers.index');
        Route::prefix('offers/{offer}')->name('offers.')->group(function (): void {
            Route::get('/', [Recruitment\OfferController::class, 'show'])->name('show');
            Route::get('edit', [Recruitment\OfferController::class, 'edit'])->name('edit');
            Route::put('/', [Recruitment\OfferController::class, 'update'])->name('update');
            Route::get('letter', [Recruitment\OfferController::class, 'letter'])->name('letter');
            Route::post('{action}', [Recruitment\OfferController::class, 'transition'])
                ->whereIn('action', ['submit', 'send', 'accept', 'decline', 'withdraw'])->name('transition');
            Route::post('approval/{decision}', [Recruitment\OfferController::class, 'decide'])
                ->whereIn('decision', ['approve', 'reject'])->name('decide');
        });

        // Onboarding and offboarding (Phase 4). Scoped through the employee.
        Route::resource('onboarding', Lifecycle\OnboardingController::class)->only(['index', 'create', 'store', 'show']);
        Route::prefix('onboarding/{onboarding}')->name('onboarding.')->scopeBindings()->group(function (): void {
            Route::post('tasks', [Lifecycle\OnboardingController::class, 'storeTask'])->name('tasks.store');
            Route::patch('tasks/{task}', [Lifecycle\OnboardingController::class, 'updateTask'])->name('tasks.update');
            Route::delete('tasks/{task}', [Lifecycle\OnboardingController::class, 'destroyTask'])->name('tasks.destroy');
            Route::post('cancel', [Lifecycle\OnboardingController::class, 'cancel'])->name('cancel');
        });

        Route::middleware('can:onboarding.manage')->group(function (): void {
            Route::resource('onboarding-templates', Lifecycle\OnboardingTemplateController::class);
            Route::prefix('onboarding-templates/{onboarding_template}')->name('onboarding-templates.')->scopeBindings()->group(function (): void {
                Route::post('tasks', [Lifecycle\OnboardingTemplateController::class, 'storeTask'])->name('tasks.store');
                Route::get('tasks/{task}/edit', [Lifecycle\OnboardingTemplateController::class, 'editTask'])->name('tasks.edit');
                Route::put('tasks/{task}', [Lifecycle\OnboardingTemplateController::class, 'updateTask'])->name('tasks.update');
                Route::delete('tasks/{task}', [Lifecycle\OnboardingTemplateController::class, 'destroyTask'])->name('tasks.destroy');
            });
        });

        Route::resource('offboarding', Lifecycle\OffboardingController::class)->only(['index', 'create', 'store', 'show', 'update']);
        Route::prefix('offboarding/{offboarding}')->name('offboarding.')->scopeBindings()->group(function (): void {
            Route::post('items', [Lifecycle\OffboardingController::class, 'storeItem'])->name('items.store');
            Route::patch('items/{item}', [Lifecycle\OffboardingController::class, 'updateItem'])->name('items.update');
            Route::post('exit-interview', [Lifecycle\OffboardingController::class, 'exitInterview'])->name('exit-interview');
            Route::post('complete', [Lifecycle\OffboardingController::class, 'complete'])->name('complete');
            Route::post('cancel', [Lifecycle\OffboardingController::class, 'cancel'])->name('cancel');
        });

        // Attendance (Phase 5a): operations (daily records, corrections, bulk entry,
        // import, locks) and configuration (shifts, schedules, calendar,
        // holidays, policies, statuses). Kept in their own files.
        require __DIR__.'/admin/attendance.php';
        require __DIR__.'/admin/scheduling.php';

        // Leave management (Phase 6).
        require __DIR__.'/admin/leave.php';

        // Payroll components: salary structures, allowances, deductions, statutory tables, loans (Phase 7a).
        require __DIR__.'/admin/payroll.php';

        // Performance, training, assets and disciplinary actions (Phase 8a).
        require __DIR__.'/admin/performance.php';
        require __DIR__.'/admin/training.php';
        require __DIR__.'/admin/assets.php';
        require __DIR__.'/admin/disciplinary.php';

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

        // My onboarding checklist (own tasks only).
        Route::get('onboarding', [Employee\OnboardingController::class, 'index'])->name('onboarding');
        Route::patch('onboarding/tasks/{task}', [Employee\OnboardingController::class, 'update'])->whereNumber('task')->name('onboarding.tasks.update');

        // My attendance (own record only): schedule, daily records, correction requests.
        Route::get('attendance', [Employee\AttendanceController::class, 'index'])->name('attendance');
        Route::post('attendance/corrections', [Employee\AttendanceController::class, 'storeCorrection'])->name('attendance.corrections.store');
        Route::post('attendance/corrections/{correction}/cancel', [Employee\AttendanceController::class, 'cancelCorrection'])->whereNumber('correction')->name('attendance.corrections.cancel');

        // My leaves (own record only): balances, requests, cancellation, attachments.
        Route::get('leaves', [Employee\LeaveController::class, 'index'])->name('leaves');
        Route::post('leaves', [Employee\LeaveController::class, 'store'])->name('leaves.store');
        Route::post('leaves/{leaveRequest}/cancel', [Employee\LeaveController::class, 'cancel'])->whereNumber('leaveRequest')->name('leaves.cancel');
        Route::get('leaves/{leaveRequest}/attachments/{attachment}', [Employee\LeaveController::class, 'attachment'])
            ->whereNumber(['leaveRequest', 'attachment'])->name('leaves.attachments.download');

        // My loans and advances (own record only): limits, requests, schedule (Phase 7a).
        Route::get('loans', [Employee\LoanController::class, 'index'])->name('loans');
        Route::post('loans', [Employee\LoanController::class, 'store'])->name('loans.store');
        Route::get('loans/{loan}', [Employee\LoanController::class, 'show'])->whereNumber('loan')->name('loans.show');
        Route::post('loans/{loan}/cancel', [Employee\LoanController::class, 'cancel'])->whereNumber('loan')->name('loans.cancel');

        // My payroll (own payslips only, Phase 7b): payslips by year, year summary, structure, PDF.
        Route::get('payroll', [Employee\PayrollController::class, 'index'])->name('payroll');
        Route::get('payroll/payslips/{payslip}', [Employee\PayrollController::class, 'show'])->whereNumber('payslip')->name('payroll.show');
        Route::get('payroll/payslips/{payslip}/pdf', [Employee\PayrollController::class, 'pdf'])->whereNumber('payslip')->name('payroll.pdf');

        // My performance, training, assets and disciplinary notices (Phase 8a, own record only).
        require __DIR__.'/portal/performance.php';
        require __DIR__.'/portal/training.php';
        require __DIR__.'/portal/assets.php';
        require __DIR__.'/portal/disciplinary.php';

        // Interviews the employee sits on as an interviewer (InterviewPolicy).
        Route::get('interviews', [Employee\InterviewController::class, 'index'])->name('interviews.index');
        Route::get('interviews/{interview}', [Employee\InterviewController::class, 'show'])->name('interviews.show');
        Route::post('interviews/{interview}/scorecard', [Employee\InterviewController::class, 'scorecard'])->name('interviews.scorecard');
        Route::get('interviews/{interview}/cv', [Employee\InterviewController::class, 'cv'])->name('interviews.cv');
    });
});
