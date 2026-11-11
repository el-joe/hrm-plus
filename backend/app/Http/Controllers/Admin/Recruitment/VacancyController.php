<?php

namespace App\Http\Controllers\Admin\Recruitment;

use App\Actions\Recruitment\SaveVacancy;
use App\Enums\VacancyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Recruitment\VacancyRequest;
use App\Models\Vacancy;
use App\Services\Recruitment\Pipeline;
use App\Services\Recruitment\PipelineBoard;
use App\Services\Recruitment\RecruitmentOptions;
use App\Services\Recruitment\SalaryVisibility;
use App\Support\IndexSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Vacancies: list, create / edit, and the vacancy page with its pipeline
 * (kanban board or list view, ?view=list). Record rules in VacancyPolicy.
 */
class VacancyController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request, RecruitmentOptions $options): View
    {
        Gate::authorize('viewAny', Vacancy::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(VacancyStatus::class)],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $query = Vacancy::query()
            ->with(['branch', 'department', 'employmentType'])
            ->withCount(['applications', 'applications as active_applications_count' => fn ($query) => $query->active()])
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->search($term))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id));

        $sort = IndexSorting::apply($query, $request, [
            'reference' => 'reference',
            'title' => Vacancy::titleColumn(),
            'deadline' => 'application_deadline',
            'status' => 'status',
            'created' => 'created_at',
        ], default: 'created', defaultDirection: 'desc');

        return view('admin.recruitment.vacancies.index', [
            'vacancies' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'options' => $options->vacancyFilters(),
        ]);
    }

    public function create(RecruitmentOptions $options, SalaryVisibility $salaries, Request $request): View
    {
        Gate::authorize('create', Vacancy::class);

        return view('admin.recruitment.vacancies.form', [
            'vacancy' => new Vacancy(['status' => VacancyStatus::Draft, 'openings' => 1]),
            'options' => $options->vacancyForm(null),
            'showSalary' => $salaries->allows($request->user()),
        ]);
    }

    public function store(VacancyRequest $request, SaveVacancy $save): RedirectResponse
    {
        $vacancy = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.vacancies.show', $vacancy)
            ->with('status', __('recruitment.messages.vacancy_created', ['reference' => $vacancy->reference]));
    }

    public function show(Request $request, Vacancy $vacancy, PipelineBoard $board, Pipeline $pipeline, SalaryVisibility $salaries): View
    {
        Gate::authorize('view', $vacancy);

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'view' => ['nullable', Rule::in(['board', 'list'])]]);
        $vacancy->load(['branch', 'department', 'position', 'jobTitle', 'employmentType', 'evaluationForm', 'hiringManager']);

        return view('admin.recruitment.vacancies.show', [
            'vacancy' => $vacancy,
            'columns' => $board->columns($vacancy, $filters),
            'stages' => $pipeline->stagesFor($vacancy),
            'filters' => $filters,
            'view' => $filters['view'] ?? 'board',
            'canMove' => $request->user()->can('recruitment.update'),
            'showSalary' => ($vacancy->salary_min !== null || $vacancy->salary_max !== null)
                && $salaries->reveal($request->user(), $vacancy, ['salary_min', 'salary_max']),
        ]);
    }

    public function edit(Request $request, Vacancy $vacancy, RecruitmentOptions $options, SalaryVisibility $salaries): View
    {
        Gate::authorize('update', $vacancy);

        return view('admin.recruitment.vacancies.form', [
            'vacancy' => $vacancy,
            'options' => $options->vacancyForm($vacancy),
            'showSalary' => $salaries->reveal($request->user(), $vacancy, ['salary_min', 'salary_max']),
        ]);
    }

    public function update(VacancyRequest $request, Vacancy $vacancy, SaveVacancy $save): RedirectResponse
    {
        $save->handle($vacancy, $request->validated(), $request->user());

        return redirect()->route('admin.vacancies.show', $vacancy)->with('status', __('recruitment.messages.vacancy_updated'));
    }

    public function destroy(Vacancy $vacancy): RedirectResponse
    {
        Gate::authorize('delete', $vacancy);

        $vacancy->delete();

        return redirect()->route('admin.vacancies.index')->with('status', __('recruitment.messages.vacancy_deleted', ['reference' => $vacancy->reference]));
    }

    public function customisePipeline(Vacancy $vacancy, Pipeline $pipeline): RedirectResponse
    {
        Gate::authorize('managePipeline', $vacancy);

        $pipeline->customise($vacancy);

        return redirect()->route('admin.recruitment-stages.index', ['vacancy' => $vacancy->id])->with('status', __('recruitment.messages.pipeline_customised'));
    }

    public function resetPipeline(Vacancy $vacancy, Pipeline $pipeline): RedirectResponse
    {
        Gate::authorize('managePipeline', $vacancy);

        $pipeline->useDefault($vacancy);

        return redirect()->route('admin.vacancies.show', $vacancy)->with('status', __('recruitment.messages.pipeline_reset'));
    }
}
