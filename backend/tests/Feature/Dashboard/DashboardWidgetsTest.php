<?php

use App\Enums\DataScopeType;
use App\Enums\HrRequestStatus;
use App\Enums\VacancyStatus;
use App\Models\Branch;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeGroup;
use App\Models\HrRequest;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Dashboard\AdminDashboard;
use App\Services\Dashboard\DashboardWidgets;
use App\Services\Dashboard\WidgetRegistry;
use App\Services\Settings\SettingsService;
use App\Support\Formatter;
use Carbon\CarbonImmutable;

/*
| Phase 8c — admin dashboard (spec module 1): the 22 widgets from live queries, permission gating and
| data scope per widget, the shared filters, per-admin visibility / order preferences.
| Written, NOT run (user decision).
*/

beforeEach(function () {
    seedRoles();
    app(SettingsService::class)->set('localisation.default_locale', 'en');
    app(SettingsService::class)->set('localisation.timezone', 'Africa/Cairo');
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'Africa/Cairo'));

    $this->cairo = Branch::factory()->create(['code' => 'CAI']);
    $this->alex = Branch::factory()->create(['code' => 'ALX']);
    $this->cairoSales = Department::factory()->create(['branch_id' => $this->cairo->id]);
    $this->alexOps = Department::factory()->create(['branch_id' => $this->alex->id]);
});

function dashboardWidget(User $admin, string $key, array $filters = []): ?array
{
    $dashboard = app(AdminDashboard::class);

    return $dashboard->widget($admin, $key, $dashboard->filters($filters));
}

function alexBranchManager(): User
{
    $manager = adminWithRoles('Branch Manager');
    $manager->dataScopes()->create(['scope_type' => DataScopeType::Branch, 'scope_id' => Branch::query()->where('code', 'ALX')->value('id')]);

    return $manager->fresh();
}

describe('registry', function () {
    it('registers all 22 widgets of spec module 1', function () {
        expect(WidgetRegistry::SPEC_WIDGETS)->toHaveCount(22);

        foreach (WidgetRegistry::SPEC_WIDGETS as $key) {
            expect(WidgetRegistry::WIDGETS)->toHaveKey($key)
                ->and(method_exists(DashboardWidgets::class, WidgetRegistry::WIDGETS[$key]['method']))->toBeTrue()
                ->and(__('dashboard.widgets.'.$key.'.title', [], 'ar'))->not->toBe('dashboard.widgets.'.$key.'.title')
                ->and(__('dashboard.widgets.'.$key.'.title', [], 'en'))->not->toBe('dashboard.widgets.'.$key.'.title');
        }
    });

    it('renders every widget a Super Admin may see without errors', function () {
        $admin = superAdmin();
        Employee::factory()->count(3)->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk();

        foreach (WidgetRegistry::SPEC_WIDGETS as $key) {
            $response->assertSee('data-widget="'.$key.'"', false);
        }
    });
});

describe('employee widgets', function () {
    it('counts total, active and inactive employees from the employees table', function () {
        Employee::factory()->count(3)->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);
        Employee::factory()->inactive()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);
        Employee::factory()->terminated()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);
        $admin = superAdmin();

        expect(dashboardWidget($admin, 'total_employees')['raw'])->toBe(4)
            ->and(dashboardWidget($admin, 'active_employees')['raw'])->toBe(3)
            ->and(dashboardWidget($admin, 'inactive_employees')['raw'])->toBe(1);
    });

    it('applies the branch, department and employee group filters', function () {
        Employee::factory()->count(2)->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);
        $alex = Employee::factory()->create(['branch_id' => $this->alex->id, 'department_id' => $this->alexOps->id]);
        $group = EmployeeGroup::factory()->create();
        $group->employees()->attach($alex->id);
        $admin = superAdmin();

        expect(dashboardWidget($admin, 'total_employees', ['branch_id' => $this->cairo->id])['raw'])->toBe(2)
            ->and(dashboardWidget($admin, 'total_employees', ['department_id' => $this->alexOps->id])['raw'])->toBe(1)
            ->and(dashboardWidget($admin, 'total_employees', ['group_id' => $group->id])['raw'])->toBe(1);
    });

    it('keeps a branch manager to the employees of their branch, whatever the filter says', function () {
        Employee::factory()->count(2)->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);
        Employee::factory()->create(['branch_id' => $this->alex->id, 'department_id' => $this->alexOps->id]);
        $manager = alexBranchManager();

        $this->actingAs($manager, 'admin')->get(route('admin.dashboard'))->assertOk();

        expect(dashboardWidget($manager, 'total_employees')['raw'])->toBe(1)
            ->and(dashboardWidget($manager, 'total_employees', ['branch_id' => $this->cairo->id])['raw'])->toBe(0);
    });

    it('lists contracts ending in the next 60 days that were not renewed', function () {
        $employee = Employee::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);
        $ending = EmployeeContract::query()->create(['employee_id' => $employee->id, 'contract_type' => 'fixed_term', 'start_date' => '2025-12-01', 'end_date' => '2026-12-01']);
        EmployeeContract::query()->create(['employee_id' => $employee->id, 'contract_type' => 'fixed_term', 'start_date' => '2025-01-01', 'end_date' => '2027-06-01']);
        $renewed = EmployeeContract::query()->create(['employee_id' => $employee->id, 'contract_type' => 'fixed_term', 'start_date' => '2025-11-01', 'end_date' => '2026-11-20']);
        EmployeeContract::query()->create(['employee_id' => $employee->id, 'contract_type' => 'fixed_term', 'start_date' => '2026-11-21', 'end_date' => '2027-11-20', 'renewal_of_id' => $renewed->id]);

        $widget = dashboardWidget(superAdmin(), 'contract_expirations');

        expect($widget['count'])->toBe('1')
            ->and($widget['items'])->toHaveCount(1)
            ->and($widget['items'][0]['value'])->toBe($ending->end_date->format('Y-m-d'));
    });

    it('lists upcoming birthdays and work anniversaries of the next 30 days', function () {
        Employee::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id, 'date_of_birth' => '1990-11-20', 'hire_date' => '2020-11-15']);
        Employee::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id, 'date_of_birth' => '1990-01-20', 'hire_date' => '2026-11-12']);
        $admin = superAdmin();

        $birthdays = dashboardWidget($admin, 'upcoming_birthdays');
        $anniversaries = dashboardWidget($admin, 'upcoming_anniversaries');

        expect($birthdays['count'])->toBe('1')
            ->and($birthdays['items'][0]['value'])->toBe('2026-11-20')
            ->and($anniversaries['count'])->toBe('1')
            ->and($anniversaries['items'][0]['meta'])->toContain('6');
    });
});

describe('module widgets', function () {
    it('counts late days in the period and today from processed attendance', function () {
        $employee = attendanceEmployee(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);
        payrollAttendanceDay($employee, '2026-11-02', 'late', ['late_minutes' => 12]);
        payrollAttendanceDay($employee, '2026-11-03', 'late', ['late_minutes' => 5]);
        payrollAttendanceDay($employee, '2026-10-20', 'late', ['late_minutes' => 5]);
        payrollAttendanceDay($employee, '2026-11-04', 'present');

        expect(dashboardWidget(superAdmin(), 'late_arrivals', ['from' => '2026-11-01', 'to' => '2026-11-10'])['raw'])->toBe(2);
    });

    it('counts open HR requests with the overdue ones', function () {
        $employee = Employee::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);
        HrRequest::factory()->create(['employee_id' => $employee->id, 'status' => HrRequestStatus::Open, 'due_at' => now()->subDay()]);
        HrRequest::factory()->create(['employee_id' => $employee->id, 'status' => HrRequestStatus::InProgress, 'due_at' => now()->addDay()]);
        HrRequest::factory()->create(['employee_id' => $employee->id, 'status' => HrRequestStatus::Resolved]);

        $widget = dashboardWidget(superAdmin(), 'pending_hr_requests');

        expect($widget['raw'])->toBe(2)->and($widget['hint'])->toContain('1');
    });

    it('counts open vacancies and new candidates of the period', function () {
        seedRecruitment();
        Vacancy::factory()->count(2)->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id]);
        Vacancy::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id, 'status' => VacancyStatus::Closed]);
        Candidate::factory()->count(3)->create(['created_at' => '2026-11-05 10:00:00']);
        Candidate::factory()->create(['created_at' => '2026-09-05 10:00:00']);
        $admin = superAdmin();

        expect(dashboardWidget($admin, 'open_vacancies')['raw'])->toBe(2)
            ->and(dashboardWidget($admin, 'new_candidates', ['from' => '2026-11-01', 'to' => '2026-11-10'])['raw'])->toBe(3);
    });

    it('sums live pay of the latest finalised period for the payroll summary', function () {
        payrollSetup();
        $employee = paidEmployee('6000.00');
        $run = finalisedRun(calculatedRun(payrollPeriod('2026-10')));
        $item = payItem($run, $employee);

        $widget = dashboardWidget(superAdmin(), 'payroll_summary', ['from' => '2026-11-01', 'to' => '2026-11-10']);

        expect(collect($widget['items'])->pluck('value')->all())->toContain(Formatter::money((string) $item->net_pay), Formatter::money((string) $item->gross_pay));
    });
});

describe('permissions', function () {
    it('shows payroll widgets only with employee_financials.view', function () {
        $registry = app(WidgetRegistry::class);

        expect($registry->canSee(adminWithRoles('Reports Viewer'), 'payroll_summary'))->toBeFalse()
            ->and($registry->canSee(adminWithRoles('Reports Viewer'), 'total_employees'))->toBeTrue()
            ->and($registry->canSee(adminWithRoles('Payroll Accountant'), 'payroll_summary'))->toBeTrue()
            ->and($registry->canSee(adminWithPermissions('dashboard.view', 'payroll.view'), 'monthly_salary_expenses'))->toBeFalse();
    });

    it('gates each module widget by its source module permission', function () {
        $registry = app(WidgetRegistry::class);
        $attendanceOnly = adminWithPermissions('dashboard.view', 'attendance.view');

        expect($registry->canSee($attendanceOnly, 'absent_today'))->toBeTrue()
            ->and($registry->canSee($attendanceOnly, 'late_arrivals'))->toBeTrue()
            ->and($registry->canSee($attendanceOnly, 'pending_leave_requests'))->toBeFalse()
            ->and($registry->canSee($attendanceOnly, 'open_vacancies'))->toBeFalse()
            ->and($registry->canSee(adminWithPermissions('attendance.view'), 'absent_today'))->toBeFalse();
    });

    it('keeps the system figures on their own permission', function () {
        $response = $this->actingAs(adminWithPermissions('roles.view'), 'admin')->get(route('admin.dashboard'))->assertOk();

        expect(array_column($response->viewData('widgets'), 'key'))->toBe(['roles']);
    });

    it('never shows a widget outside the permissions even when the preference lists it', function () {
        $admin = adminWithPermissions('dashboard.view', 'attendance.view');
        app(WidgetRegistry::class)->save($admin, ['payroll_summary', 'absent_today'], []);

        expect(app(WidgetRegistry::class)->visible($admin))->not->toContain('payroll_summary');
    });
});

describe('preferences', function () {
    it('saves widget order and hidden widgets per admin', function () {
        $admin = adminWithRoles('Reports Viewer');
        $available = app(WidgetRegistry::class)->available($admin);

        $this->actingAs($admin, 'admin')->put(route('admin.dashboard.customise.update'), [
            'position' => ['active_employees' => 1, 'total_employees' => 2],
            'visible' => array_values(array_diff($available, ['inactive_employees'])),
        ])->assertRedirect(route('admin.dashboard'));

        $visible = app(WidgetRegistry::class)->visible($admin);
        expect(array_slice($visible, 0, 2))->toBe(['active_employees', 'total_employees'])
            ->and($visible)->not->toContain('inactive_employees')
            ->and(app(WidgetRegistry::class)->visible(adminWithRoles('Reports Viewer')))->toContain('inactive_employees');

        $this->put(route('admin.dashboard.customise.update'), ['reset' => 1])->assertRedirect(route('admin.dashboard'));
        expect(app(WidgetRegistry::class)->visible($admin))->toBe($available);
    });

    it('refuses unknown widget keys', function () {
        $this->actingAs(superAdmin(), 'admin')->put(route('admin.dashboard.customise.update'), ['visible' => ['nope']])
            ->assertSessionHasErrors('visible.0');
    });
});

describe('filters', function () {
    it('validates the date range and keeps the filters in the query string', function () {
        $admin = superAdmin();

        $this->actingAs($admin, 'admin')->get(route('admin.dashboard', ['from' => '2026-11-05', 'to' => '2026-11-01']))->assertSessionHasErrors('to');
        $response = $this->get(route('admin.dashboard', ['from' => '2026-11-01', 'to' => '2026-11-10', 'branch_id' => $this->cairo->id]))->assertOk();

        expect($response->viewData('filters'))->toMatchArray(['from' => '2026-11-01', 'to' => '2026-11-10', 'branch_id' => $this->cairo->id]);
    });

    it('links the widgets to the matching report with the filters', function () {
        $widget = dashboardWidget(superAdmin(), 'late_arrivals', ['from' => '2026-11-01', 'to' => '2026-11-10', 'branch_id' => $this->cairo->id]);

        expect($widget['url'])->toContain('/admin/reports/late_arrivals')->toContain('branch_id='.$this->cairo->id)->toContain('from=2026-11-01');
    });
});
