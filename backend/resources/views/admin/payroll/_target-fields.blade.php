{{--
    "Applies to" select plus one record select per target type (only the chosen one is shown with JS;
    without JS all are visible and the server uses the one matching the type).
    Variables: $scopes (enum cases), $targetOptions (employees, groups, departments, branches, grades),
    $typeName (field name of the type), $selected (current type value), $values (array of preselected ids by field),
    optional $prefix (id prefix).
--}}
@php($prefix = $prefix ?? 'target')
<x-form.select :name="$typeName" :id="$prefix.'-type'" :label="__('payroll.fields.target_type')" required data-switch>
    @foreach ($scopes as $scope)
        <option value="{{ $scope->value }}" @selected(old($typeName, $selected) === $scope->value)>{{ $scope->label() }}</option>
    @endforeach
</x-form.select>
@foreach ([
    'employee' => ['employee_id', $targetOptions['employees'], fn ($row) => $row->label()],
    'employee_group' => ['employee_group_id', $targetOptions['groups'], fn ($row) => $row->code.' · '.$row->name],
    'job_grade' => ['job_grade_id', $targetOptions['grades'], fn ($row) => $row->code.' · '.$row->name],
    'department' => ['department_id', $targetOptions['departments'], fn ($row) => $row->code.' · '.$row->name],
    'branch' => ['branch_id', $targetOptions['branches'], fn ($row) => $row->code.' · '.$row->name],
] as $type => [$field, $rows, $label])
    @continue(! collect($scopes)->contains(fn ($scope) => $scope->value === $type))
    <div data-show-for="{{ $type }}">
        <x-form.select :name="$field" :id="$prefix.'-'.$field" :label="__('payroll.fields.'.$field)">
            <option value="">—</option>
            @foreach ($rows as $row)
                <option value="{{ $row->id }}" @selected((string) old($field, $values[$field] ?? '') === (string) $row->id)>{{ $label($row) }}</option>
            @endforeach
        </x-form.select>
    </div>
@endforeach
