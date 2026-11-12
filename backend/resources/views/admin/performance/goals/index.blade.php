@extends('layouts.admin', ['subtitle' => __('performance.goals.subtitle')])

@section('title', __('performance.goals.title'))

@section('page-actions')
    @can('create', App\Models\PerformanceGoal::class)
        <x-button icon="plus" :href="route('admin.performance-goals.create')">{{ __('performance.goals.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.performance-goals.index')" :reset="route('admin.performance-goals.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="cycle" :label="__('performance.fields.cycle')" hide-label class="md:w-52">
            <option value="">{{ __('performance.fields.cycle') }}: {{ __('app.common.all') }}</option>
            @foreach ($cycles as $cycle)
                <option value="{{ $cycle->id }}" @selected((string) ($filters['cycle'] ?? '') === (string) $cycle->id)>{{ $cycle->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="type" :label="__('performance.fields.type')" hide-label class="md:w-40">
            <option value="">{{ __('performance.fields.type') }}: {{ __('app.common.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('performance.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('performance.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('performance.goals.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="title" class="min-w-56">{{ __('performance.fields.title') }}</x-table.th>
                <x-table.th class="min-w-44">{{ __('performance.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.cycle') }}</x-table.th>
                <x-table.th sort="weight">{{ __('performance.fields.weight') }}</x-table.th>
                <x-table.th sort="progress" class="min-w-44">{{ __('performance.fields.progress') }}</x-table.th>
                <x-table.th sort="due">{{ __('performance.fields.due_date') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($goals as $goal)
            <x-table.row :data-href="route('admin.performance-goals.edit', $goal)">
                <x-table.td :label="__('performance.fields.title')">
                    <a href="{{ route('admin.performance-goals.edit', $goal) }}" class="text-label-md text-text-link hover:underline">{{ $goal->title }}</a>
                    <span class="block text-caption-md text-text-tertiary">{{ $goal->type->label() }}@if ($goal->isKpi() && $goal->target_value !== null) · {{ __('performance.goals.kpi_line', ['current' => $goal->current_value ?? '0', 'target' => $goal->target_value, 'unit' => $goal->unit]) }}@endif</span>
                </x-table.td>
                <x-table.td :label="__('performance.fields.employee')">
                    {{ $goal->employee?->name }}
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $goal->employee?->employee_number }}</span>
                </x-table.td>
                <x-table.td :label="__('performance.fields.cycle')">{{ $goal->cycle?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('performance.fields.weight')" class="tabular-nums"><span dir="ltr">{{ rtrim(rtrim((string) $goal->weight, '0'), '.') }}%</span></x-table.td>
                <x-table.td :label="__('performance.fields.progress')">@include('admin.performance._progress', ['value' => $goal->progress, 'label' => $goal->title])</x-table.td>
                <x-table.td :label="__('performance.fields.due_date')" class="whitespace-nowrap">
                    <span dir="ltr">{{ $goal->due_date?->toDateString() ?? '—' }}</span>
                    @if ($goal->isOverdue()) <x-badge tone="danger">{{ __('performance.goals.overdue') }}</x-badge> @endif
                </x-table.td>
                <x-table.td :label="__('performance.fields.status')"><x-badge :tone="$goal->status->tone()">{{ $goal->status->label() }}</x-badge></x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="target" :title="__('performance.goals.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$goals" />
        </x-slot:footer>
    </x-table>
@endsection
