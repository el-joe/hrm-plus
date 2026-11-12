<?php

namespace App\Services\HrRequests;

use App\Actions\Employees\SaveEmployeeBankAccount;
use App\Actions\Employees\UpdateEmployee;
use App\Enums\MaritalStatus;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeEmergencyContact;
use App\Models\User;
use App\Support\SensitiveValue;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The protected profile data an employee may ask HR to change (profile change requests, Phase 8b), with
 * the same validation as the admin employee forms, and how an approved change is applied — always through
 * the employee actions (UpdateEmployee for personal data and the primary emergency contact,
 * SaveEmployeeBankAccount for the primary bank account), so the audit log and history stay complete.
 *
 * Groups decide who may apply a change and whether its values are masked:
 *   personal / emergency → employees.update; sensitive → employee_sensitive.update (view: .view);
 *   bank → employee_financials.update (view: employee_financials.view).
 */
class ProfileChangeFields
{
    public const GROUP_PERSONAL = 'personal';

    public const GROUP_SENSITIVE = 'sensitive';

    public const GROUP_BANK = 'bank';

    public const GROUP_EMERGENCY = 'emergency';

    /**
     * field => [group, employee column / bank column / contact column].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const FIELDS = [
        'phone' => [self::GROUP_PERSONAL, 'phone'],
        'alternate_phone' => [self::GROUP_PERSONAL, 'alternate_phone'],
        'personal_email' => [self::GROUP_PERSONAL, 'personal_email'],
        'address' => [self::GROUP_PERSONAL, 'address'],
        'city' => [self::GROUP_PERSONAL, 'city'],
        'country_code' => [self::GROUP_PERSONAL, 'country_code'],
        'marital_status' => [self::GROUP_PERSONAL, 'marital_status'],
        'nationality' => [self::GROUP_PERSONAL, 'nationality'],
        'passport_expiry_date' => [self::GROUP_PERSONAL, 'passport_expiry_date'],
        'national_id' => [self::GROUP_SENSITIVE, 'national_id'],
        'passport_number' => [self::GROUP_SENSITIVE, 'passport_number'],
        'bank_name' => [self::GROUP_BANK, 'bank_name'],
        'bank_account_holder' => [self::GROUP_BANK, 'account_holder'],
        'bank_account_number' => [self::GROUP_BANK, 'account_number'],
        'bank_iban' => [self::GROUP_BANK, 'iban'],
        'bank_swift_code' => [self::GROUP_BANK, 'swift_code'],
        'emergency_name' => [self::GROUP_EMERGENCY, 'name'],
        'emergency_relationship' => [self::GROUP_EMERGENCY, 'relationship'],
        'emergency_phone' => [self::GROUP_EMERGENCY, 'phone'],
    ];

    public function __construct(
        private readonly UpdateEmployee $updateEmployee,
        private readonly SaveEmployeeBankAccount $saveBankAccount,
    ) {}

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::FIELDS);
    }

    public function group(string $field): string
    {
        return self::FIELDS[$field][0];
    }

    public function label(string $field): string
    {
        return __('hr_requests.profile_fields.'.$field);
    }

    public function isMasked(string $field): bool
    {
        return in_array($this->group($field), [self::GROUP_SENSITIVE, self::GROUP_BANK], true);
    }

    /**
     * Validation rules of the proposed values (input "changes[field]"), as on the admin employee forms.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(Employee $employee): array
    {
        $phone = ['string', 'max:30', 'regex:/^[0-9+()\s-]+$/'];
        $perField = [
            'phone' => $phone,
            'alternate_phone' => $phone,
            'personal_email' => ['email', 'max:255'],
            'address' => ['string', 'max:500'],
            'city' => ['string', 'max:100'],
            'country_code' => ['string', 'size:2', 'alpha'],
            'marital_status' => [Rule::enum(MaritalStatus::class)],
            'nationality' => ['string', 'size:2', 'alpha'],
            'passport_expiry_date' => ['date'],
            'national_id' => ['string', 'max:30', 'regex:/^[A-Z0-9]+$/', function (string $attribute, mixed $value, \Closure $fail) use ($employee): void {
                if (Employee::query()->withoutGlobalScopes()->where('national_id_hash', SensitiveValue::hash((string) $value))->whereKeyNot($employee->id)->exists()) {
                    $fail(__('validation.unique', ['attribute' => $this->label('national_id')]));
                }
            }],
            'passport_number' => ['string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            'bank_name' => ['string', 'max:150'],
            'bank_account_holder' => ['string', 'max:150'],
            'bank_account_number' => ['string', 'max:34', 'regex:/^[0-9A-Za-z\s-]+$/'],
            'bank_iban' => ['string', 'between:15,34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/'],
            'bank_swift_code' => ['string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
            'emergency_name' => ['string', 'max:150'],
            'emergency_relationship' => ['string', 'max:50'],
            'emergency_phone' => $phone,
        ];

        $rules = ['changes' => ['required', 'array', 'min:1'], 'changes.*' => ['nullable']];

        foreach ($perField as $field => $fieldRules) {
            $rules['changes.'.$field] = ['sometimes', 'required', ...$fieldRules];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->keys())->mapWithKeys(fn (string $field): array => ['changes.'.$field => $this->label($field)])->all();
    }

    /**
     * Normalise a proposed value like the admin forms do (identity numbers / IBAN upper-case without spaces,
     * country codes upper-case, e-mails lower-case).
     */
    public function normalise(string $field, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return match ($field) {
            'national_id', 'passport_number', 'bank_iban', 'bank_swift_code' => SensitiveValue::normalise($value),
            'country_code', 'nationality' => mb_strtoupper($value),
            'personal_email' => mb_strtolower($value),
            default => $value,
        };
    }

    /**
     * Keep only known fields, normalised, dropping the ones equal to the current value.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, string>
     */
    public function prepare(Employee $employee, array $changes): array
    {
        $prepared = [];

        foreach ($changes as $field => $value) {
            if (! array_key_exists((string) $field, self::FIELDS) || ! filled($value) || ! is_scalar($value)) {
                continue;
            }

            $value = (string) $this->normalise((string) $field, (string) $value);

            if ($value !== (string) $this->current($employee, (string) $field)) {
                $prepared[(string) $field] = $value;
            }
        }

        return $prepared;
    }

    /**
     * The value the employee has today (raw — mask before showing it).
     */
    public function current(Employee $employee, string $field): ?string
    {
        [$group, $column] = self::FIELDS[$field];

        $value = match ($group) {
            self::GROUP_BANK => $this->primaryAccount($employee)?->{$column},
            self::GROUP_EMERGENCY => $this->primaryContact($employee)?->{$column},
            default => $employee->{$column},
        };

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * A value as shown on screens: masked for identity / bank fields unless the viewer may see them,
     * enum values as their label.
     */
    public function display(string $field, ?string $value, bool $reveal = false): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($this->isMasked($field) && ! $reveal) {
            return (string) SensitiveValue::mask($value);
        }

        if ($field === 'marital_status') {
            return MaritalStatus::tryFrom($value)?->label() ?? $value;
        }

        return $value;
    }

    /**
     * Whether the admin may see the unmasked values of a group.
     */
    public function canReveal(User $user, Employee $employee, string $group): bool
    {
        return match ($group) {
            self::GROUP_SENSITIVE => $user->can('viewSensitive', $employee),
            self::GROUP_BANK => $user->can('viewFinancial', $employee),
            default => true,
        };
    }

    /**
     * Whether the admin may apply a change of this group.
     */
    public function canApply(User $user, Employee $employee, string $group): bool
    {
        return match ($group) {
            self::GROUP_SENSITIVE => $user->can('update', $employee) && $user->can('updateSensitive', $employee),
            self::GROUP_BANK => $user->can('update', $employee) && $user->can('updateFinancial', $employee),
            default => $user->can('update', $employee),
        };
    }

    /**
     * Apply approved values through the employee actions. Call inside a transaction.
     *
     * @param  array<string, string>  $values  field => new value
     */
    public function apply(Employee $employee, array $values): void
    {
        $personal = [];
        $bank = [];
        $emergency = [];

        foreach ($values as $field => $value) {
            [$group, $column] = self::FIELDS[$field];

            match ($group) {
                self::GROUP_BANK => $bank[$column] = $value,
                self::GROUP_EMERGENCY => $emergency[$column] = $value,
                default => $personal[$column] = $value,
            };
        }

        if ($emergency !== []) {
            $personal['emergency_contacts'] = $this->contactsWith($employee, $emergency);
        }

        if ($personal !== []) {
            $this->updateEmployee->handle($employee, $personal);
        }

        if ($bank !== []) {
            $account = $this->primaryAccount($employee) ?? new EmployeeBankAccount;

            if (! $account->exists) {
                $bank += ['account_holder' => $employee->localized('name', 'en') ?? $employee->name, 'bank_name' => $bank['bank_name'] ?? '—'];
            }

            $this->saveBankAccount->handle($employee, $account, $bank, makePrimary: true);
        }
    }

    /**
     * The emergency contact list with the first (primary) contact replaced by the new values.
     *
     * @param  array<string, string>  $values
     * @return list<array<string, ?string>>
     */
    private function contactsWith(Employee $employee, array $values): array
    {
        $contacts = $employee->emergencyContacts()->orderBy('sort_order')->get()
            ->map(fn (EmployeeEmergencyContact $contact): array => [
                'name' => $contact->name, 'relationship' => $contact->relationship, 'phone' => $contact->phone, 'alternate_phone' => $contact->alternate_phone,
            ])->values()->all();

        $first = $contacts[0] ?? ['name' => null, 'relationship' => null, 'phone' => null, 'alternate_phone' => null];
        $contacts[0] = [...$first, ...$values];

        return $contacts;
    }

    private function primaryAccount(Employee $employee): ?EmployeeBankAccount
    {
        return $employee->bankAccounts()->orderByDesc('is_primary')->orderBy('id')->first();
    }

    private function primaryContact(Employee $employee): ?EmployeeEmergencyContact
    {
        return $employee->emergencyContacts()->orderBy('sort_order')->orderBy('id')->first();
    }

    /**
     * Fields grouped for forms, with the employee's current values (masked where needed).
     *
     * @return Collection<string, list<array{field: string, label: string, current: string, masked: bool}>>
     */
    public function formFields(Employee $employee): Collection
    {
        return collect($this->keys())->map(fn (string $field): array => [
            'field' => $field,
            'group' => $this->group($field),
            'label' => $this->label($field),
            'current' => $this->display($field, $this->current($employee, $field)),
            'masked' => $this->isMasked($field),
        ])->groupBy('group')->map(fn (Collection $rows): array => $rows->values()->all());
    }
}
