<x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
    @include('admin.structure._identity-fields')
</x-form.section>

<x-form.section :title="__('structure.positions.placement')" :description="__('structure.positions.placement_help')">
    <x-form.select name="department_id" :label="__('structure.fields.department_id')" required>
        <option value="">—</option>
        @foreach ($departments as $department)
            <option value="{{ $department->id }}" @selected((string) old('department_id', $record->department_id) === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
        @endforeach
    </x-form.select>

    <x-form.select name="job_title_id" :label="__('structure.fields.job_title_id')" required>
        <option value="">—</option>
        @foreach ($jobTitles as $jobTitle)
            <option value="{{ $jobTitle->id }}" @selected((string) old('job_title_id', $record->job_title_id) === (string) $jobTitle->id)>{{ $jobTitle->name }}</option>
        @endforeach
    </x-form.select>

    <x-form.select name="job_grade_id" :label="__('structure.fields.job_grade_id')">
        <option value="">{{ __('structure.common.not_set') }}</option>
        @foreach ($jobGrades as $jobGrade)
            <option value="{{ $jobGrade->id }}" @selected((string) old('job_grade_id', $record->job_grade_id) === (string) $jobGrade->id)>{{ $jobGrade->code }} · {{ $jobGrade->name }}</option>
        @endforeach
    </x-form.select>

    <x-form.select name="reports_to_position_id" :label="__('structure.fields.reports_to_position_id')">
        <option value="">{{ __('structure.positions.top_of_hierarchy') }}</option>
        @foreach ($managers as $manager)
            <option value="{{ $manager->id }}" @selected((string) old('reports_to_position_id', $record->reports_to_position_id) === (string) $manager->id)>{{ $manager->name }} · {{ $manager->department?->name }}</option>
        @endforeach
    </x-form.select>

    <x-form.input name="headcount" type="number" min="1" max="9999" step="1" :label="__('structure.fields.headcount')"
        :value="old('headcount', $record->headcount ?? 1)" required :help="__('structure.positions.headcount_help')" />
    <div class="hidden md:block" aria-hidden="true"></div>

    @include('admin.structure._description-field')
    @include('admin.structure._active-switch')
</x-form.section>
