<?php

use App\Models\Employee;
use App\Models\User;

beforeEach(function () {
    $this->employee = Employee::factory()->create(['name_en' => 'Sara Adel', 'national_id' => '29001011234567', 'basic_salary' => '12345.00']);
    $this->other = Employee::factory()->create(['name_en' => 'Somebody Else']);
});

it('shows the employee their own profile only, read-only, without salary or full identity numbers', function (string $tab) {
    $user = User::factory()->employee()->create(['employee_id' => $this->employee->id, 'locale' => 'en']);

    $this->actingAs($user, 'employee')->get('/portal/profile'.($tab === 'personal' ? '' : "/{$tab}"))
        ->assertOk()
        ->assertSee('Sara Adel')
        ->assertDontSee('Somebody Else')
        ->assertDontSee('29001011234567')
        ->assertDontSee('12,345')
        ->assertDontSee('<form method="POST" action="'.url('/portal/profile'), false);
})->with(['personal', 'employment', 'documents', 'history']);

it('masks the national id but shows its last digits', function () {
    $user = User::factory()->employee()->create(['employee_id' => $this->employee->id]);

    $this->actingAs($user, 'employee')->get('/portal/profile')->assertSee('4567');
});

it('lets an admin linked to an employee open their own portal profile', function () {
    $admin = User::factory()->admin()->linkedToEmployee($this->employee->id)->create(['locale' => 'en']);

    $this->actingAs($admin, 'admin')->get('/portal/profile')->assertOk()->assertSee('Sara Adel');
    $this->actingAs($admin, 'admin')->get('/portal')->assertOk()->assertSee($this->employee->employee_number);
});

it('answers 404 when the account has no employee profile', function () {
    $this->actingAs(User::factory()->create(), 'employee')->get('/portal/profile')->assertNotFound();
});
