<?php

namespace App\Http\Controllers\Careers;

use App\Actions\Recruitment\SubmitCareerApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Careers\CareerApplicationRequest;
use App\Models\Company;
use App\Models\Vacancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public career page (no account). Shown only while the setting
 * recruitment.career_page_enabled is on (middleware "careers"); lists published vacancies (open,
 * public, inside the publish window) and takes applications with a CV.
 * Salaries are never shown here.
 */
class CareerController extends Controller
{
    public function index(): View
    {
        return view('careers.index', [
            'vacancies' => Vacancy::query()->published()->with(['branch', 'department', 'employmentType'])->latest('publish_at')->latest('id')->get(),
            'company' => Company::query()->first(),
        ]);
    }

    public function show(string $reference): View
    {
        return view('careers.show', [
            'vacancy' => $this->published($reference),
            'company' => Company::query()->first(),
            'cvTypes' => (array) setting('recruitment.cv_allowed_extensions'),
            'cvMaxKb' => (int) setting('recruitment.cv_max_size_kb'),
        ]);
    }

    public function apply(CareerApplicationRequest $request, string $reference, SubmitCareerApplication $submit): RedirectResponse
    {
        $vacancy = $this->published($reference);
        $submit->handle($vacancy, $request->safe()->except(['cv', 'consent', 'website']), $request->file('cv'), app()->getLocale());

        return redirect()->route('careers.show', $vacancy->reference)->with('applied', true);
    }

    private function published(string $reference): Vacancy
    {
        return Vacancy::query()->withoutGlobalScopes()->published()->where('reference', $reference)
            ->with(['branch', 'department', 'employmentType'])->firstOrFail();
    }
}
