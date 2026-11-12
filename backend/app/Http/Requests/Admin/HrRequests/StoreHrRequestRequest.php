<?php

namespace App\Http\Requests\Admin\HrRequests;

use App\Http\Requests\HrRequests\HrRequestRules;
use App\Models\Employee;
use App\Models\HrRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * HR files a request on an employee's behalf (hr_requests.create; the employee must be in the data scope
 * and not the admin themselves).
 */
class StoreHrRequestRequest extends FormRequest
{
    use HrRequestRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', HrRequest::class);
    }

    public function employee(): ?Employee
    {
        $id = $this->input('employee_id');

        return is_numeric($id) ? Employee::query()->employed()->whereKeyNot($this->user()->employee_id ?? 0)->find((int) $id) : null;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareRequestInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->employee() === null) {
                    $fail(__('hr_requests.validation.employee'));
                }
            }],
            ...$this->requestRules($this->employee(), ['low', 'normal', 'high', 'urgent']),
        ];
    }
}
