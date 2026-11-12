<?php

use App\Enums\TrainingEnrolmentStatus;
use App\Models\Department;
use App\Models\TrainingBudget;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Services\Training\TrainingBudgets;
use Carbon\CarbonImmutable;

require_once __DIR__.'/TrainingHelpers.php';

/*
| Training budgets (bcmath): spent per department from the enrolment cost
| snapshots of seats taken on sessions starting in the year; the company-wide
| budget covers every department; pending requests are shown apart; remaining
| and % used.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    seedTraining();
    $this->sales = Department::factory()->create();
    $this->it = Department::factory()->create();
    $this->budgets = app(TrainingBudgets::class);
});

/**
 * An enrolment with a given cost / status / department on a session starting on $start.
 */
function budgetEnrolment(Department $department, string $cost, TrainingEnrolmentStatus $status, string $start = '2026-03-01'): TrainingEnrolment
{
    return TrainingEnrolment::factory()->create([
        'training_session_id' => TrainingSession::factory()->on($start),
        'department_id' => $department->id, 'cost' => $cost, 'status' => $status,
    ]);
}

it('sums the cost of seats taken per department without float drift', function () {
    budgetEnrolment($this->sales, '333.33', TrainingEnrolmentStatus::Completed);
    budgetEnrolment($this->sales, '333.33', TrainingEnrolmentStatus::Enrolled);
    budgetEnrolment($this->sales, '333.34', TrainingEnrolmentStatus::NoShow);
    budgetEnrolment($this->sales, '0.10', TrainingEnrolmentStatus::Failed);
    budgetEnrolment($this->sales, '999.00', TrainingEnrolmentStatus::Cancelled);
    budgetEnrolment($this->sales, '999.00', TrainingEnrolmentStatus::Rejected);
    budgetEnrolment($this->sales, '500.00', TrainingEnrolmentStatus::Completed, '2025-12-31');

    expect($this->budgets->spentByDepartment(2026)->get($this->sales->id))->toBe('1000.10')
        ->and($this->budgets->spentByDepartment(2025)->get($this->sales->id))->toBe('500.00');
});

it('reports budget, spent, pending, remaining and % used; the company-wide row covers every department', function () {
    budgetEnrolment($this->sales, '1200.00', TrainingEnrolmentStatus::Completed);
    budgetEnrolment($this->sales, '300.00', TrainingEnrolmentStatus::Pending);
    budgetEnrolment($this->it, '2500.50', TrainingEnrolmentStatus::Enrolled);
    TrainingBudget::factory()->create(['year' => 2026, 'department_id' => null, 'amount' => '10000.00']);
    TrainingBudget::factory()->create(['year' => 2026, 'department_id' => $this->sales->id, 'amount' => '1000.00']);
    TrainingBudget::factory()->create(['year' => 2026, 'department_id' => $this->it->id, 'amount' => '3000.00']);

    $rows = $this->budgets->forYear(2026)->keyBy(fn (array $row) => $row['budget']->department_id ?? 'company');

    expect($rows['company']['spent'])->toBe('3700.50')
        ->and($rows['company']['committed'])->toBe('300.00')
        ->and($rows['company']['remaining'])->toBe('6299.50')
        ->and($rows['company']['used_percent'])->toBe('37.00')
        ->and($rows['company']['over'])->toBeFalse()
        ->and($rows[$this->sales->id]['spent'])->toBe('1200.00')
        ->and($rows[$this->sales->id]['remaining'])->toBe('-200.00')
        ->and($rows[$this->sales->id]['used_percent'])->toBe('120.00')
        ->and($rows[$this->sales->id]['over'])->toBeTrue()
        ->and($rows[$this->it->id]['spent'])->toBe('2500.50')
        ->and($rows[$this->it->id]['used_percent'])->toBe('83.35')
        ->and($this->budgets->totalSpent(2026))->toBe('3700.50');
});

it('keeps one budget per year and department', function () {
    $hr = adminWithRoles('HR Manager');
    TrainingBudget::factory()->create(['year' => 2026, 'department_id' => $this->sales->id]);

    $this->actingAs($hr, 'admin')->post(route('admin.training-budgets.store'), ['year' => 2026, 'department_id' => $this->sales->id, 'amount' => '500'])
        ->assertSessionHasErrors('department_id');
    $this->actingAs($hr, 'admin')->post(route('admin.training-budgets.store'), ['year' => 2027, 'department_id' => $this->sales->id, 'amount' => '500'])
        ->assertRedirect(route('admin.training-budgets.index', ['year' => 2027]));

    expect(TrainingBudget::query()->count())->toBe(2);
});

it('shows the figures on the budgets page', function () {
    budgetEnrolment($this->sales, '1200.00', TrainingEnrolmentStatus::Completed);
    TrainingBudget::factory()->create(['year' => 2026, 'department_id' => $this->sales->id, 'amount' => '1000.00']);

    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.training-budgets.index', ['year' => 2026]))
        ->assertOk()->assertSee('1,200.00')->assertSee('120.00%')->assertSee(__('training.budgets.over'));
});
