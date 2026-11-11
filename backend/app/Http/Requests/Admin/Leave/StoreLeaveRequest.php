<?php

namespace App\Http\Requests\Admin\Leave;

use App\Http\Requests\Leave\LeaveRequestRules;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A leave request filed by an admin on an employee's behalf (leaves.create,
 * employee inside the data scope). record_as_approved needs leaves.manage.
 */
class StoreLeaveRequest extends FormRequest
{
    use LeaveRequestRules;

    public function authorize(): bool
    {
        $employee = $this->employee();

        return $employee !== null && $this->user()->can('createFor', [LeaveRequest::class, $employee])
            && (! $this->boolean('record_as_approved') || $this->user()->can('leaves.manage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'record_as_approved' => ['sometimes', 'boolean'],
            ...$this->leaveRules(),
        ];
    }

    public function employee(): ?Employee
    {
        return once(fn (): ?Employee => Employee::query()->employed()->find($this->integer('employee_id')));
    }
}
