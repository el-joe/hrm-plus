<?php

namespace App\Actions\HrRequests;

use App\Enums\HrProfileChangeStatus;
use App\Enums\HrRequestPriority;
use App\Enums\HrRequestSource;
use App\Enums\HrRequestStatus;
use App\Exceptions\ApprovalException;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Models\HrRequestCategory;
use App\Models\User;
use App\Notifications\HrRequests\HrRequestNotification;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\HrRequests\HrRequestHistory;
use App\Services\HrRequests\HrRequestRecipients;
use App\Services\HrRequests\ProfileChangeFields;
use App\Support\ReferenceNumbers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Files an HR request for an employee (portal, mobile app, or HR on the employee's behalf).
 *
 * - the category must be active; the attachment rule and the category's extra fields are enforced;
 * - a profile change request keeps only the fields that really change (one open profile change request
 *   per employee at a time);
 * - categories that need approval start "pending_approval" (workflow "hr_request"), others are opened at
 *   once (SLA due time, default assignee);
 * - the employee gets a confirmation; approvers / handlers are told.
 */
class SubmitHrRequest
{
    public function __construct(
        private readonly ReferenceNumbers $references,
        private readonly ManageHrRequest $manage,
        private readonly HrRequestHistory $history,
        private readonly HrRequestRecipients $recipients,
        private readonly ProfileChangeFields $profileFields,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{subject?: ?string, description?: ?string, priority?: ?string, letter_language?: ?string, fields?: array<string, mixed>, changes?: array<string, mixed>}  $data  validated input
     * @param  list<UploadedFile>  $files
     *
     * @throws ValidationException
     */
    public function handle(Employee $employee, HrRequestCategory $category, array $data, User $submitter, HrRequestSource $source, array $files = []): HrRequest
    {
        if (! $category->is_active) {
            throw ValidationException::withMessages(['hr_request_category_id' => __('hr_requests.validation.category')]);
        }

        if ($employee->isTerminated()) {
            throw ValidationException::withMessages(['employee_id' => __('hr_requests.validation.employee')]);
        }

        if ($source !== HrRequestSource::Admin && ! setting('hr_requests.employee_requests_enabled')) {
            throw ValidationException::withMessages(['hr_request_category_id' => __('hr_requests.validation.disabled')]);
        }

        if ($category->requires_attachment && $files === []) {
            throw ValidationException::withMessages(['attachments' => __('hr_requests.validation.attachment_required')]);
        }

        $changes = $category->isProfileChange() ? $this->profileChanges($employee, (array) ($data['changes'] ?? [])) : [];
        $fieldValues = $this->fieldValues($category, (array) ($data['fields'] ?? []));
        $priority = HrRequestPriority::tryFrom((string) ($data['priority'] ?? '')) ?? $category->default_priority;

        if ($source !== HrRequestSource::Admin && $priority === HrRequestPriority::Urgent) {
            $priority = HrRequestPriority::High;
        }

        $request = DB::transaction(function () use ($employee, $category, $data, $submitter, $source, $files, $changes, $fieldValues, $priority): HrRequest {
            $request = HrRequest::create([
                'reference' => $this->references->next('HRQ'),
                'employee_id' => $employee->id,
                'hr_request_category_id' => $category->id,
                'branch_id' => $employee->branch_id,
                'department_id' => $employee->department_id,
                'subject' => filled($data['subject'] ?? null) ? $data['subject'] : $category->localized('name', $employee->user?->locale),
                'description' => $data['description'] ?? null,
                'priority' => $priority,
                'status' => $category->requires_approval ? HrRequestStatus::PendingApproval : HrRequestStatus::Open,
                'submitted_by' => $submitter->id,
                'source' => $source,
                'letter_language' => $category->isLetter() ? (in_array($data['letter_language'] ?? null, ['ar', 'en'], true) ? $data['letter_language'] : 'ar') : null,
            ]);

            foreach ($fieldValues as $value) {
                $request->fieldValues()->create($value);
            }

            foreach ($changes as $field => $value) {
                $request->profileChanges()->create([
                    'field' => $field, 'old_value' => $this->profileFields->current($employee, $field), 'new_value' => $value, 'status' => HrProfileChangeStatus::Pending,
                ]);
            }

            $this->manage->storeFiles($request, $files, $submitter);
            $this->history->record($request, 'submitted', $submitter, null, $request->status->value);
            $this->audit->log('hr_request.submitted', $request, meta: [
                'category' => $category->code, 'source' => $source->value, 'files' => count($files), 'changes' => array_keys($changes),
            ], actor: $submitter);

            if ($category->requires_approval) {
                try {
                    $this->approvals->submit($request, HrRequest::WORKFLOW, $submitter);
                } catch (ApprovalException $exception) {
                    throw ValidationException::withMessages(['hr_request_category_id' => $exception->getMessage()]);
                }
            } else {
                $this->manage->open($request, $submitter->isAdmin() && $submitter->employee_id !== $employee->id ? $submitter : null);
            }

            return $request;
        });

        $request->refresh();

        foreach ($this->recipients->employeeUsers($request) as $user) {
            $user->notify(new HrRequestNotification($request, 'received'));
        }

        if ($request->status === HrRequestStatus::PendingApproval) {
            foreach ($this->manage->approvers($request) as $approver) {
                $approver->notify(new HrRequestNotification($request, 'awaiting_approval'));
            }
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private function profileChanges(Employee $employee, array $input): array
    {
        $changes = $this->profileFields->prepare($employee, $input);

        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => __('hr_requests.validation.no_changes')]);
        }

        $hasOpen = HrRequest::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->open()
            ->whereHas('profileChanges', fn ($query) => $query->where('status', HrProfileChangeStatus::Pending))->exists();

        if ($hasOpen) {
            throw ValidationException::withMessages(['changes' => __('hr_requests.validation.profile_change_open')]);
        }

        $bank = array_filter($changes, fn (string $field): bool => str_starts_with($field, 'bank_'), ARRAY_FILTER_USE_KEY);

        if ($bank !== [] && $employee->bankAccounts()->doesntExist()
            && (! isset($changes['bank_name'], $changes['bank_account_holder']) || (! isset($changes['bank_account_number']) && ! isset($changes['bank_iban'])))) {
            throw ValidationException::withMessages(['changes' => __('hr_requests.validation.new_bank_account')]);
        }

        $emergency = array_filter($changes, fn (string $field): bool => str_starts_with($field, 'emergency_'), ARRAY_FILTER_USE_KEY);

        if ($emergency !== [] && $employee->emergencyContacts()->doesntExist()
            && ! isset($changes['emergency_name'], $changes['emergency_relationship'], $changes['emergency_phone'])) {
            throw ValidationException::withMessages(['changes' => __('hr_requests.validation.new_emergency_contact')]);
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $input  field key => value
     * @return list<array{hr_request_category_field_id: int, label_ar: string, label_en: string, value: ?string}>
     *
     * @throws ValidationException
     */
    private function fieldValues(HrRequestCategory $category, array $input): array
    {
        $values = [];
        $errors = [];

        foreach ($category->fields as $field) {
            $value = $input[$field->key] ?? null;
            $validator = validator(['value' => $value], ['value' => $field->rules()], [], ['value' => $field->label()]);

            if ($validator->fails()) {
                $errors['fields.'.$field->key] = $validator->errors()->get('value');

                continue;
            }

            if (filled($value)) {
                $values[] = ['hr_request_category_field_id' => $field->id, 'label_ar' => $field->label_ar, 'label_en' => $field->label_en, 'value' => (string) $value];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }
}
