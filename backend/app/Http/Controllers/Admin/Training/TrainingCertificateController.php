<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Training\ManageTrainingCertificate;
use App\Enums\CertificateType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Training\TrainingCertificateRequest;
use App\Models\Employee;
use App\Models\TrainingCertificate;
use App\Models\TrainingCourse;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Employees\Spreadsheet;
use App\Services\Training\TrainingFileStore;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Certificates, licences and qualifications inside the admin's data scope: the
 * register (filters: validity — expiring within the reminder window / expired),
 * record / edit / delete with a file on the private disk, authorised download, export.
 */
class TrainingCertificateController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TrainingCertificate::class);

        $filters = $this->filters($request);
        $query = $this->query($filters)->with(['employee', 'course']);
        $sort = IndexSorting::apply($query, $request, ['expires_on' => 'expires_on', 'issued_on' => 'issued_on', 'name' => 'name'], 'expires_on');
        $days = (int) setting('training.certificate_reminder_days');

        return view('admin.training.certificates.index', [
            'certificates' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'types' => CertificateType::cases(),
            'reminderDays' => $days,
            'counts' => [
                'expiring' => TrainingCertificate::query()->expiringWithin($days)->count(),
                'expired' => TrainingCertificate::query()->expired()->count(),
                'total' => TrainingCertificate::query()->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', TrainingCertificate::class);

        return $this->form(new TrainingCertificate([
            'type' => CertificateType::Certificate, 'employee_id' => $request->integer('employee') ?: null, 'issued_on' => today(),
        ]));
    }

    public function store(TrainingCertificateRequest $request, ManageTrainingCertificate $certificates): RedirectResponse
    {
        $certificate = $certificates->store($request->employee(), $request->certificateData(), $request->file('file'), $request->user());

        return redirect()->route('admin.training-certificates.index', ['employee_id' => $certificate->employee_id])
            ->with('status', __('training.certificates.saved', ['name' => $certificate->name]));
    }

    public function edit(TrainingCertificate $trainingCertificate): View
    {
        Gate::authorize('update', $trainingCertificate);

        return $this->form($trainingCertificate->load('employee'));
    }

    public function update(TrainingCertificateRequest $request, TrainingCertificate $trainingCertificate, ManageTrainingCertificate $certificates): RedirectResponse
    {
        $certificates->update($trainingCertificate, $request->certificateData(), $request->file('file'), $request->user());

        return redirect()->route('admin.training-certificates.index', ['employee_id' => $trainingCertificate->employee_id])
            ->with('status', __('training.certificates.saved', ['name' => $trainingCertificate->name]));
    }

    public function destroy(Request $request, TrainingCertificate $trainingCertificate, ManageTrainingCertificate $certificates): RedirectResponse
    {
        Gate::authorize('delete', $trainingCertificate);

        $certificates->delete($trainingCertificate, $request->user());

        return redirect()->route('admin.training-certificates.index')->with('status', __('training.certificates.deleted', ['name' => $trainingCertificate->name]));
    }

    public function download(Request $request, TrainingCertificate $trainingCertificate, TrainingFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        Gate::authorize('view', $trainingCertificate);

        $log->record(SensitiveDataAccessLog::DOCUMENT, $trainingCertificate, ['file'], ['employee_id' => $trainingCertificate->employee_id], $request->user());

        return $files->response($trainingCertificate);
    }

    public function export(Request $request, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('export', TrainingCertificate::class);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/training-certificates-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('training.fields.'.$key), [
            'employee', 'type', 'name', 'issuer', 'certificate_number', 'issued_on', 'expires_on', 'validity',
        ]);

        $rows = $this->query($filters)->with('employee')->orderBy('id')->lazy()->map(fn (TrainingCertificate $row): array => [
            $row->employee->label(), $row->type->label(), $row->name, $row->issuer ?? '', $row->certificate_number ?? '',
            $row->issued_on?->toDateString() ?? '', $row->expires_on?->toDateString() ?? '', __('training.validity.'.$row->validityState()),
        ]);

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, 'training-certificates-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(CertificateType::class)],
            'validity' => ['nullable', 'in:expiring,expired,valid,no_expiry'],
            'employee_id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TrainingCertificate>
     */
    private function query(array $filters): Builder
    {
        $days = (int) setting('training.certificate_reminder_days');

        return TrainingCertificate::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")->orWhereLike('certificate_number', "%{$search}%")
                ->orWhereHas('employee', fn (Builder $employee) => $employee->search($search))))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, int $employee) => $query->where('employee_id', $employee))
            ->when(($filters['validity'] ?? null) === 'expiring', fn (Builder $query) => $query->expiringWithin($days))
            ->when(($filters['validity'] ?? null) === 'expired', fn (Builder $query) => $query->expired())
            ->when(($filters['validity'] ?? null) === 'no_expiry', fn (Builder $query) => $query->whereNull('expires_on'))
            ->when(($filters['validity'] ?? null) === 'valid', fn (Builder $query) => $query->whereDate('expires_on', '>', today()->addDays($days)));
    }

    private function form(TrainingCertificate $certificate): View
    {
        return view('admin.training.certificates.form', [
            'certificate' => $certificate,
            'types' => CertificateType::cases(),
            'employees' => $certificate->exists ? collect() : Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'courses' => TrainingCourse::query()->orderByName()->get(),
        ]);
    }
}
