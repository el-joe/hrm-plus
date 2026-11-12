@extends('layouts.admin', ['breadcrumbs' => [['label' => __('performance.cycles.title'), 'url' => route('admin.performance-cycles.index')]]])

@section('title', $cycle->exists ? __('performance.cycles.edit') : __('performance.cycles.create'))

@section('content')
    @php
        $locked = $cycle->exists && ! $cycle->isDraft();
        $rows = old('competencies', $cycleCompetencies);
        $rows = [...$rows, ...array_fill(0, max(0, 3 - count($rows)), [])];
    @endphp
    @if ($locked)
        <x-alert tone="info">{{ __('performance.cycles.locked_help') }}</x-alert>
    @endif
    <form method="POST" action="{{ $cycle->exists ? route('admin.performance-cycles.update', $cycle) : route('admin.performance-cycles.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($cycle->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('performance.cycles.identity')">
            <x-form.input name="code" :label="__('performance.fields.code')" :value="old('code', $cycle->code)" required maxlength="30" dir="ltr" class="uppercase" pattern="[A-Za-z0-9][A-Za-z0-9._\-]*" :readonly="$locked" />
            <x-form.select name="type" :label="__('performance.fields.type')" required>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $cycle->type?->value) === $type->value) @disabled($locked && $cycle->type !== $type)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="name_ar" :label="__('structure.fields.name_ar')" :value="old('name_ar', $cycle->name_ar)" required maxlength="150" dir="rtl" lang="ar" />
            <x-form.input name="name_en" :label="__('structure.fields.name_en')" :value="old('name_en', $cycle->name_en)" required maxlength="150" dir="ltr" lang="en" />
            <div class="md:col-span-2">
                <x-form.textarea name="description" :label="__('performance.fields.description')" rows="2" maxlength="2000">{{ old('description', $cycle->description) }}</x-form.textarea>
            </div>
        </x-form.section>

        <x-form.section :title="__('performance.cycles.period')" :description="__('performance.cycles.period_help')">
            <x-form.input type="date" name="period_start" :label="__('performance.fields.period_start')" required :value="old('period_start', $cycle->period_start?->toDateString())" :readonly="$locked" />
            <x-form.input type="date" name="period_end" :label="__('performance.fields.period_end')" required :value="old('period_end', $cycle->period_end?->toDateString())" :readonly="$locked" />
            <x-form.input type="date" name="self_assessment_due" :label="__('performance.fields.self_assessment_due')" :value="old('self_assessment_due', $cycle->self_assessment_due?->toDateString())" />
            <x-form.input type="date" name="manager_assessment_due" :label="__('performance.fields.manager_assessment_due')" :value="old('manager_assessment_due', $cycle->manager_assessment_due?->toDateString())" />
            <div class="md:col-span-2">
                <input type="hidden" name="self_assessment_enabled" value="0">
                <x-form.switch name="self_assessment_enabled" :label="__('performance.fields.self_assessment_enabled')" :description="__('performance.cycles.self_help')" :checked="(bool) old('self_assessment_enabled', $cycle->self_assessment_enabled)" :disabled="$locked" />
            </div>
        </x-form.section>

        <x-form.section :title="__('performance.cycles.participants')" :description="__('performance.cycles.participants_help')">
            <x-form.select name="branch_id" :label="__('performance.fields.branch')">
                <option value="">{{ __('performance.cycles.all_branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $cycle->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="department_id" :label="__('performance.fields.department')" :help="__('performance.cycles.department_help')">
                <option value="">{{ __('performance.cycles.all_departments') }}</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) old('department_id', $cycle->department_id) === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </x-form.select>
        </x-form.section>

        <x-form.section :title="__('performance.cycles.scoring')" :description="__('performance.cycles.scoring_help')">
            <div class="md:col-span-2">
                <x-form.select name="rating_scale_id" :label="__('performance.fields.rating_scale')" required>
                    @foreach ($scales as $scale)
                        <option value="{{ $scale->id }}" @selected((string) old('rating_scale_id', $cycle->rating_scale_id) === (string) $scale->id)>{{ $scale->code }} · {{ $scale->name }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <x-form.input type="number" name="goals_weight" step="0.01" min="0" max="100" dir="ltr" required :label="__('performance.fields.goals_weight')" :value="old('goals_weight', rtrim(rtrim((string) $cycle->goals_weight, '0'), '.'))" />
            <x-form.input type="number" name="competencies_weight" step="0.01" min="0" max="100" dir="ltr" required :label="__('performance.fields.competencies_weight')" :value="old('competencies_weight', rtrim(rtrim((string) $cycle->competencies_weight, '0'), '.'))" />

            <div class="md:col-span-2 flex flex-col gap-3" data-repeater="competencies">
                <p class="text-label-md text-text-primary">{{ __('performance.cycles.competencies') }}</p>
                <p class="text-caption-md text-text-tertiary">{{ __('performance.cycles.competencies_help') }}</p>
                @error('competencies')
                    <x-alert tone="danger">{{ $message }}</x-alert>
                @enderror
                <div class="flex flex-col gap-3" data-repeater-rows>
                    @foreach ($rows as $index => $row)
                        @include('admin.performance.cycles._competency-row', ['index' => $index, 'row' => $row])
                    @endforeach
                </div>
                <template data-repeater-template>
                    @include('admin.performance.cycles._competency-row', ['index' => '__INDEX__', 'row' => []])
                </template>
                <div>
                    <x-button type="button" variant="secondary" size="sm" icon="plus" data-repeater-add hidden data-js-only>{{ __('performance.cycles.add_competency') }}</x-button>
                </div>
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="$cycle->exists ? route('admin.performance-cycles.show', $cycle) : route('admin.performance-cycles.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
