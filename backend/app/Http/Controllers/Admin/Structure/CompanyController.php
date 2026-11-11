<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Company\UpdateCompanyProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Structure\CompanyRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function show(): View
    {
        $company = Company::current();
        Gate::authorize('view', $company);

        return view('admin.structure.company.show', [
            'company' => $company,
            'localisation' => [
                'currency_code' => setting('localisation.currency_code'),
                'timezone' => setting('localisation.timezone'),
                'default_locale' => setting('localisation.default_locale'),
                'date_format' => setting('localisation.date_format'),
            ],
            'counts' => [
                'branches' => Branch::query()->active()->count(),
                'departments' => Department::query()->active()->count(),
                'positions' => Position::query()->active()->count(),
                'headcount' => (int) Position::query()->active()->sum('headcount'),
            ],
            'headquarters' => Branch::query()->where('is_headquarters', true)->first(),
        ]);
    }

    public function edit(): View
    {
        $company = Company::current();
        Gate::authorize('update', $company);

        return view('admin.structure.company.edit', ['company' => $company]);
    }

    public function update(CompanyRequest $request, UpdateCompanyProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle($request->profile(), $request->file('logo'), $request->boolean('remove_logo'));

        return redirect()->route('admin.company.show')->with('status', __('structure.company.updated'));
    }
}
