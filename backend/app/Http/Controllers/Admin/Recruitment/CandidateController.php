<?php

namespace App\Http\Controllers\Admin\Recruitment;

use App\Actions\Recruitment\ApplyToVacancy;
use App\Actions\Recruitment\SaveCandidate;
use App\Enums\CandidateSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Recruitment\CandidateRequest;
use App\Models\Candidate;
use App\Models\CandidateTag;
use App\Models\Vacancy;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Recruitment\RecruitmentFileStore;
use App\Services\Recruitment\RecruitmentOptions;
use App\Services\Recruitment\SalaryVisibility;
use App\Support\IndexSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Candidate database: list, profile (applications, interviews, notes,
 * attachments, tags), create / edit with duplicate detection, CV download.
 * Salaries are shown only to holders of employee_financials.view (logged).
 */
class CandidateController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request, RecruitmentOptions $options): View
    {
        Gate::authorize('viewAny', Candidate::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::enum(CandidateSource::class)],
            'vacancy_id' => ['nullable', 'integer'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $query = Candidate::query()
            ->with(['tags', 'applications' => fn ($query) => $query->with(['vacancy', 'stage'])->latest('applied_at')])
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->search($term))
            ->when($filters['source'] ?? null, fn ($query, string $source) => $query->where('source', $source))
            ->when($filters['vacancy_id'] ?? null, fn ($query, $id) => $query->whereHas('applications', fn ($query) => $query->where('vacancy_id', $id)))
            ->when($filters['tag'] ?? null, fn ($query, string $tag) => $query->whereHas('tags', fn ($query) => $query->where('name', $tag)));

        $sort = IndexSorting::apply($query, $request, [
            'name' => Candidate::localizedColumn(),
            'created' => 'created_at',
            'experience' => 'experience_years',
        ], default: 'created', defaultDirection: 'desc');

        return view('admin.recruitment.candidates.index', [
            'candidates' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'sources' => $options->sources(),
            'vacancies' => Vacancy::query()->orderByDesc('id')->get(['id', 'reference', 'title_ar', 'title_en']),
            'tags' => CandidateTag::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function create(Request $request, RecruitmentOptions $options, SalaryVisibility $salaries): View
    {
        Gate::authorize('create', Candidate::class);

        return view('admin.recruitment.candidates.form', [
            'candidate' => new Candidate(['source' => CandidateSource::Other]),
            'vacancies' => $options->openVacancies(),
            'selectedVacancy' => $request->integer('vacancy') ?: null,
            'sources' => $options->sources(),
            'showSalary' => $salaries->allows($request->user()),
        ]);
    }

    public function store(CandidateRequest $request, SaveCandidate $save): RedirectResponse
    {
        $candidate = $save->handle(null, $request->safe()->except(['cv', 'confirm_duplicate']), $request->user(), $request->file('cv'));

        return redirect()->route('admin.candidates.show', $candidate)->with('status', __('recruitment.messages.candidate_created', ['name' => $candidate->name]));
    }

    public function show(Request $request, Candidate $candidate, RecruitmentOptions $options, SalaryVisibility $salaries): View
    {
        Gate::authorize('view', $candidate);

        $candidate->load([
            'tags', 'employee', 'referredBy',
            'applications' => fn ($query) => $query->with(['vacancy', 'stage', 'latestOffer', 'interviews.interviewers'])->latest('applied_at'),
            'notes.author', 'attachments.uploader',
        ]);
        $hasSalary = $candidate->expected_salary !== null || $candidate->current_salary !== null;

        return view('admin.recruitment.candidates.show', [
            'candidate' => $candidate,
            'showSalary' => $hasSalary && $salaries->reveal($request->user(), $candidate, ['expected_salary', 'current_salary']),
            'canSeeSalary' => $salaries->allows($request->user()),
            'openVacancies' => $options->openVacancies()->whereNotIn('id', $candidate->applications->pluck('vacancy_id')),
        ]);
    }

    public function edit(Request $request, Candidate $candidate, RecruitmentOptions $options, SalaryVisibility $salaries): View
    {
        Gate::authorize('update', $candidate);

        return view('admin.recruitment.candidates.form', [
            'candidate' => $candidate->load('tags'),
            'vacancies' => collect(),
            'selectedVacancy' => null,
            'sources' => $options->sources(),
            'showSalary' => $salaries->reveal($request->user(), $candidate, ['expected_salary', 'current_salary']),
        ]);
    }

    public function update(CandidateRequest $request, Candidate $candidate, SaveCandidate $save): RedirectResponse
    {
        $save->handle($candidate, $request->safe()->except(['cv', 'confirm_duplicate']), $request->user(), $request->file('cv'));

        return redirect()->route('admin.candidates.show', $candidate)->with('status', __('recruitment.messages.candidate_updated'));
    }

    public function destroy(Candidate $candidate): RedirectResponse
    {
        Gate::authorize('delete', $candidate);

        $candidate->delete();

        return redirect()->route('admin.candidates.index')->with('status', __('recruitment.messages.candidate_deleted', ['name' => $candidate->name]));
    }

    public function cv(Request $request, Candidate $candidate, RecruitmentFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        Gate::authorize('view', $candidate);
        abort_if($candidate->cv_path === null, 404);

        $log->record(SensitiveDataAccessLog::CANDIDATE_FILE, $candidate, ['cv'], viewer: $request->user());

        return $files->response($candidate->cv_path, $candidate->cv_name ?? 'cv', $candidate->cv_mime ?? 'application/octet-stream');
    }

    public function apply(Request $request, Candidate $candidate, ApplyToVacancy $apply): RedirectResponse
    {
        Gate::authorize('update', $candidate);

        $data = $request->validate([
            'vacancy_id' => ['required', 'integer'],
            'source' => ['required', Rule::enum(CandidateSource::class)],
        ]);
        $vacancy = Vacancy::query()->findOrFail($data['vacancy_id']);
        Gate::authorize('update', $vacancy);

        $application = $apply->handle($candidate, $vacancy, CandidateSource::from($data['source']), null, $request->user());

        return redirect()->route('admin.applications.show', $application)->with('status', __('recruitment.messages.applied', ['vacancy' => $vacancy->title]));
    }
}
