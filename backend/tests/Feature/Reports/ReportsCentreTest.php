<?php

use App\Enums\DataScopeType;
use App\Enums\ReportExportStatus;
use App\Jobs\GenerateReportExport;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ReportExport;
use App\Models\User;
use App\Notifications\Reports\ReportExportNotification;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| Phase 8c — reports centre screens and exports: access, data scope on screen AND in exports,
| column-level permissions with access logging, Excel / CSV / PDF exports, queued exports with
| the notification, export audit. Written, NOT run (user decision).
*/

beforeEach(function () {
    seedRoles();
    app(SettingsService::class)->set('localisation.default_locale', 'en');
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00'));

    $this->cairo = Branch::factory()->create(['code' => 'CAI']);
    $this->alex = Branch::factory()->create(['code' => 'ALX']);
    $this->cairoSales = Department::factory()->create(['branch_id' => $this->cairo->id]);
    $this->alexOps = Department::factory()->create(['branch_id' => $this->alex->id]);
    $this->cairoEmployee = Employee::factory()->create(['employee_number' => 'E-CAI-1', 'branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id, 'basic_salary' => '7000.00']);
    $this->alexEmployee = Employee::factory()->create(['employee_number' => 'E-ALX-1', 'branch_id' => $this->alex->id, 'department_id' => $this->alexOps->id, 'basic_salary' => '5000.00']);
});

function alexScopedViewer(): User
{
    $viewer = adminWithRoles('Reports Viewer');
    $viewer->dataScopes()->create(['scope_type' => DataScopeType::Branch, 'scope_id' => Branch::query()->where('code', 'ALX')->value('id')]);

    return $viewer->fresh();
}

describe('access', function () {
    it('opens the catalogue and a report with reports.view only', function () {
        $this->actingAs(adminWithRoles('Reports Viewer'), 'admin')
            ->get(route('admin.reports.index'))->assertOk()->assertSee(__('reports.items.employee_directory.title'))
            ->assertDontSee(__('reports.items.payroll_summary.title'));

        $this->get(route('admin.reports.show', 'employee_directory'))->assertOk()->assertSee('E-CAI-1');
        $this->get(route('admin.reports.show', 'payroll_summary'))->assertForbidden();
        $this->get(route('admin.reports.show', 'no_such_report'))->assertNotFound();
    });

    it('refuses admins without reports.view and exports without reports.export', function () {
        $this->actingAs(adminWithPermissions('employees.view'), 'admin')->get(route('admin.reports.index'))->assertForbidden();
        $this->get(route('admin.reports.show', 'employee_directory'))->assertForbidden();

        $this->actingAs(adminWithPermissions('reports.view'), 'admin')
            ->get(route('admin.reports.export', ['employee_directory', 'format' => 'csv']))->assertForbidden();
    });

    it('validates filters and refuses a range longer than allowed', function () {
        $this->actingAs(superAdmin(), 'admin')
            ->get(route('admin.reports.show', ['new_hires', 'from' => '2026-11-10', 'to' => '2026-11-01']))->assertSessionHasErrors('to');
        $this->get(route('admin.reports.show', ['new_hires', 'from' => '2020-01-01', 'to' => '2026-11-01']))->assertSessionHasErrors('to');
        $this->get(route('admin.reports.show', ['new_hires', 'branch_id' => 'x']))->assertSessionHasErrors('branch_id');
        $this->get(route('admin.reports.export', ['new_hires', 'format' => 'docx']))->assertSessionHasErrors('format');
    });
});

describe('data scope', function () {
    it('limits the screen to the admin data scope', function () {
        $this->actingAs(alexScopedViewer(), 'admin')
            ->get(route('admin.reports.show', 'employee_directory'))->assertOk()
            ->assertSee('E-ALX-1')->assertDontSee('E-CAI-1');
    });

    it('limits every export format to the admin data scope', function () {
        $this->actingAs(alexScopedViewer(), 'admin');

        $csv = $this->get(route('admin.reports.export', ['employee_directory', 'format' => 'csv']))->assertOk()->streamedContent();
        expect($csv)->toContain('E-ALX-1')->not->toContain('E-CAI-1');

        $this->get(route('admin.reports.export', ['employee_directory', 'format' => 'xlsx']))->assertOk()->assertDownload();
        $this->get(route('admin.reports.export', ['employee_directory', 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    });

    it('keeps the scope when a filter points outside it', function () {
        $this->actingAs(alexScopedViewer(), 'admin')
            ->get(route('admin.reports.show', ['employee_directory', 'branch_id' => $this->cairo->id]))->assertOk()
            ->assertDontSee('E-CAI-1')->assertDontSee('E-ALX-1');
    });
});

describe('column permissions', function () {
    it('hides salary columns without employee_financials.view, on screen and in exports', function () {
        $this->actingAs(adminWithRoles('Reports Viewer'), 'admin')
            ->get(route('admin.reports.show', 'employee_directory'))->assertOk()->assertDontSee(__('reports.columns.basic_salary'))->assertDontSee('7,000.00');

        $csv = $this->get(route('admin.reports.export', ['employee_directory', 'format' => 'csv']))->streamedContent();
        expect($csv)->not->toContain(__('reports.columns.basic_salary'))->not->toContain('7000.00');
    });

    it('shows salary columns to financial roles and logs the access', function () {
        $hr = adminWithRoles('HR Manager');

        $this->actingAs($hr, 'admin')->get(route('admin.reports.show', 'employee_directory'))->assertOk()->assertSee('7,000.00');

        expect(AuditLog::query()->where('event', SensitiveDataAccessLog::FINANCIAL)->where('user_id', $hr->id)->exists())->toBeTrue();

        $this->get(route('admin.reports.export', ['employee_directory', 'format' => 'csv']))->assertOk();
        expect(AuditLog::query()->where('event', SensitiveDataAccessLog::FINANCIAL)->where('user_id', $hr->id)->count())->toBe(2);
    });

    it('shows disciplinary columns only with disciplinary.view and logs every view', function () {
        $specialist = adminWithRoles('HR Specialist');
        $this->actingAs($specialist, 'admin')->get(route('admin.reports.show', 'employee_directory'))->assertOk()->assertDontSee(__('reports.columns.active_warnings'));
        $this->get(route('admin.reports.show', 'disciplinary_cases'))->assertForbidden();

        $manager = adminWithRoles('HR Manager');
        $this->actingAs($manager, 'admin')->get(route('admin.reports.show', 'disciplinary_cases'))->assertOk();

        expect(AuditLog::query()->where('event', SensitiveDataAccessLog::DISCIPLINARY)->where('user_id', $manager->id)->exists())->toBeTrue();
    });
});

describe('exports', function () {
    it('downloads Excel, CSV and an Arabic PDF and audits each export', function () {
        app(SettingsService::class)->set('localisation.default_locale', 'ar');
        $admin = superAdmin();
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.reports.export', ['employees_by_branch', 'format' => 'xlsx']))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $csv = $this->get(route('admin.reports.export', ['employees_by_branch', 'format' => 'csv']))->assertOk()->streamedContent();
        $pdf = $this->get(route('admin.reports.export', ['employees_by_branch', 'format' => 'pdf']))->assertOk()->streamedContent();

        expect($csv)->toContain('CAI')->toContain('ALX')
            ->and(substr($pdf, 0, 5))->toBe('%PDF-')
            ->and(AuditLog::query()->where('event', 'reports.exported')->where('user_id', $admin->id)->pluck('meta')->pluck('format')->all())->toBe(['xlsx', 'csv', 'pdf']);
    });

    it('adds a totals row to spreadsheet exports', function () {
        $csv = $this->actingAs(superAdmin(), 'admin')->get(route('admin.reports.export', ['employees_by_branch', 'format' => 'csv']))->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        expect(end($lines))->toContain(__('reports.ui.total'))->toContain('2');
    });

    it('queues a large export, runs it as the requesting admin and notifies them', function () {
        Bus::fake([GenerateReportExport::class]);
        config(['reports.queue_threshold.csv' => 1]);
        $viewer = alexScopedViewer();

        $this->actingAs($viewer, 'admin')->get(route('admin.reports.export', ['employee_directory', 'format' => 'csv']))
            ->assertRedirect(route('admin.reports.exports.index'));

        $export = ReportExport::query()->sole();
        expect($export->status)->toBe(ReportExportStatus::Queued)->and($export->user_id)->toBe($viewer->id);
        Bus::assertDispatched(GenerateReportExport::class, fn (GenerateReportExport $job): bool => $job->exportId === $export->id);

        Notification::fake();
        Storage::fake('local');
        app()->call([new GenerateReportExport($export->id), 'handle']);

        $export->refresh();
        expect($export->status)->toBe(ReportExportStatus::Completed)->and($export->rows)->toBe(1);
        expect(Storage::disk('local')->get($export->file_path))->toContain('E-ALX-1')->not->toContain('E-CAI-1');
        Notification::assertSentTo($viewer, ReportExportNotification::class, fn (ReportExportNotification $notification): bool => $notification->event() === 'report_export_ready');

        $this->get(route('admin.reports.exports.index'))->assertOk()->assertSee(route('admin.reports.exports.download', $export));
        $this->get(route('admin.reports.exports.download', $export))->assertOk()->assertDownload($export->file_name);
        $this->actingAs(adminWithRoles('Reports Viewer'), 'admin')->get(route('admin.reports.exports.download', $export))->assertNotFound();
    });

    it('marks a queued export failed when the admin lost access, and notifies them', function () {
        Notification::fake();
        $viewer = adminWithRoles('Reports Viewer');
        $export = ReportExport::query()->create(['user_id' => $viewer->id, 'report' => 'payroll_summary', 'format' => 'csv', 'locale' => 'en', 'filters' => [], 'status' => ReportExportStatus::Queued]);

        app()->call([new GenerateReportExport($export->id), 'handle']);

        expect($export->refresh()->status)->toBe(ReportExportStatus::Failed);
        Notification::assertSentTo($viewer, ReportExportNotification::class, fn (ReportExportNotification $notification): bool => $notification->event() === 'report_export_failed');
    });

    it('refuses a PDF larger than the PDF limit', function () {
        config(['reports.pdf_max_rows' => 1]);

        $this->actingAs(superAdmin(), 'admin')->from(route('admin.reports.show', 'employee_directory'))
            ->get(route('admin.reports.export', ['employee_directory', 'format' => 'pdf']))
            ->assertRedirect(route('admin.reports.show', 'employee_directory'))->assertSessionHas('error');
    });
});

describe('screen', function () {
    it('sorts by an allowed column and ignores unknown sort keys', function () {
        $this->actingAs(superAdmin(), 'admin');

        $rows = $this->get(route('admin.reports.show', ['employee_directory', 'sort' => 'employee_number', 'direction' => 'desc']))->assertOk()->viewData('rows');
        expect(collect($rows->items())->pluck('employee_number')->all())->toBe(['E-CAI-1', 'E-ALX-1']);

        $unknown = $this->get(route('admin.reports.show', ['employee_directory', 'sort' => 'name; drop table users']))->assertOk();
        expect($unknown->viewData('sort'))->toBeNull();
        expect($this->get(route('admin.reports.show', ['employee_directory', 'sort' => 'phone']))->assertOk()->viewData('sort'))->toBeNull();
    });

    it('shows totals and a chart for grouped reports', function () {
        $response = $this->actingAs(superAdmin(), 'admin')->get(route('admin.reports.show', 'employees_by_branch'))->assertOk();

        expect($response->viewData('totals')['employed'])->toBe('2')
            ->and($response->viewData('chart')['labels'])->toBe(['ALX', 'CAI']);
        $response->assertSee('data-chart', false)->assertSee('data-report-totals', false);
    });
});

describe('default roles', function () {
    it('gives the Reports Viewer reports without salary or disciplinary access, and HR Managers the bank-detail update', function () {
        $viewer = adminWithRoles('Reports Viewer');
        $hr = adminWithRoles('HR Manager');

        expect($viewer->can('reports.view'))->toBeTrue()->and($viewer->can('reports.export'))->toBeTrue()
            ->and($viewer->can('employee_financials.view'))->toBeFalse()->and($viewer->can('disciplinary.view'))->toBeFalse()
            ->and($hr->can('employee_financials.update'))->toBeTrue();
    });

    it('shows the Reports sidebar section to report viewers only', function () {
        $this->actingAs(adminWithRoles('Reports Viewer'), 'admin')->get(route('admin.dashboard'))->assertSee(route('admin.reports.index'));
        $this->actingAs(adminWithPermissions('employees.view'), 'admin')->get(route('admin.dashboard'))->assertDontSee(route('admin.reports.index'));
    });
});
