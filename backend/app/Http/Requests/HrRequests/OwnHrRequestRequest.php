<?php

namespace App\Http\Requests\HrRequests;

use App\Models\Employee;
use App\Services\Employees\PortalProfile;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An employee files their own HR request (portal and API). Employees choose low / normal / high priority.
 */
class OwnHrRequestRequest extends FormRequest
{
    use HrRequestRules;

    public function authorize(): bool
    {
        return $this->employee() !== null;
    }

    public function employee(): ?Employee
    {
        return app(PortalProfile::class)->employeeFor($this->user());
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
        return $this->requestRules($this->employee(), ['low', 'normal', 'high']);
    }
}
