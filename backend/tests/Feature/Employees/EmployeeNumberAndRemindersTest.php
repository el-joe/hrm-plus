<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\EmployeeRecordExpiring;
use App\Services\Employees\EmployeeNumberGenerator;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('generates unique, increasing numbers and skips numbers already taken', function () {
    Employee::factory()->create(['employee_number' => 'EMP-00002']);
    $generator = app(EmployeeNumberGenerator::class);

    $numbers = collect(range(1, 5))->map(fn () => $generator->next());

    expect($numbers->all())->toBe(['EMP-00001', 'EMP-00003', 'EMP-00004', 'EMP-00005', 'EMP-00006'])
        ->and($numbers->unique())->toHaveCount(5);
});

it('never goes below the configured next sequence', function () {
    app(SettingsService::class)->setMany(['employee_number.prefix' => 'HZ', 'employee_number.next_sequence' => 500, 'employee_number.padding' => 4]);

    expect(app(EmployeeNumberGenerator::class)->next())->toBe('HZ0500')
        ->and(app(EmployeeNumberGenerator::class)->next())->toBe('HZ0501');
});

it('locks the counter row so concurrent hires cannot draw the same number', function () {
    DB::listen(function ($query) use (&$locked) {
        if (str_contains($query->sql, 'number_sequences') && str_contains(strtolower($query->sql), 'for update')) {
            $locked = true;
        }
    });

    app(EmployeeNumberGenerator::class)->next();

    expect($locked ?? false)->toBeTrue();
});

it('reminds HR (in scope) and the employee once about documents and contracts expiring soon', function () {
    Notification::fake();
    $employee = Employee::factory()->create();
    $account = User::factory()->employee()->create(['employee_id' => $employee->id]);
    $hr = adminWithRoles('HR Manager');
    $elsewhere = adminWithRoles('HR Manager');
    $elsewhere->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => Branch::factory()->create()->id]);
    $base = ['file_path' => 'x', 'file_name' => 'x.pdf', 'file_mime' => 'application/pdf', 'file_size' => 1, 'category' => 'passport'];
    $soon = $employee->documents()->create([...$base, 'title' => 'Passport', 'expiry_date' => today()->addDays(5), 'visible_to_employee' => true]);
    $employee->documents()->create([...$base, 'title' => 'Later', 'expiry_date' => today()->addDays(90)]);
    $contract = $employee->contracts()->create(['contract_type' => 'fixed_term', 'start_date' => today()->subYear(), 'end_date' => today()->addDays(10)]);

    $this->artisan('employees:send-expiry-reminders')->assertSuccessful();
    $this->artisan('employees:send-expiry-reminders')->assertSuccessful();

    Notification::assertSentToTimes($hr, EmployeeRecordExpiring::class, 2);
    Notification::assertSentToTimes($account, EmployeeRecordExpiring::class, 1);
    Notification::assertNotSentTo($elsewhere, EmployeeRecordExpiring::class);
    expect($soon->fresh()->expiry_reminded_for->toDateString())->toBe(today()->addDays(5)->toDateString())
        ->and($contract->fresh()->expiry_reminded_for)->not->toBeNull();
});
