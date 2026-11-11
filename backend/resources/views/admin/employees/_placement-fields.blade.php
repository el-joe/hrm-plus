{{--
    Job placement fields (create, transfer / promotion, rehire). Department, team, position and
    work location lists narrow to the chosen branch / department with resources/js/pages/employee-form.js
    (data-depends-on); without JavaScript every option stays available and the server checks the
    combination. Empty title / grade / cost centre / manager are filled from the position and department.
    Variables: $options (EmployeeFormOptions::form), $values (array of current values).
--}}
@php
    $value = fn (string $field) => (string) old($field, $values[$field] ?? '');
@endphp
<x-form.select name="branch_id" :label="__('employees.fields.branch_id')" required data-placement="branch_id">
    <option value="">—</option>
    @foreach ($options['branches'] as $branch)
        <option value="{{ $branch->id }}" @selected($value('branch_id') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
    @endforeach
</x-form.select>

<x-form.select name="department_id" :label="__('employees.fields.department_id')" required data-depends-on="branch_id" data-placement="department_id">
    <option value="">—</option>
    @foreach ($options['departments'] as $department)
        <option value="{{ $department->id }}" data-parent="{{ $department->branch_id }}" @selected($value('department_id') === (string) $department->id)>
            {{ $department->code }} · {{ $department->name }}{{ $department->branch ? '' : ' ('.__('structure.departments.company_wide').')' }}
        </option>
    @endforeach
</x-form.select>

<x-form.select name="position_id" :label="__('employees.fields.position_id')" data-depends-on="department_id" :help="__('employees.form.position_help')">
    <option value="">{{ __('employees.form.no_position') }}</option>
    @foreach ($options['positions'] as $position)
        <option value="{{ $position->id }}" data-parent="{{ $position->department_id }}" @selected($value('position_id') === (string) $position->id)>
            {{ $position->code }} · {{ $position->name }} ({{ $position->holders_count }}/{{ $position->headcount }})
        </option>
    @endforeach
</x-form.select>

<x-form.select name="team_id" :label="__('employees.fields.team_id')" data-depends-on="department_id">
    <option value="">{{ __('structure.common.not_set') }}</option>
    @foreach ($options['teams'] as $team)
        <option value="{{ $team->id }}" data-parent="{{ $team->department_id }}" @selected($value('team_id') === (string) $team->id)>{{ $team->code }} · {{ $team->name }}</option>
    @endforeach
</x-form.select>

<x-form.select name="job_title_id" :label="__('employees.fields.job_title_id')" :help="__('employees.form.from_position')">
    <option value="">{{ __('employees.form.use_default') }}</option>
    @foreach ($options['jobTitles'] as $title)
        <option value="{{ $title->id }}" @selected($value('job_title_id') === (string) $title->id)>{{ $title->code }} · {{ $title->name }}</option>
    @endforeach
</x-form.select>

<x-form.select name="job_grade_id" :label="__('employees.fields.job_grade_id')" :help="__('employees.form.from_position')">
    <option value="">{{ __('employees.form.use_default') }}</option>
    @foreach ($options['jobGrades'] as $grade)
        <option value="{{ $grade->id }}" @selected($value('job_grade_id') === (string) $grade->id)>{{ $grade->code }} · {{ $grade->name }}</option>
    @endforeach
</x-form.select>

<x-form.select name="employment_type_id" :label="__('employees.fields.employment_type_id')" required>
    <option value="">—</option>
    @foreach ($options['employmentTypes'] as $type)
        <option value="{{ $type->id }}" @selected($value('employment_type_id') === (string) $type->id)>{{ $type->name }}</option>
    @endforeach
</x-form.select>

<x-form.select name="cost_center_id" :label="__('employees.fields.cost_center_id')" :help="__('employees.form.from_department')">
    <option value="">{{ __('employees.form.use_default') }}</option>
    @foreach ($options['costCenters'] as $center)
        <option value="{{ $center->id }}" @selected($value('cost_center_id') === (string) $center->id)>{{ $center->code }} · {{ $center->name }}</option>
    @endforeach
</x-form.select>

<x-form.select name="manager_id" :label="__('employees.fields.manager_id')" :help="__('employees.form.manager_help')">
    <option value="">{{ __('employees.form.manager_default') }}</option>
    @foreach ($options['managers'] as $manager)
        <option value="{{ $manager->id }}" @selected($value('manager_id') === (string) $manager->id)>{{ $manager->label() }}</option>
    @endforeach
</x-form.select>

<x-form.select name="work_location_id" :label="__('employees.fields.work_location_id')" data-depends-on="branch_id">
    <option value="">{{ __('structure.common.not_set') }}</option>
    @foreach ($options['locations'] as $location)
        <option value="{{ $location->id }}" data-parent="{{ $location->branch_id }}" @selected($value('work_location_id') === (string) $location->id)>{{ $location->name }}</option>
    @endforeach
</x-form.select>
