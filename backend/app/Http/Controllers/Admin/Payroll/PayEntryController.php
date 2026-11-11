<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\ManagePayEntry;
use App\Enums\PayComponentKind;
use App\Enums\PayEntryCalculation;
use App\Enums\PayEntryRecurrence;
use App\Enums\PayEntryTarget;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payroll\BulkPayEntryRequest;
use App\Http\Requests\Admin\Payroll\PayEntryRequest;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\PayEntryAttachment;
use App\Models\SalaryComponent;
use App\Policies\PayEntryPolicy;
use App\Services\Authorization\DataScopeManager;
use App\Services\Employees\Spreadsheet;
use App\Services\Payroll\PayEntryImporter;
use App\Services\Payroll\PayrollFileStore;
use App\Services\Payroll\PayrollFormOptions;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Allowances (AllowanceController) and deductions (DeductionController):
 * list with filters, single entry, bulk assignment to many employees,
 * spreadsheet import (dry run first), detail with approval, cancel / stop,
 * supporting documents and export. Rules live in ManagePayEntry.
 */
abstract class PayEntryController extends Controller
{
    public const PER_PAGE = 25;

    abstract public function kind(): PayComponentKind;

    protected function resource(): string
    {
        return $this->kind() === PayComponentKind::Deduction ? 'deductions' : 'allowances';
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', [PayEntry::class, $this->kind()]);

        $filters = $this->filters($request);

        return view('admin.payroll.entries.index', [
            ...$this->common(),
            'entries' => $this->query($filters)->with(['component', 'employee', 'employeeGroup', 'department', 'branch', 'latestApprovalRequest.currentStep'])
                ->latest('id')->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'components' => app(PayrollFormOptions::class)->entryComponents($this->kind()),
            'statuses' => RequestStatus::cases(),
            'recurrences' => $this->recurrences(),
            'targets' => PayEntryTarget::cases(),
        ]);
    }

    public function create(Request $request, PayrollFormOptions $options): View
    {
        Gate::authorize('create', [PayEntry::class, $this->kind()]);

        return view('admin.payroll.entries.create', [
            ...$this->common(),
            ...$this->formOptions($options),
            'targetOptions' => $options->targets(),
            'preset' => ['target_type' => 'employee', 'employee_id' => $request->integer('employee') ?: null],
        ]);
    }

    public function store(PayEntryRequest $request, ManagePayEntry $entries): RedirectResponse
    {
        $entry = $entries->create($this->kind(), $request->entryData(), $request->user(), $request->attachmentFiles());

        return redirect()->route('admin.'.$this->resource().'.show', $entry)
            ->with('status', __($entry->isPending() ? 'payroll.entries.submitted' : 'payroll.entries.saved', ['reference' => $entry->reference]));
    }

    public function bulkForm(Request $request, PayrollFormOptions $options): View
    {
        Gate::authorize('create', [PayEntry::class, $this->kind()]);

        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer'], 'employee_group_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $employees = Employee::query()->employed()
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $id) => $query->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->whereIn('department_id', app(DataScopeManager::class)->withSubDepartments([$id])))
            ->when($filters['employee_group_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('groups', fn (Builder $q) => $q->whereKey($id)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->search($term))
            ->with(['department', 'branch'])->orderBy('employee_number')->limit(1000)->get();

        return view('admin.payroll.entries.bulk', [
            ...$this->common(),
            ...$this->formOptions($options),
            'filters' => $filters,
            'employees' => $employees,
            'targetOptions' => $options->targets(),
            'filtered' => array_filter($filters) !== [],
        ]);
    }

    public function bulk(BulkPayEntryRequest $request, ManagePayEntry $entries): RedirectResponse
    {
        $batch = $entries->bulk($this->kind(), $request->entryData(), $request->validated('employee_ids'), $request->user(), $request->validated('note'));

        return redirect()->route('admin.'.$this->resource().'.index', ['batch' => $batch->id])
            ->with('status', trans_choice('payroll.entries.bulk_done', $batch->entries_count, ['count' => $batch->entries_count]));
    }

    public function show(PayEntry $payEntry): View
    {
        $this->ensureKind($payEntry);
        Gate::authorize('view', $payEntry);

        return view('admin.payroll.entries.show', [
            ...$this->common(),
            'entry' => $payEntry->load(['component', 'baseComponent', 'creator', 'decider', 'attachments.uploader', 'batch',
                'approvalRequests' => fn ($query) => $query->with(['actions.actor', 'currentStep', 'workflow'])->latest('id')]),
            'target' => $payEntry->targetLabel(),
        ]);
    }

    public function decide(Request $request, PayEntry $payEntry, string $decision, ManagePayEntry $entries): RedirectResponse
    {
        $this->ensureKind($payEntry);
        Gate::authorize('decide', $payEntry);

        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $entries->decide($payEntry, $request->user(), $decision === 'approve', $data['reason'] ?? null);

        return back()->with('status', __($decision === 'approve' ? 'payroll.entries.approved' : 'payroll.entries.rejected', ['reference' => $payEntry->reference]));
    }

    public function cancel(Request $request, PayEntry $payEntry, ManagePayEntry $entries): RedirectResponse
    {
        $this->ensureKind($payEntry);
        Gate::authorize('cancel', $payEntry);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $entries->cancel($payEntry, $request->user(), $data['reason']);

        return back()->with('status', __('payroll.entries.cancelled', ['reference' => $payEntry->reference]));
    }

    public function stop(Request $request, PayEntry $payEntry, ManagePayEntry $entries): RedirectResponse
    {
        $this->ensureKind($payEntry);
        Gate::authorize('stop', $payEntry);

        $data = $request->validate(['end_date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:1000']]);
        $entries->stop($payEntry, $data['end_date'], $request->user(), $data['reason']);

        return back()->with('status', __('payroll.entries.stopped', ['reference' => $payEntry->reference, 'date' => $data['end_date']]));
    }

    public function storeAttachments(Request $request, PayEntry $payEntry, ManagePayEntry $entries): RedirectResponse
    {
        $this->ensureKind($payEntry);
        Gate::authorize('addAttachments', $payEntry);

        $data = $request->validate([
            'attachments' => ['required', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:'.(int) setting('documents.max_upload_size_kb'), 'mimes:'.implode(',', (array) setting('documents.allowed_extensions'))],
        ]);
        $entries->addAttachments($payEntry, array_values($data['attachments']), $request->user());

        return back()->with('status', __('payroll.entries.attachments_added'));
    }

    public function attachment(PayEntry $payEntry, PayEntryAttachment $attachment, PayrollFileStore $files): StreamedResponse
    {
        $this->ensureKind($payEntry);
        Gate::authorize('view', $payEntry);
        abort_unless($attachment->pay_entry_id === $payEntry->id, 404);

        return $files->response($attachment->file_path, $attachment->file_name, $attachment->file_mime);
    }

    public function importForm(): View
    {
        Gate::authorize('create', [PayEntry::class, $this->kind()]);

        return view('admin.payroll.entries.import', [...$this->common(), 'columns' => PayEntryImporter::COLUMNS, 'report' => null, 'token' => null]);
    }

    public function importTemplate(Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('create', [PayEntry::class, $this->kind()]);

        $path = storage_path('app/private/'.$this->resource().'-import-template-'.uniqid().'.xlsx');
        $spreadsheet->write($path, 'xlsx', PayEntryImporter::COLUMNS, []);

        return response()->download($path, $this->resource().'-import-template.xlsx')->deleteFileAfterSend();
    }

    public function importAnalyse(Request $request, PayEntryImporter $importer): View
    {
        Gate::authorize('create', [PayEntry::class, $this->kind()]);

        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt']]);
        $token = $importer->stash($request->file('file'), $request->user());
        $file = $importer->stashed($token, $request->user());

        return view('admin.payroll.entries.import', [
            ...$this->common(),
            'columns' => PayEntryImporter::COLUMNS,
            'report' => $importer->analyse($file['path'], $file['extension'], $this->kind()),
            'token' => $token,
            'fileName' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    public function importStore(Request $request, string $token, PayEntryImporter $importer): View|RedirectResponse
    {
        Gate::authorize('create', [PayEntry::class, $this->kind()]);

        $file = $importer->stashed($token, $request->user());

        if ($file === null) {
            return redirect()->route('admin.'.$this->resource().'.import')->with('error', __('payroll.import.expired'));
        }

        $report = $importer->import($file['path'], $file['extension'], $this->kind(), $request->user());

        if ($report['created'] === 0) {
            return view('admin.payroll.entries.import', [...$this->common(), 'columns' => PayEntryImporter::COLUMNS, 'report' => $report, 'token' => $token, 'fileName' => null]);
        }

        $importer->discard($token, $request->user());

        return redirect()->route('admin.'.$this->resource().'.index', ['batch' => $report['batch']->id])
            ->with('status', trans_choice('payroll.import.done', $report['created'], ['count' => $report['created']]));
    }

    public function export(Request $request, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('export', [PayEntry::class, $this->kind()]);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/'.$this->resource().'-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('payroll.fields.'.$key), ['reference', 'salary_component_id', 'target_type', 'calculation', 'amount', 'rate', 'recurrence', 'start_date', 'end_date', 'status', 'reason']);

        $rows = $this->query($filters)->with(['component'])->orderBy('id')->lazy()->map(fn (PayEntry $entry): array => [
            $entry->reference, $entry->component->name, $entry->targetLabel(), $entry->calculation->label(),
            $entry->amount === null ? '' : Money::format((string) $entry->amount), $entry->rate === null ? '' : (string) $entry->rate,
            $entry->recurrence->label(), $entry->start_date, $entry->end_date, $entry->status->label(), $entry->reason,
        ]);

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, $this->resource().'-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_column(RequestStatus::cases(), 'value'))],
            'salary_component_id' => ['nullable', 'integer'],
            'recurrence' => ['nullable', 'in:'.implode(',', array_column(PayEntryRecurrence::cases(), 'value'))],
            'target_type' => ['nullable', 'in:'.implode(',', array_column(PayEntryTarget::cases(), 'value'))],
            'batch' => ['nullable', 'integer'],
            'current' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PayEntry>
     */
    private function query(array $filters): Builder
    {
        $today = now()->toDateString();

        return PayEntry::query()->ofKind($this->kind())
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['salary_component_id'] ?? null, fn (Builder $query, int $id) => $query->where('salary_component_id', $id))
            ->when($filters['recurrence'] ?? null, fn (Builder $query, string $value) => $query->where('recurrence', $value))
            ->when($filters['target_type'] ?? null, fn (Builder $query, string $value) => $query->where('target_type', $value))
            ->when($filters['batch'] ?? null, fn (Builder $query, int $id) => $query->where('pay_entry_batch_id', $id))
            ->when($filters['current'] ?? false, fn (Builder $query) => $query->activeBetween($today, $today))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->whereLike('reference', "%{$term}%")
                ->orWhereHas('employee', fn (Builder $employee) => $employee->search($term))));
    }

    /**
     * @return array<string, mixed>
     */
    private function common(): array
    {
        return ['kind' => $this->kind(), 'resource' => $this->resource(), 'module' => PayEntryPolicy::module($this->kind())];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(PayrollFormOptions $options): array
    {
        return [
            'components' => $options->entryComponents($this->kind()),
            'bases' => SalaryComponent::query()->active()->whereIn('calculation_type', ['basic', 'fixed', 'percentage'])->ordered()->get(),
            'calculations' => PayEntryCalculation::cases(),
            'recurrences' => $this->recurrences(),
            'targets' => PayEntryTarget::cases(),
        ];
    }

    /**
     * @return list<PayEntryRecurrence>
     */
    private function recurrences(): array
    {
        return $this->kind() === PayComponentKind::Deduction ? PayEntryRecurrence::cases() : [PayEntryRecurrence::Recurring, PayEntryRecurrence::OneTime];
    }

    private function ensureKind(PayEntry $entry): void
    {
        abort_unless($entry->kind === $this->kind(), 404);
    }
}
