<x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
    @include('admin.structure._identity-fields')
</x-form.section>

<x-form.section :title="__('structure.common.details')" :description="__('structure.teams.placement_help')">
    <x-form.select name="department_id" :label="__('structure.fields.department_id')" required>
        <option value="">—</option>
        @foreach ($departments as $department)
            <option value="{{ $department->id }}" @selected((string) old('department_id', $record->department_id) === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="leader_employee_id" :label="__('structure.fields.leader_employee_id')" :help="__('structure.teams.leader_help')">
        <option value="">{{ __('structure.common.not_set') }}</option>
        @foreach ($leaders as $leader)
            <option value="{{ $leader->id }}" @selected((string) old('leader_employee_id', $record->leader_employee_id) === (string) $leader->id)>{{ $leader->label() }}</option>
        @endforeach
    </x-form.select>
    @include('admin.structure._description-field')
    @include('admin.structure._active-switch')
</x-form.section>
