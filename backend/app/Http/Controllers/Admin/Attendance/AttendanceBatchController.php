<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Actions\Attendance\ManageManualBatch;
use App\Enums\ManualBatchStatus;
use App\Enums\ManualEntryAction;
use App\Exceptions\ApprovalException;
use App\Http\Controllers\Controller;
use App\Models\AttendanceManualBatch;
use App\Models\AttendanceStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmployeeGroup;
use App\Models\Shift;
use App\Models\Team;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\BulkAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Bulk batches and imports: history, the confirmation screen of a draft
 * (summary of counts before anything is written), confirm / discard, and
 * the approval decision when a policy requires one.
 */
class AttendanceBatchController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AttendanceManualBatch::class);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(ManualBatchStatus::class)]]);

        return view('admin.attendance.batches.index', [
            'batches' => AttendanceManualBatch::query()->with(['creator', 'latestApprovalRequest'])->withCount('entries')
                ->where(fn ($query) => $query->where('status', '!=', ManualBatchStatus::Draft)->orWhere('created_by', $request->user()->id))
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => ManualBatchStatus::cases(),
        ]);
    }

    public function show(AttendanceManualBatch $batch, BulkAttendance $bulk): View
    {
        Gate::authorize('view', $batch);

        $batch->load(['creator', 'approvalRequests.actions.actor']);
        $entries = $batch->entries()->with(['employee' => fn ($query) => $query->withoutGlobalScopes(), 'shift', 'attendanceStatus'])->orderBy('id')->get();
        $statusNames = AttendanceStatus::query()->pluck(AttendanceStatus::localizedColumn(), 'code');

        return view('admin.attendance.batches.show', [
            'batch' => $batch,
            'entries' => $entries,
            'exceptions' => $entries->filter(fn ($entry): bool => $entry->action !== ManualEntryAction::Present),
            'statusNames' => $statusNames,
            'requiresApproval' => $batch->isDraft() && $bulk->requiresApproval($batch),
            'canConfirm' => Gate::allows('confirm', $batch),
            'canDecide' => Gate::allows('decide', $batch),
            'filterLabels' => $this->filterLabels($batch->filters ?? []),
        ]);
    }

    public function confirm(Request $request, AttendanceManualBatch $batch, ManageManualBatch $manage): RedirectResponse
    {
        Gate::authorize('confirm', $batch);

        try {
            $manage->confirm($batch, $request->user());
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['batch' => $exception->getMessage()]);
        }

        $message = $batch->status === ManualBatchStatus::Applied ? 'attendance.bulk.applied' : 'attendance.bulk.submitted';

        return redirect()->route('admin.attendance.batches.show', $batch)->with('status', __($message, ['count' => $batch->entries()->count()]));
    }

    public function discard(Request $request, AttendanceManualBatch $batch, ManageManualBatch $manage): RedirectResponse
    {
        Gate::authorize('confirm', $batch);

        $manage->discard($batch, $request->user());
        $filters = $batch->work_date ? ['date' => $batch->work_date->format('Y-m-d'), ...($batch->filters ?? [])] : [];

        return redirect()->route($batch->source->value === 'import' ? 'admin.attendance.import' : 'admin.attendance.bulk.create', $filters)
            ->with('status', __('attendance.bulk.discarded'));
    }

    public function decide(Request $request, AttendanceManualBatch $batch, string $decision, ApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('decide', $batch);

        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);

        try {
            $decision === 'approve'
                ? $approvals->approve($batch->latestApprovalRequest, $request->user(), $data['reason'] ?? null)
                : $approvals->reject($batch->latestApprovalRequest, $request->user(), $data['reason']);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return redirect()->route('admin.attendance.batches.show', $batch)->with('status', __('attendance.bulk.'.($decision === 'approve' ? 'approved' : 'rejected')));
    }

    /**
     * Human labels of the filters used to pick the employees.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function filterLabels(array $filters): array
    {
        $models = ['branch_id' => Branch::class, 'department_id' => Department::class, 'team_id' => Team::class, 'shift_id' => Shift::class, 'group_id' => EmployeeGroup::class];
        $labels = [];

        foreach ($models as $key => $model) {
            if (isset($filters[$key]) && ($record = $model::query()->withoutGlobalScopes()->find($filters[$key]))) {
                $labels[$key] = $record->code.' · '.$record->name;
            }
        }

        return $labels;
    }
}
