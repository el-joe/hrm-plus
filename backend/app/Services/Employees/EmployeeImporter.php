<?php

namespace App\Services\Employees;

use App\Actions\Employees\CreateEmployee;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\JobTitle;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use App\Support\SensitiveValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Employee import from Excel (.xlsx) or CSV, in two steps:
 *
 * 1. upload + dry run: the file is kept on the private disk and every row is
 *    validated exactly like the create form (codes resolved inside the
 *    admin's data scope, placement rules, headcount including the rows above
 *    it, duplicates inside the file). Nothing is written.
 * 2. confirm: the same file is validated again and, only if no row has an
 *    error, all rows are created in ONE transaction (all or nothing).
 *
 * Columns are listed in columns(); codes refer to the structure records
 * (branch_code => branches.code ...), manager_number to an existing employee.
 */
class EmployeeImporter
{
    public function __construct(
        private readonly Spreadsheet $spreadsheet,
        private readonly EmployeePlacement $placement,
        private readonly PositionCapacity $capacity,
        private readonly CreateEmployee $createEmployee,
    ) {}

    /**
     * @return list<string>
     */
    public function columns(User $user): array
    {
        return array_values(array_filter([
            'employee_number', 'name_ar', 'name_en', 'email', 'phone', 'gender', 'marital_status', 'date_of_birth', 'nationality',
            $user->can('employee_sensitive.update') ? 'national_id' : null,
            'hire_date', 'probation_end_date', 'branch_code', 'department_code', 'team_code', 'position_code', 'job_title_code',
            'job_grade_code', 'employment_type_code', 'manager_number',
            $user->can('employee_financials.update') ? 'basic_salary' : null,
        ]));
    }

    /**
     * Keep the upload for the confirmation step; returns its token.
     */
    public function stash(UploadedFile $file, User $user): string
    {
        $token = Str::uuid()->toString();
        $extension = strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';

        app(EmployeeFileStore::class)->disk()->putFileAs($this->folder($user), $file, $token.'.'.$extension);

        return $token;
    }

    /**
     * Absolute path of a stashed upload of this user, or null when unknown or expired.
     *
     * @return array{path: string, extension: string}|null
     */
    public function stashed(string $token, User $user): ?array
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        $disk = app(EmployeeFileStore::class)->disk();

        foreach (['xlsx', 'csv'] as $extension) {
            $relative = $this->folder($user).'/'.$token.'.'.$extension;

            if ($disk->exists($relative) && $disk->lastModified($relative) >= now()->subMinutes(config('employees.import_ttl_minutes'))->getTimestamp()) {
                return ['path' => $disk->path($relative), 'extension' => $extension];
            }
        }

        return null;
    }

    public function discard(string $token, User $user): void
    {
        $disk = app(EmployeeFileStore::class)->disk();

        foreach (['xlsx', 'csv'] as $extension) {
            $disk->delete($this->folder($user).'/'.$token.'.'.$extension);
        }
    }

    /**
     * Validate every row without writing anything.
     *
     * @return array{total: int, valid: int, errors: list<array{line: int, column: string, message: string}>, rows: array<int, array<string, mixed>>}
     */
    public function analyse(string $path, string $extension, User $user): array
    {
        $rows = $this->spreadsheet->read($path, $extension, (int) config('employees.import_max_rows'));
        $columns = $this->columns($user);
        $errors = [];
        $prepared = [];
        $seen = ['employee_number' => [], 'email' => [], 'national_id' => []];
        $seatsTaken = [];

        if ($rows === []) {
            return ['total' => 0, 'valid' => 0, 'errors' => [['line' => 1, 'column' => '', 'message' => __('employees.import.empty')]], 'rows' => []];
        }

        $missing = array_diff(['name_ar', 'name_en', 'hire_date', 'branch_code', 'department_code', 'employment_type_code'], array_keys(reset($rows)));

        if ($missing !== []) {
            return ['total' => count($rows), 'valid' => 0, 'errors' => [[
                'line' => 1, 'column' => implode(', ', $missing), 'message' => __('employees.import.missing_columns'),
            ]], 'rows' => []];
        }

        foreach ($rows as $line => $row) {
            if (isset($row['__too_many__'])) {
                $errors[] = ['line' => $line, 'column' => '', 'message' => __('employees.import.too_many_rows', ['max' => config('employees.import_max_rows')])];

                break;
            }

            $row = array_intersect_key($row, array_flip($columns));
            [$data, $rowErrors] = $this->prepareRow($row);

            foreach (['employee_number', 'email', 'national_id'] as $unique) {
                $value = $unique === 'national_id' ? SensitiveValue::hash($data[$unique] ?? null) : Str::lower((string) ($data[$unique] ?? ''));

                if (filled($value)) {
                    if (isset($seen[$unique][$value])) {
                        $rowErrors[] = [$unique, __('employees.import.duplicate_in_file', ['line' => $seen[$unique][$value]])];
                    }

                    $seen[$unique][$value] = $line;
                }
            }

            if ($rowErrors === [] && filled($data['position_id'] ?? null)) {
                $position = Position::query()->withoutGlobalScopes()->find($data['position_id']);
                $taken = ($seatsTaken[$position->id] ?? 0) + $this->capacity->occupied($position);

                if ($taken >= $position->headcount) {
                    $rowErrors[] = ['position_code', __('employees.validation.position_full', ['position' => $position->name, 'headcount' => $position->headcount])];
                } else {
                    $seatsTaken[$position->id] = ($seatsTaken[$position->id] ?? 0) + 1;
                }
            }

            foreach ($rowErrors as [$column, $message]) {
                $errors[] = ['line' => $line, 'column' => $column, 'message' => $message];
            }

            if ($rowErrors === []) {
                $prepared[$line] = $data;
            }
        }

        return ['total' => count($rows), 'valid' => count($prepared), 'errors' => $errors, 'rows' => $prepared];
    }

    /**
     * Validate again and create every row, all or nothing.
     *
     * @return array{total: int, valid: int, errors: list<array{line: int, column: string, message: string}>, created: int}
     */
    public function import(string $path, string $extension, User $user): array
    {
        $report = $this->analyse($path, $extension, $user);

        if ($report['errors'] !== [] || $report['rows'] === []) {
            return [...$report, 'created' => 0];
        }

        DB::transaction(function () use ($report): void {
            // Rows with their own number first, so a generated number can never take one of them.
            $rows = collect($report['rows'])->sortBy(fn (array $data): int => filled($data['employee_number']) ? 0 : 1);

            foreach ($rows as $data) {
                $this->createEmployee->handle($data);
            }
        });

        return [...$report, 'created' => count($report['rows'])];
    }

    /**
     * Resolve codes and validate one row.
     *
     * @param  array<string, string>  $row
     * @return array{0: array<string, mixed>, 1: list<array{0: string, 1: string}>}
     */
    private function prepareRow(array $row): array
    {
        $row = array_map(fn (string $value): ?string => $value === '' ? null : $value, $row);
        $errors = [];

        $data = [
            'employee_number' => isset($row['employee_number']) ? mb_strtoupper($row['employee_number']) : null,
            'name_ar' => $row['name_ar'] ?? null,
            'name_en' => $row['name_en'] ?? null,
            'email' => isset($row['email']) ? mb_strtolower($row['email']) : null,
            'phone' => $row['phone'] ?? null,
            'gender' => isset($row['gender']) ? mb_strtolower($row['gender']) : null,
            'marital_status' => isset($row['marital_status']) ? mb_strtolower($row['marital_status']) : null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'nationality' => isset($row['nationality']) ? mb_strtoupper($row['nationality']) : null,
            'national_id' => SensitiveValue::normalise($row['national_id'] ?? null),
            'hire_date' => $row['hire_date'] ?? null,
            'probation_end_date' => $row['probation_end_date'] ?? null,
            'basic_salary' => $row['basic_salary'] ?? null,
        ];

        $codes = [
            'branch_id' => ['branch_code', Branch::class, true],
            'department_id' => ['department_code', Department::class, true],
            'team_id' => ['team_code', Team::class, false],
            'position_id' => ['position_code', Position::class, false],
            'job_title_id' => ['job_title_code', JobTitle::class, false],
            'job_grade_id' => ['job_grade_code', JobGrade::class, false],
            'employment_type_id' => ['employment_type_code', EmploymentType::class, true],
        ];

        foreach ($codes as $attribute => [$column, $model, $required]) {
            $data[$attribute] = null;
            $code = isset($row[$column]) ? mb_strtoupper($row[$column]) : null;

            if ($code === null) {
                if ($required) {
                    $errors[] = [$column, __('validation.required', ['attribute' => $column])];
                }

                continue;
            }

            /** @var class-string<Model> $model */
            $record = $model::query()->active()->where('code', $code)->first();

            if ($record === null) {
                $errors[] = [$column, __('employees.import.unknown_code', ['code' => $code])];
            } else {
                $data[$attribute] = $record->getKey();
            }
        }

        $data['manager_id'] = null;

        if (isset($row['manager_number'])) {
            $manager = Employee::query()->active()->where('employee_number', mb_strtoupper($row['manager_number']))->first();

            if ($manager === null) {
                $errors[] = ['manager_number', __('employees.import.unknown_manager', ['number' => $row['manager_number']])];
            } else {
                $data['manager_id'] = $manager->getKey();
            }
        }

        $validator = Validator::make($data, [
            'employee_number' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/', Rule::unique('employees', 'employee_number')],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:-14 years'],
            'nationality' => ['nullable', 'string', 'size:2', 'alpha'],
            'national_id' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/'],
            'hire_date' => ['required', 'date_format:Y-m-d', 'after:1950-01-01', 'before_or_equal:+1 year'],
            'probation_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:hire_date'],
            'basic_salary' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
        ], [], trans('employees.fields'));

        foreach ($validator->errors()->messages() as $field => $messages) {
            $errors[] = [$field, $messages[0]];
        }

        if (filled($data['national_id']) && Employee::query()->withoutGlobalScopes()->where('national_id_hash', SensitiveValue::hash($data['national_id']))->exists()) {
            $errors[] = ['national_id', __('validation.unique', ['attribute' => __('employees.fields.national_id')])];
        }

        if ($errors === []) {
            $placement = $this->placement->complete(array_intersect_key($data, array_flip(Employee::PLACEMENT_COLUMNS)));

            foreach ($this->placement->errors($placement, null, checkCapacity: false) as $field => $message) {
                $errors[] = [$field, $message];
            }
        }

        return [$data, $errors];
    }

    private function folder(User $user): string
    {
        return 'imports/'.$user->getKey();
    }
}
