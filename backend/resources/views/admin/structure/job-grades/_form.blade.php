<x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
    @include('admin.structure._identity-fields')
</x-form.section>

<x-form.section :title="__('structure.job_grades.band')" :description="auth()->user()->can('employee_financials.view') ? __('structure.job_grades.band_help', ['currency' => $currency]) : null">
    <x-form.input name="level" type="number" min="1" max="999" step="1" :label="__('structure.fields.level')"
        :value="old('level', $record->level)" required :help="__('structure.job_grades.level_help')" />
    <div class="hidden md:block" aria-hidden="true"></div>
    {{-- Salary bands are financial data (employee_financials.view). --}}
    @can('employee_financials.view')
        <x-form.input name="min_salary" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" :label="__('structure.fields.min_salary')"
            :value="old('min_salary', $record->min_salary)" />
        <x-form.input name="max_salary" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" :label="__('structure.fields.max_salary')"
            :value="old('max_salary', $record->max_salary)" />
    @endcan
    @include('admin.structure._active-switch')
</x-form.section>
