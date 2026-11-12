@extends('layouts.admin', ['subtitle' => __('training.skills_matrix.subtitle')])

@section('title', __('training.skills_matrix.title'))

@section('content')
    <x-filter-bar :action="route('admin.skills-matrix')" :reset="route('admin.skills-matrix')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="department_id" :label="__('training.fields.department')" hide-label class="md:w-48">
            <option value="">{{ __('training.fields.department') }}: {{ __('app.common.all') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="category" :label="__('training.fields.category')" hide-label class="md:w-40">
            <option value="">{{ __('training.fields.category') }}: {{ __('app.common.all') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="skill_id" :label="__('training.fields.skill')" hide-label class="md:w-48">
            <option value="">{{ __('training.fields.skill') }}: {{ __('app.common.all') }}</option>
            @foreach ($allSkills as $skill)
                <option value="{{ $skill->id }}" @selected((string) ($filters['skill_id'] ?? '') === (string) $skill->id)>{{ $skill->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="min_level" :label="__('training.skills_matrix.min_level')" hide-label class="md:w-40">
            <option value="">{{ __('training.skills_matrix.min_level') }}: {{ __('app.common.all') }}</option>
            @for ($level = 1; $level <= App\Models\Skill::MAX_LEVEL; $level++)
                <option value="{{ $level }}" @selected((string) ($filters['min_level'] ?? '') === (string) $level)>≥ {{ $level }}</option>
            @endfor
        </x-form.select>
    </x-filter-bar>

    @if ($canUpdate)
        <x-card :title="__('training.skills_matrix.set_level')" :subtitle="__('training.skills_matrix.set_level_help')" data-region="set-level">
            <form method="POST" action="{{ route('admin.skills-matrix.update') }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-[2fr_2fr_1fr_auto] lg:items-end" data-validate>
                @csrf
                <x-form.select name="employee_id" :label="__('training.fields.employee')" required>
                    <option value="">—</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->label() }}</option>
                    @endforeach
                </x-form.select>
                <x-form.select name="skill_id" id="set-skill" :label="__('training.fields.skill')" required>
                    <option value="">—</option>
                    @foreach ($allSkills as $skill)
                        <option value="{{ $skill->id }}">{{ $skill->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.select name="level" :label="__('training.fields.level')" required>
                    @for ($level = 0; $level <= App\Models\Skill::MAX_LEVEL; $level++)
                        <option value="{{ $level }}" @selected($level === 3)>{{ $level }} — {{ __('training.levels.'.$level) }}</option>
                    @endfor
                </x-form.select>
                <x-button icon="check">{{ __('app.actions.save') }}</x-button>
            </form>
        </x-card>
    @endif

    <x-card padding="none">
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-max text-body-sm">
                <caption class="sr-only">{{ __('training.skills_matrix.title') }}</caption>
                <thead class="bg-surface-subtle text-label-sm text-text-secondary">
                    <tr>
                        <th scope="col" class="sticky start-0 z-10 bg-surface-subtle px-4 py-3 text-start">{{ __('training.fields.employee') }}</th>
                        @foreach ($skills as $skill)
                            <th scope="col" class="px-3 py-3 text-center"><span class="block max-w-32 truncate" title="{{ $skill->name }}">{{ $skill->name }}</span>
                                @if ($skill->category)<span class="block text-caption-sm text-text-tertiary">{{ $skill->category }}</span>@endif</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle">
                    @forelse ($employees as $employee)
                        <tr>
                            <th scope="row" class="sticky start-0 z-10 bg-surface-default px-4 py-2 text-start font-normal">
                                <span class="block text-label-md text-text-primary">{{ $employee->name }}</span>
                                <span class="block text-caption-md text-text-tertiary"><span dir="ltr">{{ $employee->employee_number }}</span> · {{ $employee->department?->name }}</span>
                            </th>
                            @foreach ($skills as $skill)
                                @php($cell = $levels->get($employee->id)?->get($skill->id))
                                <td class="px-3 py-2 text-center">
                                    @if ($cell)
                                        @include('admin.training._level', ['level' => $cell->level])
                                        @if ($cell->source === App\Enums\SkillSource::Training)
                                            <span class="block text-caption-sm text-text-tertiary">{{ __('training.enums.skill_sources.training') }}</span>
                                        @endif
                                    @else
                                        <span class="text-text-disabled" aria-label="{{ __('training.skills_matrix.none') }}">·</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ $skills->count() + 1 }}" class="px-4 py-8"><x-empty-state icon="users" :title="__('training.skills_matrix.empty')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-slot:footer>
            <x-pagination :paginator="$employees" />
        </x-slot:footer>
    </x-card>
@endsection
