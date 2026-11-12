<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Enums\CheckInChannel;
use App\Enums\MobileAttemptOutcome;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceMobileAttempt;
use App\Models\Branch;
use App\Services\Attendance\Mobile\AttemptReason;
use App\Services\Attendance\Mobile\MobileAttemptReview;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Self-service attendance attempts (mobile app and web portal, filterable by channel): the suspicious-attempt review queue (flagged,
 * pending) by default, and every accepted / rejected attempt for auditing.
 */
class MobileReviewController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AttendanceMobileAttempt::class);

        $filters = $request->validate([
            'view' => ['nullable', 'in:queue,flagged,accepted,rejected,all'],
            'reason' => ['nullable', 'in:'.implode(',', AttemptReason::ALL)],
            'channel' => ['nullable', Rule::enum(CheckInChannel::class)],
            'branch_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filters['view'] ??= 'queue';
        $timezone = (string) setting('localisation.timezone');

        $attempts = AttendanceMobileAttempt::query()->with(['employee', 'location', 'faceVerification', 'reviewer'])
            ->when($filters['view'] === 'queue', fn (Builder $query) => $query->where('outcome', MobileAttemptOutcome::Flagged)->where('review_status', ReviewStatus::Pending))
            ->when(in_array($filters['view'], ['flagged', 'accepted', 'rejected'], true), fn (Builder $query) => $query->where('outcome', $filters['view']))
            ->when($filters['reason'] ?? null, fn (Builder $query, string $reason) => $query->whereJsonContains('reasons', $reason))
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel) => $query->where('channel', $channel))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('employee', fn (Builder $query) => $query->where('branch_id', $id)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where('server_time', '>=', CarbonImmutable::parse($date, $timezone)->utc()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where('server_time', '<', CarbonImmutable::parse($date, $timezone)->addDay()->utc()))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->whereHas('employee', fn (Builder $query) => $query->search($term)))
            ->latest('server_time')->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.attendance.reviews.index', [
            'attempts' => $attempts,
            'filters' => $filters,
            'reasons' => AttemptReason::ALL,
            'channels' => CheckInChannel::cases(),
            'branches' => Branch::query()->orderBy('code')->get(),
            'queueCount' => AttendanceMobileAttempt::query()->where('outcome', MobileAttemptOutcome::Flagged)->where('review_status', ReviewStatus::Pending)->count(),
        ]);
    }

    public function show(AttendanceMobileAttempt $attempt): View
    {
        Gate::authorize('view', $attempt);

        $attempt->load(['employee.branch', 'employee.department', 'location', 'shift', 'faceVerifications', 'reviewer', 'rawEvent']);

        return view('admin.attendance.reviews.show', [
            'attempt' => $attempt,
            'recent' => AttendanceMobileAttempt::query()->where('employee_id', $attempt->employee_id)->whereKeyNot($attempt->id)
                ->where('server_time', '>=', $attempt->server_time->subDay())->where('server_time', '<=', $attempt->server_time->addDay())
                ->orderBy('server_time')->get(),
        ]);
    }

    public function decide(Request $request, AttendanceMobileAttempt $attempt, string $decision, MobileAttemptReview $review): RedirectResponse
    {
        Gate::authorize($decision, $attempt);

        if ($decision === 'approve') {
            $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;
            $review->approve($attempt, $request->user(), $note);
        } else {
            $reason = $request->validate(['note' => ['required', 'string', 'max:1000']])['note'];
            $review->reject($attempt, $request->user(), $reason);
        }

        return redirect()->route('admin.attendance.reviews.index')->with('status', __('mobile.review.'.$decision.'d'));
    }
}
