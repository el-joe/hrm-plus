<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employees\PortalProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, PortalProfile $profile): View
    {
        return view('employee.home', ['user' => $request->user(), 'employee' => $profile->employeeFor($request->user())]);
    }
}
