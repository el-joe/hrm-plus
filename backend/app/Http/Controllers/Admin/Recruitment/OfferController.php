<?php

namespace App\Http\Controllers\Admin\Recruitment;

use App\Actions\Recruitment\ManageOffer;
use App\Enums\OfferStatus;
use App\Exceptions\ApprovalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Recruitment\OfferRequest;
use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobTitle;
use App\Models\Position;
use App\Services\Approvals\ApprovalService;
use App\Services\Recruitment\SalaryVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Job offers: list, create / edit, the offer page with its workflow actions
 * (submit, approve / reject through the approval engine, send, record the
 * candidate's answer, withdraw) and the printable offer letter.
 */
class OfferController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', JobOffer::class);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(OfferStatus::class)]]);

        return view('admin.recruitment.offers.index', [
            'offers' => JobOffer::query()
                ->with(['application.candidate', 'application.vacancy', 'jobTitle'])
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->latest('id')->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, JobApplication $application, SalaryVisibility $salaries): View
    {
        Gate::authorize('create', JobOffer::class);
        Gate::authorize('view', $application);

        $application->load(['candidate', 'vacancy']);

        return view('admin.recruitment.offers.form', [
            'offer' => new JobOffer([
                'position_id' => $application->vacancy->position_id,
                'job_title_id' => $application->vacancy->job_title_id,
                'employment_type_id' => $application->vacancy->employment_type_id,
                'start_date' => today()->addDays(14),
                'expires_on' => today()->addDays((int) setting('recruitment.offer_validity_days')),
                'probation_months' => 3,
            ]),
            'application' => $application,
            'options' => $this->options(),
            'showSalary' => $salaries->allows($request->user()),
        ]);
    }

    public function store(OfferRequest $request, JobApplication $application, ManageOffer $offers): RedirectResponse
    {
        $offer = $offers->create($application, $request->validated(), $request->user());

        return redirect()->route('admin.offers.show', $offer)->with('status', __('recruitment.messages.offer_created', ['reference' => $offer->reference]));
    }

    public function show(Request $request, JobOffer $offer, SalaryVisibility $salaries, ApprovalService $approvals, ManageOffer $manage): View
    {
        Gate::authorize('view', $offer);

        $offer->load(['application.candidate', 'application.vacancy', 'position', 'jobTitle', 'employmentType', 'creator', 'approvalRequests.actions.actor']);
        $latestApproval = $offer->approvalRequests->sortByDesc('id')->first();

        return view('admin.recruitment.offers.show', [
            'offer' => $offer,
            'showSalary' => $offer->basic_salary !== null && $salaries->reveal($request->user(), $offer, ['basic_salary']),
            'canSeeSalary' => $salaries->allows($request->user()),
            'canUpdate' => $request->user()->can('update', $offer),
            'approvalRequest' => $latestApproval,
            'canDecide' => $latestApproval !== null && $approvals->canAct($latestApproval, $request->user()),
            'requiresApproval' => $manage->requiresApproval(),
        ]);
    }

    public function edit(Request $request, JobOffer $offer, SalaryVisibility $salaries): View
    {
        Gate::authorize('update', $offer);
        abort_unless($offer->status->isEditable(), 404);

        return view('admin.recruitment.offers.form', [
            'offer' => $offer,
            'application' => $offer->application->load(['candidate', 'vacancy']),
            'options' => $this->options(),
            'showSalary' => $salaries->reveal($request->user(), $offer, ['basic_salary']),
        ]);
    }

    public function update(OfferRequest $request, JobOffer $offer, ManageOffer $offers): RedirectResponse
    {
        $offers->update($offer, $request->validated());

        return redirect()->route('admin.offers.show', $offer)->with('status', __('recruitment.messages.offer_updated'));
    }

    /**
     * Workflow actions: submit, send, accept, decline, withdraw.
     */
    public function transition(Request $request, JobOffer $offer, string $action, ManageOffer $offers): RedirectResponse
    {
        Gate::authorize('update', $offer);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        match ($action) {
            'submit' => $offers->submit($offer, $request->user()),
            'send' => $offers->send($offer, $request->user()),
            'accept' => $offers->respond($offer, true, $request->user(), $note),
            'decline' => $offers->respond($offer, false, $request->user(), $note),
            'withdraw' => $offers->withdraw($offer, $request->user(), $note),
        };

        return back()->with('status', __('recruitment.messages.offer_status', ['status' => $offer->fresh()->status->label()]));
    }

    /**
     * Approve / reject the pending approval step (approval engine decides who may).
     */
    public function decide(Request $request, JobOffer $offer, string $decision, ApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('view', $offer);
        $data = $request->validate(['comment' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $approvalRequest = $offer->approvalRequests()->pending()->latest('id')->first();
        abort_if($approvalRequest === null, 404);

        try {
            $decision === 'approve'
                ? $approvals->approve($approvalRequest, $request->user(), $data['comment'] ?? null)
                : $approvals->reject($approvalRequest, $request->user(), (string) $data['comment']);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['comment' => $exception->getMessage()]);
        }

        return back()->with('status', __('recruitment.messages.offer_status', ['status' => $offer->fresh()->status->label()]));
    }

    /**
     * Printable HTML offer letter (the browser prints it to PDF).
     */
    public function letter(Request $request, JobOffer $offer, SalaryVisibility $salaries): View
    {
        Gate::authorize('view', $offer);

        $offer->load(['application.candidate', 'application.vacancy.branch', 'application.vacancy.department', 'jobTitle', 'employmentType', 'position']);

        return view('admin.recruitment.offers.letter', [
            'offer' => $offer,
            'company' => Company::query()->first(),
            'showSalary' => $offer->basic_salary !== null && $salaries->reveal($request->user(), $offer, ['basic_salary']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'positions' => Position::query()->active()->orderBy('code')->get(),
            'jobTitles' => JobTitle::query()->active()->orderBy('code')->get(),
            'employmentTypes' => EmploymentType::query()->active()->orderBy('code')->get(),
        ];
    }
}
