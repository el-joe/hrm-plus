@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('employees.index.title'), 'url' => route('admin.employees.index')]],
    'subtitle' => $employee->employee_number,
])

@section('title', $employee->name)

@section('page-actions')
    @can('update', $employee)
        <x-button variant="secondary" :href="route('admin.employees.edit', $employee)" icon="pencil">{{ __('app.actions.edit') }}</x-button>
        @unless ($employee->isTerminated())
            <x-dropdown>
                <x-slot:trigger>
                    <span class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-4 text-label-md text-text-primary shadow-xs hover:bg-surface-subtle">
                        <x-icon name="briefcase" size="sm" /> {{ __('employees.actions.change') }} <x-icon name="chevron-down" size="sm" />
                    </span>
                </x-slot:trigger>
                @foreach (App\Http\Requests\Admin\Employees\AssignmentChangeRequest::TYPES as $type)
                    <x-dropdown.item :href="route('admin.employees.assignments.create', [$employee, 'type' => $type->value])">{{ __('employees.actions.'.$type->value) }}</x-dropdown.item>
                @endforeach
                <x-dropdown.divider />
                @if ($employee->isActive())
                    <x-dropdown.item :action="route('admin.employees.status', [$employee, 'status' => 'inactive'])" method="PATCH" icon="power"
                        data-confirm="{{ __('employees.actions.confirm_deactivate', ['name' => $employee->name]) }}" data-confirm-tone="warning"
                        data-confirm-label="{{ __('employees.actions.deactivate') }}">
                        {{ __('employees.actions.deactivate') }}
                    </x-dropdown.item>
                @else
                    <x-dropdown.item :action="route('admin.employees.status', [$employee, 'status' => 'active'])" method="PATCH" icon="check-circle">
                        {{ __('employees.actions.activate') }}
                    </x-dropdown.item>
                @endif
                @can('manage', $employee)
                    <x-dropdown.item :href="route('admin.employees.terminate', $employee)" icon="log-out" tone="danger">{{ __('employees.actions.terminate') }}</x-dropdown.item>
                @endcan
            </x-dropdown>
        @endunless
    @endcan
    @if ($employee->isTerminated())
        @can('manage', $employee)
            <x-button :href="route('admin.employees.rehire', $employee)" icon="user-plus">{{ __('employees.actions.rehire') }}</x-button>
        @endcan
    @endif
@endsection

@section('content')
    <x-card data-region="summary">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <x-avatar :name="$employee->name" size="xl" :src="$employee->photo_path ? route('admin.employees.photo', $employee) : null" />
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-h4 text-text-primary">{{ $employee->name }}</p>
                    @include('admin.employees._status', ['status' => $employee->status])
                    @if ($employee->isOnProbation())
                        <x-badge tone="info">{{ __('employees.profile.on_probation') }}</x-badge>
                    @endif
                </div>
                <p class="text-body-md text-text-secondary">
                    {{ $employee->jobTitle?->name ?? __('employees.profile.no_title') }} · {{ $employee->department?->name }} · {{ $employee->branch?->name }}
                </p>
                <p class="text-caption-md text-text-tertiary">
                    <span dir="ltr">{{ $employee->employee_number }}</span>
                    · {{ __('employees.fields.hire_date') }}: <span dir="ltr">{{ $employee->hire_date?->toDateString() }}</span>
                    @if ($employee->manager)
                        · {{ __('employees.fields.manager_id') }}: {{ $employee->manager->name }}
                    @endif
                </p>
            </div>
        </div>
        @if ($employee->isTerminated())
            <x-alert tone="danger" class="mt-4" :title="__('employees.profile.terminated_title', ['date' => $employee->termination_date?->toDateString()])">
                {{ $employee->termination_type?->label() }}@if ($employee->termination_reason) — {{ $employee->termination_reason }}@endif
            </x-alert>
        @endif
    </x-card>

    <x-tabs :items="$tabs" :label="__('employees.profile.sections')" />

    <div class="flex flex-col gap-4 lg:gap-6" data-tab-content="{{ $tab }}">
        @include('admin.employees.tabs.'.$tab)
    </div>
@endsection
