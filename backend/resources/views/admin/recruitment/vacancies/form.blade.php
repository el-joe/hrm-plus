@extends('layouts.admin', [
    'breadcrumbs' => array_values(array_filter([
        ['label' => __('recruitment.vacancies.title'), 'url' => route('admin.vacancies.index')],
        $vacancy->exists ? ['label' => $vacancy->title, 'url' => route('admin.vacancies.show', $vacancy)] : null,
    ])),
])

@section('title', $vacancy->exists ? __('recruitment.vacancies.edit') : __('recruitment.vacancies.create'))

@section('content')
    @php
        $value = function (string $field) use ($vacancy): string {
            $current = $vacancy->getAttribute($field);
            $current = $current instanceof \BackedEnum ? $current->value : ($current instanceof \Carbon\CarbonInterface ? $current->toDateString() : $current);

            return (string) old($field, $current);
        };
    @endphp
    <form method="POST" action="{{ $vacancy->exists ? route('admin.vacancies.update', $vacancy) : route('admin.vacancies.store') }}"
        class="flex flex-col gap-4 lg:gap-6" data-validate data-employee-form>
        @csrf
        @if ($vacancy->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('recruitment.vacancies.section_job')" :description="__('recruitment.vacancies.section_job_help')">
            <x-form.input name="title_ar" :label="__('recruitment.fields.title_ar')" :value="$value('title_ar')" required maxlength="150" dir="rtl" />
            <x-form.input name="title_en" :label="__('recruitment.fields.title_en')" :value="$value('title_en')" required maxlength="150" dir="ltr" />
            <x-form.textarea name="description_ar" :label="__('recruitment.fields.description_ar')" rows="5" dir="rtl">{{ $value('description_ar') }}</x-form.textarea>
            <x-form.textarea name="description_en" :label="__('recruitment.fields.description_en')" rows="5" dir="ltr">{{ $value('description_en') }}</x-form.textarea>
            <x-form.textarea name="requirements_ar" :label="__('recruitment.fields.requirements_ar')" rows="5" dir="rtl" :help="__('recruitment.vacancies.requirements_help')">{{ $value('requirements_ar') }}</x-form.textarea>
            <x-form.textarea name="requirements_en" :label="__('recruitment.fields.requirements_en')" rows="5" dir="ltr" :help="__('recruitment.vacancies.requirements_help')">{{ $value('requirements_en') }}</x-form.textarea>
        </x-form.section>

        <x-form.section :title="__('recruitment.vacancies.section_placement')" :description="__('recruitment.vacancies.section_placement_help')">
            <x-form.select name="branch_id" :label="__('recruitment.fields.branch_id')" required data-placement="branch_id">
                <option value="">—</option>
                @foreach ($options['branches'] as $branch)
                    <option value="{{ $branch->id }}" @selected($value('branch_id') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="department_id" :label="__('recruitment.fields.department_id')" required data-depends-on="branch_id" data-placement="department_id">
                <option value="">—</option>
                @foreach ($options['departments'] as $department)
                    <option value="{{ $department->id }}" data-parent="{{ $department->branch_id }}" @selected($value('department_id') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="position_id" :label="__('recruitment.fields.position_id')" data-depends-on="department_id" :help="__('recruitment.vacancies.position_help')">
                <option value="">{{ __('recruitment.common.none') }}</option>
                @foreach ($options['positions'] as $position)
                    <option value="{{ $position->id }}" data-parent="{{ $position->department_id }}" @selected($value('position_id') === (string) $position->id)>{{ $position->code }} · {{ $position->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="job_title_id" :label="__('recruitment.fields.job_title_id')">
                <option value="">{{ __('recruitment.common.from_position') }}</option>
                @foreach ($options['jobTitles'] as $jobTitle)
                    <option value="{{ $jobTitle->id }}" @selected($value('job_title_id') === (string) $jobTitle->id)>{{ $jobTitle->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="employment_type_id" :label="__('recruitment.fields.employment_type_id')">
                <option value="">—</option>
                @foreach ($options['employmentTypes'] as $type)
                    <option value="{{ $type->id }}" @selected($value('employment_type_id') === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="openings" type="number" min="1" max="500" :label="__('recruitment.fields.openings')" :value="$value('openings')" required
                :help="$vacancy->filled_count ? __('recruitment.vacancies.filled_help', ['count' => $vacancy->filled_count]) : __('recruitment.vacancies.openings_help')" />
            @if ($showSalary)
                <x-form.input name="salary_min" type="number" step="0.01" min="0" :label="__('recruitment.fields.salary_min')" :value="$value('salary_min')" dir="ltr" :help="__('recruitment.vacancies.salary_help')" />
                <x-form.input name="salary_max" type="number" step="0.01" min="0" :label="__('recruitment.fields.salary_max')" :value="$value('salary_max')" dir="ltr" />
            @endif
        </x-form.section>

        <x-form.section :title="__('recruitment.vacancies.section_publishing')" :description="__('recruitment.vacancies.section_publishing_help')">
            <x-form.select name="status" :label="__('recruitment.fields.status')" required>
                @foreach ($options['statuses'] as $status)
                    <option value="{{ $status->value }}" @selected($value('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="hiring_manager_id" :label="__('recruitment.fields.hiring_manager_id')">
                <option value="">{{ __('recruitment.common.none') }}</option>
                @foreach ($options['hiringManagers'] as $manager)
                    <option value="{{ $manager->id }}" @selected($value('hiring_manager_id') === (string) $manager->id)>{{ $manager->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="publish_at" type="date" :label="__('recruitment.fields.publish_at')" :value="$value('publish_at')" />
            <x-form.input name="application_deadline" type="date" :label="__('recruitment.fields.application_deadline')" :value="$value('application_deadline')" />
            <x-form.select name="evaluation_form_id" :label="__('recruitment.fields.evaluation_form_id')" :help="__('recruitment.vacancies.form_help')">
                <option value="">{{ __('recruitment.common.none') }}</option>
                @foreach ($options['evaluationForms'] as $form)
                    <option value="{{ $form->id }}" @selected($value('evaluation_form_id') === (string) $form->id)>{{ $form->name }}</option>
                @endforeach
            </x-form.select>
            <div class="flex items-end pb-2">
                <input type="hidden" name="is_public" value="0">
                <x-form.switch name="is_public" :label="__('recruitment.fields.is_public')" :description="__('recruitment.vacancies.public_help')" :checked="(bool) old('is_public', $vacancy->is_public)" />
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="$vacancy->exists ? route('admin.vacancies.show', $vacancy) : route('admin.vacancies.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
