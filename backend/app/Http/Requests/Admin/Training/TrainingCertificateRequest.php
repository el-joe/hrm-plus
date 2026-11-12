<?php

namespace App\Http\Requests\Admin\Training;

use App\Enums\CertificateType;
use App\Models\Employee;
use App\Models\TrainingCertificate;
use App\Models\TrainingCourse;
use App\Services\Training\TrainingFileStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A certificate recorded by HR (create: for an employee in scope; update: the record).
 */
class TrainingCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certificate = $this->route('training_certificate');

        return $certificate instanceof TrainingCertificate ? $this->user()->can('update', $certificate) : $this->user()->can('create', TrainingCertificate::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = ! $this->route('training_certificate') instanceof TrainingCertificate;

        return [
            'employee_id' => [$creating ? 'required' : 'prohibited', 'integer'],
            'type' => ['required', Rule::enum(CertificateType::class)],
            'name' => ['required', 'string', 'max:200'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'training_course_id' => ['nullable', 'integer', Rule::exists(TrainingCourse::class, 'id')],
            'file' => ['nullable', ...TrainingFileStore::rules()],
        ];
    }

    public function employee(): Employee
    {
        // Scoped query: only employees the admin can see.
        $employee = Employee::query()->find($this->validated('employee_id'));
        abort_if($employee === null, 422, __('training.validation.employee_out_of_scope'));

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    public function certificateData(): array
    {
        return collect($this->validated())->except(['employee_id', 'file'])->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('training.fields');
    }
}
