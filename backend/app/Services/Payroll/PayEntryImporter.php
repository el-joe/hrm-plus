<?php

namespace App\Services\Payroll;

use App\Actions\Payroll\ManagePayEntry;
use App\Enums\PayComponentKind;
use App\Enums\PayEntryCalculation;
use App\Enums\PayEntryRecurrence;
use App\Enums\PayEntryTarget;
use App\Models\Employee;
use App\Models\PayEntryBatch;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Excel / CSV import of allowances or deductions, one row per employee
 * entry: upload → dry-run report (nothing saved) → import (all rows or none,
 * one batch). Employees outside the admin's data scope are reported as unknown.
 * Rows go through ManagePayEntry::create(), so approval rules apply as usual.
 */
class PayEntryImporter
{
    public const COLUMNS = [
        'employee_number', 'component_code', 'calculation', 'amount', 'rate', 'recurrence', 'start_date', 'end_date',
        'total_amount', 'instalment_count', 'reason',
    ];

    public function __construct(
        private readonly Spreadsheet $spreadsheet,
        private readonly EmployeeFileStore $files,
        private readonly ManagePayEntry $entries,
        private readonly AuditLogger $audit,
    ) {}

    public function stash(UploadedFile $file, User $user): string
    {
        $token = Str::uuid()->toString();
        $extension = strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';
        $this->files->disk()->putFileAs($this->folder($user), $file, $token.'.'.$extension);

        return $token;
    }

    /**
     * @return array{path: string, extension: string}|null
     */
    public function stashed(string $token, User $user): ?array
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        foreach (['xlsx', 'csv'] as $extension) {
            $relative = $this->folder($user).'/'.$token.'.'.$extension;

            if ($this->files->disk()->exists($relative) && $this->files->disk()->lastModified($relative) >= now()->subMinutes(config('employees.import_ttl_minutes'))->getTimestamp()) {
                return ['path' => $this->files->disk()->path($relative), 'extension' => $extension];
            }
        }

        return null;
    }

    public function discard(string $token, User $user): void
    {
        foreach (['xlsx', 'csv'] as $extension) {
            $this->files->disk()->delete($this->folder($user).'/'.$token.'.'.$extension);
        }
    }

    /**
     * Validate every row without writing anything.
     *
     * @return array{total: int, valid: int, errors: list<array{line: int, column: string, message: string}>, rows: array<int, array<string, mixed>>}
     */
    public function analyse(string $path, string $extension, PayComponentKind $kind): array
    {
        $rows = $this->spreadsheet->read($path, $extension, (int) config('employees.import_max_rows'));
        $components = SalaryComponent::query()->active()->forEntries($kind)->get()->keyBy(fn (SalaryComponent $c): string => strtoupper($c->code));
        $errors = [];
        $prepared = [];

        foreach ($rows as $line => $row) {
            if (isset($row['__too_many__'])) {
                $errors[] = ['line' => $line, 'column' => '', 'message' => __('payroll.import.too_many', ['max' => config('employees.import_max_rows')])];

                break;
            }

            $rowErrors = [];
            $employee = Employee::query()->where('employee_number', trim($row['employee_number'] ?? ''))->first();
            $component = $components->get(strtoupper(trim($row['component_code'] ?? '')));
            $calculation = PayEntryCalculation::tryFrom(strtolower(trim($row['calculation'] ?? 'fixed')) ?: 'fixed');
            $recurrence = PayEntryRecurrence::tryFrom(strtolower(trim($row['recurrence'] ?? '')));

            if ($employee === null) {
                $rowErrors['employee_number'] = __('payroll.import.unknown_employee');
            }

            if ($component === null) {
                $rowErrors['component_code'] = __('payroll.import.unknown_component');
            }

            if ($calculation === null) {
                $rowErrors['calculation'] = __('payroll.import.invalid_value');
            }

            if ($recurrence === null) {
                $rowErrors['recurrence'] = __('payroll.import.invalid_value');
            }

            foreach (['start_date', 'end_date'] as $column) {
                if (filled($row[$column] ?? null) && ! $this->isDate($row[$column])) {
                    $rowErrors[$column] = __('payroll.import.invalid_date');
                }
            }

            if (blank($row['start_date'] ?? null)) {
                $rowErrors['start_date'] = __('payroll.import.required');
            }

            foreach (['amount', 'rate', 'total_amount'] as $column) {
                if (filled($row[$column] ?? null) && ! preg_match('/^\d+(\.\d{1,4})?$/', trim($row[$column]))) {
                    $rowErrors[$column] = __('payroll.import.invalid_number');
                }
            }

            $data = [
                'salary_component_id' => $component?->id,
                'target_type' => PayEntryTarget::Employee->value,
                'target_id' => $employee?->id,
                'calculation' => $calculation?->value,
                'amount' => $row['amount'] ?? null ?: null,
                'rate' => $row['rate'] ?? null ?: null,
                'recurrence' => $recurrence?->value,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null ?: null,
                'total_amount' => $row['total_amount'] ?? null ?: null,
                'instalment_count' => $row['instalment_count'] ?? null ?: null,
                'reason' => $row['reason'] ?? null ?: null,
            ];

            if ($rowErrors === []) {
                $rowErrors = $this->dryRun($kind, $data);
            }

            foreach ($rowErrors as $column => $message) {
                $errors[] = ['line' => $line, 'column' => $column, 'message' => $message];
            }

            if ($rowErrors === []) {
                $prepared[$line] = $data;
            }
        }

        return ['total' => count($rows), 'valid' => count($prepared), 'errors' => $errors, 'rows' => $prepared];
    }

    /**
     * Import the analysed file (all valid rows or nothing when any row fails).
     *
     * @return array{total: int, valid: int, errors: list<array{line: int, column: string, message: string}>, rows: array<int, array<string, mixed>>, created: int, batch: ?PayEntryBatch}
     */
    public function import(string $path, string $extension, PayComponentKind $kind, User $user, ?string $fileName = null): array
    {
        $report = $this->analyse($path, $extension, $kind);

        if ($report['errors'] !== [] || $report['rows'] === []) {
            return [...$report, 'created' => 0, 'batch' => null];
        }

        $batch = DB::transaction(function () use ($report, $kind, $user, $fileName): PayEntryBatch {
            $batch = PayEntryBatch::create([
                'source' => 'import', 'kind' => $kind, 'entries_count' => count($report['rows']), 'file_name' => $fileName, 'created_by' => $user->id,
            ]);

            foreach ($report['rows'] as $data) {
                $this->entries->create($kind, $data, $user, batch: $batch);
            }

            $this->audit->log('payroll.entries_imported', null, meta: ['batch' => $batch->id, 'kind' => $kind->value, 'count' => count($report['rows'])], actor: $user);

            return $batch;
        });

        return [...$report, 'created' => count($report['rows']), 'batch' => $batch];
    }

    /**
     * The entry rules (amount, dates, instalments, reason) without saving: run create() in a rolled-back transaction.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function dryRun(PayComponentKind $kind, array $data): array
    {
        DB::beginTransaction();

        try {
            $this->entries->create($kind, $data, $this->dryRunUser());

            return [];
        } catch (ValidationException $exception) {
            return array_map(fn (array $messages): string => $messages[0], $exception->errors());
        } finally {
            DB::rollBack();
        }
    }

    private function dryRunUser(): User
    {
        return auth()->user() instanceof User ? auth()->user() : User::query()->firstOrFail();
    }

    private function isDate(string $value): bool
    {
        try {
            CarbonImmutable::createFromFormat('Y-m-d', trim($value));

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    private function folder(User $user): string
    {
        return 'imports/payroll-'.$user->getKey();
    }
}
