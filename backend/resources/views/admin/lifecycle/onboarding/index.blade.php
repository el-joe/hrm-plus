@extends('layouts.admin', ['subtitle' => __('onboarding.onboarding.subtitle')])

@section('title', __('onboarding.onboarding.title'))

@section('page-actions')
    @can('onboarding.manage')
        <x-button variant="secondary" :href="route('admin.onboarding-templates.index')" icon="file-text">{{ __('onboarding.templates.title') }}</x-button>
    @endcan
    @can('create', App\Models\Onboarding::class)
        <x-button :href="route('admin.onboarding.create')" icon="plus">{{ __('onboarding.onboarding.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.onboarding.index')" :reset="route('admin.onboarding.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('onboarding.onboarding.search')" :value="$filters['search'] ?? ''" class="md:w-72" data-filter-debounce />
        <x-form.select name="status" id="filter-status" :label="__('app.common.status')" hide-label class="md:w-44">
            @foreach (App\Enums\OnboardingStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? 'in_progress') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('onboarding.onboarding.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('onboarding.fields.employee_id') }}</x-table.th>
                <x-table.th>{{ __('onboarding.fields.start_date') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('onboarding.onboarding.progress') }}</x-table.th>
                <x-table.th>{{ __('onboarding.onboarding.open_tasks') }}</x-table.th>
                <x-table.th>{{ __('app.common.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($onboardings as $onboarding)
            <x-table.row :data-href="route('admin.onboarding.show', $onboarding)">
                <x-table.td :label="__('onboarding.fields.employee_id')">
                    <a href="{{ route('admin.onboarding.show', $onboarding) }}" class="block text-label-md text-text-primary hover:text-text-link">{{ $onboarding->employee->name }}</a>
                    <span class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $onboarding->employee->employee_number }}</span> · {{ $onboarding->employee->jobTitle?->name ?? $onboarding->employee->department?->name }}</span>
                </x-table.td>
                <x-table.td :label="__('onboarding.fields.start_date')" class="tabular-nums"><span dir="ltr">{{ $onboarding->start_date->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('onboarding.onboarding.progress')">@include('admin.lifecycle._progress', ['value' => $onboarding->progress(), 'label' => __('onboarding.onboarding.progress')])</x-table.td>
                <x-table.td :label="__('onboarding.onboarding.open_tasks')" class="tabular-nums">
                    {{ $onboarding->pendingCount() }}
                    @php($overdue = $onboarding->tasks->filter->isOverdue()->count())
                    @if ($overdue)
                        <x-badge tone="danger">{{ trans_choice('onboarding.onboarding.overdue_count', $overdue, ['count' => $overdue]) }}</x-badge>
                    @endif
                </x-table.td>
                <x-table.td :label="__('app.common.status')">@include('admin.recruitment._badge', ['value' => $onboarding->status])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="log-in" :title="__('onboarding.onboarding.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$onboardings" />
        </x-slot:footer>
    </x-table>
@endsection
