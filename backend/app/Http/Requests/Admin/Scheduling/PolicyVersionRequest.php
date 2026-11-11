<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Models\AttendancePolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new version of a policy's rules; it must start after the latest version.
 */
class PolicyVersionRequest extends FormRequest
{
    use PolicyRuleRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->policy());
    }

    public function policy(): AttendancePolicy
    {
        return $this->route('attendance_policy');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $latest = $this->policy()->versions()->first()?->effective_from?->format('Y-m-d');

        return [
            ...$this->ruleRules(),
            'effective_from' => array_values(array_filter(['required', 'date_format:Y-m-d', $latest ? 'after:'.$latest : null])),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $latest = $this->policy()->versions()->first()?->effective_from?->format('Y-m-d');

        return ['effective_from.after' => __('schedules.policies.effective_after', ['date' => $latest])];
    }
}
