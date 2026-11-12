<?php

/*
| Helpers of the HR request tests (Phase 8b). Required by each test file; guarded so several files can
| include them in one run.
*/

use App\Actions\HrRequests\SubmitHrRequest;
use App\Enums\HrRequestSource;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Models\HrRequestCategory;
use App\Models\User;
use Database\Seeders\HrRequestDefaultsSeeder;

if (! function_exists('seedHrRequests')) {
    /**
     * Roles, permissions, the "hr_request" workflow, default letters and categories.
     */
    function seedHrRequests(): void
    {
        seedRoles();
        test()->seed(HrRequestDefaultsSeeder::class);
    }

    /**
     * An employee with an active portal account.
     *
     * @return array{0: Employee, 1: User}
     */
    function hrRequestEmployee(array $attributes = []): array
    {
        $employee = Employee::factory()->create($attributes);
        $user = User::factory()->employee()->create(['employee_id' => $employee->id]);

        return [$employee, $user];
    }

    function hrCategory(string $code): HrRequestCategory
    {
        return HrRequestCategory::query()->where('code', $code)->with('fields')->firstOrFail();
    }

    /**
     * File a request through the action (as the employee's own account by default).
     *
     * @param  array<string, mixed>  $data
     */
    function fileHrRequest(Employee $employee, User $by, string $code = 'GENERAL_INQUIRY', array $data = [], array $files = [], HrRequestSource $source = HrRequestSource::Portal): HrRequest
    {
        return app(SubmitHrRequest::class)->handle($employee, hrCategory($code), ['description' => 'Please help with my question.', ...$data], $by, $source, $files)->refresh();
    }
}
