<?php

namespace App\Http\Controllers\Admin\Recruitment;

use App\Actions\Recruitment\ConvertCandidateToEmployee;
use App\Actions\Recruitment\MoveApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Recruitment\ConvertCandidateRequest;
use App\Http\Requests\Admin\Recruitment\MoveApplicationRequest;
use App\Models\JobApplication;
use App\Services\Employees\EmployeeFormOptions;
use App\Services\Recruitment\Pipeline;
use App\Services\Recruitment\SalaryVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * One application: stage history, interviews and scorecards, offers, notes,
 * the stage control (also used by the board, which posts as JSON) and the
 * "hire" conversion.
 */
class ApplicationController extends Controller
{
    public function show(Request $request, JobApplication $application, Pipeline $pipeline, SalaryVisibility $salaries): View
    {
        Gate::authorize('view', $application);

        $application->load([
            'candidate.tags', 'vacancy', 'stage', 'employee',
            'stageChanges.changedBy',
            'interviews' => fn ($query) => $query->with(['interviewers', 'scorecards.interviewer']),
            'offers.creator',
            'notes' => fn ($query) => $query->with('author'),
        ]);
        $offerSalaries = $application->offers->whereNotNull('basic_salary');

        return view('admin.recruitment.applications.show', [
            'application' => $application,
            'stages' => $pipeline->stagesFor($application->vacancy),
            'showSalary' => $offerSalaries->isNotEmpty() && $salaries->reveal($request->user(), $offerSalaries->first(), ['basic_salary']),
        ]);
    }

    public function move(MoveApplicationRequest $request, JobApplication $application, MoveApplication $move): JsonResponse|RedirectResponse
    {
        $move->handle($application, $request->stage(), $request->user(), $request->validated('note'), $request->validated('rejection_reason'));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('recruitment.messages.moved', ['stage' => $application->stage->name]),
                'application' => ['id' => $application->id, 'stage_id' => $application->stage_id, 'status' => $application->status->value],
            ]);
        }

        return back()->with('status', __('recruitment.messages.moved', ['stage' => $application->stage->name]));
    }

    public function convertForm(Request $request, JobApplication $application, EmployeeFormOptions $options, SalaryVisibility $salaries): View
    {
        Gate::authorize('convert', $application);

        $application->load(['candidate', 'vacancy.department', 'latestOffer']);
        $offer = $application->latestOffer;
        $vacancy = $application->vacancy;
        $canSetSalary = $salaries->allows($request->user()) && $request->user()->can('employee_financials.update');

        return view('admin.recruitment.applications.convert', [
            'application' => $application,
            'offer' => $offer,
            'options' => $options->form(null),
            'canSetSalary' => $canSetSalary,
            'values' => [
                'branch_id' => $vacancy->branch_id,
                'department_id' => $vacancy->department_id,
                'position_id' => $offer?->position_id ?? $vacancy->position_id,
                'job_title_id' => $offer?->job_title_id ?? $vacancy->job_title_id,
                'employment_type_id' => $offer?->employment_type_id ?? $vacancy->employment_type_id,
            ],
            'salary' => $offer?->basic_salary !== null && $salaries->reveal($request->user(), $offer, ['basic_salary']) ? $offer->basic_salary : null,
        ]);
    }

    public function convert(ConvertCandidateRequest $request, JobApplication $application, ConvertCandidateToEmployee $convert): RedirectResponse
    {
        $employee = $convert->handle($application, $request->payload(), $request->user());

        return redirect()->route('admin.employees.show', $employee)->with('status', __('recruitment.messages.converted', [
            'name' => $employee->name, 'number' => $employee->employee_number,
        ]));
    }
}
