<?php

use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;

/*
| Phase 8c — the reports centre registry: every report of spec module 16 "Required Reports" (48) is
| registered, has a valid definition and translations in both languages. Written, NOT run.
*/

it('registers every required report of spec module 16', function () {
    $registry = app(ReportRegistry::class);
    $required = array_merge(...array_values(ReportRegistry::REQUIRED));

    expect($required)->toHaveCount(48)->and(array_unique($required))->toHaveCount(48);

    foreach (ReportRegistry::REQUIRED as $area => $keys) {
        foreach ($keys as $key) {
            expect($registry->find($key))->toBeInstanceOf(Report::class, "{$key} is not registered")
                ->and($registry->find($key)->area())->toBe($area);
        }
    }
});

it('gives every report a valid definition and translations', function () {
    foreach (app(ReportRegistry::class)->all() as $key => $report) {
        expect($report->key())->toBe($key)
            ->and(ReportRegistry::AREAS)->toContain($report->area())
            ->and(array_diff($report->filters(), ReportFilters::SHARED))->toBe([])
            ->and($report->columns())->not->toBeEmpty();

        foreach ($report->columns() as $column) {
            expect($column)->toBeInstanceOf(Column::class);
        }

        foreach (array_keys($report->sortable()) as $sortKey) {
            expect($report->columns())->toHaveKey($sortKey);
        }

        foreach (['ar', 'en'] as $locale) {
            expect(__('reports.items.'.$key.'.title', [], $locale))->not->toBe('reports.items.'.$key.'.title')
                ->and(__('reports.items.'.$key.'.description', [], $locale))->not->toBe('reports.items.'.$key.'.description')
                ->and(__('reports.areas.'.$report->area(), [], $locale))->not->toBe('reports.areas.'.$report->area());
        }
    }
});

it('guards salary columns behind employee_financials.view and payroll reports as a whole', function () {
    $registry = app(ReportRegistry::class);

    foreach (ReportRegistry::REQUIRED['payroll'] as $key) {
        expect($registry->find($key)->requires())->toContain('employee_financials.view');
    }

    expect($registry->find('employee_directory')->columns()['basic_salary']->permission())->toBe('employee_financials.view')
        ->and($registry->find('employee_directory')->columns()['active_warnings']->permission())->toBe('disciplinary.view')
        ->and($registry->find('disciplinary_cases')->requires())->toContain('disciplinary.view');
});

it('lists only the reports an admin may open, grouped by area', function () {
    $viewer = adminWithRoles('Reports Viewer');
    $catalogue = app(ReportRegistry::class)->catalogue($viewer);

    expect($catalogue->keys()->all())->not->toContain('payroll', 'disciplinary')
        ->and($catalogue->get('employees'))->toHaveCount(9)
        ->and(app(ReportRegistry::class)->catalogue(superAdmin())->keys()->all())->toContain('payroll', 'disciplinary')
        ->and(app(ReportRegistry::class)->catalogue(adminWithPermissions('employees.view')))->toBeEmpty();
});
