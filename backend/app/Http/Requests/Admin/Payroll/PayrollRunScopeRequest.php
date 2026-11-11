<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Enums\PayrollRunType;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Settlement scope of a run (wizard preview, creation, scope change): period,
 * type and the filters — branches, departments, teams, positions, employee
 * groups (AND across, OR within), individual employees to add, employees to
 * leave out, or the whole company. Ids outside the admin's data scope simply
 * match nobody (the population query is scoped).
 */
class PayrollRunScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $run = $this->route('payroll_run');

        return $run instanceof PayrollRun ? $this->user()->can('update', $run) : $this->user()->can('create', PayrollRun::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $list = ['nullable', 'array', 'max:500'];
        $creating = ! $this->route('payroll_run') instanceof PayrollRun;

        return [
            'payroll_period_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('payroll_periods', 'id')],
            'type' => [$creating ? 'required' : 'prohibited', Rule::in(array_map(fn (PayrollRunType $type): string => $type->value, PayrollRunType::creatable()))],
            'name' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'scope' => ['required', 'array'],
            'scope.all' => ['nullable', 'boolean'],
            'scope.branch_ids' => $list,
            'scope.branch_ids.*' => ['integer', Rule::exists('branches', 'id')],
            'scope.department_ids' => $list,
            'scope.department_ids.*' => ['integer', Rule::exists('departments', 'id')],
            'scope.team_ids' => $list,
            'scope.team_ids.*' => ['integer', Rule::exists('teams', 'id')],
            'scope.position_ids' => $list,
            'scope.position_ids.*' => ['integer', Rule::exists('positions', 'id')],
            'scope.employee_group_ids' => $list,
            'scope.employee_group_ids.*' => ['integer', Rule::exists('employee_groups', 'id')],
            'scope.employee_ids' => $list,
            'scope.employee_ids.*' => ['integer', Rule::exists('employees', 'id')],
            'scope.exclude_employee_ids' => $list,
            'scope.exclude_employee_ids.*' => ['integer', Rule::exists('employees', 'id')],
        ];
    }

    public function period(): PayrollPeriod
    {
        return PayrollPeriod::query()->findOrFail($this->validated('payroll_period_id'));
    }

    public function type(): PayrollRunType
    {
        return PayrollRunType::from($this->validated('type'));
    }

    /**
     * @return array<string, mixed>
     */
    public function scope(): array
    {
        return (array) $this->validated('scope');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll_runs.runs.fields');
    }
}
