<?php

namespace App\Http\Requests\Admin\Disciplinary;

use App\Models\DisciplinaryCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Report an incident (disciplinary.create). The employee must be inside the
 * reporter's data scope and not the reporter (checked by the action).
 */
class ReportIncidentRequest extends FormRequest
{
    use EvidenceRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', DisciplinaryCase::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'violation_type_id' => ['required', 'integer'],
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            ...$this->evidenceRules(),
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function evidenceFiles(): array
    {
        return array_values(array_filter((array) $this->file('evidence', [])));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('disciplinary.fields');
    }
}
