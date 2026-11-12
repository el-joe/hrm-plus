<?php

namespace App\Http\Controllers\Admin\Performance;

use App\Actions\Performance\ManagePerformanceReview;
use App\Enums\PerformanceReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\ReviewRatingsRequest;
use App\Models\Department;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\Employees\Spreadsheet;
use App\Services\Performance\PerformanceVisibility;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Performance reviews in the admin area: the list (HR: everything in scope;
 * managers: their reporting line), the review page with the manager assessment,
 * HR calibration (approval engine), skipping the self-assessment and cancelling.
 * Rules live in App\Actions\Performance\ManagePerformanceReview.
 */
class PerformanceReviewController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(private readonly PerformanceVisibility $visibility) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PerformanceReview::class);

        $filters = $this->filters($request);
        $query = $this->query($request, $filters)->with(['employee', 'cycle', 'finalRating', 'reviewer', 'latestApprovalRequest.currentStep']);
        $sort = IndexSorting::apply($query, $request, [
            'reference' => 'reference',
            'status' => 'status',
            'score' => 'overall_score',
            'updated' => 'updated_at',
        ], default: 'updated', defaultDirection: 'desc');

        $counts = $this->query($request, [])->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.performance.reviews.index', [
            'reviews' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'counts' => $counts,
            'statuses' => PerformanceReviewStatus::cases(),
            'cycles' => PerformanceCycle::query()->where('status', '!=', 'draft')->orderByDesc('period_start')->get(),
            'departments' => Department::query()->orderByName()->get(),
            'seesAll' => $this->visibility->seesAll($request->user()),
        ]);
    }

    public function export(Request $request, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('export', PerformanceReview::class);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/performance-reviews-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('performance.fields.'.$key), [
            'reference', 'employee_number', 'employee', 'department', 'cycle', 'reviewer', 'status', 'self_score', 'goals_score', 'competencies_score', 'overall_score', 'final_rating', 'approved_at',
        ]);

        $rows = $this->query($request, $filters)->with(['employee', 'cycle', 'finalRating', 'reviewer', 'department'])->orderBy('id')->lazy()
            ->map(fn (PerformanceReview $review): array => [
                $review->reference, $review->employee?->employee_number, $review->employee?->name, $review->department?->name, $review->cycle->name,
                $review->reviewer?->name ?? '', $review->status->label(), (string) ($review->self_overall_score ?? ''), (string) ($review->goals_score ?? ''),
                (string) ($review->competencies_score ?? ''), (string) ($review->overall_score ?? ''), $review->finalRating?->label ?? '',
                $review->approved_at?->toDateString() ?? '',
            ]);

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, 'performance-reviews-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    public function show(Request $request, PerformanceReview $performanceReview, ManagePerformanceReview $manage): View
    {
        Gate::authorize('view', $performanceReview);

        $performanceReview->load([
            'employee.jobTitle', 'employee.department', 'reviewer', 'cycle.ratingScale.levels', 'finalRating', 'competencies.competency', 'managerSubmitter', 'approver',
            'approvalRequests' => fn ($query) => $query->with(['actions.actor', 'currentStep'])->latest('id'),
        ]);

        return view('admin.performance.reviews.show', [
            'review' => $performanceReview,
            'goals' => $performanceReview->goalsQuery()->get(),
            'levels' => $performanceReview->cycle->ratingScale->levels,
            'canAssess' => $request->user()->can('assess', $performanceReview),
            'canDecide' => $request->user()->can('decide', $performanceReview),
            'canSkipSelf' => $request->user()->can('skipSelf', $performanceReview),
            'canCancel' => $request->user()->can('cancel', $performanceReview),
            'showSelf' => $performanceReview->self_submitted_at !== null,
        ]);
    }

    public function assess(ReviewRatingsRequest $request, PerformanceReview $performanceReview, ManagePerformanceReview $manage): RedirectResponse
    {
        Gate::authorize('assess', $performanceReview);

        $submit = $request->boolean('submit');
        $manage->saveAssessment($performanceReview, $request->ratings(), $request->user(), $submit);

        return redirect()->route('admin.performance-reviews.show', $performanceReview)
            ->with('status', __($submit ? 'performance.reviews.submitted' : 'performance.reviews.draft_saved'));
    }

    public function decide(Request $request, PerformanceReview $performanceReview, string $decision, ManagePerformanceReview $manage): RedirectResponse
    {
        Gate::authorize('decide', $performanceReview);

        $data = $request->validate([
            'reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:2000'],
            'calibrated_level_id' => ['nullable', 'integer'],
        ]);

        $manage->decide($performanceReview, $request->user(), $decision === 'approve', $data['reason'] ?? null, isset($data['calibrated_level_id']) ? (int) $data['calibrated_level_id'] : null);

        return redirect()->route('admin.performance-reviews.show', $performanceReview)
            ->with('status', __($decision === 'approve' ? 'performance.reviews.approved' : 'performance.reviews.returned', ['reference' => $performanceReview->reference]));
    }

    public function skipSelf(Request $request, PerformanceReview $performanceReview, ManagePerformanceReview $manage): RedirectResponse
    {
        Gate::authorize('skipSelf', $performanceReview);
        $manage->skipSelf($performanceReview, $request->user());

        return back()->with('status', __('performance.reviews.self_skipped'));
    }

    public function cancel(Request $request, PerformanceReview $performanceReview, ManagePerformanceReview $manage): RedirectResponse
    {
        Gate::authorize('cancel', $performanceReview);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $manage->cancel($performanceReview, $request->user(), $data['reason']);

        return back()->with('status', __('performance.reviews.cancelled', ['reference' => $performanceReview->reference]));
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PerformanceReviewStatus::class)],
            'cycle' => ['nullable', 'integer'],
            'department' => ['nullable', 'integer'],
            'awaiting_me' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PerformanceReview>
     */
    private function query(Request $request, array $filters): Builder
    {
        $user = $request->user();

        return $this->visibility->reviews(PerformanceReview::query(), $user)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('employee', fn (Builder $employee) => $employee->search($search))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['cycle'] ?? null, fn (Builder $query, int|string $cycle) => $query->where('performance_cycle_id', $cycle))
            ->when($filters['department'] ?? null, fn (Builder $query, int|string $department) => $query->where('department_id', $department))
            ->when(($filters['awaiting_me'] ?? false) && $user->employee_id !== null, fn (Builder $query) => $query
                ->where('status', PerformanceReviewStatus::ManagerAssessment)->where('reviewer_id', $user->employee_id));
    }
}
