<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Employees\EmployeeFileStore;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profile photo, served from the private disk to admins who may view the employee.
 */
class EmployeePhotoController extends Controller
{
    public function __invoke(Employee $employee, EmployeeFileStore $files): StreamedResponse
    {
        Gate::authorize('view', $employee);
        abort_if($employee->photo_path === null, 404);

        $extension = strtolower(pathinfo($employee->photo_path, PATHINFO_EXTENSION));

        return $files->response($employee->photo_path, 'photo.'.$extension, 'image/'.($extension === 'jpg' ? 'jpeg' : $extension), inline: true);
    }
}
