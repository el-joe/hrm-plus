<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An employee's own leave request (portal and mobile API); the controller
 * resolves the employee from the signed-in account.
 */
class OwnLeaveRequest extends FormRequest
{
    use LeaveRequestRules;

    public function authorize(): bool
    {
        return $this->user()?->employee_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->leaveRules();
    }
}
